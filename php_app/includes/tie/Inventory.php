<?php
/** Phase 15: authoritative option selection and atomic inventory holds. */

final class UthengaTieInventorySelectionContracts
{
    public static function selections($input): array
    {
        if (!is_array($input) || $input === []) throw UthengaTieErrors::validation(['selections' => 'Choose a published ticket, seat, or room option for every payable plan activity.']);
        $result = []; $allowed = ['ticket_type' => 'event', 'seat_class' => 'transport', 'room_type' => 'accommodation'];
        foreach ($input as $entry) {
            if (!is_array($entry)) throw UthengaTieErrors::validation(['selections' => 'Each inventory selection must be an object.']);
            $serviceId = trim((string) ($entry['service_id'] ?? '')); $type = strtolower(trim((string) ($entry['resource_type'] ?? ''))); $resourceId = $entry['resource_id'] ?? null; $quantity = $entry['quantity'] ?? null;
            if (!preg_match('/^[A-Za-z0-9_-]{1,30}$/', $serviceId) || !isset($allowed[$type]) || !filter_var($resourceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]])) throw UthengaTieErrors::validation(['selections' => 'Every selection needs a valid service, resource type, resource ID, and quantity.']);
            if (isset($result[$serviceId])) throw UthengaTieErrors::validation(['selections' => 'Only one inventory option may be selected per service.']);
            $result[$serviceId] = ['service_id' => $serviceId, 'resource_type' => $type, 'resource_id' => (int) $resourceId, 'quantity' => (int) $quantity, 'category' => $allowed[$type]];
        }
        return array_values($result);
    }
}

final class UthengaTieMariaDbInventoryHoldProvider implements UthengaTieInventoryHoldProvider
{
    public function __construct(private PDO $db) {}
    public function quote(array $plan, array $selections): array
    {
        $selections = UthengaTieInventorySelectionContracts::selections($selections); $activities = array_column($plan['activities'] ?? [], null, 'service_id'); $selected = array_column($selections, null, 'service_id');
        foreach ($activities as $serviceId => $activity) {
            $category = (string) ($activity['category'] ?? '');
            if (!in_array($category, ['event', 'transport', 'accommodation'], true)) throw UthengaTieErrors::validation(['plan' => 'Tours and other services need an authoritative inventory provider before payment can begin.']);
            if (!isset($selected[$serviceId]) || $selected[$serviceId]['category'] !== $category) throw UthengaTieErrors::validation(['selections' => 'Choose a matching inventory option for every service in the approved plan.']);
        }
        $nights = $this->nights($plan['trip_summary'] ?? []); $currency = (string) (($plan['trip_summary']['currency'] ?? APP_CURRENCY)); $lines = []; $total = 0.0;
        foreach ($selections as $selection) {
            if ($selection['resource_type'] === 'room_type' && UthengaTieFeatureFlags::enabled('accommodation_v2')) {
                $trip = $plan['trip_summary'] ?? []; $quoted = (new UthengaAccommodationService($this->db))->quoteListing($selection['service_id'], $selection['resource_id'], $selection['quantity'], (string) ($trip['start_date'] ?? ''), (string) ($trip['end_date'] ?? ''));
                $lineTotal = (float) $quoted['total']; $payable = (float) $quoted['deposit_required']; $total += $payable;
                $lines[] = ['service_id'=>$selection['service_id'],'resource_type'=>'room_type','resource_id'=>$selection['resource_id'],'title'=>$quoted['room']['room_name'],'unit_price'=>round($lineTotal/max(1,$selection['quantity']*$quoted['nights']),2),'quantity'=>$selection['quantity'],'nights'=>$quoted['nights'],'total'=>$lineTotal,'payable_total'=>$payable,'balance_due'=>round($lineTotal-$payable,2),'rate_plan_id'=>$quoted['rate_plan']['id'],'currency'=>$quoted['currency']];
                continue;
            }
            $row = $this->resource($selection); $multiplier = $selection['quantity']; $lineTotal = round((float) $row['price'] * $multiplier, 2); $total += $lineTotal;
            $lines[] = ['service_id' => $selection['service_id'], 'resource_type' => $selection['resource_type'], 'resource_id' => $selection['resource_id'], 'title' => $row['name'], 'unit_price' => (float) $row['price'], 'quantity' => $selection['quantity'], 'nights' => null, 'total' => $lineTotal, 'payable_total'=>$lineTotal, 'balance_due'=>0.0, 'currency' => $currency];
        }
        return ['amount' => round($total, 2), 'currency' => $currency, 'line_items' => $lines];
    }
    public function acquire(array $plan, array $quote): array
    {
        $selections = UthengaTieInventorySelectionContracts::selections($quote['selections'] ?? null); $activities = array_column($plan['activities'] ?? [], null, 'service_id');
        foreach ($selections as $selection) if (!isset($activities[$selection['service_id']]) || ($activities[$selection['service_id']]['category'] ?? '') !== $selection['category']) throw UthengaTieErrors::validation(['selections' => 'The selected inventory option does not match this approved plan.']);
        $ownsTransaction = !$this->db->inTransaction(); if ($ownsTransaction) $this->db->beginTransaction(); $held = []; /* Local time, not UTC — expires_at is compared against MySQL's NOW(), whose session time_zone is 'SYSTEM' here, not UTC. gmdate() made every hold expire immediately. */ $expires = date('Y-m-d H:i:s', time() + max(60, UthengaTieConfig::integer('TIE_INVENTORY_HOLD_SECONDS', 900)));
        try {
            $this->expireLocked();
            $quoted = array_column($quote['line_items'] ?? [], null, 'service_id');
            foreach ($selections as $selection) {
                $id = $this->uuid(); $start=null; $end=null; $row=null;
                if ($selection['resource_type']==='room_type' && UthengaTieFeatureFlags::enabled('accommodation_v2')) { $trip=$plan['trip_summary']??[];$start=(string)($trip['start_date']??'');$end=(string)($trip['end_date']??'');$accommodation=(new UthengaAccommodationService($this->db))->acquireExternalHold($id,$selection['service_id'],$selection['resource_id'],$selection['quantity'],$start,$end);$row=['price'=>round((float)$accommodation['total']/max(1,$selection['quantity']*$accommodation['nights']),2)];if(!isset($quoted[$selection['service_id']])||(float)$quoted[$selection['service_id']]['total']!==(float)$accommodation['total'])throw UthengaTieErrors::validation(['inventory'=>'The accommodation price changed. Review the new quote before payment.']); }
                else { $row=$this->reserve($selection);if(!isset($quoted[$selection['service_id']])||(float)$quoted[$selection['service_id']]['unit_price']!==(float)$row['price'])throw UthengaTieErrors::validation(['inventory'=>'The selected inventory price changed. Review the new quote before payment.']); }
                $stmt=$this->db->prepare('INSERT INTO tie_inventory_holds (id,user_id,plan_id,resource_type,resource_id,listing_id,quantity,start_date,end_date,status,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$id,(string)($plan['user_id']??''),$plan['plan_id'],$selection['resource_type'],$selection['resource_id'],$selection['service_id'],$selection['quantity'],$start,$end,'ACTIVE',$expires]);$held[]=['hold_id'=>$id,'service_id'=>$selection['service_id'],'resource_type'=>$selection['resource_type'],'resource_id'=>$selection['resource_id'],'unit_price'=>$row['price']];
            }
            if ($ownsTransaction) $this->db->commit(); return ['hold_id' => $held[0]['hold_id'], 'hold_ids' => array_column($held, 'hold_id'), 'expires_at' => $expires, 'resources' => $held];
        } catch (Throwable $error) { if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    public function release(string $holdId): void
    {
        $ownsTransaction = !$this->db->inTransaction(); if ($ownsTransaction) $this->db->beginTransaction(); try { $stmt = $this->db->prepare('SELECT * FROM tie_inventory_holds WHERE id = ? FOR UPDATE'); $stmt->execute([$holdId]); $hold = $stmt->fetch(); if (is_array($hold) && $hold['status'] === 'ACTIVE') { $this->restore($hold,'RELEASED'); $this->db->prepare("UPDATE tie_inventory_holds SET status='RELEASED', released_at=NOW() WHERE id=?")->execute([$holdId]); } if ($ownsTransaction) $this->db->commit(); } catch (Throwable $error) { if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    public function consume(string $holdId, string $paymentIntentId, ?string $bookingId = null): void { $hold=$this->db->prepare("SELECT * FROM tie_inventory_holds WHERE id=? AND status='ACTIVE' AND expires_at>NOW() LIMIT 1 FOR UPDATE");$hold->execute([$holdId]);$row=$hold->fetch();if(!is_array($row))throw UthengaTieErrors::validation(['inventory'=>'The inventory hold is no longer active.']);if($row['resource_type']==='room_type'&&UthengaTieFeatureFlags::enabled('accommodation_v2')){if(!$bookingId)throw UthengaTieErrors::validation(['booking'=>'Accommodation confirmation requires its booking record.']);(new UthengaAccommodationService($this->db))->consumeExternalHold($holdId,$bookingId,(string)$row['user_id']);}$stmt = $this->db->prepare("UPDATE tie_inventory_holds SET status='CONSUMED', payment_intent_id=?, booking_id=?, consumed_at=NOW() WHERE id=? AND status='ACTIVE' AND expires_at > NOW()"); $stmt->execute([$paymentIntentId,$bookingId,$holdId]); if ($stmt->rowCount() !== 1) throw UthengaTieErrors::validation(['inventory' => 'The inventory hold is no longer active.']); }
    private function reserve(array $selection): array
    {
        $map = ['ticket_type' => ['ticket_types', 'remaining_quantity', 'price'], 'seat_class' => ['seat_classes', 'remaining_seats', 'price'], 'room_type' => ['room_types', 'available_rooms', 'price_per_night']]; [$table, $remaining, $price] = $map[$selection['resource_type']];
        $stmt = $this->db->prepare("SELECT id, listing_id, $price AS price, $remaining AS remaining FROM $table WHERE id=? AND listing_id=? AND is_active=1 FOR UPDATE"); $stmt->execute([$selection['resource_id'], $selection['service_id']]); $row = $stmt->fetch();
        if (!is_array($row) || (int) $row['remaining'] < $selection['quantity']) throw UthengaTieErrors::validation(['inventory' => 'The selected inventory option is no longer available.']);
        $update = $this->db->prepare("UPDATE $table SET $remaining = $remaining - ? WHERE id=? AND listing_id=? AND $remaining >= ?"); $update->execute([$selection['quantity'], $selection['resource_id'], $selection['service_id'], $selection['quantity']]); if ($update->rowCount() !== 1) throw UthengaTieErrors::validation(['inventory' => 'The selected inventory option changed while the hold was being created.']); return $row;
    }
    private function resource(array $selection): array
    {
        $map = ['ticket_type' => ['ticket_types', 'price', 'name'], 'seat_class' => ['seat_classes', 'price', 'class_name'], 'room_type' => ['room_types', 'price_per_night', 'room_name']]; [$table, $price, $name] = $map[$selection['resource_type']];
        $stmt = $this->db->prepare("SELECT listing_id, $price AS price, $name AS name FROM $table WHERE id=? AND listing_id=? AND is_active=1 LIMIT 1"); $stmt->execute([$selection['resource_id'], $selection['service_id']]); $row = $stmt->fetch(); if (!is_array($row) || (float) $row['price'] <= 0) throw UthengaTieErrors::validation(['selections' => 'The selected inventory option is unavailable or has no payable price.']); return $row;
    }
    private function restore(array $hold,string $status='RELEASED'): void { if($hold['resource_type']==='room_type'&&UthengaTieFeatureFlags::enabled('accommodation_v2')){(new UthengaAccommodationService($this->db))->releaseExternalHold((string)$hold['id'],$status);return;}$map = ['ticket_type' => ['ticket_types', 'remaining_quantity'], 'seat_class' => ['seat_classes', 'remaining_seats']]; if (!isset($map[$hold['resource_type']])) return; [$table, $remaining] = $map[$hold['resource_type']]; $this->db->prepare("UPDATE $table SET $remaining = $remaining + ? WHERE id=? AND listing_id=?")->execute([(int) $hold['quantity'], (int) $hold['resource_id'], $hold['listing_id']]); }
    private function expireLocked(): void { $rows = $this->db->query("SELECT * FROM tie_inventory_holds WHERE status='ACTIVE' AND expires_at <= NOW() FOR UPDATE")->fetchAll(); foreach ($rows as $hold) { $this->restore($hold,'EXPIRED'); $this->db->prepare("UPDATE tie_inventory_holds SET status='EXPIRED', released_at=NOW() WHERE id=?")->execute([$hold['id']]); } }
    private function nights(array $trip): int { try { if (empty($trip['start_date']) || empty($trip['end_date'])) return 1; return max(1, (new DateTimeImmutable($trip['start_date']))->diff(new DateTimeImmutable($trip['end_date']))->days); } catch (Throwable $error) { return 1; } }
    private function uuid(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}

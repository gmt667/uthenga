<?php
/** Phase 15: commit verified payment + active holds into canonical bookings. */

require_once __DIR__ . '/Tickets.php';

interface UthengaTieBookingCommitProvider { public function commit(array $plan, array $intent): array; }
final class UthengaTieUnavailableBookingCommitProvider implements UthengaTieBookingCommitProvider { public function commit(array $plan, array $intent): array { throw UthengaTieErrors::providerUnavailable('booking_commit_provider'); } }

final class UthengaTieMariaDbBookingCommitProvider implements UthengaTieBookingCommitProvider
{
    public function __construct(private PDO $db, private UthengaTieInventoryHoldProvider $holds) {}
    public function commit(array $plan, array $intent): array
    {
        $quote = is_array($intent['quote_snapshot'] ?? null) ? $intent['quote_snapshot'] : []; $lines = array_column($quote['line_items'] ?? [], null, 'service_id'); $selections = array_column($quote['selections'] ?? [], null, 'service_id'); $holdIds = $intent['diagnostics']['hold_ids'] ?? [];
        if (!is_array($holdIds) || $holdIds === [] || count($lines) !== count($plan['activities'] ?? [])) throw UthengaTieErrors::validation(['payment' => 'The payment intent lacks a complete held booking quote.']);
        $user = $this->user((string) $intent['user_id']); $this->db->beginTransaction(); $bookings = []; $bookingByService = [];
        try {
            foreach ($plan['activities'] as $activity) {
                $serviceId = (string) $activity['service_id']; $listing = $this->listing($serviceId); $line = $lines[$serviceId] ?? null; $selection = $selections[$serviceId] ?? null;
                if (!is_array($line) || !is_array($selection)) throw UthengaTieErrors::validation(['payment' => 'The held quote no longer matches the trip plan.']);
                $bookingId = 'BKG-' . strtoupper(bin2hex(random_bytes(6))); $reference = 'TIE-' . strtoupper(bin2hex(random_bytes(6))); $details = ['quantity' => (int) $selection['quantity'], 'ticket_type_id' => $selection['resource_type'] === 'ticket_type' ? (int) $selection['resource_id'] : null, 'seat_class_id' => $selection['resource_type'] === 'seat_class' ? (int) $selection['resource_id'] : null, 'room_type_id' => $selection['resource_type'] === 'room_type' ? (int) $selection['resource_id'] : null, 'check_in_date' => $plan['trip_summary']['start_date'] ?? null, 'check_out_date' => $plan['trip_summary']['end_date'] ?? null, 'tour_date' => $plan['trip_summary']['start_date'] ?? null, 'payment_intent_id' => $intent['id'], 'amount_paid' => (float) ($line['payable_total'] ?? $line['total']), 'balance_due' => (float) ($line['balance_due'] ?? 0), 'rate_plan_id' => $line['rate_plan_id'] ?? null];
                $this->insertBooking(['id' => $bookingId, 'listing' => $listing, 'user' => $user, 'details' => $details, 'line' => $line, 'currency' => $intent['currency'], 'reference' => $reference]); $this->insertTransaction($reference, $bookingId, $user, $listing, $line, $intent);
                if ($listing['listing_type'] === 'event') { UthengaTicketsService::issueOnCommit($this->db, $bookingId); }
                $bookings[] = ['booking_id' => $bookingId, 'service_id' => $serviceId, 'amount' => (float) $line['total'], 'currency' => $intent['currency']]; $bookingByService[$serviceId] = $bookingId;
            }
            $resources = array_column($intent['diagnostics']['hold_resources'] ?? [], null, 'hold_id');
            foreach ($holdIds as $holdId) { $serviceId = (string) ($resources[$holdId]['service_id'] ?? ''); $this->holds->consume((string) $holdId, $intent['id'], $bookingByService[$serviceId] ?? null); } $this->db->commit(); return $bookings;
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    private function user(string $id): array { $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::authentication(); return $row; }
    private function listing(string $id): array { $stmt = $this->db->prepare('SELECT id, listing_type, title, image, vendor_id FROM listings WHERE id=? AND is_active=1 LIMIT 1 FOR UPDATE'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['booking' => 'A held listing is no longer active.']); return $row; }
    private function insertBooking(array $data): void
    {
        $l = $data['listing']; $u = $data['user']; $d = $data['details']; $line = $data['line'];
        $sql = 'INSERT INTO bookings (id, listing_id, ticket_type_id, seat_class_id, room_type_id, quantity, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, details, currency, total_price, commission_paid, discount_amount, tax_amount, commission_amount, payment_status, payment_gateway, booking_status, reference_name, transaction_id, qr_code, booked_at, confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $paymentStatus = (float) ($line['payable_total'] ?? $line['total']) >= (float) $line['total'] ? 'Paid' : 'Pending';
        $this->db->prepare($sql)->execute([$data['id'], $l['id'], $d['ticket_type_id'], $d['seat_class_id'], $d['room_type_id'], $d['quantity'], $l['title'], $l['image'], $l['listing_type'], $u['id'], $u['name'], $u['email'], json_encode(array_filter($d, static fn($v) => $v !== null), JSON_UNESCAPED_SLASHES), $data['currency'], $line['total'], $paymentStatus, 'PayChangu', 'confirmed', $l['title'], $data['reference'], 'UTHENGA-' . $data['id']]);
    }
    private function insertTransaction(string $reference, string $bookingId, array $user, array $listing, array $line, array $intent): void
    {
        $id = 'TXN-' . strtoupper(bin2hex(random_bytes(6))); $sql = 'INSERT INTO transactions (id, transaction_reference, booking_id, customer_id, user_id, customer_name, amount, gateway, gateway_ref, gateway_name, transaction_type, status, receipt_number, vendor_id, metadata, transaction_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())';
        $paid = (float) ($line['payable_total'] ?? $line['total']); $metadata = ['source' => 'tie_verified_payment', 'payment_intent_id' => $intent['id'], 'provider_tx_ref' => $intent['provider_tx_ref'], 'quote_hash' => $intent['quote_hash'], 'service_id' => $line['service_id'], 'booking_total' => (float) $line['total'], 'balance_due' => (float) ($line['balance_due'] ?? 0)]; $this->db->prepare($sql)->execute([$id, $reference, $bookingId, $user['id'], $user['id'], $user['name'], $paid, 'PayChangu', $intent['provider_tx_ref'], 'PayChangu', 'booking_payment', 'success', 'REC-' . strtoupper(bin2hex(random_bytes(5))), $listing['vendor_id'], json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    }
}

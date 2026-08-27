<?php
/** Enterprise accommodation v2: nightly inventory and stay operations. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_accommodation_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$dates = UthengaAccommodationContracts::dates('2027-01-10', '2027-01-12');
tie_accommodation_assert($dates['dates'] === ['2027-01-10', '2027-01-11'], 'Checkout date does not consume inventory.');
tie_accommodation_assert($dates['nights'] === 2, 'Two occupied nights are calculated deterministically.');
tie_accommodation_assert(UthengaAccommodationPermissions::allows('FRONT_DESK', 'stay.write'), 'Front desk may operate guest stays.');
tie_accommodation_assert(!UthengaAccommodationPermissions::allows('HOUSEKEEPING', 'finance.read'), 'Housekeeping cannot read finance data.');
tie_accommodation_assert(in_array('stay.balance_override', UthengaAccommodationPermissions::capabilities('OWNER'), true), 'Owner capabilities explicitly expose governed balance override.');
tie_accommodation_assert(!in_array('stay.balance_override', UthengaAccommodationPermissions::capabilities('FRONT_DESK'), true), 'Front desk capabilities do not expose balance override.');

if ($pdo instanceof PDO) {
    $fixture = $pdo->query("SELECT p.id property_id,p.vendor_id,p.listing_id,rt.id room_type_id,rp.id rate_plan_id
        FROM tie_accommodation_properties p
        INNER JOIN room_types rt ON rt.property_id=p.id AND rt.is_active=1
        INNER JOIN tie_accommodation_rate_plans rp ON rp.property_id=p.id AND rp.room_type_id=rt.id AND rp.is_active=1
        ORDER BY p.created_at,rt.id LIMIT 1")->fetch();
    tie_accommodation_assert(is_array($fixture), 'Migrated accommodation data provides a property, room type and rate plan fixture.');

    $service = new UthengaAccommodationService($pdo);
    $propertyId = (string) $fixture['property_id'];
    $actorId = (string) $fixture['vendor_id'];
    $roomTypeId = (int) $fixture['room_type_id'];
    $ratePlanId = (string) $fixture['rate_plan_id'];
    $listingId = (string) $fixture['listing_id'];
    $checkIn = '2027-02-10';
    $checkOut = '2027-02-12';
    $requestId = 'test-accommodation-v2';

    $portfolio=$service->portfolio($actorId);$owned=array_values(array_filter($portfolio['properties'],fn(array $property)=>$property['id']===$propertyId))[0]??null;
    tie_accommodation_assert(is_array($owned)&&in_array('inventory.write',$owned['access_capabilities'],true),'Portfolio exposes explicit capabilities for safe clients.');

    $customerQuery=$pdo->prepare("SELECT id FROM users WHERE role=? AND is_approved=1 AND account_status='active' LIMIT 1");$customerQuery->execute([ROLE_CUSTOMER]);$customerId=$customerQuery->fetchColumn();
    if($customerId){try{$service->createProperty((string)$customerId,['name'=>'Forbidden customer property','address'=>'No address'],'test-customer-property');throw new RuntimeException('Customer created a vendor property.');}catch(UthengaTieException $error){tie_accommodation_assert($error->type()==='authorization_error','Only approved vendor-capable roles can create properties.');}}

    $outsiderQuery=$pdo->prepare('SELECT id FROM users WHERE id<>? LIMIT 1');$outsiderQuery->execute([$actorId]);$outsider=$outsiderQuery->fetchColumn();
    if($outsider){try{$service->rooms($propertyId,(string)$outsider);throw new RuntimeException('Cross-property access was accepted.');}catch(UthengaTieException $error){tie_accommodation_assert($error->type()==='authorization_error','Unassigned users cannot read another property.');}}

    $pdo->beginTransaction();
    try {
        $holdProvider=new UthengaTieMariaDbInventoryHoldProvider($pdo);$holdPlan=['plan_id'=>'TIEPLAN-ACCOMMODATION-HOLD','user_id'=>$actorId,'trip_summary'=>['currency'=>'MWK','start_date'=>'2027-04-10','end_date'=>'2027-04-12'],'activities'=>[['service_id'=>$listingId,'category'=>'accommodation']]];$holdSelection=[['service_id'=>$listingId,'resource_type'=>'room_type','resource_id'=>$roomTypeId,'quantity'=>1]];$holdQuote=$holdProvider->quote($holdPlan,$holdSelection);$holdQuote['selections']=$holdSelection;$hold=$holdProvider->acquire($holdPlan,$holdQuote);
        $heldCount=$pdo->prepare("SELECT COUNT(*) FROM tie_accommodation_hold_nights WHERE hold_id=? AND status='ACTIVE'");$heldCount->execute([$hold['hold_id']]);tie_accommodation_assert((int)$heldCount->fetchColumn()===2,'Payment hold reserves every occupied night, not a global room counter.');$holdProvider->release($hold['hold_id']);$releasedCount=$pdo->prepare("SELECT COUNT(*) FROM tie_accommodation_hold_nights WHERE hold_id=? AND status='RELEASED'");$releasedCount->execute([$hold['hold_id']]);tie_accommodation_assert((int)$releasedCount->fetchColumn()===2,'Released payment hold restores every night exactly once.');

        $calendar = $service->calendar($propertyId, $actorId, $checkIn, $checkOut);
        tie_accommodation_assert(count($calendar['nights']) >= 2, 'Calendar materializes every occupied stay night.');
        $before = [];
        foreach ($calendar['nights'] as $night) {
            if ((int) $night['room_type_id'] === $roomTypeId) $before[$night['stay_date']] = (int) $night['confirmed_rooms'];
        }
        tie_accommodation_assert(count($before) === 2, 'Selected room type has both requested inventory nights.');

        $reservation = $service->createManualReservation($propertyId, $actorId, [
            'room_type_id' => $roomTypeId,
            'rate_plan_id' => $ratePlanId,
            'quantity' => 1,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'source' => 'FRONT_DESK',
            'guest_name' => 'Transactional Test Guest',
            'guest_email' => 'accommodation-test@example.invalid',
            'adults' => 1,
            'children' => 0,
        ], $requestId, 'accommodation-test-cancel-001');
        tie_accommodation_assert(in_array($reservation['status'], ['CONFIRMED', 'PENDING_APPROVAL'], true), 'Manual reservation uses the configured booking policy.');

        $held = $service->calendar($propertyId, $actorId, $checkIn, $checkOut);
        foreach ($held['nights'] as $night) {
            if ((int) $night['room_type_id'] === $roomTypeId) tie_accommodation_assert((int) $night['confirmed_rooms'] === $before[$night['stay_date']] + 1, 'Reservation confirms exactly one room on every occupied night.');
        }

        $cancelled = $service->reservationAction($propertyId, $actorId, ['reservation_id' => $reservation['id'], 'operation' => $reservation['status'] === 'PENDING_APPROVAL' ? 'REJECT' : 'CANCEL', 'reason' => 'Automated rollback fixture'], $requestId);
        tie_accommodation_assert($cancelled['status'] === 'CANCELLED', 'Cancellation reaches a terminal state.');
        $released = $service->calendar($propertyId, $actorId, $checkIn, $checkOut);
        foreach ($released['nights'] as $night) {
            if ((int) $night['room_type_id'] === $roomTypeId) tie_accommodation_assert((int) $night['confirmed_rooms'] === $before[$night['stay_date']], 'Cancellation restores every occupied night exactly once.');
        }

        $lastRoomIn='2027-03-10';$lastRoomOut='2027-03-11';
        $lastCalendar=$service->calendar($propertyId,$actorId,$lastRoomIn,$lastRoomOut);
        $lastNight=array_values(array_filter($lastCalendar['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId))[0];
        $service->updateNight($propertyId,$actorId,['room_type_id'=>$roomTypeId,'stay_date'=>$lastRoomIn,'blocked_rooms'=>(int)$lastNight['capacity_rooms']-1,'closed'=>false,'version'=>$lastNight['version']],$requestId);
        $lastRoom=$service->createManualReservation($propertyId,$actorId,['room_type_id'=>$roomTypeId,'rate_plan_id'=>$ratePlanId,'quantity'=>1,'check_in_date'=>$lastRoomIn,'check_out_date'=>$lastRoomOut,'source'=>'PHONE','guest_name'=>'Last Room Winner','adults'=>1,'children'=>0],$requestId,'accommodation-last-room-win');
        try{
            $service->createManualReservation($propertyId,$actorId,['room_type_id'=>$roomTypeId,'rate_plan_id'=>$ratePlanId,'quantity'=>1,'check_in_date'=>$lastRoomIn,'check_out_date'=>$lastRoomOut,'source'=>'PHONE','guest_name'=>'Last Room Loser','adults'=>1,'children'=>0],$requestId,'accommodation-last-room-lose');
            throw new RuntimeException('Final nightly room was sold twice.');
        }catch(UthengaTieException $error){tie_accommodation_assert($error->type()==='validation_error','Second final-room attempt fails through deterministic inventory validation.');}
        $service->reservationAction($propertyId,$actorId,['reservation_id'=>$lastRoom['id'],'operation'=>$lastRoom['status']==='PENDING_APPROVAL'?'REJECT':'CANCEL','reason'=>'Release final-room fixture'],$requestId);
        $lastCurrent=$service->calendar($propertyId,$actorId,$lastRoomIn,$lastRoomOut);
        $lastCurrentNight=array_values(array_filter($lastCurrent['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId))[0];
        $service->updateNight($propertyId,$actorId,['room_type_id'=>$roomTypeId,'stay_date'=>$lastRoomIn,'blocked_rooms'=>0,'closed'=>false,'version'=>$lastCurrentNight['version']],$requestId);

        $roomRecord=array_values(array_filter($service->rooms($propertyId,$actorId)['rooms'],fn(array $room)=>(int)$room['id']===$roomTypeId))[0];
        $updatedRoom=$service->updateRoomType($propertyId,$actorId,['room_type_id'=>$roomTypeId,'version'=>$roomRecord['version'],'room_name'=>$roomRecord['room_name'],'description'=>'Versioned room update fixture','price_per_night'=>$roomRecord['price_per_night'],'total_rooms'=>$roomRecord['total_rooms'],'max_occupancy'=>$roomRecord['max_occupancy'],'adults_capacity'=>$roomRecord['adults_capacity'],'children_capacity'=>$roomRecord['children_capacity']],$requestId);
        tie_accommodation_assert((int)$updatedRoom['version']===(int)$roomRecord['version']+1,'Room type updates use optimistic versioning.');

        $unit = $service->saveUnit($propertyId, $actorId, ['room_type_id' => $roomTypeId, 'unit_code' => 'TEST-' . strtoupper(bin2hex(random_bytes(3))), 'unit_name' => 'Transactional room', 'floor_label' => 'Test floor'], $requestId);
        $unit=$service->updateUnit($propertyId,$actorId,['unit_id'=>$unit['id'],'version'=>$unit['version'],'room_type_id'=>$roomTypeId,'unit_code'=>$unit['unit_code'],'unit_name'=>'Updated transactional room','floor_label'=>'Test floor'],$requestId);
        tie_accommodation_assert((int)$unit['version']===2,'Physical-unit updates use optimistic versioning.');

        $blockCalendar=$service->calendar($propertyId,$actorId,$checkIn,$checkOut);$firstNight=array_values(array_filter($blockCalendar['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId&&$night['stay_date']===$checkIn))[0];
        $service->updateNight($propertyId,$actorId,['room_type_id'=>$roomTypeId,'stay_date'=>$checkIn,'blocked_rooms'=>1,'closed'=>false,'version'=>$firstNight['version']],$requestId);
        $maintenance = $service->saveTask($propertyId, $actorId, ['unit_id' => $unit['id'], 'task_kind' => 'MAINTENANCE', 'priority' => 'HIGH', 'block_from' => $checkIn, 'block_until' => $checkOut, 'note' => 'Transactional maintenance block'], $requestId);
        $maintenanceCalendar = $service->calendar($propertyId, $actorId, $checkIn, $checkOut);
        foreach ($maintenanceCalendar['nights'] as $night) if ((int)$night['room_type_id']===$roomTypeId) tie_accommodation_assert((int)$night['blocked_rooms']===(int)$night['manual_blocked_rooms']+(int)$night['maintenance_blocked_rooms'],'Blocked inventory remains an exact source projection.');
        $activeFirst=array_values(array_filter($maintenanceCalendar['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId&&$night['stay_date']===$checkIn))[0];$service->updateNight($propertyId,$actorId,['room_type_id'=>$roomTypeId,'stay_date'=>$checkIn,'blocked_rooms'=>$activeFirst['blocked_rooms'],'closed'=>false,'version'=>$activeFirst['version']],$requestId);
        $service->updateTask($propertyId, $actorId, ['task_id' => $maintenance['id'], 'status' => 'COMPLETED', 'version' => $maintenance['version']], $requestId);
        $restoredCalendar = $service->calendar($propertyId, $actorId, $checkIn, $checkOut);$restoredFirst=array_values(array_filter($restoredCalendar['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId&&$night['stay_date']===$checkIn))[0];
        tie_accommodation_assert((int)$restoredFirst['manual_blocked_rooms']===1&&(int)$restoredFirst['maintenance_blocked_rooms']===0&&(int)$restoredFirst['blocked_rooms']===1,'Maintenance release preserves the independent manual block.');
        $service->updateNight($propertyId,$actorId,['room_type_id'=>$roomTypeId,'stay_date'=>$checkIn,'blocked_rooms'=>0,'closed'=>false,'version'=>$restoredFirst['version']],$requestId);
        $inspection = $service->saveTask($propertyId, $actorId, ['unit_id' => $unit['id'], 'task_kind' => 'INSPECTION', 'priority' => 'NORMAL', 'note' => 'Return-to-service inspection'], $requestId);
        $service->updateTask($propertyId, $actorId, ['task_id' => $inspection['id'], 'status' => 'COMPLETED', 'version' => $inspection['version']], $requestId);

        $futureStay=$service->createManualReservation($propertyId,$actorId,['room_type_id'=>$roomTypeId,'rate_plan_id'=>$ratePlanId,'quantity'=>1,'check_in_date'=>$checkIn,'check_out_date'=>$checkOut,'source'=>'PHONE','guest_name'=>'Early Arrival Guard','adults'=>1,'children'=>0],$requestId,'accommodation-early-arrival');
        if($futureStay['status']==='PENDING_APPROVAL')$futureStay=$service->reservationAction($propertyId,$actorId,['reservation_id'=>$futureStay['id'],'operation'=>'APPROVE'],$requestId);
        $futurePayment=$service->recordPayment($propertyId,$actorId,['reservation_id'=>$futureStay['id'],'amount'=>$futureStay['deposit_required'],'method'=>'CASH','reference'=>'EARLY-ARRIVAL-PAID'],$requestId,'accommodation-early-payment');
        $futureAssignment=$service->assignUnit($propertyId,$actorId,['reservation_id'=>$futureStay['id'],'unit_id'=>$unit['id']],$requestId);
        try{$service->stayAction($propertyId,$actorId,['reservation_id'=>$futureStay['id'],'operation'=>'CHECK_IN'],$requestId);throw new RuntimeException('Future reservation checked in early.');}catch(UthengaTieException $error){tie_accommodation_assert($error->type()==='validation_error','Arrival-date guard rejects early check-in.');}
        $assignmentRows=$service->reservations($propertyId,$actorId)['active_assignments'];$futureActive=array_values(array_filter($assignmentRows,fn(array $assignment)=>$assignment['id']===$futureAssignment['id']))[0];
        $service->unassignUnit($propertyId,$actorId,['assignment_id'=>$futureActive['id'],'version'=>$futureActive['version']],$requestId);
        $service->reservationAction($propertyId,$actorId,['reservation_id'=>$futureStay['id'],'operation'=>'CANCEL','reason'=>'Release early-arrival fixture'],$requestId);

        $unitTwo=$service->saveUnit($propertyId,$actorId,['room_type_id'=>$roomTypeId,'unit_code'=>'TEST-'.strtoupper(bin2hex(random_bytes(3))),'unit_name'=>'Replacement room','floor_label'=>'Test floor'],$requestId);
        $propertyTimezone=(string)$owned['timezone'];$arrivalIn=(new DateTimeImmutable('now',new DateTimeZone($propertyTimezone)))->format('Y-m-d');$arrivalOut=(new DateTimeImmutable($arrivalIn))->modify('+2 days')->format('Y-m-d');$extendedOut=(new DateTimeImmutable($arrivalIn))->modify('+3 days')->format('Y-m-d');
        $arrivalCalendar=$service->calendar($propertyId,$actorId,$arrivalIn,$extendedOut);$arrivalBefore=[];foreach($arrivalCalendar['nights'] as $night)if((int)$night['room_type_id']===$roomTypeId)$arrivalBefore[$night['stay_date']]=(int)$night['confirmed_rooms'];
        $stay = $service->createManualReservation($propertyId, $actorId, ['room_type_id'=>$roomTypeId,'rate_plan_id'=>$ratePlanId,'quantity'=>1,'check_in_date'=>$arrivalIn,'check_out_date'=>$arrivalOut,'source'=>'WALK_IN','guest_name'=>'Stay Lifecycle Test','adults'=>1,'children'=>0], $requestId, 'accommodation-test-stay-001');
        if($stay['status']==='PENDING_APPROVAL')$stay=$service->reservationAction($propertyId,$actorId,['reservation_id'=>$stay['id'],'operation'=>'APPROVE'],$requestId);
        $stay=$service->modifyReservation($propertyId,$actorId,['reservation_id'=>$stay['id'],'version'=>$stay['version'],'room_type_id'=>$roomTypeId,'rate_plan_id'=>$ratePlanId,'quantity'=>1,'check_in_date'=>$arrivalIn,'check_out_date'=>$extendedOut,'adults'=>1,'children'=>0,'guest_name'=>$stay['guest_name']],$requestId);
        tie_accommodation_assert($stay['check_out_date']===$extendedOut,'Reservation modification persists the revised checkout date.');
        $modifiedCalendar=$service->calendar($propertyId,$actorId,$arrivalIn,$extendedOut);$addedDate=(new DateTimeImmutable($arrivalOut))->format('Y-m-d');$addedNight=array_values(array_filter($modifiedCalendar['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId&&$night['stay_date']===$addedDate))[0];tie_accommodation_assert((int)$addedNight['confirmed_rooms']===$arrivalBefore[$addedDate]+1,'Atomic repricing acquires the newly added stay night.');
        $payment=$service->recordPayment($propertyId,$actorId,['reservation_id'=>$stay['id'],'amount'=>$stay['deposit_required'],'method'=>'CASH','reference'=>'TEST-CASH-RECEIPT-001'],$requestId,'accommodation-test-payment-001');
        tie_accommodation_assert(str_starts_with($payment['transaction']['receipt_number'],'REC-'),'Payment posting creates an auditable receipt.');
        $firstAssignment=$service->assignUnit($propertyId,$actorId,['reservation_id'=>$stay['id'],'unit_id'=>$unit['id']],$requestId);$assignmentRows=$service->reservations($propertyId,$actorId)['active_assignments'];$firstActive=array_values(array_filter($assignmentRows,fn(array $assignment)=>$assignment['id']===$firstAssignment['id']))[0];$moved=$service->moveUnit($propertyId,$actorId,['assignment_id'=>$firstActive['id'],'version'=>$firstActive['version'],'to_unit_id'=>$unitTwo['id']],$requestId);tie_accommodation_assert($moved['unit_id']===$unitTwo['id'],'Upcoming assignment can move to another clean room.');
        $checkedIn=$service->stayAction($propertyId,$actorId,['reservation_id'=>$stay['id'],'operation'=>'CHECK_IN'],$requestId);tie_accommodation_assert($checkedIn['status']==='CHECKED_IN','Paid, assigned arrival can check in on its booked date.');
        $checkedOut=$service->stayAction($propertyId,$actorId,['reservation_id'=>$stay['id'],'operation'=>'CHECK_OUT'],$requestId);tie_accommodation_assert($checkedOut['status']==='CHECKED_OUT','Settled stay can check out.');
        $task=$pdo->prepare("SELECT COUNT(*) FROM tie_accommodation_unit_tasks WHERE reservation_id=? AND task_kind='HOUSEKEEPING' AND status='OPEN'");$task->execute([$stay['id']]);tie_accommodation_assert((int)$task->fetchColumn()===1,'Checkout creates one housekeeping task for the occupied unit.');
        $unitState=$pdo->prepare('SELECT operational_status FROM tie_accommodation_units WHERE id=?');$unitState->execute([$unitTwo['id']]);tie_accommodation_assert($unitState->fetchColumn()==='DIRTY','Checkout marks the occupied room dirty.');
        $dashboard=$service->dashboard($propertyId,$actorId);tie_accommodation_assert((float)$dashboard['metrics']['today_revenue']>=(float)$payment['transaction']['amount'],'Dashboard revenue is transaction-date evidence, not reservation creation totals.');

        $policy=$service->saveCancellationPolicy($propertyId,$actorId,['name'=>'Test flexible policy','free_cancel_hours'=>48,'penalty_percent'=>50,'no_show_percent'=>100],$requestId);$policy=$service->updateCancellationPolicy($propertyId,$actorId,['policy_id'=>$policy['id'],'version'=>$policy['version'],'name'=>'Updated test policy','free_cancel_hours'=>48,'penalty_percent'=>50,'no_show_percent'=>100],$requestId);tie_accommodation_assert((int)$policy['version']===2,'Cancellation policy updates are versioned.');
        $depositRate=$service->saveRatePlan($propertyId,$actorId,['room_type_id'=>$roomTypeId,'cancellation_policy_id'=>$policy['id'],'name'=>'Deposit test rate','base_rate'=>10000,'booking_mode'=>'INSTANT','payment_mode'=>'DEPOSIT','deposit_percent'=>50,'minimum_stay'=>1,'maximum_stay'=>30],$requestId);$depositRate=$service->updateRatePlan($propertyId,$actorId,['rate_plan_id'=>$depositRate['id'],'version'=>$depositRate['version'],'room_type_id'=>$roomTypeId,'cancellation_policy_id'=>$policy['id'],'name'=>'Updated deposit test rate','base_rate'=>10000,'booking_mode'=>'INSTANT','payment_mode'=>'DEPOSIT','deposit_percent'=>50,'minimum_stay'=>1,'maximum_stay'=>30],$requestId);tie_accommodation_assert((int)$depositRate['version']===2,'Rate plan updates are versioned.');
        $singleNight=(new DateTimeImmutable($arrivalIn))->modify('+1 day')->format('Y-m-d');$deduplicated=$service->calendar($propertyId,$actorId,$arrivalIn,$singleNight);$selectedRows=array_values(array_filter($deduplicated['nights'],fn(array $night)=>(int)$night['room_type_id']===$roomTypeId));tie_accommodation_assert(count($selectedRows)===1,'Calendar emits one room-type row per date with multiple active rate plans.');

        $balanceStay=$service->createManualReservation($propertyId,$actorId,['room_type_id'=>$roomTypeId,'rate_plan_id'=>$depositRate['id'],'quantity'=>1,'check_in_date'=>$arrivalIn,'check_out_date'=>$arrivalOut,'source'=>'FRONT_DESK','guest_name'=>'Balance Gate Test','adults'=>1,'children'=>0],$requestId,'accommodation-balance-stay');$balancePay=$service->recordPayment($propertyId,$actorId,['reservation_id'=>$balanceStay['id'],'amount'=>$balanceStay['deposit_required'],'method'=>'CASH','reference'=>'BALANCE-DEPOSIT'],$requestId,'accommodation-balance-payment');$service->assignUnit($propertyId,$actorId,['reservation_id'=>$balanceStay['id'],'unit_id'=>$unit['id']],$requestId);$service->stayAction($propertyId,$actorId,['reservation_id'=>$balanceStay['id'],'operation'=>'CHECK_IN'],$requestId);try{$service->stayAction($propertyId,$actorId,['reservation_id'=>$balanceStay['id'],'operation'=>'CHECK_OUT'],$requestId);throw new RuntimeException('Outstanding stay checked out without override.');}catch(UthengaTieException $error){tie_accommodation_assert($error->type()==='validation_error','Outstanding balance blocks ordinary checkout.');}$overrideCheckout=$service->stayAction($propertyId,$actorId,['reservation_id'=>$balanceStay['id'],'operation'=>'CHECK_OUT','allow_balance_override'=>true,'balance_override_reason'=>'Manager-approved test debt follow-up'],$requestId);tie_accommodation_assert($overrideCheckout['status']==='CHECKED_OUT','Authorised owner can record an explicit balance override.');
        $service->deactivateRatePlan($propertyId,$actorId,['rate_plan_id'=>$depositRate['id'],'version'=>$depositRate['version']],$requestId);$service->deactivateCancellationPolicy($propertyId,$actorId,['policy_id'=>$policy['id'],'version'=>$policy['version']],$requestId);

        $throwaway=$service->saveUnit($propertyId,$actorId,['room_type_id'=>$roomTypeId,'unit_code'=>'TEST-'.strtoupper(bin2hex(random_bytes(3))),'unit_name'=>'Deactivate fixture'],$requestId);$inactive=$service->deactivateUnit($propertyId,$actorId,['unit_id'=>$throwaway['id'],'version'=>$throwaway['version']],$requestId);tie_accommodation_assert((int)$inactive['is_active']===0,'Unused physical units deactivate without deleting their identity.');
        $throwawayRoom=$service->saveRoomType($propertyId,$actorId,['room_name'=>'Deactivate '.strtoupper(bin2hex(random_bytes(2))),'description'=>'Deactivation fixture','price_per_night'=>5000,'total_rooms'=>1,'max_occupancy'=>1,'adults_capacity'=>1,'children_capacity'=>0],$requestId);$throwawayInventory=$service->rooms($propertyId,$actorId);$throwawayRate=array_values(array_filter($throwawayInventory['rate_plans'],fn(array $rate)=>(int)$rate['room_type_id']===(int)$throwawayRoom['id']&&$rate['is_active']))[0];$service->deactivateRatePlan($propertyId,$actorId,['rate_plan_id'=>$throwawayRate['id'],'version'=>$throwawayRate['version']],$requestId);$inactiveRoom=$service->deactivateRoomType($propertyId,$actorId,['room_type_id'=>$throwawayRoom['id'],'version'=>$throwawayRoom['version']],$requestId);tie_accommodation_assert((int)$inactiveRoom['is_active']===0,'Unused room types deactivate after their sale dependencies are disabled.');

        $audit = $service->auditLog($propertyId, $actorId);
        tie_accommodation_assert(count($audit['events']) >= 16, 'Critical accommodation actions produce audit evidence.');
        $report=$service->report($propertyId,$actorId,$arrivalIn,$extendedOut);
        tie_accommodation_assert($report['schema_version']==='tie-accommodation-report/v2','Reports expose an explicit versioned contract.');
        tie_accommodation_assert($report['operations']['completed']>=1,'Completed stays appear in the selected operational report period.');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

echo "Enterprise accommodation v2 tests passed.\n";

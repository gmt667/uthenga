<?php
/**
 * Phase 11: real-time transport coordination.
 *
 * This is intentionally a pre-journey subsystem. It creates temporary,
 * vendor-controlled seat requests; it never creates a booking or payment.
 */

final class UthengaTieCoordinationContracts
{
    // A manual map pin is an explicit, foreground user action. It is never
    // inferred from IP and remains session-only like browser geolocation.
    private const LOCATION_SOURCES = ['browser_geolocation', 'device_gps', 'manual_location'];

    public static function createRun(array $input): array
    {
        $serviceId = self::identifier($input['service_id'] ?? null, 'service_id');
        $seatClassId = self::positiveInt($input['seat_class_id'] ?? null, 'seat_class_id');
        // Capacity is fixed by the activated transport profile. The driver only
        // reports the seats physically free for this particular departure.
        $remaining = self::positiveInt($input['remaining_seats'] ?? null, 'remaining_seats', 500, true);
        $departure = self::dateTime($input['planned_departure_at'] ?? null, 'planned_departure_at');
        if ($departure <= new DateTimeImmutable('now')) throw UthengaTieErrors::validation(['planned_departure_at' => 'Departure must be in the future.']);
        $loadingLocation = self::text($input['loading_location'] ?? null, 200);
        if ($loadingLocation === null) throw UthengaTieErrors::validation(['loading_location' => 'Confirm where passengers are loading for this departure.']);
        return ['service_id' => $serviceId, 'seat_class_id' => $seatClassId, 'remaining_seats' => $remaining, 'planned_departure_at' => $departure, 'loading_location' => $loadingLocation, 'driver_note' => self::text($input['driver_note'] ?? null, 500)];
    }

    public static function requestSession(array $input): array
    {
        $runId = self::uuid($input['run_id'] ?? null, 'run_id');
        $passengers = self::positiveInt($input['passenger_count'] ?? 1, 'passenger_count', 20);
        $location = self::foregroundLocation($input['location'] ?? null);
        return ['run_id' => $runId, 'passenger_count' => $passengers, 'location' => $location, 'destination' => self::text($input['destination'] ?? null, 200)];
    }

    public static function discover(array $input): array
    {
        $passengers = self::positiveInt($input['passenger_count'] ?? 1, 'passenger_count', 20);
        return ['passenger_count' => $passengers, 'destination' => self::text($input['destination'] ?? null, 120), 'origin' => self::text($input['origin'] ?? null, 120), 'location' => self::foregroundLocation($input['location'] ?? null)];
    }

    public static function decision(array $input): array
    {
        $sessionId = self::uuid($input['session_id'] ?? null, 'session_id');
        $decision = strtoupper(trim((string) ($input['decision'] ?? '')));
        if (!in_array($decision, ['ACCEPT', 'DECLINE'], true)) throw UthengaTieErrors::validation(['decision' => 'Decision must be ACCEPT or DECLINE.']);
        return ['session_id' => $sessionId, 'decision' => $decision, 'reason' => self::text($input['reason'] ?? null, 160)];
    }

    public static function customerAction(array $input): array
    {
        $sessionId = self::uuid($input['session_id'] ?? null, 'session_id');
        $action = strtoupper(trim((string) ($input['action'] ?? '')));
        if (!in_array($action, ['EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDED', 'CANCEL'], true)) throw UthengaTieErrors::validation(['action' => 'Unsupported coordination action.']);
        return ['session_id' => $sessionId, 'action' => $action, 'reason' => self::text($input['reason'] ?? null, 160)];
    }

    public static function runUpdate(array $input): array
    {
        $runId = self::uuid($input['run_id'] ?? null, 'run_id'); $result = ['run_id' => $runId];
        if (array_key_exists('remaining_seats', $input)) $result['remaining_seats'] = self::positiveInt($input['remaining_seats'], 'remaining_seats', 500, true);
        if (array_key_exists('loading_status', $input)) { $value = strtoupper(trim((string) $input['loading_status'])); if (!in_array($value, ['NOT_OPEN', 'LOADING', 'CLOSED'], true)) throw UthengaTieErrors::validation(['loading_status' => 'Loading status is invalid.']); $result['loading_status'] = $value; }
        if (array_key_exists('status', $input)) { $value = strtoupper(trim((string) $input['status'])); if (!in_array($value, ['SCHEDULED', 'LOADING', 'TRAVELLING', 'DEPARTED', 'CANCELLED', 'COMPLETED'], true)) throw UthengaTieErrors::validation(['status' => 'Run status is invalid.']); $result['status'] = $value === 'DEPARTED' ? 'TRAVELLING' : $value; }
        if (array_key_exists('planned_departure_at', $input)) $result['planned_departure_at'] = self::dateTime($input['planned_departure_at'], 'planned_departure_at');
        if (count($result) === 1) throw UthengaTieErrors::validation(['update' => 'Provide at least one run update.']);
        return $result;
    }

    public static function locationUpdate(array $input): array
    {
        return ['session_id' => self::uuid($input['session_id'] ?? null, 'session_id'), 'location' => self::foregroundLocation($input['location'] ?? null)];
    }

    public static function message(array $input): array
    {
        $body = self::text($input['body'] ?? null, 1000);
        if ($body === null) throw UthengaTieErrors::validation(['body' => 'A message is required.']);
        return ['session_id' => self::uuid($input['session_id'] ?? null, 'session_id'), 'body' => $body];
    }

    public static function call(array $input): array { return ['session_id' => self::uuid($input['session_id'] ?? null, 'session_id')]; }
    // Shared by driver-only session actions that take no other input:
    // confirming boarding and marking a no-show.
    public static function sessionOnly(array $input): array { return ['session_id' => self::uuid($input['session_id'] ?? null, 'session_id')]; }

    public static function addWalkIn(array $input): array
    {
        $name = self::text($input['walk_in_name'] ?? null, 120);
        if ($name === null) throw UthengaTieErrors::validation(['walk_in_name' => "The walk-in passenger's name is required."]);
        $passengers = self::positiveInt($input['passenger_count'] ?? 1, 'passenger_count', 20);
        return ['run_id' => self::uuid($input['run_id'] ?? null, 'run_id'), 'walk_in_name' => $name, 'passenger_count' => $passengers, 'destination' => self::text($input['destination'] ?? null, 200)];
    }

    // Session-only input, same shape as sessionOnly() — kept distinct for
    // readability at the call site (confirmDroppedOff isn't a "no-op-shaped"
    // action, it's the per-passenger completion event).
    public static function dropOff(array $input): array { return ['session_id' => self::uuid($input['session_id'] ?? null, 'session_id')]; }

    private const ISSUE_CATEGORIES = ['vehicle', 'accident', 'passenger', 'route', 'medical', 'other'];
    public static function reportIssue(array $input): array
    {
        $category = strtolower(trim((string) ($input['category'] ?? '')));
        if (!in_array($category, self::ISSUE_CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Choose a valid issue category.']);
        $description = self::text($input['description'] ?? null, 1000);
        if ($description === null) throw UthengaTieErrors::validation(['description' => 'Describe the issue.']);
        return ['run_id' => self::uuid($input['run_id'] ?? null, 'run_id'), 'category' => $category, 'description' => $description];
    }
    public static function callDecision(array $input): array { $decision = strtoupper(trim((string) ($input['decision'] ?? ''))); if (!in_array($decision, ['ACCEPT', 'DECLINE'], true)) throw UthengaTieErrors::validation(['decision' => 'Decision must be ACCEPT or DECLINE.']); return ['call_request_id' => self::uuid($input['call_request_id'] ?? null, 'call_request_id'), 'decision' => $decision]; }
    public static function signal(array $input): array
    {
        $kind = strtolower(trim((string) ($input['kind'] ?? '')));
        if (!in_array($kind, ['offer', 'answer', 'ice', 'hangup'], true)) throw UthengaTieErrors::validation(['kind' => 'Signal kind must be offer, answer, ice, or hangup.']);
        $payload = $input['payload'] ?? null;
        if ($kind !== 'hangup' && !is_array($payload)) throw UthengaTieErrors::validation(['payload' => 'A signal payload is required.']);
        return ['call_request_id' => self::uuid($input['call_request_id'] ?? null, 'call_request_id'), 'kind' => $kind, 'payload' => is_array($payload) ? $payload : []];
    }
    // Exposed publicly so api/tie/coordination/call-signals.php can validate its ?call_request_id= query param the same way every mutating action already validates it.
    public static function callRequestId($value): string { return self::uuid($value, 'call_request_id'); }

    private static function foregroundLocation($input): array
    {
        if (!is_array($input)) throw UthengaTieErrors::validation(['location' => 'Current foreground location is required for Quick Travel.']);
        $permission = strtoupper(trim((string) ($input['permission'] ?? ''))); $source = strtolower(trim((string) ($input['source'] ?? '')));
        if ($permission !== 'GRANTED') throw UthengaTieErrors::validation(['location.permission' => 'Quick Travel requires explicit location permission.']);
        if (!in_array($source, self::LOCATION_SOURCES, true)) throw UthengaTieErrors::validation(['location.source' => 'Quick Travel accepts only foreground device location.']);
        $lat = $input['latitude'] ?? null; $lng = $input['longitude'] ?? null; $accuracy = $input['accuracy_m'] ?? null;
        if (!is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90 || !is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180) throw UthengaTieErrors::validation(['location' => 'Valid current coordinates are required.']);
        // A browser may legitimately report a coarse network-derived position
        // (tens or hundreds of kilometres). Quick Travel needs consent and
        // valid coordinates; it does not use accuracy to calculate a route.
        if ($accuracy !== null && (!is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > 1000000)) throw UthengaTieErrors::validation(['location.accuracy_m' => 'Location accuracy is invalid.']);
        return ['latitude' => round((float) $lat, 7), 'longitude' => round((float) $lng, 7), 'accuracy_m' => $accuracy === null ? null : round((float) $accuracy, 2), 'source' => $source];
    }
    private static function identifier($value, string $field): string { $value = trim((string) $value); if (!preg_match('/^[A-Za-z0-9_-]{1,30}$/', $value)) throw UthengaTieErrors::validation([$field => 'A valid identifier is required.']); return $value; }
    private static function uuid($value, string $field): string { $value = trim((string) $value); if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) throw UthengaTieErrors::validation([$field => 'A valid identifier is required.']); return strtolower($value); }
    private static function positiveInt($value, string $field, int $max = 100000, bool $allowZero = false): int { if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowZero ? 0 : 1, 'max_range' => $max]])) throw UthengaTieErrors::validation([$field => "{$field} must be a valid whole number."]); return (int) $value; }
    private static function dateTime($value, string $field): DateTimeImmutable { try { $date = new DateTimeImmutable(trim((string) $value)); } catch (Throwable $error) { throw UthengaTieErrors::validation([$field => 'A valid date and time is required.']); } return $date->setTimezone(new DateTimeZone('UTC')); }
    private static function text($value, int $maximum): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, $maximum); }
}

/**
 * Declarative UI instructions for the Agent Workspace. These are derived from
 * coordination state, never from LLM prose, so the UI and business workflow
 * cannot disagree about what the customer may do next.
 */
final class UthengaTieCoordinationWorkspace
{
    public static function discovery(array $runs): array
    {
        $count = count($runs);
        return [
            'schema_version' => 'tie-agent-workspace/v1',
            'state' => $count > 0 ? 'CANDIDATE_SELECTION' : 'NO_LIVE_DEPARTURES',
            'message' => $count > 0
                ? "I found {$count} live " . ($count === 1 ? 'departure' : 'departures') . ". Choose one and I will send your seat request to its driver."
                : 'There is no live Uthenga departure matching this request right now. You can adjust the trip details or check again shortly.',
            'component' => $count > 0 ? 'transport_candidates' : 'empty_state',
            'input' => ['expected' => false],
            'allowed_actions' => $count > 0 ? ['REQUEST_SEAT'] : ['REFINE_SEARCH'],
            'subscriptions' => [],
        ];
    }

    public static function session(array $session): array
    {
        $status = (string) $session['status'];
        $run = is_array($session['run'] ?? null) ? $session['run'] : [];
        $runStatus = (string) ($run['status'] ?? '');
        $definitions = [
            'PENDING_VENDOR' => ['AWAITING_DRIVER', 'Your request is with the driver. I will update this workspace as soon as they respond.', 'waiting', ['CANCEL'], true],
            'ACCEPTED' => ['DRIVER_CONFIRMED', 'Good news. The driver accepted your request. Head to the pickup point when you are ready.', 'mission', ['EN_ROUTE', 'ARRIVED_AT_PICKUP', 'CANCEL', 'MESSAGE', 'REQUEST_CALL'], true],
            'CUSTOMER_EN_ROUTE' => ['WALKING_TO_PICKUP', 'You are marked as on the way. Keep this workspace open for a driver update or boarding instruction.', 'mission', ['ARRIVED_AT_PICKUP', 'CANCEL', 'MESSAGE', 'REQUEST_CALL'], true],
            'ARRIVED_AT_PICKUP' => ['READY_TO_BOARD', 'You are at the pickup point. Let the driver know once you are safely on the vehicle.', 'mission', ['BOARDED', 'CANCEL', 'MESSAGE', 'REQUEST_CALL'], true],
            'BOARDING_REQUESTED' => ['BOARDING_REQUESTED', 'You are marked as on board. Waiting for the driver to confirm.', 'mission', ['CANCEL', 'MESSAGE', 'REQUEST_CALL'], true],
            'BOARDED' => $runStatus === 'COMPLETED'
                ? ['JOURNEY_COMPLETED', 'Your journey is complete. Thank you for riding with Uthenga.', 'summary', [], false]
                : ['ONBOARD', $runStatus === 'TRAVELLING' ? 'Your trip is under way. I will keep the live journey state in this workspace.' : 'Boarding is confirmed. Complete payment for this trip below.', 'journey', ['MESSAGE', 'REQUEST_CALL'], true],
            'CUSTOMER_CANCELLED' => ['REQUEST_CANCELLED', 'Your request was cancelled and any held seat was released.', 'summary', ['START_OVER'], false],
            'DECLINED' => ['REQUEST_DECLINED', 'The driver could not accept this request. You can return to the available departures.', 'summary', ['START_OVER'], false],
            'EXPIRED' => ['REQUEST_EXPIRED', 'This coordination request expired before it could be completed. You can search for another live departure.', 'summary', ['START_OVER'], false],
            'NO_SHOW' => ['DEPARTURE_MISSED', 'The departure closed before boarding was confirmed. You can search for another live departure.', 'summary', ['START_OVER'], false],
            'CANCELLED' => ['SESSION_CANCELLED', 'This transport session was cancelled. You can search for another live departure.', 'summary', ['START_OVER'], false],
            // Reached only via the driver's per-passenger "Confirm Dropped
            // Off" — a multi-passenger taxi can complete one passenger while
            // the vehicle keeps travelling with the others still aboard.
            'COMPLETED' => ['JOURNEY_COMPLETED', 'Your journey is complete. Thank you for riding with Uthenga.', 'summary', [], false],
        ];
        [$state, $message, $component, $actions, $poll] = $definitions[$status] ?? ['SESSION_UPDATE', 'I have an update to your transport session.', 'mission', [], false];
        return ['schema_version' => 'tie-agent-workspace/v1', 'state' => $state, 'message' => $message, 'component' => $component, 'input' => ['expected' => false], 'allowed_actions' => $actions, 'subscriptions' => $poll ? [['event' => 'coordination_session_changed', 'mode' => 'poll', 'interval_seconds' => 15]] : []];
    }
}

final class UthengaTieCoordinationService
{
    private const RESERVING_STATUSES = ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED'];
    public function __construct(private ?PDO $db) {}

    public function createRun(array $input, string $vendorId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::createRun($input); $this->db->beginTransaction();
        try {
            // Lock the vendor row first. This serialises competing create-session
            // requests for the same driver even when there is no active run yet.
            $this->lockVendor($vendorId);
            $this->expireRunsForVendor($vendorId);
            $active = $this->activeRunForVendor($vendorId);
            if ($active !== null) throw UthengaTieErrors::validation(['session' => 'You already have an active transport session. Mark it travelling, complete it, or cancel it before starting another.']);
            $listing = $this->listingForVendor($request['service_id'], $vendorId); if (strtolower((string) $listing['listing_type']) !== 'transport') throw UthengaTieErrors::validation(['service_id' => 'Quick Travel runs require a transport listing.']);
            $seat = $this->db->prepare('SELECT id, total_seats FROM seat_classes WHERE id=? AND listing_id=? AND is_active=1 FOR UPDATE'); $seat->execute([$request['seat_class_id'], $request['service_id']]); $seatRow = $seat->fetch(); if (!is_array($seatRow)) throw UthengaTieErrors::validation(['seat_class_id' => 'Choose an active seat class owned by this transport service.']);
            $capacity = (int) $seatRow['total_seats']; if ($request['remaining_seats'] > $capacity) throw UthengaTieErrors::validation(['remaining_seats' => "Available seats cannot exceed this vehicle's configured capacity of {$capacity}."]);
            $id = $this->id(); $statement = $this->db->prepare('INSERT INTO tie_transport_runs (id, service_id, vendor_id, driver_user_id, seat_class_id, service_date, planned_departure_at, capacity, remaining_seats, status, loading_status, loading_location, driver_note, loading_started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'); $statement->execute([$id, $request['service_id'], $vendorId, $vendorId, $request['seat_class_id'], $request['planned_departure_at']->format('Y-m-d'), $request['planned_departure_at']->format('Y-m-d H:i:s'), $capacity, $request['remaining_seats'], 'LOADING', 'LOADING', $request['loading_location'], $request['driver_note']]);
            $this->db->commit(); return $this->run($id, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function request(array $input, string $customerId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::requestSession($input); $this->db->beginTransaction();
        try {
            $this->expire(); $run = $this->lockedRun($request['run_id']); if (!in_array($run['status'], ['SCHEDULED', 'LOADING'], true) || $run['loading_status'] === 'CLOSED') throw UthengaTieErrors::validation(['run' => 'This departure is not accepting coordination requests.']); if ((new DateTimeImmutable((string) $run['planned_departure_at'], new DateTimeZone('UTC')))->getTimestamp() <= time()) throw UthengaTieErrors::validation(['run' => 'This departure has already left.']);
            $id = $this->id(); $expires = gmdate('Y-m-d H:i:s', time() + max(60, UthengaTieConfig::integer('TIE_COORDINATION_REQUEST_SECONDS', 600))); $stmt = $this->db->prepare('INSERT INTO tie_transport_sessions (id, run_id, service_id, customer_id, vendor_id, passenger_count, destination, status, reservation_state, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'); $stmt->execute([$id, $run['id'], $run['service_id'], $customerId, $run['vendor_id'], $request['passenger_count'], $request['destination'], 'PENDING_VENDOR', 'NONE', $expires]); $this->event($id, 'SESSION_CREATED', 'customer', $customerId, ['passenger_count' => $request['passenger_count']]); $this->snapshot($id, 'customer', $customerId, $request['location']); $this->db->commit(); return $this->session($id, $customerId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function discover(array $input, string $customerId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::discover($input); $this->expire();
        $stmt = $this->db->prepare("SELECT r.*, l.title, l.image, l.location, l.meta, l.gps_lat, l.gps_lng, sc.class_name, sc.price FROM tie_transport_runs r INNER JOIN listings l ON l.id=r.service_id AND l.is_active=1 LEFT JOIN seat_classes sc ON sc.id=r.seat_class_id AND sc.is_active=1 WHERE r.status IN ('SCHEDULED','LOADING') AND r.loading_status <> 'CLOSED' AND r.remaining_seats >= ? AND r.planned_departure_at > UTC_TIMESTAMP() ORDER BY r.planned_departure_at ASC LIMIT 30"); $stmt->execute([$request['passenger_count']]);
        $runs = []; foreach ($stmt->fetchAll() as $row) { $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: []; if ($request['destination'] !== null && !$this->samePlace($request['destination'], (string) ($meta['routeTo'] ?? ''))) continue; if ($request['origin'] !== null && !$this->samePlace($request['origin'], (string) ($meta['routeFrom'] ?? ''))) continue; $pickup = is_numeric($row['gps_lat'] ?? null) && is_numeric($row['gps_lng'] ?? null) ? ['latitude' => round((float) $row['gps_lat'], 6), 'longitude' => round((float) $row['gps_lng'], 6)] : null; $runs[] = $this->publicRun($row) + ['title' => (string) $row['title'], 'image' => $row['image'] ? (string) $row['image'] : null, 'origin' => (string) ($meta['routeFrom'] ?? ''), 'destination' => (string) ($meta['routeTo'] ?? ''), 'departure_point' => (string) ($row['location'] ?? ''), 'pickup_coordinates' => $pickup, 'seat_class' => (string) ($row['class_name'] ?? ''), 'fare' => $row['price'] === null ? null : (float) $row['price'], 'currency' => APP_CURRENCY]; }
        return ['schema_version' => 'tie-quick-travel-discovery/v1', 'runs' => $runs, 'workspace' => UthengaTieCoordinationWorkspace::discovery($runs), 'location_required' => true, 'provenance' => ['runs' => 'vendor_published', 'location' => 'foreground_session_context']];
    }

    public function vendorDecision(array $input, string $vendorId): array
    {
        $this->db(); $decision = UthengaTieCoordinationContracts::decision($input); $this->db->beginTransaction();
        try {
            $this->expire(); $session = $this->lockedSession($decision['session_id']); $this->assertVendor($session, $vendorId); if ($session['status'] !== 'PENDING_VENDOR') throw UthengaTieErrors::validation(['session' => 'This request has already been handled.']);
            if ($decision['decision'] === 'DECLINE') { $this->db->prepare("UPDATE tie_transport_sessions SET status='DECLINED', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='vendor', cancellation_reason=? WHERE id=?")->execute([$decision['reason'], $session['id']]); $this->event($session['id'], 'VENDOR_DECLINED', 'vendor', $vendorId, ['reason' => $decision['reason']]); }
            else { $run = $this->lockedRun($session['run_id']); if (!in_array($run['status'], ['SCHEDULED', 'LOADING'], true) || $run['loading_status'] === 'CLOSED' || (int) $run['remaining_seats'] < (int) $session['passenger_count']) throw UthengaTieErrors::validation(['run' => 'There are no longer enough seats available for this request.']); $this->db->prepare('UPDATE tie_transport_runs SET remaining_seats=remaining_seats-?, version=version+1 WHERE id=? AND remaining_seats>=?')->execute([(int) $session['passenger_count'], $run['id'], (int) $session['passenger_count']]); $expires = gmdate('Y-m-d H:i:s', time() + max(60, UthengaTieConfig::integer('TIE_COORDINATION_ACCEPTED_SECONDS', 900))); $this->db->prepare("UPDATE tie_transport_sessions SET status='ACCEPTED', reservation_state='HELD', accepted_at=UTC_TIMESTAMP(), expires_at=? WHERE id=?")->execute([$expires, $session['id']]); $this->event($session['id'], 'VENDOR_ACCEPTED', 'vendor', $vendorId, ['reservation_expires_at' => $expires]); }
            $this->db->commit(); return $this->session($session['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    // Driver-only: a passenger with no Uthenga account, physically present at
    // the vehicle. Enters directly at BOARDING_REQUESTED — the same driver
    // Confirm Boarding step every other passenger goes through, never a
    // shortcut around it — and is tagged booking_source='walk_in' for
    // reconciliation and analytics.
    public function addWalkIn(array $input, string $vendorId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::addWalkIn($input); $this->db->beginTransaction();
        try {
            $this->expire(); $run = $this->lockedRun($request['run_id']);
            if ((string) $run['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            if (!in_array($run['status'], ['SCHEDULED', 'LOADING'], true) || $run['loading_status'] === 'CLOSED') throw UthengaTieErrors::validation(['run' => 'This departure is not accepting passengers.']);
            if ((int) $run['remaining_seats'] < $request['passenger_count']) throw UthengaTieErrors::validation(['run' => 'There are not enough free seats for this walk-in.']);
            $this->db->prepare('UPDATE tie_transport_runs SET remaining_seats=remaining_seats-?, version=version+1 WHERE id=? AND remaining_seats>=?')->execute([$request['passenger_count'], $run['id'], $request['passenger_count']]);
            $id = $this->id(); $expires = gmdate('Y-m-d H:i:s', time() + max(60, UthengaTieConfig::integer('TIE_COORDINATION_ACCEPTED_SECONDS', 900)));
            $this->db->prepare('INSERT INTO tie_transport_sessions (id, run_id, service_id, customer_id, walk_in_name, booking_source, vendor_id, passenger_count, destination, status, reservation_state, expires_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, $run['id'], $run['service_id'], $request['walk_in_name'], 'walk_in', $vendorId, $request['passenger_count'], $request['destination'], 'BOARDING_REQUESTED', 'HELD', $expires]);
            $this->event($id, 'WALK_IN_ADDED', 'vendor', $vendorId, ['passenger_count' => $request['passenger_count']]);
            $this->db->commit(); return $this->session($id, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function customerAction(array $input, string $customerId): array
    {
        // Boarding is a two-step handshake: the customer's 'BOARDED' action
        // only *requests* boarding (BOARDING_REQUESTED). It becomes BOARDED
        // only once the driver physically verifies presence and calls
        // confirmBoarding() — see the class docblock's "driver confirms
        // physical boarding" principle.
        $this->db(); $action = UthengaTieCoordinationContracts::customerAction($input); $this->db->beginTransaction();
        try {
            $this->expire(); $session = $this->lockedSession($action['session_id']); $this->assertCustomer($session, $customerId); $next = ['EN_ROUTE' => 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP' => 'ARRIVED_AT_PICKUP', 'BOARDED' => 'BOARDING_REQUESTED', 'CANCEL' => 'CUSTOMER_CANCELLED'][$action['action']];
            // A customer can withdraw a request at any point before boarding
            // is confirmed, including before the driver has even decided —
            // PENDING_VENDOR must stay cancellable, not just post-acceptance.
            $allowed = ['PENDING_VENDOR' => ['CUSTOMER_CANCELLED'], 'ACCEPTED' => ['CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED', 'CUSTOMER_CANCELLED'], 'CUSTOMER_EN_ROUTE' => ['ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED', 'CUSTOMER_CANCELLED'], 'ARRIVED_AT_PICKUP' => ['BOARDING_REQUESTED', 'CUSTOMER_CANCELLED']]; if (!in_array($next, $allowed[$session['status']] ?? [], true)) throw UthengaTieErrors::validation(['session' => 'This action is not available in the current coordination state.']);
            if ($next === 'CUSTOMER_CANCELLED') { $this->releaseReservation($session); $this->db->prepare("UPDATE tie_transport_sessions SET status=?, reservation_state='RELEASED', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='customer', cancellation_reason=? WHERE id=?")->execute([$next, $action['reason'], $session['id']]); } else { $fields = $next === 'ARRIVED_AT_PICKUP' ? ', arrived_at=UTC_TIMESTAMP()' : ''; $this->db->prepare("UPDATE tie_transport_sessions SET status=?{$fields} WHERE id=?")->execute([$next, $session['id']]); }
            $this->event($session['id'], $action['action'], 'customer', $customerId, $action['reason'] ? ['reason' => $action['reason']] : []); $this->db->commit(); return $this->session($session['id'], $customerId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    // Driver-only: verifies the passenger is physically present and confirms
    // boarding. This is the only path to BOARDED — a customer can never set
    // it directly (see customerAction()'s BOARDING_REQUESTED mapping).
    public function confirmBoarding(array $input, string $vendorId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::sessionOnly($input); $this->db->beginTransaction();
        try {
            $this->expire(); $session = $this->lockedSession($request['session_id']); $this->assertVendor($session, $vendorId);
            if ($session['status'] !== 'BOARDING_REQUESTED') throw UthengaTieErrors::validation(['session' => 'This passenger has not requested boarding yet.']);
            $this->db->prepare("UPDATE tie_transport_sessions SET status='BOARDED', boarded_at=UTC_TIMESTAMP(), reservation_state='CONSUMED' WHERE id=?")->execute([$session['id']]);
            $this->event($session['id'], 'BOARDING_CONFIRMED', 'vendor', $vendorId, []);
            $this->db->commit(); return $this->session($session['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    // Driver-only: a boarded passenger reaches their own destination and
    // exits, independent of the run's own completion — a multi-passenger
    // taxi can drop passengers off one at a time while the vehicle
    // continues, not just all-at-once when the whole departure ends.
    public function confirmDroppedOff(array $input, string $vendorId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::dropOff($input); $this->db->beginTransaction();
        try {
            $this->expire(); $session = $this->lockedSession($request['session_id']); $this->assertVendor($session, $vendorId);
            if ($session['status'] !== 'BOARDED') throw UthengaTieErrors::validation(['session' => 'Only a boarded passenger can be marked dropped off.']);
            $run = $this->runRow((string) $session['run_id']);
            if ($run['status'] !== 'TRAVELLING') throw UthengaTieErrors::validation(['run' => 'A passenger can only be dropped off once the trip is under way.']);
            $this->db->prepare("UPDATE tie_transport_sessions SET status='COMPLETED' WHERE id=?")->execute([$session['id']]);
            $this->event($session['id'], 'PASSENGER_DROPPED_OFF', 'vendor', $vendorId, []);
            $this->db->commit(); return $this->session($session['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    // Driver-only: resolves a straggler before departure without waiting for
    // the run-level TRAVELLING transition to sweep it up automatically.
    public function vendorMarkNoShow(array $input, string $vendorId): array
    {
        $this->db(); $request = UthengaTieCoordinationContracts::sessionOnly($input); $this->db->beginTransaction();
        try {
            $this->expire(); $session = $this->lockedSession($request['session_id']); $this->assertVendor($session, $vendorId);
            if (!in_array($session['status'], ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED'], true)) throw UthengaTieErrors::validation(['session' => 'This passenger cannot be marked as a no-show right now.']);
            $this->releaseReservation($session);
            $this->db->prepare("UPDATE tie_transport_sessions SET status='NO_SHOW', reservation_state='RELEASED', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='vendor', cancellation_reason='Marked as a no-show by the driver.' WHERE id=?")->execute([$session['id']]);
            $this->event($session['id'], 'NO_SHOW', 'vendor', $vendorId, []);
            $this->db->commit(); return $this->session($session['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    // Driver-only, run-scoped incident log (vehicle/accident/passenger/route/
    // medical/other) — deliberately independent of the per-session event log.
    public function reportIssue(array $input, string $vendorId): array
    {
        $this->db(); $report = UthengaTieCoordinationContracts::reportIssue($input);
        $run = $this->runRow($report['run_id']); if ((string) $run['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        $id = $this->id();
        $this->db->prepare('INSERT INTO tie_transport_run_issues (id, run_id, vendor_id, category, description) VALUES (?, ?, ?, ?, ?)')->execute([$id, $report['run_id'], $vendorId, $report['category'], $report['description']]);
        return ['id' => $id, 'category' => $report['category'], 'description' => $report['description'], 'created_at' => gmdate('c')];
    }

    public function updateRun(array $input, string $vendorId): array
    {
        $this->db(); $update = UthengaTieCoordinationContracts::runUpdate($input); $this->db->beginTransaction();
        try {
            $run = $this->lockedRun($update['run_id']); if ((string) $run['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            $this->assertRunTransition((string) $run['status'], $update['status'] ?? null);
            if (isset($update['remaining_seats'])) { $held = $this->heldSeats($run['id']); $maximum = (int) $run['capacity'] - $held; if ($update['remaining_seats'] > $maximum) throw UthengaTieErrors::validation(['remaining_seats' => 'Remaining seats cannot erase seats already held through Uthenga.']); }
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if (($update['status'] ?? '') === 'LOADING') { $update['loading_status'] = 'LOADING'; $update['loading_started_at'] = $now; }
            if (($update['status'] ?? '') === 'TRAVELLING') { $update['loading_status'] = 'CLOSED'; $update['actual_departure_at'] = $now; $update['travelling_started_at'] = $now; $this->markNoShows($run['id']); }
            if (($update['status'] ?? '') === 'CANCELLED') { $update['loading_status'] = 'CLOSED'; $this->cancelRunSessions($run['id'], $vendorId, 'Driver cancelled this transport session.'); }
            if (($update['status'] ?? '') === 'COMPLETED') { $update['loading_status'] = 'CLOSED'; $update['completed_at'] = $now; }
            $fields = []; $params = []; foreach (['remaining_seats', 'loading_status', 'status'] as $field) if (array_key_exists($field, $update)) { $fields[] = "{$field}=?"; $params[] = $update[$field]; } if (isset($update['planned_departure_at'])) { $fields[] = 'planned_departure_at=?'; $params[] = $update['planned_departure_at']->format('Y-m-d H:i:s'); $fields[] = 'service_date=?'; $params[] = $update['planned_departure_at']->format('Y-m-d'); } foreach (['actual_departure_at', 'loading_started_at', 'travelling_started_at', 'completed_at'] as $timestamp) if (isset($update[$timestamp])) { $fields[] = "{$timestamp}=?"; $params[] = $update[$timestamp]->format('Y-m-d H:i:s'); } $fields[] = 'version=version+1'; $params[] = $run['id']; $this->db->prepare('UPDATE tie_transport_runs SET ' . implode(', ', $fields) . ' WHERE id=?')->execute($params);
            $this->db->commit(); return $this->run($run['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function location(array $input, string $actorId): array
    {
        $this->db(); $input = UthengaTieCoordinationContracts::locationUpdate($input); $session = $this->sessionRow($input['session_id']); $role = $this->role($session, $actorId); if ($role === null) throw UthengaTieErrors::authorization(); $this->snapshot($session['id'], $role, $actorId, $input['location']); $this->event($session['id'], 'LOCATION_UPDATED', $role, $actorId, ['source' => $input['location']['source']]); return $this->session($session['id'], $actorId);
    }

    public function sendMessage(array $input, string $actorId): array
    {
        $this->db(); $input = UthengaTieCoordinationContracts::message($input); $session = $this->sessionRow($input['session_id']); $role = $this->role($session, $actorId); if ($role === null || !$this->isInteractive($session['status'])) throw UthengaTieErrors::authorization(); $id = $this->id(); $this->db->prepare('INSERT INTO tie_transport_messages (id, session_id, sender_id, sender_role, body) VALUES (?, ?, ?, ?, ?)')->execute([$id, $session['id'], $actorId, $role, $input['body']]); $this->event($session['id'], 'MESSAGE_SENT', $role, $actorId, []); return ['message_id' => $id, 'created_at' => gmdate('c')];
    }

    public function requestCall(array $input, string $actorId): array
    {
        $this->db(); $input = UthengaTieCoordinationContracts::call($input); $session = $this->sessionRow($input['session_id']); $role = $this->role($session, $actorId); if ($role === null || !$this->isInteractive($session['status'])) throw UthengaTieErrors::authorization(); $recipient = $role === 'customer' ? (string) $session['vendor_id'] : (string) $session['customer_id']; $id = $this->id(); $expires = gmdate('Y-m-d H:i:s', time() + 300); $this->db->prepare('INSERT INTO tie_transport_call_requests (id, session_id, requester_id, recipient_id, status, expires_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $session['id'], $actorId, $recipient, 'REQUESTED', $expires]); $this->event($session['id'], 'CALL_REQUESTED', $role, $actorId, []); return ['call_request_id' => $id, 'status' => 'REQUESTED', 'expires_at' => gmdate('c', strtotime($expires))];
    }

    public function decideCall(array $input, string $actorId): array
    {
        // Accepting a call no longer reveals a phone number — it only flips
        // status to ACCEPTED, which is what tells both sides (via session()'s
        // call state) to start a real in-app WebRTC call instead.
        $this->db(); $input = UthengaTieCoordinationContracts::callDecision($input); $call = $this->db->prepare('SELECT * FROM tie_transport_call_requests WHERE id=? LIMIT 1'); $call->execute([$input['call_request_id']]); $row = $call->fetch(); if (!is_array($row) || (string) $row['recipient_id'] !== $actorId || $row['status'] !== 'REQUESTED' || strtotime((string) $row['expires_at'] . ' UTC') <= time()) throw UthengaTieErrors::authorization(); $status = $input['decision'] === 'ACCEPT' ? 'ACCEPTED' : 'DECLINED'; $this->db->prepare('UPDATE tie_transport_call_requests SET status=?, accepted_at=IF(?="ACCEPTED", UTC_TIMESTAMP(), NULL) WHERE id=?')->execute([$status, $status, $row['id']]); $this->event((string) $row['session_id'], 'CALL_' . $status, $this->role($this->sessionRow((string) $row['session_id']), $actorId) ?: 'vendor', $actorId, []); return ['status' => $status, 'call_request_id' => (string) $row['id']];
    }

    public function postSignal(array $input, string $actorId): array
    {
        $this->db(); $input = UthengaTieCoordinationContracts::signal($input); $this->db->beginTransaction();
        try {
            $call = $this->db->prepare('SELECT * FROM tie_transport_call_requests WHERE id=? LIMIT 1 FOR UPDATE'); $call->execute([$input['call_request_id']]); $row = $call->fetch();
            if (!is_array($row) || ((string) $row['requester_id'] !== $actorId && (string) $row['recipient_id'] !== $actorId)) throw UthengaTieErrors::authorization();
            $isRequester = (string) $row['requester_id'] === $actorId;
            if ($input['kind'] === 'hangup') {
                if ($row['status'] === 'REQUESTED') $this->db->prepare('UPDATE tie_transport_call_requests SET status=? WHERE id=?')->execute([$isRequester ? 'CANCELLED' : 'DECLINED', $row['id']]);
                elseif ($row['status'] === 'ACCEPTED') $this->db->prepare("UPDATE tie_transport_call_requests SET status='ENDED' WHERE id=?")->execute([$row['id']]);
            } elseif ($row['status'] !== 'ACCEPTED') {
                throw UthengaTieErrors::validation(['call_request_id' => 'This call is not active.']);
            }
            $role = $this->role($this->sessionRow((string) $row['session_id']), $actorId);
            if ($role === null) throw UthengaTieErrors::authorization();
            $this->db->prepare('INSERT INTO tie_transport_call_signals (call_request_id, sender_role, kind, payload) VALUES (?, ?, ?, ?)')->execute([$row['id'], $role, $input['kind'], json_encode($input['payload'], JSON_UNESCAPED_SLASHES)]);
            $this->event((string) $row['session_id'], 'CALL_SIGNAL_' . strtoupper($input['kind']), $role, $actorId, []);
            $this->db->commit();
            return ['posted' => true];
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function signals(string $callRequestId, string $actorId, int $sinceId): array
    {
        $this->db(); $call = $this->db->prepare('SELECT requester_id, recipient_id FROM tie_transport_call_requests WHERE id=? LIMIT 1'); $call->execute([$callRequestId]); $row = $call->fetch();
        if (!is_array($row) || ((string) $row['requester_id'] !== $actorId && (string) $row['recipient_id'] !== $actorId)) throw UthengaTieErrors::authorization();
        $stmt = $this->db->prepare('SELECT id, sender_role, kind, payload, created_at FROM tie_transport_call_signals WHERE call_request_id=? AND id>? ORDER BY id ASC LIMIT 200');
        $stmt->execute([$callRequestId, $sinceId]);
        return ['signals' => array_map(fn(array $r): array => ['id' => (int) $r['id'], 'sender_role' => (string) $r['sender_role'], 'kind' => (string) $r['kind'], 'payload' => json_decode((string) $r['payload'], true), 'created_at' => gmdate('c', strtotime($r['created_at'] . ' UTC'))], $stmt->fetchAll())];
    }

    public function session(string $sessionId, string $actorId): array
    {
        $this->db(); $this->expire(); $session = $this->sessionRow($sessionId); $role = $this->role($session, $actorId); if ($role === null) throw UthengaTieErrors::authorization(); $run = $this->runRow((string) $session['run_id']); $publicSession = $this->publicSession($session, $run); $events = $this->db->prepare('SELECT event_type, actor_type, created_at FROM tie_transport_session_events WHERE session_id=? ORDER BY id DESC LIMIT 50'); $events->execute([$sessionId]); $messages = $this->db->prepare('SELECT id, sender_role, body, created_at, read_at FROM tie_transport_messages WHERE session_id=? ORDER BY created_at ASC LIMIT 100'); $messages->execute([$sessionId]); return ['schema_version' => 'tie-coordination-session/v1', 'session' => $publicSession, 'workspace' => UthengaTieCoordinationWorkspace::session($publicSession), 'viewer_role' => $role, 'events' => $events->fetchAll(), 'messages' => $messages->fetchAll(), 'call' => $this->callState($sessionId, $actorId), 'latest_locations' => $this->latestLocations($sessionId, $role), 'provenance' => ['location' => 'ephemeral_session_scoped', 'booking' => 'not_created']];
    }

    public function run(string $runId, string $vendorId): array { $this->db(); $run = $this->runRow($runId); if ((string) $run['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization(); return ['schema_version' => 'tie-transport-run/v1', 'run' => $this->publicRun($run), 'pending_requests' => $this->vendorSessions($runId)]; }
    // Read-only departure history for the Trips workspace's "Departures"
    // view — Phase 4 of the Quick Taxi rebuild. Kept deliberately separate
    // from tie_trips' single-passenger trip list rather than forced into the
    // same shape (a multi-passenger manifest isn't one trip row).
    // Read-only receipt history for the customer's own past Quick Taxi
    // requests — every session they've ever created, most recent first,
    // with the real payment receipt (if any) for that session.
    public function customerHistory(string $customerId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            "SELECT s.id, s.status, s.passenger_count, s.created_at, s.boarded_at, s.cancelled_at, s.cancellation_reason,
             r.loading_location, r.planned_departure_at, r.actual_departure_at, r.completed_at AS run_completed_at, r.status AS run_status,
             (SELECT p.amount FROM tie_transport_payments p WHERE p.session_id = s.id AND p.state='PAID' ORDER BY p.created_at DESC LIMIT 1) AS paid_amount,
             (SELECT p.method FROM tie_transport_payments p WHERE p.session_id = s.id AND p.state='PAID' ORDER BY p.created_at DESC LIMIT 1) AS paid_method,
             (SELECT p.confirmed_at FROM tie_transport_payments p WHERE p.session_id = s.id AND p.state='PAID' ORDER BY p.created_at DESC LIMIT 1) AS paid_at
             FROM tie_transport_sessions s INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE s.customer_id = ? ORDER BY s.created_at DESC LIMIT 100"
        );
        $stmt->execute([$customerId]);
        $trips = array_map(fn(array $row): array => [
            'id' => (string) $row['id'],
            'status' => (string) $row['status'],
            'passenger_count' => (int) $row['passenger_count'],
            'loading_location' => (string) $row['loading_location'],
            'requested_at' => gmdate('c', strtotime($row['created_at'] . ' UTC')),
            'boarded_at' => $row['boarded_at'] ? gmdate('c', strtotime($row['boarded_at'] . ' UTC')) : null,
            'cancelled_at' => $row['cancelled_at'] ? gmdate('c', strtotime($row['cancelled_at'] . ' UTC')) : null,
            'cancellation_reason' => $row['cancellation_reason'] ?? null,
            'completed_at' => $row['run_completed_at'] ? gmdate('c', strtotime($row['run_completed_at'] . ' UTC')) : null,
            'run_status' => (string) $row['run_status'],
            'receipt' => $row['paid_amount'] !== null ? [
                'amount' => (float) $row['paid_amount'],
                'method' => (string) $row['paid_method'],
                'paid_at' => $row['paid_at'] ? gmdate('c', strtotime($row['paid_at'] . ' UTC')) : null,
            ] : null,
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-customer-trip-history/v1', 'trips' => $trips];
    }

    public function vendorDepartureHistory(string $vendorId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            "SELECT r.*,
             (SELECT COALESCE(SUM(s.passenger_count),0) FROM tie_transport_sessions s WHERE s.run_id=r.id AND s.status IN ('BOARDED','COMPLETED')) AS boarded_passengers,
             (SELECT COALESCE(SUM(p.amount),0) FROM tie_transport_payments p INNER JOIN tie_transport_sessions s2 ON s2.id=p.session_id WHERE s2.run_id=r.id AND p.state='PAID') AS total_revenue,
             (SELECT COALESCE(SUM(p.amount),0) FROM tie_transport_payments p INNER JOIN tie_transport_sessions s3 ON s3.id=p.session_id WHERE s3.run_id=r.id AND p.state='PAID' AND p.method='cash') AS cash_revenue
             FROM tie_transport_runs r WHERE r.vendor_id=? AND r.status IN ('COMPLETED','CANCELLED','EXPIRED') ORDER BY COALESCE(r.completed_at, r.updated_at) DESC LIMIT 100"
        );
        $stmt->execute([$vendorId]);
        $departures = array_map(fn(array $row): array => $this->publicRun($row) + [
            'boarded_passengers' => (int) $row['boarded_passengers'],
            'total_revenue' => (float) $row['total_revenue'],
            'cash_revenue' => (float) $row['cash_revenue'],
            'digital_revenue' => (float) $row['total_revenue'] - (float) $row['cash_revenue'],
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-departure-history/v1', 'departures' => $departures];
    }

    public function vendorDepartureManifest(string $vendorId, string $runId): array
    {
        $this->db(); $run = $this->runRow($runId);
        if ((string) $run['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        $stmt = $this->db->prepare('SELECT * FROM tie_transport_sessions WHERE run_id=? ORDER BY created_at ASC');
        $stmt->execute([$runId]);
        $sessions = array_map(fn(array $row): array => $this->publicSession($row, $run), $stmt->fetchAll());
        return ['schema_version' => 'tie-departure-manifest/v1', 'run' => $this->publicRun($run), 'sessions' => $sessions];
    }

    // Includes each passenger's live location (for the driver's "all
    // passengers on one map" view) and when they started walking (for the
    // driver-side walking-progress advisory) — both real, both optional
    // (null when a passenger hasn't shared a location, or hasn't started
    // walking yet).
    public function vendorQueue(string $vendorId): array {
        $this->db(); $this->expire();
        $stmt = $this->db->prepare('SELECT * FROM tie_transport_sessions WHERE vendor_id=? AND status IN ("PENDING_VENDOR","ACCEPTED","CUSTOMER_EN_ROUTE","ARRIVED_AT_PICKUP","BOARDING_REQUESTED","BOARDED") ORDER BY expires_at ASC LIMIT 100');
        $stmt->execute([$vendorId]);
        $items = [];
        foreach ($stmt->fetchAll() as $session) {
            $public = $this->publicSession($session, $this->runRow((string) $session['run_id']));
            $public['location'] = $this->latestActorLocation((string) $session['id'], 'customer');
            $public['en_route_since'] = $session['status'] === 'CUSTOMER_EN_ROUTE' ? $this->sessionEventTime((string) $session['id'], 'EN_ROUTE') : null;
            $items[] = $public;
        }
        $calls = $this->db->prepare("SELECT id, session_id, expires_at FROM tie_transport_call_requests WHERE recipient_id=? AND status='REQUESTED' AND expires_at>UTC_TIMESTAMP() ORDER BY created_at ASC LIMIT 50"); $calls->execute([$vendorId]);
        $active = $this->activeRunForVendor($vendorId);
        return ['schema_version' => 'tie-vendor-coordination-queue/v1', 'active_run' => $active ? $this->publicRun($active) : null, 'sessions' => $items, 'call_requests' => $calls->fetchAll()];
    }

    private function latestActorLocation(string $sessionId, string $actorType): ?array
    {
        $stmt = $this->db->prepare('SELECT latitude, longitude, accuracy_m, captured_at FROM tie_transport_location_snapshots WHERE session_id=? AND actor_type=? AND expires_at>UTC_TIMESTAMP() ORDER BY captured_at DESC LIMIT 1');
        $stmt->execute([$sessionId, $actorType]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return ['latitude' => round((float) $row['latitude'], 6), 'longitude' => round((float) $row['longitude'], 6), 'accuracy_m' => $row['accuracy_m'] !== null ? round((float) $row['accuracy_m'], 2) : null, 'captured_at' => gmdate('c', strtotime($row['captured_at'] . ' UTC'))];
    }

    private function sessionEventTime(string $sessionId, string $eventType): ?string
    {
        $stmt = $this->db->prepare('SELECT created_at FROM tie_transport_session_events WHERE session_id=? AND event_type=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$sessionId, $eventType]);
        $value = $stmt->fetchColumn();
        return $value !== false ? gmdate('c', strtotime($value . ' UTC')) : null;
    }

    private function expire(): void
    {
        if (!$this->db) return; $ownsTransaction = !$this->db->inTransaction(); if ($ownsTransaction) $this->db->beginTransaction(); try { $stmt = $this->db->query("SELECT * FROM tie_transport_sessions WHERE status IN ('PENDING_VENDOR','ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED') AND expires_at <= UTC_TIMESTAMP() FOR UPDATE"); foreach ($stmt->fetchAll() as $session) { if (in_array($session['status'], self::RESERVING_STATUSES, true)) $this->releaseReservation($session); $this->db->prepare("UPDATE tie_transport_sessions SET status='EXPIRED', reservation_state=IF(reservation_state='HELD','RELEASED',reservation_state), cancelled_at=UTC_TIMESTAMP(), cancellation_actor='system', cancellation_reason='Coordination window expired' WHERE id=?")->execute([$session['id']]); $this->event($session['id'], 'SESSION_EXPIRED', 'system', null, []); }
            $runs = $this->db->query("SELECT * FROM tie_transport_runs WHERE status IN ('SCHEDULED','LOADING') AND planned_departure_at <= UTC_TIMESTAMP() FOR UPDATE");
            foreach ($runs->fetchAll() as $run) $this->expireRun($run);
            if ($ownsTransaction) $this->db->commit(); } catch (Throwable $error) { if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    private function expireRunsForVendor(string $vendorId): void { $stmt = $this->db->prepare("SELECT * FROM tie_transport_runs WHERE vendor_id=? AND status IN ('SCHEDULED','LOADING') AND planned_departure_at <= UTC_TIMESTAMP() FOR UPDATE"); $stmt->execute([$vendorId]); foreach ($stmt->fetchAll() as $run) $this->expireRun($run); }
    private function expireRun(array $run): void { $this->cancelRunSessions((string) $run['id'], 'system', 'The driver did not start this transport session before its planned departure.'); $this->db->prepare("UPDATE tie_transport_runs SET status='EXPIRED', loading_status='CLOSED', expired_at=UTC_TIMESTAMP(), version=version+1 WHERE id=?")->execute([$run['id']]); }
    private function activeRunForVendor(string $vendorId): ?array { $stmt = $this->db->prepare("SELECT * FROM tie_transport_runs WHERE vendor_id=? AND status IN ('SCHEDULED','LOADING','TRAVELLING','DEPARTED') ORDER BY created_at DESC LIMIT 1 FOR UPDATE"); $stmt->execute([$vendorId]); $row = $stmt->fetch(); return is_array($row) ? $row : null; }
    private function lockVendor(string $vendorId): void { $stmt = $this->db->prepare('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$vendorId]); if (!$stmt->fetch()) throw UthengaTieErrors::authorization(); }
    private function assertRunTransition(string $from, ?string $to): void { if ($to === null || $to === $from) return; $from = $from === 'DEPARTED' ? 'TRAVELLING' : $from; $allowed = ['SCHEDULED' => ['LOADING', 'CANCELLED'], 'LOADING' => ['TRAVELLING', 'CANCELLED'], 'TRAVELLING' => ['COMPLETED', 'CANCELLED']]; if (!in_array($to, $allowed[$from] ?? [], true)) throw UthengaTieErrors::validation(['status' => "A transport session cannot move from {$from} to {$to}."]); }
    private function cancelRunSessions(string $runId, string $actorId, string $reason): void { $stmt = $this->db->prepare("SELECT * FROM tie_transport_sessions WHERE run_id=? AND status IN ('PENDING_VENDOR','ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED') FOR UPDATE"); $stmt->execute([$runId]); foreach ($stmt->fetchAll() as $session) { $this->releaseReservation($session); $this->db->prepare("UPDATE tie_transport_sessions SET status='CANCELLED', reservation_state=IF(reservation_state='HELD','RELEASED',reservation_state), cancelled_at=UTC_TIMESTAMP(), cancellation_actor=?, cancellation_reason=? WHERE id=?")->execute([$actorId === 'system' ? 'system' : 'vendor', $reason, $session['id']]); $this->event($session['id'], 'RUN_CANCELLED', $actorId === 'system' ? 'system' : 'vendor', $actorId === 'system' ? null : $actorId, []); } }
    private function releaseReservation(array $session): void { if (($session['reservation_state'] ?? '') !== 'HELD') return; $this->db->prepare('UPDATE tie_transport_runs SET remaining_seats=LEAST(capacity, remaining_seats+?), version=version+1 WHERE id=?')->execute([(int) $session['passenger_count'], $session['run_id']]); }
    private function markNoShows(string $runId): void { $stmt = $this->db->prepare("SELECT * FROM tie_transport_sessions WHERE run_id=? AND status IN ('ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED') FOR UPDATE"); $stmt->execute([$runId]); foreach ($stmt->fetchAll() as $session) { $this->db->prepare("UPDATE tie_transport_sessions SET status='NO_SHOW', reservation_state='RELEASED', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='vendor', cancellation_reason='Departure marked as departed' WHERE id=?")->execute([$session['id']]); $this->event($session['id'], 'NO_SHOW', 'vendor', null, []); } }
    private function heldSeats(string $runId): int { $stmt = $this->db->prepare("SELECT COALESCE(SUM(passenger_count), 0) FROM tie_transport_sessions WHERE run_id=? AND reservation_state='HELD' AND status IN ('ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED')"); $stmt->execute([$runId]); return (int) $stmt->fetchColumn(); }
    // A rolling window from "now", not capped by the session's own expires_at:
    // a BOARDED session's expires_at reflects when its pre-boarding
    // reservation window closed (often already in the past by the time the
    // trip is actually under way), so capping against it would make every
    // live-location update during a real journey expire immediately.
    private function snapshot(string $sessionId, string $actorType, string $actorId, array $location): void { $expires = time() + max(300, UthengaTieConfig::integer('TIE_COORDINATION_LOCATION_MAX_SECONDS', 7200)); $this->db->prepare('INSERT INTO tie_transport_location_snapshots (session_id, actor_type, actor_id, latitude, longitude, accuracy_m, source, captured_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)')->execute([$sessionId, $actorType, $actorId, $location['latitude'], $location['longitude'], $location['accuracy_m'], $location['source'], gmdate('Y-m-d H:i:s', $expires)]); }
    private function latestLocations(string $sessionId, string $viewerRole): array { $stmt = $this->db->prepare('SELECT actor_type, latitude, longitude, accuracy_m, source, captured_at FROM tie_transport_location_snapshots WHERE session_id=? AND expires_at>UTC_TIMESTAMP() ORDER BY captured_at DESC'); $stmt->execute([$sessionId]); $seen = []; foreach ($stmt->fetchAll() as $row) if (!isset($seen[$row['actor_type']])) $seen[$row['actor_type']] = ['actor' => $row['actor_type'], 'latitude' => (float) $row['latitude'], 'longitude' => (float) $row['longitude'], 'accuracy_m' => $row['accuracy_m'] === null ? null : (float) $row['accuracy_m'], 'source' => $row['source'], 'captured_at' => gmdate('c', strtotime($row['captured_at'] . ' UTC'))]; return array_values($seen); }
    // Only a display name is ever surfaced for an in-app call — never a phone
    // number. Uses the most recent call request this actor is party to for
    // the session, regardless of status, so RINGING/ACCEPTED/ENDED all
    // resolve from one query.
    private function callState(string $sessionId, string $actorId): array
    {
        // Ordered by updated_at, not created_at: an older call that was just
        // hung up must take priority over a newer call that went terminal
        // earlier (e.g. was declined before the older one was even placed).
        $stmt = $this->db->prepare('SELECT id, requester_id, recipient_id, status, expires_at FROM tie_transport_call_requests WHERE session_id=? AND (requester_id=? OR recipient_id=?) ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$sessionId, $actorId, $actorId]); $row = $stmt->fetch();
        $none = ['state' => 'NONE', 'call_request_id' => null, 'peer_name' => null];
        if (!is_array($row)) return $none;
        $isRequester = (string) $row['requester_id'] === $actorId;
        $status = (string) $row['status'];
        $expired = $status === 'REQUESTED' && strtotime((string) $row['expires_at'] . ' UTC') <= time();
        $state = $expired ? 'NONE' : match ($status) {
            'REQUESTED' => $isRequester ? 'RINGING_OUT' : 'RINGING_IN',
            'ACCEPTED' => 'ACCEPTED',
            'DECLINED' => 'DECLINED',
            'CANCELLED' => 'CANCELLED',
            'ENDED' => 'ENDED',
            default => 'NONE',
        };
        if ($state === 'NONE') return $none;
        $peerId = $isRequester ? (string) $row['recipient_id'] : (string) $row['requester_id'];
        $peer = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1'); $peer->execute([$peerId]); $peerRow = $peer->fetch();
        return ['state' => $state, 'call_request_id' => (string) $row['id'], 'peer_name' => is_array($peerRow) ? (string) $peerRow['name'] : null];
    }
    private function vendorSessions(string $runId): array { $stmt = $this->db->prepare("SELECT * FROM tie_transport_sessions WHERE run_id=? AND status IN ('PENDING_VENDOR','ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED','BOARDED') ORDER BY expires_at ASC"); $stmt->execute([$runId]); return array_map(fn(array $row): array => $this->publicSession($row, $this->runRow($runId)), $stmt->fetchAll()); }
    private function publicSession(array $session, array $run): array { return ['id' => (string) $session['id'], 'status' => (string) $session['status'], 'passenger_count' => (int) $session['passenger_count'], 'destination' => $session['destination'] ?? null, 'reservation_state' => (string) $session['reservation_state'], 'customer_name' => $this->customerName($session['customer_id'] ?? null, $session['walk_in_name'] ?? null), 'booking_source' => (string) ($session['booking_source'] ?? 'uthenga'), 'expires_at' => gmdate('c', strtotime((string) $session['expires_at'])), 'accepted_at' => $session['accepted_at'] ? gmdate('c', strtotime($session['accepted_at'])) : null, 'arrived_at' => $session['arrived_at'] ? gmdate('c', strtotime($session['arrived_at'])) : null, 'boarded_at' => $session['boarded_at'] ? gmdate('c', strtotime($session['boarded_at'])) : null, 'run' => $this->publicRun($run)]; }
    // A named passenger card (Grace Banda, 2 seats) beats an anonymous count
    // for the driver's loading board — but never a phone number, per this
    // module's Uthenga-mediated-contact principle.
    private function customerName(?string $customerId, ?string $walkInName): ?string { if ($walkInName !== null) return $walkInName; if ($customerId === null) return null; $stmt = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1'); $stmt->execute([$customerId]); $row = $stmt->fetch(); return is_array($row) ? (string) $row['name'] : null; }
    private function publicRun(array $run): array { return ['id' => (string) $run['id'], 'service_id' => (string) $run['service_id'], 'planned_departure_at' => $this->utcIso((string) $run['planned_departure_at']), 'actual_departure_at' => $this->utcIso($run['actual_departure_at'] ?? null), 'loading_started_at' => $this->utcIso($run['loading_started_at'] ?? null), 'travelling_started_at' => $this->utcIso($run['travelling_started_at'] ?? null), 'completed_at' => $this->utcIso($run['completed_at'] ?? null), 'loading_location' => (string) ($run['loading_location'] ?? ''), 'driver_note' => $run['driver_note'] ?? null, 'status' => (string) $run['status'], 'loading_status' => (string) $run['loading_status'], 'remaining_seats' => (int) $run['remaining_seats'], 'capacity' => (int) $run['capacity']]; }
    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function role(array $session, string $actorId): ?string { return (string) $session['customer_id'] === $actorId ? 'customer' : ((string) $session['vendor_id'] === $actorId ? 'vendor' : null); }
    private function isInteractive(string $status): bool { return in_array($status, ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP'], true); }
    /**
     * Route destinations are vendor-entered text. A customer should not have
     * to reproduce a vendor's punctuation, casing, or British/American spelling
     * just to find a live departure.
     */
    private function samePlace(string $left, string $right): bool
    {
        return $this->placeKey($left) === $this->placeKey($right);
    }

    private function placeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\barea\s*0*(\d{1,2})\b/u', 'area $1', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $aliases = [
            'city centre' => 'lilongwe city centre',
            'city center' => 'lilongwe city centre',
            'lilongwe city center' => 'lilongwe city centre',
            'lilongwe cbd' => 'lilongwe city centre',
            'central business district' => 'lilongwe city centre',
            'lilongwe central business district' => 'lilongwe city centre',
        ];
        return $aliases[$value] ?? $value;
    }
    private function assertCustomer(array $session, string $customerId): void { if ((string) $session['customer_id'] !== $customerId) throw UthengaTieErrors::authorization(); }
    private function assertVendor(array $session, string $vendorId): void { if ((string) $session['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization(); }
    private function listingForVendor(string $serviceId, string $vendorId): array { $stmt = $this->db->prepare('SELECT id, listing_type, vendor_id FROM listings WHERE id=? AND vendor_id=? AND is_active=1 LIMIT 1 FOR UPDATE'); $stmt->execute([$serviceId, $vendorId]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::authorization(); return $row; }
    private function lockedRun(string $id): array { $stmt = $this->db->prepare('SELECT * FROM tie_transport_runs WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['run_id' => 'Transport departure not found.']); return $row; }
    private function runRow(string $id): array { $stmt = $this->db->prepare('SELECT * FROM tie_transport_runs WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['run_id' => 'Transport departure not found.']); return $row; }
    private function lockedSession(string $id): array { $stmt = $this->db->prepare('SELECT * FROM tie_transport_sessions WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['session_id' => 'Coordination session not found.']); return $row; }
    private function sessionRow(string $id): array { $stmt = $this->db->prepare('SELECT * FROM tie_transport_sessions WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['session_id' => 'Coordination session not found.']); return $row; }
    private function event(string $sessionId, string $type, string $actorType, ?string $actorId, array $payload): void { $this->db->prepare('INSERT INTO tie_transport_session_events (session_id, event_type, actor_type, actor_id, payload) VALUES (?, ?, ?, ?, ?)')->execute([$sessionId, $type, $actorType, $actorId, $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_SLASHES)]); }
    private function id(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('coordination'); }
}

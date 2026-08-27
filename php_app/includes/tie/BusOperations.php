<?php
/**
 * Bus Operations Center. Reuses listings(listing_type='transport') as a
 * vendor's route and seat_classes as that route's seat-class template —
 * the exact tables Quick Taxi's Coordination.php already reads for
 * transport listings — plus the meta.routeFrom/routeTo/vehicleType/
 * departureTime/arrivalTime/scheduleDays/pricePerSeat convention already
 * used by Coordination.php, Query.php and VendorProfiles.php, so a route
 * created here is a real, fully interoperable transport listing.
 *
 * New here: a route only describes a recurring service — tie_bus_departures
 * turns it into concrete, ticketable dated instances, each with its own
 * per-class seat inventory (tie_bus_departure_seats) snapshotted at
 * creation so a later price edit on the route never changes an
 * already-sold ticket. Ticket issuance mirrors event_tickets/Tickets.php's
 * proven id/qr_token/HMAC-signature shape. Boarding verification mirrors
 * gate_sessions/gate_scans' valid/invalid/duplicate contract, but scoped
 * to a vendor's own departure rather than admin+event.
 *
 * Payment is taken directly against bookings/transactions (the same
 * column shapes BookingCommit.php already writes for every other listing
 * type) rather than through BookingCommit.php itself, which is tightly
 * coupled to the Trip Planner's plan/quote/hold/payment-intent machinery
 * that a standalone ticket purchase never creates — the same reasoning
 * TransportPayment.php's own header comment documents for Quick Taxi.
 */

final class UthengaTieBusOperationsContracts
{
    private const TICKET_SIGNATURE_SECRET = 'uthenga-tie-bus-ticket-v1';

    public static function nonEmptyString($value, string $field, int $max = 200): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > $max) throw UthengaTieErrors::validation([$field => "Provide a value up to {$max} characters."]);
        return $value;
    }

    public static function positiveInt($value, string $field): int
    {
        $value = (int) $value;
        if ($value < 1) throw UthengaTieErrors::validation([$field => 'A positive number is required.']);
        return $value;
    }

    public static function futureDatetime($value, string $field): string
    {
        $value = trim((string) $value);
        try { $date = new DateTimeImmutable($value); } catch (Throwable) { throw UthengaTieErrors::validation([$field => 'Provide a valid date and time.']); }
        return $date->format('Y-m-d H:i:s');
    }

    public static function uuid($value, string $field = 'id'): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) throw UthengaTieErrors::validation([$field => 'A valid identifier is required.']);
        return strtolower($value);
    }

    public static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function ticketSignature(string $ticketId, string $token): string
    {
        return hash_hmac('sha256', $ticketId . '.' . $token, self::TICKET_SIGNATURE_SECRET);
    }
}

final class UthengaTieBusOperationsService
{
    private const ALLOWED_IMAGES = [
        'axon_bus' => '/assets/images/buses/axon_bus.svg',
        'malawi_express' => '/assets/images/buses/malawi_express.svg',
        'speed_coaster' => '/assets/images/buses/speed_coaster.svg',
    ];

    public function __construct(private ?PDO $db, private UthengaTiePaymentGateway $gateway, private UthengaTieCustomerPaymentMethodsService $paymentMethods) {}

    // ───────────────────────────── Vendor: routes ─────────────────────────────

    public function listRoutes(string $vendorId): array
    {
        $db = $this->db();
        $stmt = $db->prepare("SELECT id, title, location, image, meta, is_active FROM listings WHERE vendor_id=? AND listing_type='transport' ORDER BY created_at DESC");
        $stmt->execute([$vendorId]);
        $routes = [];
        foreach ($stmt->fetchAll() as $row) $routes[] = $this->publicRoute($row);
        return ['schema_version' => 'tie-bus-ops/v1', 'routes' => $routes];
    }

    public function createRoute(string $vendorId, string $vendorName, array $input): array
    {
        $title = UthengaTieBusOperationsContracts::nonEmptyString($input['title'] ?? null, 'title');
        $origin = UthengaTieBusOperationsContracts::nonEmptyString($input['origin'] ?? null, 'origin', 120);
        $destination = UthengaTieBusOperationsContracts::nonEmptyString($input['destination'] ?? null, 'destination', 120);
        $vehicleType = trim((string) ($input['vehicle_type'] ?? 'Coach Bus')) ?: 'Coach Bus';
        $departureTime = trim((string) ($input['departure_time'] ?? ''));
        $arrivalTime = trim((string) ($input['arrival_time'] ?? ''));
        $pickupLocation = trim((string) ($input['pickup_location'] ?? '')) !== '' ? UthengaTieBusOperationsContracts::nonEmptyString($input['pickup_location'], 'pickup_location', 200) : $title;
        $scheduleDays = is_array($input['schedule_days'] ?? null) ? array_values(array_filter(array_map('strval', $input['schedule_days']))) : [];
        $imageKey = (string) ($input['image'] ?? '');
        $image = self::ALLOWED_IMAGES[$imageKey] ?? self::ALLOWED_IMAGES[array_rand(self::ALLOWED_IMAGES)];
        $seatClasses = is_array($input['seat_classes'] ?? null) ? $input['seat_classes'] : [];
        if ($seatClasses === []) throw UthengaTieErrors::validation(['seat_classes' => 'Add at least one seat class.']);

        $totalSeats = 0; $minPrice = null;
        $classRows = [];
        foreach ($seatClasses as $class) {
            $name = UthengaTieBusOperationsContracts::nonEmptyString($class['class_name'] ?? null, 'class_name', 80);
            $price = round((float) ($class['price'] ?? 0), 2);
            $seats = UthengaTieBusOperationsContracts::positiveInt($class['total_seats'] ?? null, 'total_seats');
            if ($price <= 0) throw UthengaTieErrors::validation(['price' => 'Each seat class needs a price above zero.']);
            $classRows[] = ['class_name' => $name, 'price' => $price, 'total_seats' => $seats];
            $totalSeats += $seats;
            $minPrice = $minPrice === null ? $price : min($minPrice, $price);
        }

        $meta = ['vehicleType' => $vehicleType, 'routeFrom' => $origin, 'routeTo' => $destination, 'departureTime' => $departureTime, 'arrivalTime' => $arrivalTime, 'scheduleDays' => $scheduleDays, 'pricePerSeat' => $minPrice, 'totalSeats' => $totalSeats, 'availableSeats' => $totalSeats];
        $listingId = 'BUS-' . strtoupper(bin2hex(random_bytes(6)));
        $db = $this->db(); $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO listings (id, listing_type, title, description, location, image, vendor_id, vendor_name, meta, is_active) VALUES (?, 'transport', ?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute([$listingId, $title, "{$origin} to {$destination} — operated by {$vendorName}", $pickupLocation, $image, $vendorId, $vendorName, json_encode($meta, JSON_UNESCAPED_SLASHES)]);
            foreach ($classRows as $class) {
                $db->prepare('INSERT INTO seat_classes (listing_id, class_name, price, total_seats, remaining_seats, is_active) VALUES (?, ?, ?, ?, ?, 1)')
                    ->execute([$listingId, $class['class_name'], $class['price'], $class['total_seats'], $class['total_seats']]);
            }
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
        return $this->listRoutes($vendorId);
    }

    public function updateRoute(string $vendorId, array $input): array
    {
        $listingId = UthengaTieBusOperationsContracts::nonEmptyString($input['listing_id'] ?? null, 'listing_id', 30);
        $route = $this->ownedRoute($vendorId, $listingId);
        $isActive = array_key_exists('is_active', $input) ? (empty($input['is_active']) ? 0 : 1) : (int) $route['is_active'];
        if (array_key_exists('title', $input)) { $title = UthengaTieBusOperationsContracts::nonEmptyString($input['title'], 'title'); }
        else { $title = (string) $route['title']; }
        $this->db()->prepare('UPDATE listings SET title=?, is_active=? WHERE id=?')->execute([$title, $isActive, $listingId]);
        if (is_array($input['add_seat_class'] ?? null)) {
            $class = $input['add_seat_class'];
            $name = UthengaTieBusOperationsContracts::nonEmptyString($class['class_name'] ?? null, 'class_name', 80);
            $price = round((float) ($class['price'] ?? 0), 2);
            $seats = UthengaTieBusOperationsContracts::positiveInt($class['total_seats'] ?? null, 'total_seats');
            if ($price <= 0) throw UthengaTieErrors::validation(['price' => 'A seat class needs a price above zero.']);
            $this->db()->prepare('INSERT INTO seat_classes (listing_id, class_name, price, total_seats, remaining_seats, is_active) VALUES (?, ?, ?, ?, ?, 1)')->execute([$listingId, $name, $price, $seats, $seats]);
        }
        return $this->listRoutes($vendorId);
    }

    // ─────────────────────────── Vendor: departures ────────────────────────────

    public function listDepartures(string $vendorId, array $filters = []): array
    {
        $where = ['l.vendor_id=?']; $params = [$vendorId];
        if (!empty($filters['listing_id'])) { $where[] = 'd.listing_id=?'; $params[] = $filters['listing_id']; }
        if (!empty($filters['from_date'])) { $where[] = 'DATE(d.departure_at)>=?'; $params[] = $filters['from_date']; }
        $stmt = $this->db()->prepare("SELECT d.*, l.title AS route_title, l.location, l.image AS route_image, l.vendor_name, l.meta FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE " . implode(' AND ', $where) . ' ORDER BY d.departure_at ASC LIMIT 200');
        $stmt->execute($params);
        $departures = [];
        foreach ($stmt->fetchAll() as $row) $departures[] = $this->publicDeparture($row);
        return ['schema_version' => 'tie-bus-ops/v1', 'departures' => $departures];
    }

    public function createDeparture(string $vendorId, array $input): array
    {
        $listingId = UthengaTieBusOperationsContracts::nonEmptyString($input['listing_id'] ?? null, 'listing_id', 30);
        $route = $this->ownedRoute($vendorId, $listingId);
        $departureAt = UthengaTieBusOperationsContracts::futureDatetime($input['departure_at'] ?? null, 'departure_at');
        if (strtotime($departureAt) <= time()) throw UthengaTieErrors::validation(['departure_at' => 'Schedule a departure in the future.']);
        $arrivalEstimate = trim((string) ($input['arrival_estimate'] ?? '')) !== '' ? UthengaTieBusOperationsContracts::futureDatetime($input['arrival_estimate'], 'arrival_estimate') : null;
        $notes = trim((string) ($input['notes'] ?? '')) ?: null;

        // Customer listing overrides — all optional, NULL always inherits the route's own presentation.
        $listingTitle = mb_substr(trim((string) ($input['listing_title'] ?? '')), 0, 200) ?: null;
        $listingImageKey = trim((string) ($input['image'] ?? '')) ?: null;
        if ($listingImageKey !== null && !array_key_exists($listingImageKey, self::ALLOWED_IMAGES)) throw UthengaTieErrors::validation(['image' => 'Choose a valid cover image.']);
        $listingImage = $listingImageKey !== null ? self::ALLOWED_IMAGES[$listingImageKey] : null;
        $customerDescription = mb_substr(trim((string) ($input['customer_description'] ?? '')), 0, 500) ?: null;
        $highlights = mb_substr(trim((string) ($input['highlights'] ?? '')), 0, 300) ?: null;
        $cardStyle = trim((string) ($input['card_style'] ?? 'standard'));
        if (!in_array($cardStyle, ['standard', 'premium', 'compact'], true)) throw UthengaTieErrors::validation(['card_style' => 'Choose a valid card style.']);

        $classesStmt = $this->db()->prepare('SELECT id, class_name, price, total_seats FROM seat_classes WHERE listing_id=? AND is_active=1');
        $classesStmt->execute([$listingId]);
        $templates = $classesStmt->fetchAll();
        if ($templates === []) throw UthengaTieErrors::validation(['seat_classes' => 'This route has no active seat classes yet.']);
        $overrides = is_array($input['seat_classes'] ?? null) ? array_column($input['seat_classes'], null, 'seat_class_id') : [];

        $db = $this->db(); $db->beginTransaction();
        try {
            $departureId = UthengaTieBusOperationsContracts::newUuid();
            $db->prepare('INSERT INTO tie_bus_departures (id, listing_id, departure_at, arrival_estimate, notes, listing_title, image, customer_description, highlights, card_style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$departureId, $listingId, $departureAt, $arrivalEstimate, $notes, $listingTitle, $listingImage, $customerDescription, $highlights, $cardStyle]);
            foreach ($templates as $template) {
                $override = $overrides[$template['id']] ?? [];
                $price = isset($override['price']) ? round((float) $override['price'], 2) : (float) $template['price'];
                $seats = isset($override['total_seats']) ? UthengaTieBusOperationsContracts::positiveInt($override['total_seats'], 'total_seats') : (int) $template['total_seats'];
                $db->prepare('INSERT INTO tie_bus_departure_seats (departure_id, seat_class_id, class_name, price, total_seats, remaining_seats) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$departureId, $template['id'], $template['class_name'], $price, $seats, $seats]);
            }
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
        return ['created_departure_id' => $departureId] + $this->listDepartures($vendorId, ['listing_id' => $listingId]);
    }

    public function cancelDeparture(string $vendorId, string $departureId, ?string $reason = null): array
    {
        $departure = $this->ownedDeparture($vendorId, $departureId);
        if (!in_array($departure['status'], ['scheduled', 'boarding'], true)) throw UthengaTieErrors::validation(['status' => 'Only a scheduled or boarding departure can be cancelled.']);
        $reason = trim((string) $reason);

        $db = $this->db(); $db->beginTransaction();
        try {
            // A boarded ticket's passenger already travelled — only unboarded
            // (issued) tickets are real casualties of cancelling the departure.
            $ticketStmt = $db->prepare("SELECT id, departure_seat_id FROM tie_bus_tickets WHERE departure_id=? AND status='issued'");
            $ticketStmt->execute([$departureId]);
            $tickets = $ticketStmt->fetchAll();
            foreach ($tickets as $ticket) {
                $db->prepare("UPDATE tie_bus_tickets SET status='cancelled' WHERE id=?")->execute([$ticket['id']]);
                $db->prepare('UPDATE tie_bus_departure_seats SET remaining_seats=LEAST(remaining_seats+1, total_seats) WHERE id=?')->execute([$ticket['departure_seat_id']]);
            }
            $notesUpdate = $reason !== '' ? ", notes=TRIM(CONCAT(COALESCE(notes,''), '\n[Cancelled] ', ?))" : '';
            $params = $reason !== '' ? [$reason, $departureId] : [$departureId];
            $db->prepare("UPDATE tie_bus_departures SET status='cancelled'{$notesUpdate} WHERE id=?")->execute($params);
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }

        $result = $this->listDepartures($vendorId, ['listing_id' => $departure['listing_id']]);
        $result['cancelled_ticket_count'] = count($tickets);
        return $result;
    }

    // ─────────────────────────────── Vendor: dashboard ─────────────────────────

    public function dashboard(string $vendorId): array
    {
        $db = $this->db();
        $today = $db->prepare("SELECT d.*, l.title AS route_title, l.location, l.image AS route_image, l.vendor_name, l.meta FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND DATE(d.departure_at)=CURDATE() ORDER BY d.departure_at ASC");
        $today->execute([$vendorId]);
        $todayDepartures = array_map(fn(array $row) => $this->publicDeparture($row), $today->fetchAll());

        $seatTotals = $db->prepare("SELECT COALESCE(SUM(ds.total_seats - ds.remaining_seats),0) AS sold, COALESCE(SUM(ds.remaining_seats),0) AS remaining
            FROM tie_bus_departure_seats ds INNER JOIN tie_bus_departures d ON d.id=ds.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=?");
        $seatTotals->execute([$vendorId]);
        $totals = $seatTotals->fetch() ?: ['sold' => 0, 'remaining' => 0];

        $revenue = $db->prepare("SELECT COALESCE(SUM(ds.price),0) AS revenue, COUNT(*) AS ticket_count FROM tie_bus_tickets t
            INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id
            INNER JOIN tie_bus_departures d ON d.id=t.departure_id
            INNER JOIN listings l ON l.id=d.listing_id
            INNER JOIN bookings b ON b.id=t.booking_id
            WHERE l.vendor_id=? AND b.payment_status='Paid' AND t.status != 'cancelled'");
        $revenue->execute([$vendorId]);
        $revenueRow = $revenue->fetch() ?: ['revenue' => 0, 'ticket_count' => 0];

        $passengersTodayStmt = $db->prepare("SELECT COUNT(*) FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
            WHERE l.vendor_id=? AND DATE(d.departure_at)=CURDATE() AND t.status != 'cancelled'");
        $passengersTodayStmt->execute([$vendorId]);

        $scans = $db->prepare("SELECT bs.scan_result, bs.code_entered, bs.method, bs.scanned_at, t.passenger_name FROM tie_bus_boarding_scans bs
            INNER JOIN tie_bus_boarding_sessions s ON s.id=bs.session_id INNER JOIN tie_bus_departures d ON d.id=s.departure_id INNER JOIN listings l ON l.id=d.listing_id
            LEFT JOIN tie_bus_tickets t ON t.id=bs.ticket_id WHERE l.vendor_id=? ORDER BY bs.scanned_at DESC LIMIT 10");
        $scans->execute([$vendorId]);

        $routeCountStmt = $db->prepare("SELECT COUNT(*) FROM listings WHERE vendor_id=? AND listing_type='transport' AND is_active=1"); $routeCountStmt->execute([$vendorId]);

        return [
            'schema_version' => 'tie-bus-ops/v1',
            'active_routes' => (int) $routeCountStmt->fetchColumn(),
            'today_departures' => $todayDepartures,
            'seats_sold' => (int) $totals['sold'],
            'seats_remaining' => (int) $totals['remaining'],
            'revenue_paid' => (float) $revenueRow['revenue'],
            'tickets_sold' => (int) $revenueRow['ticket_count'],
            'passengers_today' => (int) $passengersTodayStmt->fetchColumn(),
            'currency' => APP_CURRENCY,
            'recent_scans' => array_map(fn(array $row) => [
                'result' => (string) $row['scan_result'], 'code' => (string) $row['code_entered'], 'method' => (string) $row['method'],
                'passenger_name' => $row['passenger_name'] ?? null, 'scanned_at' => $this->utcIso((string) $row['scanned_at']),
            ], $scans->fetchAll()),
        ];
    }

    // ─────────────────────────── Vendor: boarding sessions ─────────────────────

    public function startBoardingSession(string $vendorId, string $departureId): array
    {
        $departure = $this->ownedDeparture($vendorId, $departureId);
        $db = $this->db();
        $existing = $db->prepare("SELECT * FROM tie_bus_boarding_sessions WHERE departure_id=? AND status='active' ORDER BY started_at DESC LIMIT 1");
        $existing->execute([$departureId]);
        $row = $existing->fetch();
        if (is_array($row)) return $this->sessionStats($vendorId, (string) $row['id']);
        if ($departure['status'] === 'scheduled') $db->prepare("UPDATE tie_bus_departures SET status='boarding' WHERE id=?")->execute([$departureId]);
        $sessionId = UthengaTieBusOperationsContracts::newUuid();
        $db->prepare('INSERT INTO tie_bus_boarding_sessions (id, departure_id, vendor_id, started_by) VALUES (?, ?, ?, ?)')->execute([$sessionId, $departureId, $vendorId, $vendorId]);
        return $this->sessionStats($vendorId, $sessionId);
    }

    public function stopBoardingSession(string $vendorId, string $sessionId): array
    {
        $session = $this->ownedSession($vendorId, $sessionId);
        $db = $this->db(); $db->beginTransaction();
        try {
            $db->prepare("UPDATE tie_bus_boarding_sessions SET status='stopped', stopped_at=NOW() WHERE id=?")->execute([$sessionId]);
            // Boarding closing is the real "trip departed" moment — any ticket
            // still 'issued' (never scanned) genuinely becomes a no-show now,
            // rather than staying issued forever with no terminal state.
            $db->prepare("UPDATE tie_bus_tickets SET status='no_show' WHERE departure_id=? AND status='issued'")->execute([$session['departure_id']]);
            $db->prepare("UPDATE tie_bus_departures SET status='departed' WHERE id=? AND status='boarding'")->execute([$session['departure_id']]);
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
        $stats = $this->sessionStats($vendorId, $sessionId);
        $summary = ['booked' => 0, 'boarded' => 0, 'no_show' => 0, 'cancelled' => 0];
        foreach ($stats['manifest'] as $row) { $summary['booked']++; if (isset($summary[$row['status']])) $summary[$row['status']]++; }
        $stats['final_manifest'] = $summary;
        return $stats;
    }

    public function sessionStats(string $vendorId, string $sessionId): array
    {
        $session = $this->ownedSession($vendorId, $sessionId);
        $db = $this->db();
        $manifest = $db->prepare('SELECT t.id, t.passenger_name, t.seat_label, t.status, t.vehicle_reg_number, ds.class_name FROM tie_bus_tickets t INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id WHERE t.departure_id=? ORDER BY t.created_at ASC');
        $manifest->execute([$session['departure_id']]);
        $scans = $db->prepare('SELECT scan_result, code_entered, method, scanned_at, notes FROM tie_bus_boarding_scans WHERE session_id=? ORDER BY scanned_at DESC LIMIT 25');
        $scans->execute([$sessionId]);
        return [
            'schema_version' => 'tie-bus-ops/v1',
            'session' => ['id' => (string) $session['id'], 'departure_id' => (string) $session['departure_id'], 'status' => (string) $session['status'],
                'total_scanned' => (int) $session['total_scanned'], 'total_valid' => (int) $session['total_valid'], 'total_invalid' => (int) $session['total_invalid'], 'total_duplicate' => (int) $session['total_duplicate']],
            'manifest' => array_map(fn(array $row) => ['ticket_id' => (string) $row['id'], 'passenger_name' => (string) $row['passenger_name'], 'seat_label' => $row['seat_label'], 'seat_class' => (string) $row['class_name'], 'status' => (string) $row['status'], 'vehicle_reg_number' => $row['vehicle_reg_number'] ?? null], $manifest->fetchAll()),
            'recent_scans' => array_map(fn(array $row) => ['result' => (string) $row['scan_result'], 'code' => (string) $row['code_entered'], 'method' => (string) $row['method'], 'notes' => $row['notes'], 'scanned_at' => $this->utcIso((string) $row['scanned_at'])], $scans->fetchAll()),
        ];
    }

    public function verifyTicket(string $vendorId, array $input): array
    {
        $sessionId = UthengaTieBusOperationsContracts::uuid($input['session_id'] ?? null, 'session_id');
        $code = trim((string) ($input['code'] ?? ''));
        $method = in_array($input['method'] ?? '', ['manual', 'qr'], true) ? $input['method'] : 'manual';
        if ($code === '') throw UthengaTieErrors::validation(['code' => 'Enter or scan a ticket code.']);
        $db = $this->db(); $db->beginTransaction();
        try {
            $session = $this->lockedSession($sessionId);
            if ((string) $session['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            if ($session['status'] !== 'active') throw UthengaTieErrors::validation(['session' => 'This boarding session is not active.']);

            [$ticketId, $token] = str_contains($code, '.') ? explode('.', $code, 2) : [$code, null];
            $ticketId = strtoupper(trim($ticketId));
            $stmt = $db->prepare('SELECT t.*, l.vendor_id AS listing_vendor_id FROM tie_bus_tickets t
                INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE t.id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();

            $result = 'invalid'; $notes = null; $reasonCode = 'not_found';
            if (!is_array($ticket) || (string) $ticket['listing_vendor_id'] !== $vendorId) { $notes = 'Ticket not found for your routes.'; $reasonCode = 'not_found'; }
            elseif ((string) $ticket['departure_id'] !== (string) $session['departure_id']) { $notes = 'This ticket is for a different departure.'; $reasonCode = 'wrong_departure'; }
            elseif ($token !== null && !hash_equals((string) $ticket['verification_signature'], UthengaTieBusOperationsContracts::ticketSignature($ticketId, $token))) { $notes = 'The QR code does not match this ticket.'; $reasonCode = 'signature_mismatch'; }
            elseif ($ticket['status'] === 'cancelled') { $notes = 'This ticket was cancelled.'; $reasonCode = 'cancelled'; }
            elseif ($ticket['status'] === 'no_show') { $notes = 'This ticket was already marked a no-show after boarding closed.'; $reasonCode = 'no_show'; }
            elseif ($ticket['status'] === 'boarded') { $result = 'duplicate'; $notes = 'Already boarded at ' . $this->utcIso((string) $ticket['boarded_at']); $reasonCode = 'duplicate'; }
            else {
                $result = 'valid'; $reasonCode = 'valid';
                $db->prepare("UPDATE tie_bus_tickets SET status='boarded', boarded_at=NOW(), boarded_by=? WHERE id=?")->execute([$vendorId, $ticket['id']]);
            }

            $db->prepare('INSERT INTO tie_bus_boarding_scans (session_id, code_entered, ticket_id, scan_result, method, scanned_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sessionId, $code, is_array($ticket) ? $ticket['id'] : null, $result, $method, $vendorId, $notes]);
            $counterColumn = ['valid' => 'total_valid', 'invalid' => 'total_invalid', 'duplicate' => 'total_duplicate'][$result];
            $db->prepare("UPDATE tie_bus_boarding_sessions SET total_scanned=total_scanned+1, {$counterColumn}={$counterColumn}+1 WHERE id=?")->execute([$sessionId]);
            $db->commit();

            return ['scan_result' => $result, 'reason_code' => $reasonCode, 'notes' => $notes, 'ticket' => is_array($ticket) ? ['id' => (string) $ticket['id'], 'passenger_name' => (string) $ticket['passenger_name'], 'seat_label' => $ticket['seat_label']] : null];
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    // ────────────────────────────── Customer: search ───────────────────────────

    public function searchDepartures(array $filters): array
    {
        $origin = trim((string) ($filters['origin'] ?? ''));
        $destination = trim((string) ($filters['destination'] ?? ''));
        $date = trim((string) ($filters['date'] ?? ''));
        $where = ["d.status='scheduled'", 'd.departure_at > UTC_TIMESTAMP()'];
        $params = [];
        if ($origin !== '') { $where[] = "JSON_UNQUOTE(JSON_EXTRACT(l.meta, '$.routeFrom')) LIKE ?"; $params[] = "%{$origin}%"; }
        if ($destination !== '') { $where[] = "JSON_UNQUOTE(JSON_EXTRACT(l.meta, '$.routeTo')) LIKE ?"; $params[] = "%{$destination}%"; }
        if ($date !== '') { $where[] = 'DATE(d.departure_at)=?'; $params[] = $date; }
        $stmt = $this->db()->prepare("SELECT d.*, l.title AS route_title, l.location, l.image AS route_image, l.vendor_name, l.meta FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id AND l.is_active=1 WHERE " . implode(' AND ', $where) . ' ORDER BY d.departure_at ASC LIMIT 40');
        $stmt->execute($params);
        $departures = [];
        foreach ($stmt->fetchAll() as $row) $departures[] = $this->publicDeparture($row);
        return ['schema_version' => 'tie-bus-ops/v1', 'departures' => $departures];
    }

    // ────────────────────────────── Customer: purchase ─────────────────────────

    public function purchaseTicket(array $input, array $user): array
    {
        $departureSeatId = UthengaTieBusOperationsContracts::positiveInt($input['departure_seat_id'] ?? null, 'departure_seat_id');
        $quantity = max(1, min(10, (int) ($input['quantity'] ?? 1)));
        $passengers = is_array($input['passengers'] ?? null) ? array_values($input['passengers']) : [];
        if (count($passengers) !== $quantity) throw UthengaTieErrors::validation(['passengers' => 'Provide a passenger name for each seat.']);
        foreach ($passengers as $i => $passenger) $passengers[$i] = ['name' => UthengaTieBusOperationsContracts::nonEmptyString($passenger['name'] ?? null, 'passenger_name', 150), 'phone' => trim((string) ($passenger['phone'] ?? '')) ?: null, 'seat_label' => trim((string) ($passenger['seat_label'] ?? '')) ?: null];

        // A saved payment method is mandatory — checked before anything else so
        // an unconfigured customer never reaches (or holds) real seat inventory.
        $paymentMethodId = trim((string) ($input['payment_method_id'] ?? ''));
        if ($paymentMethodId === '') throw UthengaTieErrors::validation(['payment_method_id' => 'Add a payment method before purchasing a ticket.']);
        $method = $this->paymentMethods->ownedMethod($user['id'], $paymentMethodId);
        $nameParts = preg_split('/\s+/', trim((string) $user['name']), 2);
        $firstName = (string) ($nameParts[0] ?? 'Customer'); $lastName = (string) ($nameParts[1] ?? '');

        $db = $this->db(); $db->beginTransaction();
        try {
            $seat = $this->lockedDepartureSeat($departureSeatId);
            if ($seat['departure_status'] !== 'scheduled') throw UthengaTieErrors::validation(['departure' => 'This departure is no longer open for booking.']);
            // The vendor-side "Schedule a Trip" wizard only enforces "vehicle
            // required" in its own JS — createDeparture() itself never writes
            // vehicle_id, so a departure could exist and reach this far without
            // one if the wizard's second (assign) call was skipped or failed.
            // Block the sale here rather than issue a ticket with no vehicle.
            if (empty($seat['vehicle_id'])) throw UthengaTieErrors::validation(['departure' => 'This departure has not been assigned a vehicle yet and is not yet open for ticket sales.']);
            if ((int) $seat['remaining_seats'] < $quantity) throw UthengaTieErrors::validation(['quantity' => 'Not enough seats remaining in this class.']);

            $amount = round((float) $seat['price'] * $quantity, 2);
            $bookingId = 'BKG-' . strtoupper(bin2hex(random_bytes(6)));
            $reference = 'TIE-' . strtoupper(bin2hex(random_bytes(6)));
            $details = ['departure_id' => $seat['departure_id'], 'departure_seat_id' => $departureSeatId, 'passengers' => $passengers, 'departure_at' => $seat['departure_at']];

            // Freeze the commission rate now, at charge creation — reconcilePayment()
            // reuses this exact rate when posting to the shared ledger later, so an
            // admin rate change in between can't retroactively affect this ticket.
            $feeRule = function_exists('uthenga_finance_active_fee_rule') ? uthenga_finance_active_fee_rule('transport') : null;
            $commissionRate = $feeRule !== null ? (float) $feeRule['commission_rate'] : (function_exists('uthenga_finance_commission_rate') ? uthenga_finance_commission_rate('transport') : 0.0);
            $feeMetadata = json_encode(['fee_rule_id' => $feeRule['id'] ?? null, 'commission_rate' => $commissionRate], JSON_UNESCAPED_SLASHES);

            $db->prepare('UPDATE tie_bus_departure_seats SET remaining_seats=remaining_seats-? WHERE id=? AND remaining_seats>=?')->execute([$quantity, $departureSeatId, $quantity]);
            $db->prepare('INSERT INTO bookings (id, listing_id, seat_class_id, quantity, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, details, currency, total_price, payment_status, payment_gateway, booking_status, transaction_id, qr_code, booked_at) VALUES (?, ?, ?, ?, ?, ?, \'transport\', ?, ?, ?, ?, ?, ?, \'Pending\', \'PayChangu\', \'pending_payment\', ?, ?, NOW())')
                ->execute([$bookingId, $seat['listing_id'], $seat['seat_class_id'], $quantity, $seat['listing_title'], $seat['listing_image'], $user['id'], $user['name'], $user['email'], json_encode($details, JSON_UNESCAPED_SLASHES), APP_CURRENCY, $amount, $reference, 'UTHENGA-' . $bookingId]);

            $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));
            $db->prepare('INSERT INTO transactions (id, booking_id, customer_id, customer_name, amount, gateway, gateway_ref, payment_channel, payment_method_id, transaction_type, status, receipt_number, vendor_id, metadata) VALUES (?, ?, ?, ?, ?, \'PayChangu\', ?, ?, ?, \'booking_payment\', \'pending\', ?, ?, ?)')
                ->execute([$transactionId, $bookingId, $user['id'], $user['name'], $amount, $reference, $method['channel'], $method['id'], 'REC-' . strtoupper(bin2hex(random_bytes(5))), $seat['vendor_id'], $feeMetadata]);

            $result = ['booking_id' => $bookingId, 'amount' => $amount, 'currency' => APP_CURRENCY, 'payment_channel' => $method['channel']];
            if ($method['channel'] === 'mobile_money') {
                $charge = $this->gateway->chargeMobileMoney(['mobile' => UthengaTieCustomerPaymentMethodsContracts::toGatewayMobile($method['mobile_number']), 'operator_ref_id' => $method['operator_ref_id'], 'amount' => $amount, 'charge_id' => $reference, 'email' => $user['email'], 'first_name' => $firstName, 'last_name' => $lastName]);
                $db->prepare('UPDATE transactions SET metadata=? WHERE id=?')->execute([json_encode(['fee_rule_id' => $feeRule['id'] ?? null, 'commission_rate' => $commissionRate, 'provider_reference' => $charge['reference'], 'provider_charge_id' => $charge['charge_id']], JSON_UNESCAPED_SLASHES), $transactionId]);
                $result['status'] = 'awaiting_mobile_confirmation';
                $result['instructions'] = 'Approve the payment prompt on your phone (' . UthengaTieCustomerPaymentMethodsContracts::maskMobile($method['mobile_number']) . ') to complete this purchase.';
            } else {
                $charge = $this->gateway->chargeBankTransfer(['amount' => $amount, 'currency' => APP_CURRENCY, 'charge_id' => $reference, 'email' => $user['email'], 'first_name' => $firstName, 'last_name' => $lastName]);
                $db->prepare('UPDATE transactions SET metadata=? WHERE id=?')->execute([json_encode(['fee_rule_id' => $feeRule['id'] ?? null, 'commission_rate' => $commissionRate, 'provider_reference' => $charge['reference'], 'provider_charge_id' => $charge['charge_id']], JSON_UNESCAPED_SLASHES), $transactionId]);
                $result['status'] = 'awaiting_bank_transfer';
                $result['bank_name'] = $charge['bank_name'];
                $result['account_number'] = $charge['account_number'];
                $result['account_name'] = $charge['account_name'];
                $result['expires_at'] = $charge['expires_at'];
            }
            $db->commit();
            return $result;
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    /**
     * $webhookPayload is only ever populated by receiveWebhook(), already
     * signature-verified — it's the sole proof of payment for bank_transfer,
     * since PayChangu exposes no independent verify call for that channel.
     * Every other channel still re-confirms with the gateway directly and
     * ignores this payload, per this module's "never trust a webhook body
     * alone" rule.
     */
    public function reconcilePayment(string $bookingId, ?array $webhookPayload = null): array
    {
        $db = $this->db(); $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM bookings WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();
            if (!is_array($booking)) throw UthengaTieErrors::validation(['booking_id' => 'Booking not found.']);
            if ($booking['payment_status'] === 'Paid') { $db->commit(); return $this->publicTicketBundle($bookingId); }

            $txnStmt = $db->prepare('SELECT * FROM transactions WHERE booking_id=? ORDER BY created_at DESC LIMIT 1 FOR UPDATE'); $txnStmt->execute([$bookingId]);
            $transaction = $txnStmt->fetch();
            if (!is_array($transaction)) throw UthengaTieErrors::validation(['booking_id' => 'No payment attempt found for this booking.']);
            if ($transaction['status'] === 'success') { $db->commit(); return $this->publicTicketBundle($bookingId); }

            $channel = (string) ($transaction['payment_channel'] ?? '');
            if ($channel === 'bank_transfer') {
                // No synchronous verify call exists for this channel — the
                // signed webhook payload IS the proof; a plain poll (no
                // payload) just reports "still pending" until it lands.
                $verifiedOk = $webhookPayload !== null && strtolower((string) ($webhookPayload['status'] ?? '')) === 'success' && is_numeric($webhookPayload['amount'] ?? null) && abs(((float) $webhookPayload['amount']) - (float) $transaction['amount']) < 0.01;
                if (!$verifiedOk) { $db->commit(); return $this->publicTicketBundle($bookingId); }
                $db->prepare("UPDATE bookings SET payment_status='Paid', booking_status='confirmed', confirmed_at=NOW() WHERE id=?")->execute([$bookingId]);
                $db->prepare("UPDATE transactions SET status='success' WHERE id=?")->execute([$transaction['id']]);
                $this->issueBusTickets($this->reloadBooking($bookingId));
                $feeMeta = json_decode((string) ($transaction['metadata'] ?? '{}'), true) ?: [];
                UthengaPaymentEngine::postExternalLedgers([
                    'payment_intent_id' => $transaction['id'],
                    'intent_ref'        => $transaction['gateway_ref'],
                    'service_category'  => 'transport',
                    'gross_amount'      => (float) $transaction['amount'],
                    'vendor_id'         => (string) $transaction['vendor_id'],
                    'fee_rule_id'       => $feeMeta['fee_rule_id'] ?? null,
                    'commission_rate'   => $feeMeta['commission_rate'] ?? null,
                ]);
                $db->commit();
                return $this->publicTicketBundle($bookingId);
            }

            // The gateway can genuinely fail to answer for a charge the customer
            // hasn't completed yet (e.g. an unknown-reference 404) — that means
            // "still pending", not a hard error the customer's poll loop should see.
            try {
                $verification = $channel === 'mobile_money'
                    ? $this->gateway->verifyMobileMoneyCharge((string) $transaction['gateway_ref'])
                    : $this->gateway->verify((string) $transaction['gateway_ref']);
            } catch (Throwable) { $db->commit(); return $this->publicTicketBundle($bookingId); }
            $verifiedOk = strtolower((string) ($verification['status'] ?? '')) === 'success' && $verification['amount'] !== null && abs(((float) $verification['amount']) - (float) $transaction['amount']) < 0.01;

            if ($verifiedOk) {
                $db->prepare("UPDATE bookings SET payment_status='Paid', booking_status='confirmed', confirmed_at=NOW() WHERE id=?")->execute([$bookingId]);
                $db->prepare("UPDATE transactions SET status='success' WHERE id=?")->execute([$transaction['id']]);
                $this->issueBusTickets($this->reloadBooking($bookingId));
                if ($channel === 'mobile_money' && !empty($transaction['payment_method_id'])) $this->paymentMethods->markVerified((string) $transaction['payment_method_id']);
                $feeMeta = json_decode((string) ($transaction['metadata'] ?? '{}'), true) ?: [];
                UthengaPaymentEngine::postExternalLedgers([
                    'payment_intent_id' => $transaction['id'],
                    'intent_ref'        => $transaction['gateway_ref'],
                    'service_category'  => 'transport',
                    'gross_amount'      => (float) $transaction['amount'],
                    'vendor_id'         => (string) $transaction['vendor_id'],
                    'fee_rule_id'       => $feeMeta['fee_rule_id'] ?? null,
                    'commission_rate'   => $feeMeta['commission_rate'] ?? null,
                ]);
            } else {
                $db->prepare("UPDATE bookings SET payment_status='Failed', booking_status='cancelled' WHERE id=?")->execute([$bookingId]);
                $db->prepare("UPDATE transactions SET status='failed' WHERE id=?")->execute([$transaction['id']]);
                $details = json_decode((string) $booking['details'], true) ?: [];
                if (!empty($details['departure_seat_id'])) $db->prepare('UPDATE tie_bus_departure_seats SET remaining_seats=remaining_seats+? WHERE id=?')->execute([(int) $booking['quantity'], (int) $details['departure_seat_id']]);
            }
            $db->commit();
            return $this->publicTicketBundle($bookingId);
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    public function receiveWebhook(string $payload, string $signature): array
    {
        if (!$this->gateway->verifyWebhookSignature($payload, $signature)) throw UthengaTieErrors::authorization();
        $data = json_decode($payload, true); $data = is_array($data) ? $data : [];
        // Hosted checkout keys its webhook by tx_ref; direct-charge (mobile
        // money / bank transfer) events key by a top-level charge_id instead.
        $reference = (string) ($data['tx_ref'] ?? ($data['data']['tx_ref'] ?? ($data['charge_id'] ?? '')));
        if ($reference === '') throw UthengaTieErrors::validation(['tx_ref' => 'A transaction reference is required.']);
        $stmt = $this->db()->prepare('SELECT booking_id FROM transactions WHERE gateway_ref=? ORDER BY created_at DESC LIMIT 1'); $stmt->execute([$reference]);
        $bookingId = $stmt->fetchColumn();
        if (!$bookingId) throw UthengaTieErrors::validation(['tx_ref' => 'Unknown transaction reference.']);
        return $this->reconcilePayment((string) $bookingId, $data);
    }

    public function myTickets(string $customerId): array
    {
        $stmt = $this->db()->prepare("SELECT t.*, d.departure_at, d.arrival_estimate, d.status AS departure_status, l.title, l.location, l.image, l.vendor_name, l.meta,
                ds.price AS fare, tt.template_style, tt.logo_url, tt.accent_color, tt.footer_message, tt.contact_phone, tt.contact_email
            FROM tie_bus_tickets t INNER JOIN bookings b ON b.id=t.booking_id INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
                INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id LEFT JOIN tie_bus_ticket_templates tt ON tt.vendor_id=l.vendor_id
            WHERE b.customer_id=? ORDER BY d.departure_at DESC LIMIT 100");
        $stmt->execute([$customerId]);
        return ['schema_version' => 'tie-bus-ops/v1', 'tickets' => array_map(fn(array $row) => $this->publicTicket($row, true), $stmt->fetchAll())];
    }

    public function ticketDetail(string $customerId, string $ticketId): array
    {
        $stmt = $this->db()->prepare("SELECT t.*, b.customer_id, d.departure_at, d.arrival_estimate, d.status AS departure_status, l.title, l.location, l.image, l.vendor_name, l.meta,
                ds.price AS fare, tt.template_style, tt.logo_url, tt.accent_color, tt.footer_message, tt.contact_phone, tt.contact_email
            FROM tie_bus_tickets t INNER JOIN bookings b ON b.id=t.booking_id INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
                INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id LEFT JOIN tie_bus_ticket_templates tt ON tt.vendor_id=l.vendor_id
            WHERE t.id=? LIMIT 1");
        $stmt->execute([strtoupper($ticketId)]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['customer_id'] !== $customerId) throw new UthengaTieException('not_found', 'Ticket not found.', 404);
        return ['schema_version' => 'tie-bus-ops/v1', 'ticket' => $this->publicTicket($row, true)];
    }

    // ────────────────────────────── Vendor: analytics ───────────────────────────

    public function analyticsOverview(string $vendorId): array
    {
        $db = $this->db();
        $g = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(ds.price),0) t FROM tie_bus_tickets t
            INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id
            INNER JOIN tie_bus_departures d ON d.id=t.departure_id
            INNER JOIN listings l ON l.id=d.listing_id
            INNER JOIN bookings b ON b.id=t.booking_id
            WHERE l.vendor_id=? AND b.payment_status='Paid' AND t.status != 'cancelled'");
        $g->execute([$vendorId]); $gRow = $g->fetch();
        $tickets = (int) $gRow['c'];
        $gross = round((float) $gRow['t'], 2);

        $routesStmt = $db->prepare("SELECT l.id AS listing_id, l.title, l.meta, COUNT(t.id) AS tickets, COALESCE(SUM(ds.price),0) AS revenue
            FROM listings l LEFT JOIN tie_bus_departures d ON d.listing_id=l.id LEFT JOIN tie_bus_tickets t ON t.departure_id=d.id AND t.status != 'cancelled' LEFT JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id
            WHERE l.vendor_id=? AND l.listing_type='transport' GROUP BY l.id, l.title, l.meta ORDER BY tickets DESC");
        $routesStmt->execute([$vendorId]);
        $routes = array_map(function (array $row) {
            $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
            return ['listing_id' => (string) $row['listing_id'], 'title' => (string) $row['title'], 'origin' => (string) ($meta['routeFrom'] ?? ''), 'destination' => (string) ($meta['routeTo'] ?? ''), 'tickets' => (int) $row['tickets'], 'revenue' => round((float) $row['revenue'], 2)];
        }, $routesStmt->fetchAll());

        return [
            'schema_version' => 'tie-bus-ops/v1', 'currency' => APP_CURRENCY, 'gross_revenue' => $gross, 'tickets_sold' => $tickets,
            'average_ticket_price' => $tickets > 0 ? round($gross / $tickets, 2) : 0.0, 'active_routes' => count($routes), 'routes' => $routes,
        ];
    }

    public function analyticsTrend(string $vendorId, int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $stmt = $this->db()->prepare("SELECT DATE(created_at) d, COALESCE(SUM(total_price),0) t FROM bookings
            WHERE listing_id IN (SELECT id FROM listings WHERE vendor_id=? AND listing_type='transport') AND payment_status='Paid' AND created_at >= (UTC_TIMESTAMP() - INTERVAL ? DAY)
            GROUP BY DATE(created_at)");
        $stmt->execute([$vendorId, $days]);
        $byDate = [];
        foreach ($stmt->fetchAll() as $row) $byDate[(string) $row['d']] = (float) $row['t'];
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['date' => $date, 'amount' => round($byDate[$date] ?? 0.0, 2)];
        }
        return ['schema_version' => 'tie-bus-ops/v1', 'days' => $days, 'series' => $series];
    }

    // ────────────────────────────── Vendor: passengers ──────────────────────────

    public function passengers(string $vendorId, string $search = ''): array
    {
        $where = ['l.vendor_id=?']; $params = [$vendorId];
        $search = trim($search);
        if ($search !== '') { $where[] = '(t.passenger_name LIKE ? OR t.passenger_phone LIKE ?)'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
        $stmt = $this->db()->prepare("SELECT t.passenger_name, t.passenger_phone, t.status, t.created_at, l.meta, b.total_price, b.payment_status
            FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
            LEFT JOIN bookings b ON b.id=t.booking_id
            WHERE " . implode(' AND ', $where) . " ORDER BY t.created_at ASC");
        $stmt->execute($params);
        $byIdentity = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['passenger_phone'] !== null && trim((string) $row['passenger_phone']) !== '' ? 'p:' . trim((string) $row['passenger_phone']) : 'n:' . trim((string) $row['passenger_name']);
            $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
            $route = trim(($meta['routeFrom'] ?? '') . ' → ' . ($meta['routeTo'] ?? ''), ' →');
            if (!isset($byIdentity[$key])) $byIdentity[$key] = ['identity' => $key, 'name' => (string) $row['passenger_name'], 'phone' => $row['passenger_phone'], 'ticket_count' => 0, 'total_spend' => 0.0, 'routes' => [], 'last_trip_at' => null];
            if ($row['status'] !== 'cancelled') {
                $byIdentity[$key]['ticket_count']++;
                if ($row['payment_status'] === 'Paid') $byIdentity[$key]['total_spend'] += (float) $row['total_price'];
            }
            if ($route !== '' && !in_array($route, $byIdentity[$key]['routes'], true)) $byIdentity[$key]['routes'][] = $route;
            $byIdentity[$key]['last_trip_at'] = $this->utcIso((string) $row['created_at']);
        }
        $passengers = array_values($byIdentity);
        usort($passengers, fn(array $a, array $b) => $b['ticket_count'] <=> $a['ticket_count']);
        return ['schema_version' => 'tie-bus-ops/v1', 'passengers' => array_slice($passengers, 0, 300)];
    }

    public function passengerDetail(string $vendorId, string $identity): array
    {
        $identity = trim($identity);
        if ($identity === '') throw UthengaTieErrors::validation(['identity' => 'A passenger identity is required.']);
        $isPhone = str_starts_with($identity, 'p:');
        $value = substr($identity, 2);
        $where = $isPhone ? 't.passenger_phone=?' : "t.passenger_name=? AND (t.passenger_phone IS NULL OR t.passenger_phone='')";
        $stmt = $this->db()->prepare("SELECT t.id AS ticket_id, t.passenger_name, t.seat_label, t.status, d.departure_at, l.title, l.meta, b.total_price
            FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
            LEFT JOIN bookings b ON b.id=t.booking_id
            WHERE l.vendor_id=? AND $where ORDER BY d.departure_at DESC LIMIT 100");
        $stmt->execute([$vendorId, $value]);
        $rows = $stmt->fetchAll();
        if (!$rows) throw UthengaTieErrors::validation(['identity' => 'No tickets found for this passenger.']);
        $items = array_map(function (array $r) {
            $meta = json_decode((string) ($r['meta'] ?? '{}'), true) ?: [];
            $route = trim(($meta['routeFrom'] ?? '') . ' → ' . ($meta['routeTo'] ?? ''), ' →');
            return ['ticket_id' => (string) $r['ticket_id'], 'route' => $route !== '' ? $route : (string) $r['title'], 'departure_at' => $this->utcIso((string) $r['departure_at']), 'seat_label' => $r['seat_label'], 'status' => (string) $r['status'], 'amount' => $r['total_price'] !== null ? (float) $r['total_price'] : null];
        }, $rows);
        return ['schema_version' => 'tie-bus-ops/v1', 'name' => (string) $rows[0]['passenger_name'], 'items' => $items];
    }

    // ──────────────────────────── Vendor: all tickets ────────────────────────────

    public function listAllTickets(string $vendorId, array $filters = []): array
    {
        $where = ['l.vendor_id=?']; $params = [$vendorId];
        $code = trim((string) ($filters['code'] ?? ''));
        $passenger = trim((string) ($filters['passenger'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        if ($code !== '') { $where[] = 't.id LIKE ?'; $params[] = '%' . strtoupper($code) . '%'; }
        if ($passenger !== '') { $where[] = '(t.passenger_name LIKE ? OR t.passenger_phone LIKE ?)'; $params[] = "%{$passenger}%"; $params[] = "%{$passenger}%"; }
        if ($status !== '' && in_array($status, ['issued', 'boarded', 'cancelled', 'no_show'], true)) { $where[] = 't.status=?'; $params[] = $status; }
        $route = trim((string) ($filters['route'] ?? ''));
        if ($route !== '') { $where[] = 'l.title LIKE ?'; $params[] = "%{$route}%"; }
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') { $where[] = 'DATE(d.departure_at) >= ?'; $params[] = $dateFrom; }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') { $where[] = 'DATE(d.departure_at) <= ?'; $params[] = $dateTo; }

        $stmt = $this->db()->prepare("SELECT t.*, d.departure_at, d.status AS departure_status, l.title, l.location, l.image, l.vendor_name, l.meta,
                b.total_price AS booking_amount, b.payment_status AS booking_payment_status
            FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
                LEFT JOIN bookings b ON b.id=t.booking_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY t.created_at DESC LIMIT 200');
        $stmt->execute($params);
        return ['schema_version' => 'tie-bus-ops/v1', 'tickets' => array_map(function (array $row) {
            $ticket = $this->publicTicket($row, true);
            $ticket['amount'] = isset($row['booking_amount']) ? (float) $row['booking_amount'] : null;
            $ticket['payment_status'] = $row['booking_payment_status'] ?? null;
            return $ticket;
        }, $stmt->fetchAll())];
    }

    public function ticketsOverview(string $vendorId): array
    {
        $db = $this->db();
        $todayStmt = $db->prepare("SELECT COUNT(*) FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND DATE(d.departure_at)=CURDATE()");
        $todayStmt->execute([$vendorId]);
        $soldStmt = $db->prepare("SELECT COUNT(*) FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND t.status IN ('issued','boarded')");
        $soldStmt->execute([$vendorId]);
        $seatsStmt = $db->prepare("SELECT COALESCE(SUM(ds.remaining_seats),0) FROM tie_bus_departure_seats ds INNER JOIN tie_bus_departures d ON d.id=ds.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND d.status IN ('scheduled','boarding') AND d.departure_at >= UTC_TIMESTAMP()");
        $seatsStmt->execute([$vendorId]);
        $revenueStmt = $db->prepare("SELECT COALESCE(SUM(ds2.price),0) FROM tie_bus_tickets t2
            INNER JOIN tie_bus_departure_seats ds2 ON ds2.id=t2.departure_seat_id
            INNER JOIN tie_bus_departures d2 ON d2.id=t2.departure_id
            INNER JOIN listings l2 ON l2.id=d2.listing_id
            INNER JOIN bookings b2 ON b2.id=t2.booking_id
            WHERE l2.vendor_id=? AND b2.payment_status='Paid' AND t2.status != 'cancelled'");
        $revenueStmt->execute([$vendorId]);
        return [
            'schema_version' => 'tie-bus-ops/v1', 'today_departures' => (int) $todayStmt->fetchColumn(), 'tickets_sold' => (int) $soldStmt->fetchColumn(),
            'available_seats' => (int) $seatsStmt->fetchColumn(), 'revenue_paid' => (float) $revenueStmt->fetchColumn(),
        ];
    }

    public function cancelTicket(string $vendorId, string $ticketId, ?string $reason = null): array
    {
        $db = $this->db(); $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT t.*, l.vendor_id FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE t.id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([strtoupper($ticketId)]);
            $ticket = $stmt->fetch();
            if (!is_array($ticket) || (string) $ticket['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            if ($ticket['status'] === 'cancelled') throw UthengaTieErrors::validation(['ticket_id' => 'This ticket is already cancelled.']);
            if ($ticket['status'] === 'boarded') throw UthengaTieErrors::validation(['ticket_id' => 'A boarded ticket cannot be cancelled.']);

            $db->prepare("UPDATE tie_bus_tickets SET status='cancelled' WHERE id=?")->execute([$ticket['id']]);
            $db->prepare('UPDATE tie_bus_departure_seats SET remaining_seats=LEAST(remaining_seats+1, total_seats) WHERE id=?')->execute([$ticket['departure_seat_id']]);
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }

        $detail = $this->db()->prepare("SELECT t.*, d.departure_at, d.status AS departure_status, l.title, l.location, l.image, l.vendor_name, l.meta
            FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE t.id=? LIMIT 1");
        $detail->execute([strtoupper($ticketId)]);
        return ['schema_version' => 'tie-bus-ops/v1', 'ticket' => $this->publicTicket($detail->fetch(), true)];
    }

    // ────────────────────────────────── Mapping ─────────────────────────────────

    private function publicRoute(array $row): array
    {
        $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
        $classesStmt = $this->db()->prepare('SELECT id, class_name, price, total_seats, remaining_seats, is_active FROM seat_classes WHERE listing_id=? ORDER BY price ASC');
        $classesStmt->execute([$row['id']]);
        return [
            'listing_id' => (string) $row['id'], 'title' => (string) $row['title'], 'origin' => (string) ($meta['routeFrom'] ?? ''), 'destination' => (string) ($meta['routeTo'] ?? ''),
            'vehicle_type' => (string) ($meta['vehicleType'] ?? ''), 'departure_time' => (string) ($meta['departureTime'] ?? ''), 'arrival_time' => (string) ($meta['arrivalTime'] ?? ''),
            'schedule_days' => is_array($meta['scheduleDays'] ?? null) ? array_values($meta['scheduleDays']) : [], 'pickup_location' => (string) $row['location'], 'image' => (string) $row['image'], 'is_active' => (bool) $row['is_active'],
            'seat_classes' => array_map(fn(array $c) => ['id' => (int) $c['id'], 'class_name' => (string) $c['class_name'], 'price' => (float) $c['price'], 'total_seats' => (int) $c['total_seats'], 'remaining_seats' => (int) $c['remaining_seats'], 'is_active' => (bool) $c['is_active']], $classesStmt->fetchAll()),
        ];
    }

    private function publicDeparture(array $row): array
    {
        $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
        $out = [
            'departure_id' => (string) $row['id'], 'listing_id' => (string) $row['listing_id'],
            'title' => (string) (($row['listing_title'] ?? '') !== '' ? $row['listing_title'] : ($row['route_title'] ?? '')),
            'origin' => (string) ($meta['routeFrom'] ?? ''), 'destination' => (string) ($meta['routeTo'] ?? ''), 'vehicle_type' => (string) ($meta['vehicleType'] ?? ''),
            'operator' => (string) ($row['vendor_name'] ?? ''), 'pickup_location' => (string) ($row['location'] ?? ''),
            'image' => (string) (($row['image'] ?? '') !== '' ? $row['image'] : ($row['route_image'] ?? '')),
            'customer_description' => $row['customer_description'] ?? null,
            'highlights' => !empty($row['highlights']) ? array_values(array_filter(array_map('trim', explode(',', (string) $row['highlights'])))) : [],
            'card_style' => (string) ($row['card_style'] ?? 'standard'),
            'departure_at' => $this->utcIso((string) $row['departure_at']), 'arrival_estimate' => $row['arrival_estimate'] ? $this->utcIso((string) $row['arrival_estimate']) : null, 'status' => (string) $row['status'],
            'vehicle' => null, 'driver' => null,
        ];
        if (!empty($row['vehicle_id'])) {
            $vStmt = $this->db()->prepare('SELECT reg_number, make_model FROM tie_bus_fleet_vehicles WHERE id=? LIMIT 1'); $vStmt->execute([$row['vehicle_id']]);
            $vRow = $vStmt->fetch(); if (is_array($vRow)) $out['vehicle'] = ['id' => (string) $row['vehicle_id'], 'reg_number' => (string) $vRow['reg_number'], 'make_model' => (string) $vRow['make_model']];
        }
        if (!empty($row['driver_id'])) {
            $dStmt = $this->db()->prepare('SELECT name, phone FROM tie_bus_drivers WHERE id=? LIMIT 1'); $dStmt->execute([$row['driver_id']]);
            $dRow = $dStmt->fetch(); if (is_array($dRow)) $out['driver'] = ['id' => (string) $row['driver_id'], 'name' => (string) $dRow['name'], 'phone' => $dRow['phone']];
        }
        $seatsStmt = $this->db()->prepare('SELECT id, class_name, price, total_seats, remaining_seats FROM tie_bus_departure_seats WHERE departure_id=? ORDER BY price ASC');
        $seatsStmt->execute([$row['id']]);
        $out['seat_classes'] = array_map(fn(array $c) => ['departure_seat_id' => (int) $c['id'], 'class_name' => (string) $c['class_name'], 'price' => (float) $c['price'], 'total_seats' => (int) $c['total_seats'], 'remaining_seats' => (int) $c['remaining_seats']], $seatsStmt->fetchAll());
        return $out;
    }

    private function publicTicket(array $row, bool $includeSignature = false): array
    {
        $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
        $ticket = [
            'ticket_id' => (string) $row['id'], 'booking_id' => (string) ($row['booking_id'] ?? ''), 'status' => (string) $row['status'], 'passenger_name' => (string) $row['passenger_name'], 'seat_label' => $row['seat_label'],
            'vehicle_reg_number' => $row['vehicle_reg_number'] ?? null, 'vehicle_make_model' => $row['vehicle_make_model'] ?? null,
            'fare' => isset($row['fare']) ? (float) $row['fare'] : null,
            'title' => (string) $row['title'], 'origin' => (string) ($meta['routeFrom'] ?? ''), 'destination' => (string) ($meta['routeTo'] ?? ''), 'operator' => (string) ($row['vendor_name'] ?? ''),
            'pickup_location' => (string) ($row['location'] ?? ''), 'image' => (string) ($row['image'] ?? ''), 'departure_at' => $this->utcIso((string) $row['departure_at']),
            'departure_status' => (string) $row['departure_status'], 'boarded_at' => $row['boarded_at'] ? $this->utcIso((string) $row['boarded_at']) : null,
            'template_style' => (string) ($row['template_style'] ?? 'classic_blue'),
            'template_logo_url' => $row['logo_url'] ?? null, 'template_accent_color' => $row['accent_color'] ?? null,
            'template_footer_message' => $row['footer_message'] ?? null, 'template_contact_phone' => $row['contact_phone'] ?? null, 'template_contact_email' => $row['contact_email'] ?? null,
        ];
        if ($includeSignature) $ticket['qr_payload'] = $row['id'] . '.' . $row['qr_token'];
        return $ticket;
    }

    private function publicTicketBundle(string $bookingId): array
    {
        $stmt = $this->db()->prepare('SELECT payment_status, booking_status, customer_id FROM bookings WHERE id=? LIMIT 1'); $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!is_array($booking)) throw UthengaTieErrors::validation(['booking_id' => 'Booking not found.']);
        $tickets = [];
        if ($booking['payment_status'] === 'Paid') {
            $ticketsStmt = $this->db()->prepare("SELECT t.*, d.departure_at, d.arrival_estimate, d.status AS departure_status, l.title, l.location, l.image, l.vendor_name, l.meta,
                    ds.price AS fare, tt.template_style, tt.logo_url, tt.accent_color, tt.footer_message, tt.contact_phone, tt.contact_email
                FROM tie_bus_tickets t INNER JOIN tie_bus_departures d ON d.id=t.departure_id INNER JOIN listings l ON l.id=d.listing_id
                    INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id LEFT JOIN tie_bus_ticket_templates tt ON tt.vendor_id=l.vendor_id
                WHERE t.booking_id=?");
            $ticketsStmt->execute([$bookingId]);
            $tickets = array_map(fn(array $row) => $this->publicTicket($row, true), $ticketsStmt->fetchAll());
        }
        return ['booking_id' => $bookingId, 'payment_status' => (string) $booking['payment_status'], 'booking_status' => (string) $booking['booking_status'], 'tickets' => $tickets];
    }

    private function issueBusTickets(array $booking): int
    {
        if ($booking['listing_type'] !== 'transport' || $booking['payment_status'] !== 'Paid') return 0;
        $countStmt = $this->db()->prepare('SELECT COUNT(*) FROM tie_bus_tickets WHERE booking_id=?'); $countStmt->execute([$booking['id']]);
        $existing = (int) $countStmt->fetchColumn();
        $want = max((int) $booking['quantity'] - $existing, 0);
        if ($want <= 0) return 0;
        $details = json_decode((string) ($booking['details'] ?? '{}'), true) ?: [];
        $passengers = is_array($details['passengers'] ?? null) ? $details['passengers'] : [];
        $departureId = (string) ($details['departure_id'] ?? ''); $departureSeatId = (int) ($details['departure_seat_id'] ?? 0);
        $seatRow = $this->db()->prepare('SELECT ds.class_name, l.id AS listing_id, l.vendor_id, v.reg_number AS vehicle_reg_number, v.make_model AS vehicle_make_model FROM tie_bus_departure_seats ds INNER JOIN tie_bus_departures d ON d.id=ds.departure_id INNER JOIN listings l ON l.id=d.listing_id LEFT JOIN tie_bus_fleet_vehicles v ON v.id=d.vehicle_id WHERE ds.id=? LIMIT 1');
        $seatRow->execute([$departureSeatId]); $seat = $seatRow->fetch() ?: [];

        $issued = 0;
        for ($i = 0; $i < $want; $i++) {
            $passenger = $passengers[$existing + $i] ?? ['name' => $booking['customer_name'], 'phone' => null, 'seat_label' => null];
            $ticketId = 'UTH-BUS-' . strtoupper(bin2hex(random_bytes(4)));
            $token = bin2hex(random_bytes(24));
            $signature = UthengaTieBusOperationsContracts::ticketSignature($ticketId, $token);
            $this->db()->prepare('INSERT INTO tie_bus_tickets (id, booking_id, departure_id, departure_seat_id, passenger_name, passenger_phone, seat_label, vehicle_reg_number, vehicle_make_model, qr_token, verification_signature, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'issued\')')
                ->execute([$ticketId, $booking['id'], $departureId, $departureSeatId, $passenger['name'] ?? $booking['customer_name'], $passenger['phone'] ?? null, $passenger['seat_label'] ?? null, $seat['vehicle_reg_number'] ?? null, $seat['vehicle_make_model'] ?? null, $token, $signature]);
            $this->db()->prepare('INSERT INTO booking_items (booking_id, vendor_id, item_type, reference_id, item_name, quantity, unit_price, subtotal, service_date, metadata) VALUES (?,?,\'bus\',?,?,1,?,?,?,?)')
                ->execute([$booking['id'], $seat['vendor_id'] ?? null, $ticketId, $booking['listing_title'], $booking['total_price'] / max(1, (int) $booking['quantity']), $booking['total_price'] / max(1, (int) $booking['quantity']), substr((string) ($details['departure_at'] ?? ''), 0, 10) ?: null,
                    json_encode(['departure_at' => $details['departure_at'] ?? null, 'seat_class' => $seat['class_name'] ?? null, 'seat_label' => $passenger['seat_label'] ?? null], JSON_UNESCAPED_SLASHES)]);
            $issued++;
        }
        return $issued;
    }

    // ─────────────────────────────────── Guards ─────────────────────────────────

    private function ownedRoute(string $vendorId, string $listingId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM listings WHERE id=? AND listing_type='transport' LIMIT 1"); $stmt->execute([$listingId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function ownedDeparture(string $vendorId, string $departureId): array
    {
        $stmt = $this->db()->prepare('SELECT d.*, l.vendor_id FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.id=? LIMIT 1'); $stmt->execute([$departureId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function ownedSession(string $vendorId, string $sessionId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_bus_boarding_sessions WHERE id=? LIMIT 1'); $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function lockedSession(string $sessionId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_bus_boarding_sessions WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['session_id' => 'Boarding session not found.']);
        return $row;
    }

    private function lockedDepartureSeat(int $departureSeatId): array
    {
        $stmt = $this->db()->prepare('SELECT ds.*, d.departure_at, d.status AS departure_status, d.vehicle_id, l.id AS listing_id, l.title AS listing_title, l.image AS listing_image, l.vendor_id
            FROM tie_bus_departure_seats ds INNER JOIN tie_bus_departures d ON d.id=ds.departure_id INNER JOIN listings l ON l.id=d.listing_id WHERE ds.id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$departureSeatId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['departure_seat_id' => 'Seat class not found.']);
        $row['departure_id'] = (string) $row['departure_id'];
        return $row;
    }

    private function reloadBooking(string $bookingId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM bookings WHERE id=? LIMIT 1'); $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: [];
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('bus_operations');
        return $this->db;
    }
}

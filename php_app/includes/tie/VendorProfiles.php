<?php
/** Vendor-facing operating profiles; marketplace rows are created internally. */
final class UthengaTieVendorProfileContracts
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public static function transport(array $input): array
    {
        $name = self::text($input['profile_name'] ?? null, 180); $origin = self::text($input['origin'] ?? null, 120); $destination = self::text($input['destination'] ?? null, 120); $pickup = self::text($input['pickup_location'] ?? null, 200); $vehicle = strtolower(trim((string) ($input['vehicle_type'] ?? ''))); $departure = trim((string) ($input['departure_time'] ?? '')); $fare = $input['fare_per_seat'] ?? null; $seats = $input['total_seats'] ?? null;
        $errors = []; if ($name === null) $errors['profile_name'] = 'Give this transport service a name.'; if ($origin === null) $errors['origin'] = 'Enter where this service starts.'; if ($destination === null) $errors['destination'] = 'Enter where this service goes.'; if ($pickup === null) $errors['pickup_location'] = 'Enter the pickup or terminal location.'; if (!in_array($vehicle, ['bus', 'coach', 'minibus', 'van', 'car', 'taxi'], true)) $errors['vehicle_type'] = 'Choose a supported transport type.'; if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $departure)) $errors['departure_time'] = 'Departure time must use HH:MM.'; if (!is_numeric($fare) || (float) $fare <= 0 || (float) $fare > 100000000) $errors['fare_per_seat'] = 'Enter a valid fare per seat.'; if (!filter_var($seats, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]])) $errors['total_seats'] = 'Seats must be between 1 and 500.';
        $days = is_array($input['schedule_days'] ?? null) ? $input['schedule_days'] : self::DAYS; $days = array_values(array_unique(array_filter(array_map('strval', $days), fn(string $day): bool => in_array($day, self::DAYS, true)))); if ($days === []) $errors['schedule_days'] = 'Choose at least one operating day.'; if ($origin !== null && $destination !== null && mb_strtolower($origin) === mb_strtolower($destination)) $errors['destination'] = 'Destination must differ from origin.'; if ($errors) throw UthengaTieErrors::validation($errors);
        return ['profile_name' => $name, 'vehicle_type' => $vehicle, 'origin' => $origin, 'destination' => $destination, 'pickup_location' => $pickup, 'departure_time' => $departure, 'fare_per_seat' => round((float) $fare, 2), 'total_seats' => (int) $seats, 'schedule_days' => $days, 'description' => self::text($input['description'] ?? null, 1000) ?: "{$vehicle} service from {$origin} to {$destination}."];
    }
    public static function profileId($value): string { $value = trim((string) $value); if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) throw UthengaTieErrors::validation(['profile_id' => 'A valid service profile is required.']); return strtolower($value); }
    private static function text($value, int $max): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, $max); }
}

/**
 * Canonical lifecycle for vendor-owned operating profiles. A profile can be
 * prepared privately, but it cannot become marketplace inventory until it has
 * passed its category validation and an authorised review outcome.
 */
final class UthengaTieVendorServiceLifecycle
{
    public const NEW = 'NEW';
    public const PRIVATE_DRAFT = 'PRIVATE_DRAFT';
    public const SETUP_INCOMPLETE = 'SETUP_INCOMPLETE';
    public const READY_FOR_REVIEW = 'READY_FOR_REVIEW';
    public const PUBLISHED = 'PUBLISHED';
    public const ACTIVE = 'ACTIVE';
    public const PAUSED = 'PAUSED';
    public const ARCHIVED = 'ARCHIVED';

    private const TRANSITIONS = [
        self::NEW => [self::PRIVATE_DRAFT],
        self::PRIVATE_DRAFT => [self::SETUP_INCOMPLETE, self::READY_FOR_REVIEW, self::ARCHIVED],
        self::SETUP_INCOMPLETE => [self::PRIVATE_DRAFT, self::READY_FOR_REVIEW, self::ARCHIVED],
        self::READY_FOR_REVIEW => [self::PRIVATE_DRAFT, self::PUBLISHED, self::ARCHIVED],
        self::PUBLISHED => [self::ACTIVE, self::PAUSED, self::ARCHIVED],
        self::ACTIVE => [self::PAUSED, self::ARCHIVED],
        self::PAUSED => [self::PUBLISHED, self::ACTIVE, self::ARCHIVED],
        self::ARCHIVED => [],
    ];

    public static function assertTransition(string $from, string $to): void
    {
        if ($from === $to) return;
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw UthengaTieErrors::validation(['lifecycle' => "A service cannot move from {$from} to {$to}."]);
        }
    }

    public static function states(): array
    {
        return [self::NEW, self::PRIVATE_DRAFT, self::SETUP_INCOMPLETE, self::READY_FOR_REVIEW, self::PUBLISHED, self::ACTIVE, self::PAUSED, self::ARCHIVED];
    }
}

final class UthengaTieVendorProfileService
{
    public function __construct(private ?PDO $db) {}
    public function activateTransport(array $input, string $vendorId): array
    {
        $this->db(); $transport = UthengaTieVendorProfileContracts::transport($input); $this->db->beginTransaction();
        try {
            $vendor = $this->approvedVendor($vendorId); $profileId = $this->uuid(); $listingId = $this->marketplaceId('TRN');
            $this->db->prepare("UPDATE tie_vendor_service_profiles SET is_active=0, status=IF(status='ACTIVE', 'PUBLISHED', status), deactivated_at=UTC_TIMESTAMP() WHERE vendor_id=? AND is_active=1")->execute([$vendorId]);
            $meta = ['serviceProfileId' => $profileId, 'vehicleType' => $transport['vehicle_type'], 'routeFrom' => $transport['origin'], 'routeTo' => $transport['destination'], 'departureTime' => $transport['departure_time'], 'arrivalTime' => null, 'scheduleDays' => $transport['schedule_days'], 'pricePerSeat' => $transport['fare_per_seat'], 'baseFare' => $transport['fare_per_seat'], 'availableSeats' => $transport['total_seats'], 'totalSeats' => $transport['total_seats'], 'pickupLocation' => $transport['pickup_location']];
            $this->db->prepare('INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)')->execute([$listingId, 'transport', $transport['profile_name'], $transport['description'], $transport['pickup_location'], 'assets/images/hero/transport-van.png', json_encode([]), $vendorId, $vendor['name'], json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $seat = $this->db->prepare('INSERT INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, 1)'); $seat->execute([$listingId, 'Standard seat', 'Standard seat for ' . $transport['profile_name'], $transport['fare_per_seat'], $transport['total_seats'], $transport['total_seats']]);
            $this->db->prepare('INSERT INTO tie_vendor_service_profiles (id, vendor_id, profile_type, profile_name, status, is_active, listing_id, configuration, activated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, UTC_TIMESTAMP())')->execute([$profileId, $vendorId, 'transport', $transport['profile_name'], UthengaTieVendorServiceLifecycle::ACTIVE, $listingId, json_encode($transport, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $this->recordEvent($profileId, $vendorId, $vendorId, 'legacy_transport_activated', null, UthengaTieVendorServiceLifecycle::ACTIVE, 'Legacy guided activation created an active transport profile.');
            $this->notify($vendorId, 'Transport service active', 'Your transport service is active. You can now create a live departure when you are ready.');
            $this->db->commit(); return $this->profile($profileId, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    public function activate(string $profileId, string $vendorId): array
    {
        $this->db(); $profileId = UthengaTieVendorProfileContracts::profileId($profileId); $this->db->beginTransaction();
        try {
            $profile = $this->profileRow($profileId, $vendorId, true); $this->approvedVendor($vendorId);
            if (!in_array((string) $profile['status'], [UthengaTieVendorServiceLifecycle::PUBLISHED, UthengaTieVendorServiceLifecycle::PAUSED, UthengaTieVendorServiceLifecycle::ACTIVE], true) || empty($profile['listing_id'])) throw UthengaTieErrors::validation(['profile' => 'Only a reviewed, published service can become the active service workspace.']);
            UthengaTieVendorServiceLifecycle::assertTransition((string) $profile['status'], UthengaTieVendorServiceLifecycle::ACTIVE);
            $this->db->prepare("UPDATE tie_vendor_service_profiles SET is_active=0, status=IF(status='ACTIVE', 'PUBLISHED', status), deactivated_at=UTC_TIMESTAMP() WHERE vendor_id=? AND is_active=1")->execute([$vendorId]);
            $this->db->prepare("UPDATE tie_vendor_service_profiles SET is_active=1, status='ACTIVE', activated_at=UTC_TIMESTAMP(), deactivated_at=NULL WHERE id=? AND vendor_id=?")->execute([$profile['id'], $vendorId]);
            $this->db->prepare('UPDATE listings SET is_active=1 WHERE id=? AND vendor_id=?')->execute([$profile['listing_id'], $vendorId]);
            $this->recordEvent((string) $profile['id'], $vendorId, $vendorId, 'activated', (string) $profile['status'], UthengaTieVendorServiceLifecycle::ACTIVE, null);
            $this->db->commit(); return $this->profile($profileId, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    /** Save operating details without implicitly creating a public listing. */
    public function saveTransportSettings(array $input, string $vendorId): array
    {
        $this->db(); $this->approvedVendor($vendorId); $transport = UthengaTieVendorProfileContracts::transport($input);
        $image = trim((string) ($input['vehicle_image_url'] ?? ''));
        $driverName = trim(mb_substr((string) ($input['driver_name'] ?? ''), 0, 120));
        $driverPhone = trim(mb_substr((string) ($input['driver_phone'] ?? ''), 0, 60));
        $preferences = is_array($input['operational_preferences'] ?? null) ? $input['operational_preferences'] : [];
        if ($image !== '' && !preg_match('~^(https?://|assets/)~i', $image)) throw UthengaTieErrors::validation(['vehicle_image_url' => 'Use an https image URL or an Uthenga assets/ path.']);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("SELECT * FROM tie_vendor_service_profiles WHERE vendor_id=? AND profile_type='transport' ORDER BY is_active DESC, updated_at DESC LIMIT 1 FOR UPDATE"); $statement->execute([$vendorId]); $profile = $statement->fetch();
            $existing = is_array($profile) ? (json_decode((string) ($profile['configuration'] ?? '{}'), true) ?: []) : [];
            $configuration = array_merge($existing, $transport, ['vehicle_image_url' => $image, 'driver_name' => $driverName, 'driver_phone' => $driverPhone, 'operational_preferences' => $preferences, 'setup_complete' => true]);
            if (!is_array($profile)) {
                $id = $this->uuid();
                $this->db->prepare("INSERT INTO tie_vendor_service_profiles (id, vendor_id, profile_type, profile_name, status, is_active, configuration) VALUES (?, ?, 'transport', ?, 'SETUP_INCOMPLETE', 0, ?)")->execute([$id, $vendorId, $transport['profile_name'], json_encode($configuration, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
                $this->recordEvent($id, $vendorId, $vendorId, 'settings_saved', UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT, UthengaTieVendorServiceLifecycle::SETUP_INCOMPLETE, 'Complete setup and explicitly submit this service for review before publication.');
                $this->notify($vendorId, 'Finish service setup', 'Your transport settings are saved privately. Submit the completed service for review before it can be published.');
                $this->db->commit(); return $this->profile($id, $vendorId);
            }
            if ((bool) $profile['is_active']) {
                $this->synchroniseActiveTransportListing($profile, $transport, $configuration, $vendorId);
                $status = (string) ($profile['status'] ?: 'ACTIVE');
            } else {
                $status = (string) ($profile['status'] ?? UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT);
                if (in_array($status, [UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT, UthengaTieVendorServiceLifecycle::SETUP_INCOMPLETE], true)) {
                    $status = UthengaTieVendorServiceLifecycle::SETUP_INCOMPLETE;
                }
            }
            $this->db->prepare('UPDATE tie_vendor_service_profiles SET profile_name=?, status=?, configuration=? WHERE id=? AND vendor_id=?')->execute([$transport['profile_name'], $status, json_encode($configuration, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $profile['id'], $vendorId]);
            if ($status === UthengaTieVendorServiceLifecycle::SETUP_INCOMPLETE) {
                $this->recordEvent((string) $profile['id'], $vendorId, $vendorId, 'settings_saved', (string) $profile['status'], $status, 'Complete setup and explicitly submit this service for review before publication.');
                $this->notify($vendorId, 'Finish service setup', 'Your transport settings are saved privately. Submit the completed service for review before it can be published.');
            }
            $this->db->commit(); return $this->profile((string) $profile['id'], $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    /** Create a deliberately private shell. Saving settings and publishing are separate actions. */
    public function createDraft(string $type, string $vendorId, ?string $name = null): array
    {
        $this->db(); $this->approvedVendor($vendorId); $type = $this->profileType($type);
        $id = $this->uuid(); $label = trim(mb_substr((string) $name, 0, 180)) ?: ucfirst($type) . ' service';
        $configuration = ['setup_complete' => false, 'lifecycle_version' => 'vendor-service-lifecycle/v1'];
        $this->db->prepare('INSERT INTO tie_vendor_service_profiles (id, vendor_id, profile_type, profile_name, status, is_active, configuration) VALUES (?, ?, ?, ?, ?, 0, ?)')->execute([$id, $vendorId, $type, $label, UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT, json_encode($configuration, JSON_UNESCAPED_SLASHES)]);
        $this->recordEvent($id, $vendorId, $vendorId, 'draft_created', null, UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT, 'Private drafts are never visible or bookable.');
        $this->notify($vendorId, 'Finish service setup', "Your {$type} service is a private draft. Complete Settings before submitting it for review.");
        return $this->profile($id, $vendorId);
    }
    public function submitForReview(string $profileId, string $vendorId): array
    {
        $this->db(); $profileId = UthengaTieVendorProfileContracts::profileId($profileId); $this->db->beginTransaction();
        try {
            $profile = $this->profileRow($profileId, $vendorId, true); $this->approvedVendor($vendorId);
            UthengaTieVendorServiceLifecycle::assertTransition((string) $profile['status'], UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW);
            $this->validateForPublication($profile);
            $this->db->prepare("UPDATE tie_vendor_service_profiles SET status='READY_FOR_REVIEW', is_active=0, deactivated_at=UTC_TIMESTAMP() WHERE id=? AND vendor_id=?")->execute([$profileId, $vendorId]);
            $this->recordEvent($profileId, $vendorId, $vendorId, 'review_requested', (string) $profile['status'], UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW, 'Vendor declared mandatory setup complete.');
            $this->notify($vendorId, 'Service submitted for review', 'Your service setup is complete and has been submitted for review. It is still private until approved.');
            $this->db->commit(); return $this->profile($profileId, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    /** Admin-only review; an approval creates the linked marketplace inventory once. */
    public function review(string $profileId, string $decision, string $note, string $adminId): array
    {
        $this->db(); $profileId = UthengaTieVendorProfileContracts::profileId($profileId); $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['APPROVE', 'REJECT'], true)) throw UthengaTieErrors::validation(['decision' => 'Choose APPROVE or REJECT.']);
        $this->db->beginTransaction();
        try {
            $profile = $this->profileByAdmin($profileId, true);
            if ((string) $profile['status'] !== UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW) throw UthengaTieErrors::validation(['profile' => 'Only a service awaiting review can receive a review outcome.']);
            $vendor = $this->approvedVendor((string) $profile['vendor_id']);
            $next = $decision === 'APPROVE' ? UthengaTieVendorServiceLifecycle::PUBLISHED : UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT;
            if ($decision === 'APPROVE') {
                $this->validateForPublication($profile);
                if ((string) $profile['listing_id'] === '') $this->publishProfile($profile, $vendor);
            }
            $this->db->prepare('UPDATE tie_vendor_service_profiles SET status=?, is_active=0, deactivated_at=IF(?="PUBLISHED", NULL, UTC_TIMESTAMP()) WHERE id=?')->execute([$next, $next, $profileId]);
            // Accommodation v2 owns the property lifecycle while the shared
            // service profile remains the administrator review envelope. Keep
            // both records and the existing listing in one review transaction.
            if ((string) $profile['profile_type'] === 'accommodation') {
                $property = $this->db->prepare('SELECT id,listing_id,status FROM tie_accommodation_properties WHERE service_profile_id=? LIMIT 1 FOR UPDATE');
                $property->execute([$profileId]); $propertyRow = $property->fetch();
                if (is_array($propertyRow)) {
                    $propertyNext = $decision === 'APPROVE' ? 'PUBLISHED' : 'PRIVATE_DRAFT';
                    $this->db->prepare('UPDATE tie_accommodation_properties SET status=?,version=version+1 WHERE id=?')->execute([$propertyNext,$propertyRow['id']]);
                    if ((string) ($propertyRow['listing_id'] ?? '') !== '') {
                        $this->db->prepare('UPDATE listings SET is_active=? WHERE id=? AND vendor_id=?')->execute([$decision === 'APPROVE' ? 1 : 0,$propertyRow['listing_id'],$profile['vendor_id']]);
                    }
                    $this->db->prepare('INSERT INTO tie_accommodation_audit_events (id,property_id,actor_id,action_key,entity_type,entity_id,correlation_id,before_state,after_state) VALUES (UUID(),?,?,?,?,?,?,?,?)')->execute([(string)$propertyRow['id'],$adminId,$decision === 'APPROVE' ? 'property.review_approved' : 'property.review_rejected','property',(string)$propertyRow['id'],UthengaTieObservability::requestId(),json_encode(['status'=>$propertyRow['status']]),json_encode(['status'=>$propertyNext,'note'=>mb_substr($note,0,1000)])]);
                }
            }
            $this->recordEvent($profileId, (string) $profile['vendor_id'], $adminId, $decision === 'APPROVE' ? 'review_approved' : 'review_rejected', UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW, $next, $note);
            $this->notify((string) $profile['vendor_id'], $decision === 'APPROVE' ? 'Service published' : 'Service review needs changes', $decision === 'APPROVE' ? 'Your service was approved and published. Select it to make it your active service workspace.' : ('Your service was returned to private draft.' . ($note !== '' ? ' Review note: ' . $note : '')));
            $this->db->commit(); return $this->profileForAdmin($profileId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    public function pause(string $profileId, string $vendorId): array { return $this->endPublication($profileId, $vendorId, UthengaTieVendorServiceLifecycle::PAUSED, 'paused'); }
    public function archive(string $profileId, string $vendorId): array { return $this->endPublication($profileId, $vendorId, UthengaTieVendorServiceLifecycle::ARCHIVED, 'archived'); }
    /** Only exposes server-owned identifiers required to create a live departure. */
    public function transportSessionOptions(string $vendorId): array
    {
        $this->db(); $this->approvedVendor($vendorId);
        $stmt = $this->db->prepare("SELECT p.id AS profile_id, p.profile_name, p.status, p.is_active, p.listing_id, p.configuration, sc.id AS seat_class_id, sc.total_seats, sc.remaining_seats, sc.price FROM tie_vendor_service_profiles p INNER JOIN seat_classes sc ON sc.listing_id=p.listing_id AND sc.is_active=1 WHERE p.vendor_id=? AND p.profile_type='transport' AND p.status IN ('PUBLISHED','ACTIVE') AND p.listing_id IS NOT NULL ORDER BY p.is_active DESC, p.updated_at DESC");
        $stmt->execute([$vendorId]); $options = [];
        foreach ($stmt->fetchAll() as $row) {
            $config = json_decode((string) ($row['configuration'] ?? '{}'), true) ?: [];
            $options[] = ['profile_id' => (string) $row['profile_id'], 'service_id' => (string) $row['listing_id'], 'seat_class_id' => (int) $row['seat_class_id'], 'name' => (string) $row['profile_name'], 'active' => (bool) $row['is_active'], 'capacity' => (int) $row['total_seats'], 'inventory_remaining' => (int) $row['remaining_seats'], 'fare' => (float) $row['price'], 'origin' => (string) ($config['origin'] ?? ''), 'destination' => (string) ($config['destination'] ?? ''), 'loading_location' => (string) ($config['pickup_location'] ?? ''), 'departure_time' => (string) ($config['departure_time'] ?? '')];
        }
        return ['schema_version' => 'tie-driver-session-options/v1', 'options' => $options];
    }
    public function dashboard(string $vendorId): array
    {
        $this->db(); $this->approvedVendor($vendorId); $stmt = $this->db->prepare('SELECT * FROM tie_vendor_service_profiles WHERE vendor_id=? ORDER BY is_active DESC, updated_at DESC'); $stmt->execute([$vendorId]); $profiles = []; foreach ($stmt->fetchAll() as $row) $profiles[] = $this->public($row); return ['schema_version' => 'tie-vendor-profiles/v2', 'lifecycle' => UthengaTieVendorServiceLifecycle::states(), 'profiles' => $profiles, 'active_profile' => $profiles[0] ?? null, 'supported_profiles' => [['type' => 'transport', 'label' => 'Transport Operator', 'description' => 'Coordinate passengers, seats, departures, and boarding in real time.'], ['type' => 'accommodation', 'label' => 'Accommodation Host', 'description' => 'Rooms, rates, availability, and check-in operations.'], ['type' => 'event', 'label' => 'Event Organiser', 'description' => 'Venue, tickets, schedules, and check-in operations.'], ['type' => 'tour', 'label' => 'Tour Operator', 'description' => 'Itineraries, guides, capacity, and pickup operations.']]];
    }
    public function profile(string $profileId, string $vendorId): array { $this->db(); return ['schema_version' => 'tie-vendor-profile/v1', 'profile' => $this->public($this->profileRow($profileId, $vendorId))]; }
    private function approvedVendor(string $vendorId): array { $stmt = $this->db->prepare("SELECT id, name, role FROM users WHERE id=? AND is_approved=1 AND account_status='active' LIMIT 1"); $stmt->execute([$vendorId]); $row = $stmt->fetch(); if (!is_array($row) || !in_array((string) $row['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization(); return $row; }
    private function synchroniseActiveTransportListing(array $profile, array $transport, array $configuration, string $vendorId): void
    {
        $listingId = (string) ($profile['listing_id'] ?? ''); if ($listingId === '') throw UthengaTieErrors::validation(['profile' => 'The active transport profile has no linked marketplace service.']);
        $runs = $this->db->prepare("SELECT COUNT(*) FROM tie_transport_runs WHERE vendor_id=? AND service_id=? AND status IN ('SCHEDULED','LOADING','TRAVELLING') FOR UPDATE"); $runs->execute([$vendorId, $listingId]);
        if ((int) $runs->fetchColumn() > 0) throw UthengaTieErrors::validation(['session' => 'Complete or cancel the active transport session before changing vehicle capacity, fare, or route.']);
        $listing = $this->db->prepare('SELECT meta FROM listings WHERE id=? AND vendor_id=? AND listing_type="transport" LIMIT 1 FOR UPDATE'); $listing->execute([$listingId, $vendorId]); $row = $listing->fetch(); if (!is_array($row)) throw UthengaTieErrors::authorization();
        $meta = json_decode((string) ($row['meta'] ?? '{}'), true) ?: []; $meta = array_merge($meta, ['vehicleType' => $transport['vehicle_type'], 'routeFrom' => $transport['origin'], 'routeTo' => $transport['destination'], 'departureTime' => $transport['departure_time'], 'scheduleDays' => $transport['schedule_days'], 'pricePerSeat' => $transport['fare_per_seat'], 'baseFare' => $transport['fare_per_seat'], 'availableSeats' => $transport['total_seats'], 'totalSeats' => $transport['total_seats'], 'pickupLocation' => $transport['pickup_location']]);
        $this->db->prepare('UPDATE listings SET title=?, description=?, location=?, image=?, meta=? WHERE id=? AND vendor_id=?')->execute([$transport['profile_name'], $transport['description'], $transport['pickup_location'], $configuration['vehicle_image_url'] ?: 'assets/images/hero/transport-van.png', json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $listingId, $vendorId]);
        $seat = $this->db->prepare('SELECT id, total_seats, remaining_seats FROM seat_classes WHERE listing_id=? AND is_active=1 ORDER BY sort_order,id LIMIT 1 FOR UPDATE'); $seat->execute([$listingId]); $seatRow = $seat->fetch(); if (!is_array($seatRow)) throw UthengaTieErrors::validation(['seat_inventory' => 'The active transport service has no active seat inventory.']);
        $committed = max(0, (int) $seatRow['total_seats'] - (int) $seatRow['remaining_seats']); if ($transport['total_seats'] < $committed) throw UthengaTieErrors::validation(['total_seats' => 'Capacity cannot be lower than seats already committed.']);
        $this->db->prepare('UPDATE seat_classes SET description=?, price=?, total_seats=?, remaining_seats=? WHERE id=?')->execute(['Standard seat for ' . $transport['profile_name'], $transport['fare_per_seat'], $transport['total_seats'], $transport['total_seats'] - $committed, $seatRow['id']]);
    }
    private function endPublication(string $profileId, string $vendorId, string $next, string $event): array
    {
        $this->db(); $profileId = UthengaTieVendorProfileContracts::profileId($profileId); $this->db->beginTransaction();
        try {
            $profile = $this->profileRow($profileId, $vendorId, true); $this->approvedVendor($vendorId);
            UthengaTieVendorServiceLifecycle::assertTransition((string) $profile['status'], $next);
            if ((string) $profile['profile_type'] === 'transport' && $this->hasLiveTransportRun($vendorId, (string) ($profile['listing_id'] ?? ''))) throw UthengaTieErrors::validation(['session' => 'Complete or cancel the active transport session before pausing or archiving this service.']);
            $this->db->prepare('UPDATE tie_vendor_service_profiles SET status=?, is_active=0, deactivated_at=UTC_TIMESTAMP() WHERE id=? AND vendor_id=?')->execute([$next, $profileId, $vendorId]);
            if ((string) ($profile['listing_id'] ?? '') !== '') $this->db->prepare('UPDATE listings SET is_active=0 WHERE id=? AND vendor_id=?')->execute([$profile['listing_id'], $vendorId]);
            $this->recordEvent($profileId, $vendorId, $vendorId, $event, (string) $profile['status'], $next, null);
            $this->notify($vendorId, $next === UthengaTieVendorServiceLifecycle::PAUSED ? 'Service paused' : 'Service archived', $next === UthengaTieVendorServiceLifecycle::PAUSED ? 'Your service is paused and no longer available to customers.' : 'Your service is archived and no longer available to customers.');
            $this->db->commit(); return $this->profile($profileId, $vendorId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    private function validateForPublication(array $profile): void
    {
        $configuration = json_decode((string) ($profile['configuration'] ?? '{}'), true) ?: [];
        $type = (string) $profile['profile_type'];
        if ($type === 'transport') { UthengaTieVendorProfileContracts::transport($configuration); return; }
        $required = match ($type) {
            'accommodation' => ['property_name', 'location', 'rooms'],
            'event' => ['venue', 'start_at', 'ticket_classes', 'capacity'],
            'tour' => ['itinerary', 'start_date', 'capacity', 'guide_name', 'pickup_location'],
            default => [],
        };
        $missing = []; foreach ($required as $field) if (empty($configuration[$field])) $missing[$field] = 'Complete this service setting before publication.';
        if ($missing !== []) throw UthengaTieErrors::validation($missing);
    }
    private function publishProfile(array $profile, array $vendor): void
    {
        $type = (string) $profile['profile_type'];
        if ($type === 'accommodation') { $this->publishAccommodationProfile($profile, $vendor); return; }
        if ($type !== 'transport') throw UthengaTieErrors::validation(['profile' => 'This category needs its dedicated publication API before it can be published.']);
        $transport = UthengaTieVendorProfileContracts::transport(json_decode((string) $profile['configuration'], true) ?: []); $listingId = $this->marketplaceId('TRN');
        $configuration = json_decode((string) $profile['configuration'], true) ?: [];
        $meta = ['serviceProfileId' => (string) $profile['id'], 'vehicleType' => $transport['vehicle_type'], 'routeFrom' => $transport['origin'], 'routeTo' => $transport['destination'], 'departureTime' => $transport['departure_time'], 'scheduleDays' => $transport['schedule_days'], 'pricePerSeat' => $transport['fare_per_seat'], 'baseFare' => $transport['fare_per_seat'], 'availableSeats' => $transport['total_seats'], 'totalSeats' => $transport['total_seats'], 'pickupLocation' => $transport['pickup_location']];
        $image = trim((string) ($configuration['vehicle_image_url'] ?? '')) ?: 'assets/images/hero/transport-van.png';
        $this->db->prepare('INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)')->execute([$listingId, 'transport', $transport['profile_name'], $transport['description'], $transport['pickup_location'], $image, json_encode([]), $profile['vendor_id'], $vendor['name'], json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $this->db->prepare('INSERT INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, 1)')->execute([$listingId, 'Standard seat', 'Standard seat for ' . $transport['profile_name'], $transport['fare_per_seat'], $transport['total_seats'], $transport['total_seats']]);
        $this->db->prepare('UPDATE tie_vendor_service_profiles SET listing_id=? WHERE id=?')->execute([$listingId, $profile['id']]);
    }
    /** Publish only a reviewed accommodation profile and atomically create its room inventory. */
    private function publishAccommodationProfile(array $profile, array $vendor): void
    {
        if (!uthenga_table_exists('room_types')) throw UthengaTieErrors::providerUnavailable('room_inventory');
        $configuration = json_decode((string) ($profile['configuration'] ?? '{}'), true) ?: [];
        $rooms = is_array($configuration['rooms'] ?? null) ? $configuration['rooms'] : [];
        if ($rooms === []) throw UthengaTieErrors::validation(['rooms' => 'Add at least one room type before publication.']);
        $listingId = $this->marketplaceId('ACC');
        $name = trim((string) ($configuration['property_name'] ?? $profile['profile_name']));
        $location = trim((string) ($configuration['location'] ?? ''));
        $description = trim((string) ($configuration['description'] ?? ''));
        $image = trim((string) ($configuration['image_url'] ?? '')) ?: 'assets/images/hero/hotel-room.png';
        $validatedRooms = []; $metaRooms = [];
        foreach ($rooms as $index => $room) {
            if (!is_array($room)) continue;
            $roomName = trim(mb_substr((string) ($room['room_name'] ?? $room['name'] ?? ''), 0, 120));
            $price = $room['price_per_night'] ?? null; $total = $room['total_rooms'] ?? null; $available = $room['available_rooms'] ?? $total; $occupancy = $room['max_occupancy'] ?? 2;
            if ($roomName === '' || !is_numeric($price) || (float) $price < 0 || filter_var($total, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) === false || filter_var($available, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => (int) $total]]) === false || filter_var($occupancy, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]) === false) throw UthengaTieErrors::validation(['rooms' => 'Every room type needs a name, valid rate, total rooms, available rooms, and occupancy.']);
            $amenities = is_array($room['amenities'] ?? null) ? array_values($room['amenities']) : [];
            $validatedRooms[] = ['name' => $roomName, 'description' => mb_substr((string) ($room['description'] ?? ''), 0, 1000), 'price' => round((float) $price, 2), 'total' => (int) $total, 'available' => (int) $available, 'occupancy' => (int) $occupancy, 'amenities' => $amenities, 'sort_order' => $index];
            $metaRooms[] = ['name' => $roomName, 'pricePerNight' => round((float) $price, 2), 'availableRooms' => (int) $available, 'capacity' => (int) $occupancy];
        }
        if ($metaRooms === []) throw UthengaTieErrors::validation(['rooms' => 'At least one valid room type is required.']);
        $meta = ['serviceProfileId' => (string) $profile['id'], 'rooms' => $metaRooms, 'contactPhone' => trim((string) ($configuration['contact_phone'] ?? ''))];
        $this->db->prepare('INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)')->execute([$listingId, 'accommodation', $name, $description, $location, $image, json_encode([]), $profile['vendor_id'], $vendor['name'], json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $insertRoom = $this->db->prepare('INSERT INTO room_types (listing_id, room_name, description, price_per_night, total_rooms, available_rooms, max_occupancy, amenities, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
        foreach ($validatedRooms as $room) $insertRoom->execute([$listingId, $room['name'], $room['description'], $room['price'], $room['total'], $room['available'], $room['occupancy'], json_encode($room['amenities'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $room['sort_order']]);
        $this->db->prepare('UPDATE tie_vendor_service_profiles SET listing_id=? WHERE id=?')->execute([$listingId, $profile['id']]);
    }
    private function hasLiveTransportRun(string $vendorId, string $listingId): bool
    {
        if ($listingId === '') return false; $runs = $this->db->prepare("SELECT COUNT(*) FROM tie_transport_runs WHERE vendor_id=? AND service_id=? AND status IN ('SCHEDULED','LOADING','TRAVELLING') FOR UPDATE"); $runs->execute([$vendorId, $listingId]); return (int) $runs->fetchColumn() > 0;
    }
    private function profileType(string $type): string
    {
        $type = strtolower(trim($type)); if (!in_array($type, ['transport', 'accommodation', 'event', 'tour'], true)) throw UthengaTieErrors::validation(['profile_type' => 'Choose a supported service profile.']); return $type;
    }
    private function profileByAdmin(string $id, bool $lock = false): array { $stmt = $this->db->prepare('SELECT * FROM tie_vendor_service_profiles WHERE id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '')); $stmt->execute([$id]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::validation(['profile_id' => 'Service profile not found.']); return $row; }
    private function profileForAdmin(string $id): array { return ['schema_version' => 'tie-vendor-profile/v2', 'profile' => $this->public($this->profileByAdmin($id))]; }
    private function recordEvent(string $profileId, string $vendorId, ?string $actorId, string $event, ?string $from, string $to, ?string $note): void { try { $this->db->prepare('INSERT INTO tie_vendor_service_profile_events (profile_id, vendor_id, actor_id, event_type, from_status, to_status, note) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$profileId, $vendorId, $actorId, $event, $from, $to, $note === '' ? null : mb_substr((string) $note, 0, 1000)]); } catch (Throwable $ignore) {} }
    private function notify(string $userId, string $title, string $body): void { if (!UthengaTieFeatureFlags::enabled('notifications')) return; try { (new UthengaTieNotificationOutbox($this->db))->enqueue($userId, ['channel' => 'in_app', 'title' => $title, 'body' => $body]); } catch (Throwable $ignore) {} }
    private function profileRow(string $id, string $vendorId, bool $lock = false): array { $stmt = $this->db->prepare('SELECT * FROM tie_vendor_service_profiles WHERE id=? AND vendor_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '')); $stmt->execute([$id, $vendorId]); $row = $stmt->fetch(); if (!is_array($row)) throw UthengaTieErrors::authorization(); return $row; }
    private function public(array $row): array { $config = json_decode((string) ($row['configuration'] ?? '{}'), true) ?: []; return ['id' => (string) $row['id'], 'type' => (string) $row['profile_type'], 'name' => (string) $row['profile_name'], 'status' => (string) $row['status'], 'active' => (bool) $row['is_active'], 'listing_id' => $row['listing_id'], 'configuration' => $config, 'activated_at' => $row['activated_at'] ? gmdate('c', strtotime($row['activated_at'] . ' UTC')) : null]; }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('vendor_profiles'); }
    private function uuid(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
    private function marketplaceId(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(7))); }
}

/** AI may extract a draft only. Activation still passes deterministic validation. */
final class UthengaTieVendorProfileDraftService
{
    public function __construct(private UthengaTieLlmGateway $llm) {}
    public function transport(string $message): array
    {
        $message = trim(mb_substr($message, 0, 2000)); if ($message === '') throw UthengaTieErrors::validation(['message' => 'Describe the transport service you want to operate.']);
        $fallback = $this->heuristic($message);
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['profile_name', 'vehicle_type', 'origin', 'destination', 'pickup_location', 'departure_time', 'total_seats', 'fare_per_seat', 'description'], 'properties' => ['profile_name' => ['type' => 'string'], 'vehicle_type' => ['type' => 'string', 'enum' => ['', 'bus', 'coach', 'minibus', 'van', 'car', 'taxi']], 'origin' => ['type' => 'string'], 'destination' => ['type' => 'string'], 'pickup_location' => ['type' => 'string'], 'departure_time' => ['type' => 'string'], 'total_seats' => ['type' => 'string'], 'fare_per_seat' => ['type' => 'string'], 'description' => ['type' => 'string']]];
        try { if (!UthengaTieFeatureFlags::enabled('ai')) throw UthengaTieErrors::providerUnavailable('vendor_profile_draft'); $raw = $this->llm->generateStructured(['system' => 'You extract a transport-service setup draft for Uthenga. Never invent missing facts. Use empty strings for unknown values. Return only the required JSON schema. Vehicle must be bus, coach, minibus, van, car, taxi, or empty. Departure time must be HH:MM or empty. Amounts contain numbers only.', 'user_message' => $message, 'conversation_history' => [], 'evidence' => []], $schema); $draft = array_intersect_key($raw, array_flip(array_keys($schema['properties']))); } catch (Throwable $error) { $draft = $fallback; }
        foreach ($schema['properties'] as $key => $_) $draft[$key] = is_scalar($draft[$key] ?? null) ? trim((string) $draft[$key]) : '';
        if (!in_array($draft['vehicle_type'], ['', 'bus', 'coach', 'minibus', 'van', 'car', 'taxi'], true)) $draft['vehicle_type'] = $fallback['vehicle_type'];
        if ($draft['departure_time'] !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $draft['departure_time'])) $draft['departure_time'] = '';
        foreach (['total_seats', 'fare_per_seat'] as $number) $draft[$number] = preg_replace('/[^0-9.]/', '', $draft[$number]);
        $missing = []; foreach (['profile_name', 'vehicle_type', 'origin', 'destination', 'pickup_location', 'departure_time', 'total_seats', 'fare_per_seat'] as $field) if ($draft[$field] === '') $missing[] = $field;
        return ['schema_version' => 'tie-vendor-profile-draft/v1', 'draft' => $draft, 'missing_fields' => $missing, 'provider' => $this->llm->provider(), 'confirmation_required' => true];
    }
    private function heuristic(string $message): array
    {
        $lower = mb_strtolower($message); $vehicle = ''; foreach (['minibus', 'coach', 'bus', 'taxi', 'van', 'car'] as $candidate) if (str_contains($lower, $candidate)) { $vehicle = $candidate; break; }
        $route = []; if (preg_match('/\bfrom\s+(.+?)\s+to\s+([^,.\n]+)/i', $message, $matches)) $route = [trim($matches[1]), trim($matches[2])]; $seats = preg_match('/\b(\d{1,3})\s*(?:-|–)?\s*(?:seats?|passengers?)/i', $message, $matches) ? $matches[1] : ''; $fare = preg_match('/\b(?:mwk|mk)\s*([\d,]+)/i', $message, $matches) ? str_replace(',', '', $matches[1]) : ''; $time = preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', $message, $matches) ? str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ':' . $matches[2] : '';
        return ['profile_name' => '', 'vehicle_type' => $vehicle, 'origin' => $route[0] ?? '', 'destination' => $route[1] ?? '', 'pickup_location' => '', 'departure_time' => $time, 'total_seats' => $seats, 'fare_per_seat' => $fare, 'description' => $message];
    }
}

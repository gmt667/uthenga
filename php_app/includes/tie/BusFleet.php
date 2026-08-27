<?php
/**
 * Bus Operations Center — Fleet, Drivers, Maintenance. Re-keys the exact
 * document/maintenance/issue log shape Quick Taxi's Vehicle.php already
 * proved out (real expiry dates only, a plain service log, an issue log —
 * never a fabricated "vehicle health" score) onto a genuinely vendor-owned,
 * multi-vehicle fleet table, since Vehicle.php's tables are keyed by
 * driver_user_id (one vehicle per driver-user) and cannot hold a bus
 * company's fleet. Drivers are a plain operational roster, not platform
 * user accounts — unlike Events' tie_staff, a bus driver has no reason to
 * need a Uthenga login.
 */
final class UthengaTieBusFleetService
{
    public function __construct(private ?PDO $db) {}

    // ───────────────────────────────── Vehicles ─────────────────────────────────

    public function listVehicles(string $vendorId): array
    {
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM tie_bus_fleet_vehicles WHERE vendor_id=? ORDER BY created_at DESC');
        $stmt->execute([$vendorId]);
        $vehicles = $stmt->fetchAll();
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));

        $out = [];
        foreach ($vehicles as $row) {
            $docStmt = $db->prepare('SELECT document_type, expiry_date FROM tie_bus_fleet_documents WHERE vehicle_id=? ORDER BY expiry_date ASC LIMIT 1');
            $docStmt->execute([$row['id']]);
            $doc = $docStmt->fetch();
            $nextDocument = null;
            if (is_array($doc)) {
                $expiry = new DateTimeImmutable((string) $doc['expiry_date'], new DateTimeZone('UTC'));
                $daysRemaining = (int) $today->diff($expiry)->format('%r%a');
                $nextDocument = ['type' => (string) $doc['document_type'], 'expiry_date' => (string) $doc['expiry_date'], 'status' => $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring_soon' : 'valid'), 'days_remaining' => $daysRemaining];
            }
            $issueCountStmt = $db->prepare("SELECT COUNT(*) FROM tie_bus_fleet_issues WHERE vehicle_id=? AND status='open'");
            $issueCountStmt->execute([$row['id']]);

            $depStmt = $db->prepare("SELECT d.id, d.departure_at, d.status, l.title FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.vehicle_id=? AND d.status IN ('scheduled','boarding') AND d.departure_at >= UTC_TIMESTAMP() ORDER BY d.departure_at ASC LIMIT 1");
            $depStmt->execute([$row['id']]);
            $dep = $depStmt->fetch();
            $nextDeparture = is_array($dep) ? ['departure_id' => (string) $dep['id'], 'title' => (string) $dep['title'], 'departure_at' => $this->utcIso((string) $dep['departure_at']), 'status' => (string) $dep['status']] : null;

            $out[] = [
                'id' => (string) $row['id'], 'reg_number' => (string) $row['reg_number'], 'fleet_number' => $row['fleet_number'], 'make_model' => (string) $row['make_model'],
                'vehicle_type' => $row['vehicle_type'], 'manufacturer' => $row['manufacturer'], 'year' => $row['year'] !== null ? (int) $row['year'] : null, 'color' => $row['color'],
                'capacity' => (int) $row['capacity'], 'standing_capacity' => $row['standing_capacity'] !== null ? (int) $row['standing_capacity'] : null, 'luggage_capacity' => $row['luggage_capacity'],
                'amenities' => json_decode((string) ($row['amenities'] ?? '[]'), true) ?: [], 'gps_enabled' => (bool) $row['gps_enabled'],
                'maintenance_threshold_km' => $row['maintenance_threshold_km'] !== null ? (int) $row['maintenance_threshold_km'] : null,
                'boarding_buffer_minutes' => $row['boarding_buffer_minutes'] !== null ? (int) $row['boarding_buffer_minutes'] : null,
                'status' => (string) $row['status'], 'photo_url' => $row['photo_url'],
                'next_document' => $nextDocument, 'open_issue_count' => (int) $issueCountStmt->fetchColumn(), 'next_departure' => $nextDeparture,
            ];
        }
        return ['schema_version' => 'tie-bus-fleet/v1', 'vehicles' => $out];
    }

    public function fleetOverview(string $vendorId): array
    {
        $vehicles = $this->listVehicles($vendorId)['vehicles'];
        $onTripIds = [];
        $tripStmt = $this->db()->prepare("SELECT DISTINCT d.vehicle_id FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND d.status IN ('boarding','departed') AND d.vehicle_id IS NOT NULL");
        $tripStmt->execute([$vendorId]);
        foreach ($tripStmt->fetchAll() as $row) $onTripIds[] = (string) $row['vehicle_id'];

        $total = count($vehicles); $onTrip = 0; $available = 0; $maintenance = 0; $documentIssues = 0;
        foreach ($vehicles as $v) {
            $isOnTrip = in_array($v['id'], $onTripIds, true);
            if ($isOnTrip) $onTrip++;
            if ($v['status'] === 'maintenance') $maintenance++;
            elseif ($v['status'] === 'active' && !$isOnTrip) $available++;
            if ($v['next_document'] && in_array($v['next_document']['status'], ['expired', 'expiring_soon'], true)) $documentIssues++;
        }
        return ['schema_version' => 'tie-bus-fleet/v1', 'total' => $total, 'available' => $available, 'on_trip' => $onTrip, 'maintenance' => $maintenance, 'document_issues' => $documentIssues];
    }

    public function vehicleAssignments(string $vendorId, string $vehicleId): array
    {
        $vehicle = $this->ownedVehicle($vendorId, $vehicleId);
        $stmt = $this->db()->prepare("SELECT d.id, d.departure_at, d.status, l.title FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.vehicle_id=? AND l.vendor_id=? ORDER BY d.departure_at DESC LIMIT 50");
        $stmt->execute([$vehicle['id'], $vendorId]);
        return ['schema_version' => 'tie-bus-fleet/v1', 'vehicle_id' => $vehicle['id'], 'items' => array_map(fn(array $r) => [
            'departure_id' => (string) $r['id'], 'title' => (string) $r['title'], 'departure_at' => $this->utcIso((string) $r['departure_at']), 'status' => (string) $r['status'],
        ], $stmt->fetchAll())];
    }

    public function createVehicle(string $vendorId, array $input): array
    {
        $regNumber = UthengaTieBusOperationsContracts::nonEmptyString($input['reg_number'] ?? null, 'reg_number', 30);
        $makeModel = UthengaTieBusOperationsContracts::nonEmptyString($input['make_model'] ?? null, 'make_model', 120);
        $capacity = UthengaTieBusOperationsContracts::positiveInt($input['capacity'] ?? null, 'capacity');
        $year = trim((string) ($input['year'] ?? '')) !== '' ? (int) $input['year'] : null;
        $standingCapacity = trim((string) ($input['standing_capacity'] ?? '')) !== '' ? (int) $input['standing_capacity'] : null;
        $maintThreshold = trim((string) ($input['maintenance_threshold_km'] ?? '')) !== '' ? (int) $input['maintenance_threshold_km'] : null;
        $boardingBuffer = trim((string) ($input['boarding_buffer_minutes'] ?? '')) !== '' ? (int) $input['boarding_buffer_minutes'] : null;
        $amenities = is_array($input['amenities'] ?? null) ? array_values(array_filter(array_map('strval', $input['amenities']))) : [];
        $id = UthengaTieBusOperationsContracts::newUuid();
        $this->db()->prepare('INSERT INTO tie_bus_fleet_vehicles (id, vendor_id, reg_number, fleet_number, make_model, vehicle_type, manufacturer, year, color, capacity, standing_capacity, luggage_capacity, amenities, gps_enabled, maintenance_threshold_km, boarding_buffer_minutes, photo_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $id, $vendorId, $regNumber, trim((string) ($input['fleet_number'] ?? '')) ?: null, $makeModel,
                trim((string) ($input['vehicle_type'] ?? '')) ?: null, trim((string) ($input['manufacturer'] ?? '')) ?: null, $year, trim((string) ($input['color'] ?? '')) ?: null,
                $capacity, $standingCapacity, trim((string) ($input['luggage_capacity'] ?? '')) ?: null, json_encode($amenities),
                !empty($input['gps_enabled']) ? 1 : 0, $maintThreshold, $boardingBuffer, trim((string) ($input['photo_url'] ?? '')) ?: null,
            ]);
        return ['created_vehicle_id' => $id] + $this->listVehicles($vendorId);
    }

    public function updateVehicle(string $vendorId, array $input): array
    {
        $vehicle = $this->ownedVehicle($vendorId, (string) ($input['vehicle_id'] ?? ''));
        $status = (string) ($input['status'] ?? $vehicle['status']);
        if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) throw UthengaTieErrors::validation(['status' => 'Invalid vehicle status.']);
        $this->db()->prepare('UPDATE tie_bus_fleet_vehicles SET status=? WHERE id=?')->execute([$status, $vehicle['id']]);
        return $this->listVehicles($vendorId);
    }

    // ───────────────────────────────── Documents ────────────────────────────────

    public function documents(string $vendorId, string $vehicleId): array
    {
        $vehicle = $this->ownedVehicle($vendorId, $vehicleId);
        $stmt = $this->db()->prepare('SELECT document_type, expiry_date, file_url FROM tie_bus_fleet_documents WHERE vehicle_id=? ORDER BY expiry_date ASC');
        $stmt->execute([$vehicle['id']]);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $items = array_map(function (array $row) use ($today) {
            $expiry = new DateTimeImmutable((string) $row['expiry_date'], new DateTimeZone('UTC'));
            $daysRemaining = (int) $today->diff($expiry)->format('%r%a');
            return ['type' => (string) $row['document_type'], 'expiry_date' => (string) $row['expiry_date'], 'file_url' => $row['file_url'], 'status' => $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring_soon' : 'valid'), 'days_remaining' => $daysRemaining];
        }, $stmt->fetchAll());
        return ['schema_version' => 'tie-bus-fleet/v1', 'vehicle_id' => $vehicle['id'], 'documents' => $items];
    }

    public function saveDocument(string $vendorId, array $input): array
    {
        $vehicle = $this->ownedVehicle($vendorId, (string) ($input['vehicle_id'] ?? ''));
        $type = UthengaTieBusOperationsContracts::nonEmptyString($input['document_type'] ?? null, 'document_type', 40);
        $expiry = trim((string) ($input['expiry_date'] ?? ''));
        if ($expiry === '') throw UthengaTieErrors::validation(['expiry_date' => 'An expiry date is required.']);
        try { $expiry = (new DateTimeImmutable($expiry))->format('Y-m-d'); } catch (Throwable) { throw UthengaTieErrors::validation(['expiry_date' => 'Use a valid date.']); }
        $fileUrl = trim((string) ($input['file_url'] ?? '')) ?: null;
        $this->db()->prepare('INSERT INTO tie_bus_fleet_documents (vehicle_id, document_type, expiry_date, file_url) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE expiry_date=VALUES(expiry_date), file_url=COALESCE(VALUES(file_url), file_url), updated_at=NOW()')
            ->execute([$vehicle['id'], $type, $expiry, $fileUrl]);
        return $this->documents($vendorId, $vehicle['id']);
    }

    // ─────────────────────────────── Maintenance ────────────────────────────────

    public function maintenanceHistory(string $vendorId, ?string $vehicleId = null, int $limit = 100): array
    {
        $db = $this->db();
        if ($vehicleId !== null) {
            $vehicle = $this->ownedVehicle($vendorId, $vehicleId);
            $stmt = $db->prepare('SELECT m.*, v.reg_number FROM tie_bus_fleet_maintenance m INNER JOIN tie_bus_fleet_vehicles v ON v.id=m.vehicle_id WHERE m.vehicle_id=? ORDER BY m.serviced_at DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute([$vehicle['id']]);
        } else {
            $stmt = $db->prepare('SELECT m.*, v.reg_number FROM tie_bus_fleet_maintenance m INNER JOIN tie_bus_fleet_vehicles v ON v.id=m.vehicle_id WHERE v.vendor_id=? ORDER BY m.serviced_at DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute([$vendorId]);
        }
        return ['schema_version' => 'tie-bus-fleet/v1', 'items' => array_map(fn(array $r) => [
            'id' => (int) $r['id'], 'vehicle_id' => (string) $r['vehicle_id'], 'reg_number' => (string) $r['reg_number'], 'service_type' => (string) $r['service_type'],
            'serviced_at' => (string) $r['serviced_at'], 'mileage_km' => $r['mileage_km'] !== null ? (int) $r['mileage_km'] : null,
            'cost' => $r['cost'] !== null ? (float) $r['cost'] : null, 'notes' => $r['notes'],
        ], $stmt->fetchAll())];
    }

    public function logMaintenance(string $vendorId, array $input): array
    {
        $vehicle = $this->ownedVehicle($vendorId, (string) ($input['vehicle_id'] ?? ''));
        $serviceType = UthengaTieBusOperationsContracts::nonEmptyString($input['service_type'] ?? null, 'service_type', 120);
        $servicedAt = trim((string) ($input['serviced_at'] ?? ''));
        try { $servicedAt = $servicedAt === '' ? gmdate('Y-m-d') : (new DateTimeImmutable($servicedAt))->format('Y-m-d'); } catch (Throwable) { throw UthengaTieErrors::validation(['serviced_at' => 'Use a valid date.']); }
        $mileage = isset($input['mileage_km']) && trim((string) $input['mileage_km']) !== '' ? UthengaTieBusOperationsContracts::positiveInt($input['mileage_km'], 'mileage_km') : null;
        $cost = isset($input['cost']) && trim((string) $input['cost']) !== '' ? round((float) $input['cost'], 2) : null;
        $this->db()->prepare('INSERT INTO tie_bus_fleet_maintenance (vehicle_id, service_type, serviced_at, mileage_km, cost, notes) VALUES (?,?,?,?,?,?)')
            ->execute([$vehicle['id'], $serviceType, $servicedAt, $mileage, $cost, trim((string) ($input['notes'] ?? '')) ?: null]);
        return $this->maintenanceHistory($vendorId, null);
    }

    // ────────────────────────────────── Issues ──────────────────────────────────

    public function issues(string $vendorId, ?string $vehicleId = null, ?string $status = null): array
    {
        $db = $this->db();
        $where = ['v.vendor_id=?']; $params = [$vendorId];
        if ($vehicleId !== null) { $where[] = 'i.vehicle_id=?'; $params[] = $this->ownedVehicle($vendorId, $vehicleId)['id']; }
        if ($status !== null) { $where[] = 'i.status=?'; $params[] = $status; }
        $stmt = $db->prepare('SELECT i.*, v.reg_number FROM tie_bus_fleet_issues i INNER JOIN tie_bus_fleet_vehicles v ON v.id=i.vehicle_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.status ASC, i.created_at DESC');
        $stmt->execute($params);
        return ['schema_version' => 'tie-bus-fleet/v1', 'items' => array_map(fn(array $r) => [
            'id' => (int) $r['id'], 'vehicle_id' => (string) $r['vehicle_id'], 'reg_number' => (string) $r['reg_number'], 'category' => (string) $r['category'],
            'description' => (string) $r['description'], 'severity' => (string) $r['severity'], 'cost' => $r['cost'] !== null ? (float) $r['cost'] : null, 'status' => (string) $r['status'],
            'created_at' => $this->utcIso((string) $r['created_at']), 'resolved_at' => $r['resolved_at'] ? $this->utcIso((string) $r['resolved_at']) : null,
        ], $stmt->fetchAll())];
    }

    public function reportIssue(string $vendorId, array $input): array
    {
        $vehicle = $this->ownedVehicle($vendorId, (string) ($input['vehicle_id'] ?? ''));
        $category = UthengaTieBusOperationsContracts::nonEmptyString($input['category'] ?? null, 'category', 40);
        $description = UthengaTieBusOperationsContracts::nonEmptyString($input['description'] ?? null, 'description', 1000);
        $severity = in_array($input['severity'] ?? '', ['low', 'medium', 'critical'], true) ? $input['severity'] : 'low';
        $cost = isset($input['cost']) && trim((string) $input['cost']) !== '' ? round((float) $input['cost'], 2) : null;
        $db = $this->db(); $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO tie_bus_fleet_issues (vehicle_id, category, description, severity, cost) VALUES (?,?,?,?,?)')
                ->execute([$vehicle['id'], $category, $description, $severity, $cost]);
            if (!empty($input['mark_out_of_service'])) {
                $db->prepare("UPDATE tie_bus_fleet_vehicles SET status='maintenance' WHERE id=?")->execute([$vehicle['id']]);
            }
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
        return $this->issues($vendorId);
    }

    public function resolveIssue(string $vendorId, int $issueId): array
    {
        $stmt = $this->db()->prepare('SELECT i.id, v.vendor_id FROM tie_bus_fleet_issues i INNER JOIN tie_bus_fleet_vehicles v ON v.id=i.vehicle_id WHERE i.id=? LIMIT 1');
        $stmt->execute([$issueId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        $this->db()->prepare("UPDATE tie_bus_fleet_issues SET status='resolved', resolved_at=NOW() WHERE id=?")->execute([$issueId]);
        return $this->issues($vendorId);
    }

    public function allDocumentIssues(string $vendorId): array
    {
        $stmt = $this->db()->prepare('SELECT doc.document_type, doc.expiry_date, v.id AS vehicle_id, v.reg_number FROM tie_bus_fleet_documents doc INNER JOIN tie_bus_fleet_vehicles v ON v.id=doc.vehicle_id WHERE v.vendor_id=? ORDER BY doc.expiry_date ASC');
        $stmt->execute([$vendorId]);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $daysRemaining = (int) $today->diff(new DateTimeImmutable((string) $row['expiry_date'], new DateTimeZone('UTC')))->format('%r%a');
            $status = $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring_soon' : 'valid');
            if (!in_array($status, ['expired', 'expiring_soon'], true)) continue;
            $items[] = ['vehicle_id' => (string) $row['vehicle_id'], 'reg_number' => (string) $row['reg_number'], 'type' => (string) $row['document_type'], 'expiry_date' => (string) $row['expiry_date'], 'status' => $status, 'days_remaining' => $daysRemaining];
        }
        return ['schema_version' => 'tie-bus-fleet/v1', 'items' => $items];
    }

    public function maintenanceOverview(string $vendorId): array
    {
        $documentIssues = count($this->allDocumentIssues($vendorId)['items']);
        $openIssues = count($this->issues($vendorId, null, 'open')['items']);
        $db = $this->db();
        $stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(m.cost),0) FROM tie_bus_fleet_maintenance m INNER JOIN tie_bus_fleet_vehicles v ON v.id=m.vehicle_id WHERE v.vendor_id=? AND m.serviced_at >= DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)");
        $stmt->execute([$vendorId]);
        [$serviceCount, $serviceCost] = $stmt->fetch(PDO::FETCH_NUM);
        $issueCostStmt = $db->prepare("SELECT COALESCE(SUM(i.cost),0) FROM tie_bus_fleet_issues i INNER JOIN tie_bus_fleet_vehicles v ON v.id=i.vehicle_id WHERE v.vendor_id=? AND i.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
        $issueCostStmt->execute([$vendorId]);
        $totalCost = round((float) $serviceCost + (float) $issueCostStmt->fetchColumn(), 2);
        return ['schema_version' => 'tie-bus-fleet/v1', 'document_issues' => $documentIssues, 'open_issues' => $openIssues, 'services_last_30_days' => (int) $serviceCount, 'total_cost_last_30_days' => $totalCost];
    }

    // ───────────────────────────────── Drivers ──────────────────────────────────

    public function listDrivers(string $vendorId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_bus_drivers WHERE vendor_id=? ORDER BY created_at DESC');
        $stmt->execute([$vendorId]);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        return ['schema_version' => 'tie-bus-fleet/v1', 'drivers' => array_map(fn(array $r) => [
            'id' => (string) $r['id'], 'name' => (string) $r['name'], 'phone' => $r['phone'], 'license_number' => $r['license_number'],
            'license_expiry' => $r['license_expiry'], 'license_status' => $this->licenseStatus($r['license_expiry'], $today), 'status' => (string) $r['status'],
        ], $stmt->fetchAll())];
    }

    public function driverOverview(string $vendorId): array
    {
        $drivers = $this->listDrivers($vendorId)['drivers'];
        $onTripIds = [];
        $tripStmt = $this->db()->prepare("SELECT DISTINCT d.driver_id FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE l.vendor_id=? AND d.status IN ('boarding','departed') AND d.driver_id IS NOT NULL");
        $tripStmt->execute([$vendorId]);
        foreach ($tripStmt->fetchAll() as $row) $onTripIds[] = (string) $row['driver_id'];

        $total = count($drivers); $active = 0; $onTrip = 0; $licenseIssues = 0;
        foreach ($drivers as $d) {
            if ($d['status'] === 'active') $active++;
            if (in_array($d['id'], $onTripIds, true)) $onTrip++;
            if (in_array($d['license_status'], ['expired', 'expiring_soon'], true)) $licenseIssues++;
        }
        return ['schema_version' => 'tie-bus-fleet/v1', 'total' => $total, 'active' => $active, 'on_trip' => $onTrip, 'license_issues' => $licenseIssues];
    }

    public function driverAssignments(string $vendorId, string $driverId): array
    {
        $driver = $this->ownedDriver($vendorId, $driverId);
        $stmt = $this->db()->prepare("SELECT d.id, d.departure_at, d.status, l.title FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.driver_id=? AND l.vendor_id=? ORDER BY d.departure_at DESC LIMIT 50");
        $stmt->execute([$driver['id'], $vendorId]);
        return ['schema_version' => 'tie-bus-fleet/v1', 'driver_id' => $driver['id'], 'items' => array_map(fn(array $r) => [
            'departure_id' => (string) $r['id'], 'title' => (string) $r['title'], 'departure_at' => $this->utcIso((string) $r['departure_at']), 'status' => (string) $r['status'],
        ], $stmt->fetchAll())];
    }

    public function createDriver(string $vendorId, array $input): array
    {
        $name = UthengaTieBusOperationsContracts::nonEmptyString($input['name'] ?? null, 'name', 120);
        $id = UthengaTieBusOperationsContracts::newUuid();
        $this->db()->prepare('INSERT INTO tie_bus_drivers (id, vendor_id, name, phone, license_number, license_expiry) VALUES (?,?,?,?,?,?)')
            ->execute([$id, $vendorId, $name, trim((string) ($input['phone'] ?? '')) ?: null, trim((string) ($input['license_number'] ?? '')) ?: null, $this->parseOptionalDate($input['license_expiry'] ?? null, 'license_expiry')]);
        return $this->listDrivers($vendorId);
    }

    public function updateDriver(string $vendorId, array $input): array
    {
        $driver = $this->ownedDriver($vendorId, (string) ($input['driver_id'] ?? ''));
        $status = (string) ($input['status'] ?? $driver['status']);
        if (!in_array($status, ['active', 'inactive'], true)) throw UthengaTieErrors::validation(['status' => 'Invalid driver status.']);
        $name = array_key_exists('name', $input) ? UthengaTieBusOperationsContracts::nonEmptyString($input['name'], 'name', 120) : $driver['name'];
        $phone = array_key_exists('phone', $input) ? (trim((string) $input['phone']) ?: null) : $driver['phone'];
        $license = array_key_exists('license_number', $input) ? (trim((string) $input['license_number']) ?: null) : $driver['license_number'];
        $expiry = array_key_exists('license_expiry', $input) ? $this->parseOptionalDate($input['license_expiry'], 'license_expiry') : $driver['license_expiry'];
        $this->db()->prepare('UPDATE tie_bus_drivers SET status=?, name=?, phone=?, license_number=?, license_expiry=? WHERE id=?')
            ->execute([$status, $name, $phone, $license, $expiry, $driver['id']]);
        return $this->listDrivers($vendorId);
    }

    // ─────────────────────────── Departure assignment ───────────────────────────

    public function assignDeparture(string $vendorId, string $departureId, ?string $vehicleId, ?string $driverId): array
    {
        $stmt = $this->db()->prepare('SELECT d.id, l.vendor_id FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.id=? LIMIT 1');
        $stmt->execute([$departureId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();

        $resolvedVehicleId = null; $resolvedDriverId = null;
        if ($vehicleId !== null && $vehicleId !== '') $resolvedVehicleId = $this->ownedVehicle($vendorId, $vehicleId)['id'];
        if ($driverId !== null && $driverId !== '') $resolvedDriverId = $this->ownedDriver($vendorId, $driverId)['id'];

        $this->db()->prepare('UPDATE tie_bus_departures SET vehicle_id=?, driver_id=? WHERE id=?')->execute([$resolvedVehicleId, $resolvedDriverId, $departureId]);
        return ['schema_version' => 'tie-bus-fleet/v1', 'departure_id' => $departureId, 'vehicle_id' => $resolvedVehicleId, 'driver_id' => $resolvedDriverId];
    }

    /** Which vehicles/drivers can genuinely be assigned to a departure right now —
     *  excludes a vehicle in maintenance, a driver with an expired license, and
     *  either side already assigned to another departure within a 3-hour window
     *  of this one (no trip-duration is stored, so this is a defensible proxy
     *  for a real time conflict rather than a fabricated precise overlap check). */
    /** $departureId is null (with $departureAt supplied instead) when checking
     *  eligibility for a departure that doesn't exist yet — the Create Departure
     *  wizard, before it has anything to exclude from its own conflict query. */
    public function assignmentEligibility(string $vendorId, ?string $departureId, ?string $departureAt = null): array
    {
        $db = $this->db();
        if ($departureId !== null && $departureId !== '') {
            $depStmt = $db->prepare('SELECT d.id, d.departure_at, l.vendor_id FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id WHERE d.id=? LIMIT 1');
            $depStmt->execute([$departureId]);
            $dep = $depStmt->fetch();
            if (!is_array($dep) || (string) $dep['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            $targetAt = (string) $dep['departure_at'];
        } else {
            if (!$departureAt) throw UthengaTieErrors::validation(['departure_at' => 'A departure date and time is required.']);
            try { $targetAt = (new DateTimeImmutable($departureAt))->format('Y-m-d H:i:s'); } catch (Throwable) { throw UthengaTieErrors::validation(['departure_at' => 'Use a valid date and time.']); }
            $departureId = '';
        }

        $conflictStmt = $db->prepare("SELECT d.vehicle_id, d.driver_id, d.departure_at, l.title
            FROM tie_bus_departures d INNER JOIN listings l ON l.id=d.listing_id
            WHERE l.vendor_id=? AND d.id != ? AND d.status IN ('scheduled','boarding')
                  AND ABS(TIMESTAMPDIFF(MINUTE, d.departure_at, ?)) <= 180");
        $conflictStmt->execute([$vendorId, $departureId, $targetAt]);
        $vehicleConflicts = []; $driverConflicts = [];
        foreach ($conflictStmt->fetchAll() as $row) {
            $conflict = ['title' => (string) $row['title'], 'departure_at' => $this->utcIso((string) $row['departure_at'])];
            if ($row['vehicle_id']) $vehicleConflicts[(string) $row['vehicle_id']] = $conflict;
            if ($row['driver_id']) $driverConflicts[(string) $row['driver_id']] = $conflict;
        }

        $vehicles = array_map(function (array $v) use ($vehicleConflicts) {
            $reasonCode = null;
            if ($v['status'] !== 'active') $reasonCode = $v['status'] === 'maintenance' ? 'maintenance' : 'inactive';
            elseif (isset($vehicleConflicts[$v['id']])) $reasonCode = 'conflict';
            return ['id' => $v['id'], 'reg_number' => $v['reg_number'], 'make_model' => $v['make_model'], 'status' => $v['status'],
                'eligible' => $reasonCode === null, 'reason_code' => $reasonCode, 'conflict' => $vehicleConflicts[$v['id']] ?? null];
        }, $this->listVehicles($vendorId)['vehicles']);

        $drivers = array_map(function (array $d) use ($driverConflicts) {
            $reasonCode = null;
            if ($d['status'] !== 'active') $reasonCode = 'inactive';
            elseif ($d['license_status'] === 'expired') $reasonCode = 'license_expired';
            elseif (isset($driverConflicts[$d['id']])) $reasonCode = 'conflict';
            return ['id' => $d['id'], 'name' => $d['name'], 'license_status' => $d['license_status'],
                'eligible' => $reasonCode === null, 'reason_code' => $reasonCode, 'conflict' => $driverConflicts[$d['id']] ?? null];
        }, $this->listDrivers($vendorId)['drivers']);

        return ['schema_version' => 'tie-bus-fleet/v1', 'departure_id' => $departureId, 'vehicles' => $vehicles, 'drivers' => $drivers];
    }

    // ─────────────────────────────────── Guards ─────────────────────────────────

    private function ownedVehicle(string $vendorId, string $vehicleId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_bus_fleet_vehicles WHERE id=? LIMIT 1');
        $stmt->execute([$vehicleId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function ownedDriver(string $vendorId, string $driverId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_bus_drivers WHERE id=? LIMIT 1');
        $stmt->execute([$driverId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (string) $row['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function licenseStatus(?string $expiry, DateTimeImmutable $today): string
    {
        if ($expiry === null || $expiry === '') return 'none';
        $daysRemaining = (int) $today->diff(new DateTimeImmutable($expiry, new DateTimeZone('UTC')))->format('%r%a');
        return $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring_soon' : 'valid');
    }

    private function parseOptionalDate(mixed $value, string $field): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        try { return (new DateTimeImmutable($value))->format('Y-m-d'); } catch (Throwable) { throw UthengaTieErrors::validation([$field => 'Use a valid date.']); }
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('bus_fleet');
        return $this->db;
    }
}

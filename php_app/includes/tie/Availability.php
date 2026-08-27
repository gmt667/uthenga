<?php
/** Deterministic Phase 4 availability and business-rule boundary. */

final class UthengaTieAvailabilityRequest
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieAvailabilityContracts
{
    public static function request(array $input): UthengaTieAvailabilityRequest
    {
        $errors = [];
        $serviceId = self::text($input['service_id'] ?? null, 30);
        if ($serviceId === null || !preg_match('/^[A-Za-z0-9_-]+$/', $serviceId)) $errors['service_id'] = 'A valid service_id is required.';
        $quantity = $input['quantity'] ?? 1;
        if (!filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]])) $errors['quantity'] = 'Quantity must be an integer from 1 to 10000.';
        $start = self::date($input['start_date'] ?? null, 'start_date', $errors);
        $end = self::date($input['end_date'] ?? null, 'end_date', $errors);
        if ($end !== null && $start === null) $errors['end_date'] = 'End date requires a start date.';
        if ($start !== null && $end !== null && $start >= $end) $errors['end_date'] = 'End date must be after start date.';
        if ($errors) throw UthengaTieErrors::validation($errors);
        return new UthengaTieAvailabilityRequest([
            'service_id' => $serviceId, 'quantity' => (int) $quantity, 'start_date' => $start, 'end_date' => $end,
            'origin' => self::text($input['origin'] ?? null, 120), 'destination' => self::text($input['destination'] ?? null, 120),
            // Selects a published option only; the client never provides availability or price.
            'inventory_option' => strtolower(self::text($input['inventory_option'] ?? null, 80) ?: 'standard'),
        ]);
    }
    private static function text($value, int $max): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : substr($value, 0, $max); }
    private static function date($value, string $field, array &$errors): ?string
    {
        $value = self::text($value, 10); if ($value === null) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { $errors[$field] = 'Date must use YYYY-MM-DD.'; return null; }
        return $value;
    }
}

final class UthengaTieAvailabilityEngine
{
    public function validateCandidate(UthengaTieVendorCandidate $candidate, UthengaTieAvailabilityRequest $request): array
    {
        $started = microtime(true); $data = $candidate->toArray(); $violations = []; $warnings = [];
        $vendor = $this->vendorRules($data, $violations);
        $service = $this->serviceRules($data, $violations);
        $dates = $this->dateRules($data, $request->data, $violations);
        $availability = $this->availabilityRules($data, $request->data, $violations, $warnings);
        $capacity = $this->capacityRules($request->data, $availability);
        $constraints = ['minimum_quantity' => 1, 'maximum_quantity' => null, 'requested_quantity' => $request->data['quantity'], 'valid' => $request->data['quantity'] >= 1, 'source' => 'existing_booking_request_contract'];
        if (($data['price']['amount'] ?? null) === null) $warnings[] = $this->rule(true, 'PRICE_UNAVAILABLE', 'warning', 'The published service has no normalized price.');
        return [
            'eligible' => count($violations) === 0, 'service_id' => $data['service_id'], 'category' => $data['category'], 'request' => $request->toArray(),
            'vendor' => $vendor, 'service' => $service, 'dates' => $dates, 'availability' => $availability, 'capacity' => $capacity, 'booking_constraints' => $constraints,
            'violations' => $violations, 'warnings' => $warnings,
            'information' => [$this->rule(true, 'BOOKING_REVALIDATION_REQUIRED', 'info', 'The existing booking system must perform its final authoritative check immediately before creating a booking.')],
            'checked_at' => gmdate('c'), 'duration_ms' => round((microtime(true) - $started) * 1000, 2), 'source' => $data['source'], 'revalidation_required' => true,
        ];
    }

    /** In-memory batch validation prevents N+1 database reads after Phase 3 retrieval. */
    public function validateCandidates(array $candidates, UthengaTieAvailabilityRequest $request): array
    {
        $results = [];
        foreach ($candidates as $candidate) $results[] = $this->validateCandidate($candidate instanceof UthengaTieVendorCandidate ? $candidate : new UthengaTieVendorCandidate($candidate), $request);
        return $results;
    }

    private function vendorRules(array $candidate, array &$violations): array
    {
        $vendor = $candidate['vendor'];
        if (empty($vendor['exists'])) $violations[] = $this->rule(false, 'VENDOR_NOT_FOUND', 'blocking', 'The service vendor does not exist.');
        elseif (empty($vendor['approved'])) $violations[] = $this->rule(false, 'VENDOR_NOT_APPROVED', 'blocking', 'The service vendor is not approved.');
        elseif (($vendor['status'] ?? '') !== 'active') $violations[] = $this->rule(false, 'VENDOR_INACTIVE', 'blocking', 'The service vendor is not active.');
        return ['eligible' => empty($violations), 'status' => $vendor['status'] ?? 'missing', 'source' => 'users'];
    }

    private function serviceRules(array $candidate, array &$violations): array
    {
        $service = $candidate['service'] ?? [];
        if (empty($service['is_active'])) $violations[] = $this->rule(false, 'SERVICE_INACTIVE', 'blocking', 'The service is not active.');
        if (($candidate['category']['code'] ?? '') === 'event' && (($candidate['schedule']['date'] ?? null) < $this->today())) $violations[] = $this->rule(false, 'SERVICE_EXPIRED', 'blocking', 'The event date has passed.');
        return ['eligible' => empty($violations), 'lifecycle_status' => $service['lifecycle_status'] ?? 'unknown', 'source' => 'listings.is_active'];
    }

    private function dateRules(array $candidate, array $request, array &$violations): array
    {
        $type = $candidate['category']['code']; $start = $request['start_date']; $end = $request['end_date'];
        if ($start !== null && $start < $this->today()) $violations[] = $this->rule(false, 'DATE_IN_PAST', 'blocking', 'The requested date is in the past.');
        if ($type === 'event') {
            if ($start === null) $violations[] = $this->rule(false, 'DATE_REQUIRED', 'blocking', 'An event date is required.');
            elseif (($candidate['schedule']['date'] ?? null) !== $start) $violations[] = $this->rule(false, 'EVENT_DATE_MISMATCH', 'blocking', 'The requested date does not match the event date.');
        } elseif ($type === 'accommodation') {
            if ($start === null || $end === null) $violations[] = $this->rule(false, 'DATE_RANGE_REQUIRED', 'blocking', 'Check-in and check-out dates are required for accommodation.');
        } elseif ($type === 'transport') {
            if ($start === null) $violations[] = $this->rule(false, 'DATE_REQUIRED', 'blocking', 'A transport departure date is required.');
            elseif (!in_array((new DateTimeImmutable($start))->format('l'), $candidate['schedule']['days'] ?? [], true)) $violations[] = $this->rule(false, 'SCHEDULE_MISMATCH', 'blocking', 'Transport does not operate on the requested date.');
            $this->routeRules($candidate, $request, $violations);
        } elseif ($type === 'tour') {
            if ($start === null) $violations[] = $this->rule(false, 'DATE_REQUIRED', 'blocking', 'A tour start date is required.');
            elseif (!in_array($start, $candidate['schedule']['dates_available'] ?? [], true)) $violations[] = $this->rule(false, 'DATE_UNAVAILABLE', 'blocking', 'The tour does not operate on the requested date.');
        }
        return ['valid' => empty($violations), 'timezone' => date_default_timezone_get(), 'start_date' => $start, 'end_date' => $end];
    }

    private function routeRules(array $candidate, array $request, array &$violations): void
    {
        if ($request['origin'] === null || $request['destination'] === null) { $violations[] = $this->rule(false, 'ROUTE_CONTEXT_REQUIRED', 'blocking', 'Origin and destination are required for transport validation.'); return; }
        if (!$this->sameText($request['origin'], $candidate['schedule']['origin'] ?? null) || !$this->sameText($request['destination'], $candidate['schedule']['destination'] ?? null)) $violations[] = $this->rule(false, 'ROUTE_MISMATCH', 'blocking', 'The requested route does not match this transport service.');
    }

    private function availabilityRules(array $candidate, array $request, array &$violations, array &$warnings): array
    {
        $base = ['status' => 'unknown', 'validation_status' => 'unknown', 'source' => null, 'checked_at' => gmdate('c'), 'quantity_available' => null, 'quantity_requested' => $request['quantity'], 'freshness' => 'unknown', 'reason' => null];
        if (($candidate['category']['code'] ?? '') !== 'event') {
            $base['reason'] = 'No authoritative inventory source is deployed for this category.';
            $violations[] = $this->rule(false, 'AVAILABILITY_UNKNOWN', 'blocking', $base['reason']);
            return $base;
        }
        $option = null;
        foreach ($candidate['availability']['options'] ?? [] as $entry) if (($entry['code'] ?? '') === $request['inventory_option']) { $option = $entry; break; }
        if ($option === null) { $base['reason'] = 'The requested ticket inventory option is not published.'; $violations[] = $this->rule(false, 'INVENTORY_OPTION_UNKNOWN', 'blocking', $base['reason']); return $base; }
        $quantity = (int) ($option['declared_units'] ?? 0);
        $base['source'] = 'listings.meta.event_inventory'; $base['quantity_available'] = $quantity; $base['freshness'] = $this->freshness($candidate['source']['updated_at'] ?? null);
        if ($base['freshness'] === 'stale') { $base['status'] = 'stale'; $base['validation_status'] = 'stale'; $base['reason'] = 'Configured availability freshness threshold has elapsed.'; $violations[] = $this->rule(false, 'AVAILABILITY_STALE', 'blocking', $base['reason']); }
        elseif ($quantity < $request['quantity']) { $base['status'] = 'unavailable'; $base['validation_status'] = 'validated'; $base['reason'] = 'Requested quantity exceeds the current ticket inventory.'; $violations[] = $this->rule(false, 'CAPACITY_EXCEEDED', 'blocking', $base['reason']); }
        else { $base['status'] = $quantity === $request['quantity'] ? 'limited' : 'available'; $base['validation_status'] = 'validated'; $base['source_type'] = 'legacy_booking_inventory'; $warnings[] = $this->rule(true, 'LEGACY_INVENTORY_SOURCE', 'warning', 'This event uses the existing legacy booking inventory source and must be revalidated during booking.'); }
        return $base;
    }

    private function capacityRules(array $request, array $availability): array { $known = $availability['quantity_available'] !== null; return ['valid' => $known && $availability['quantity_available'] >= $request['quantity'], 'quantity_requested' => $request['quantity'], 'quantity_available' => $availability['quantity_available'], 'source' => $availability['source'], 'validation_status' => $availability['validation_status']]; }
    private function freshness(?string $updatedAt): string { $maxAge = UthengaTieConfig::integer('TIE_AVAILABILITY_MAX_AGE_SECONDS', 0); if ($maxAge <= 0 || $updatedAt === null) return 'not_policy_configured'; try { return (time() - (new DateTimeImmutable($updatedAt))->getTimestamp()) > $maxAge ? 'stale' : 'fresh'; } catch (Throwable $e) { return 'unknown'; } }
    private function today(): string { return (new DateTimeImmutable('now'))->format('Y-m-d'); }
    private function sameText(?string $left, ?string $right): bool { return $left !== null && $right !== null && mb_strtolower(trim($left)) === mb_strtolower(trim($right)); }
    private function rule(bool $passed, string $code, string $severity, string $message): array { return ['passed' => $passed, 'rule_code' => $code, 'severity' => $severity, 'message' => $message]; }
}

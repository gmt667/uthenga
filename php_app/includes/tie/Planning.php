<?php
/** Phase 9: deterministic, approval-driven trip-plan composition. */

final class UthengaTiePlanCreateRequest
{
    public const SCHEMA_VERSION = 'trip-plan-create-request/v1';
    public UthengaTieRecommendationRequest $recommendationRequest;
    public string $title;
    public int $maxDailyActivities;
    public function __construct(UthengaTieRecommendationRequest $recommendationRequest, string $title, int $maxDailyActivities)
    { $this->recommendationRequest = $recommendationRequest; $this->title = $title; $this->maxDailyActivities = $maxDailyActivities; }
}

final class UthengaTieTripPlanResult
{
    public const SCHEMA_VERSION = 'trip-plan-result/v1';
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTiePlanContracts
{
    private const CREATE_FIELDS = ['title', 'max_daily_activities', 'destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km', 'category', 'limit', 'csrf_token'];
    public static function create(array $input, string $userId): UthengaTiePlanCreateRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::CREATE_FIELDS));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported plan field(s): ' . implode(', ', $unknown) . '. Plans derive all marketplace facts server-side.']);
        $title = trim((string) ($input['title'] ?? 'My travel plan'));
        if ($title === '' || strlen($title) > 220) throw UthengaTieErrors::validation(['title' => 'Plan title must be 1-220 characters.']);
        $maximum = $input['max_daily_activities'] ?? UthengaTieConfig::integer('TIE_PLAN_MAX_DAILY_ACTIVITIES', 3);
        if (!filter_var($maximum, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]])) throw UthengaTieErrors::validation(['max_daily_activities' => 'Maximum daily activities must be an integer from 1 to 12.']);
        $recommendationInput = array_intersect_key($input, array_flip(['destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km', 'category', 'limit', 'csrf_token']));
        return new UthengaTiePlanCreateRequest(UthengaTieRecommendationContracts::request($recommendationInput, $userId), $title, (int) $maximum);
    }
    public static function planId(array $input): string
    {
        $id = trim((string) ($input['plan_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9-]{8,32}$/', $id)) throw UthengaTieErrors::validation(['plan_id' => 'A valid plan ID is required.']);
        return $id;
    }
    public static function update(array $input): array
    {
        $allowed = ['plan_id', 'operation', 'service_id', 'replacement_service_id', 'target_date', 'csrf_token']; $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported plan update field(s): ' . implode(', ', $unknown) . '.']);
        self::planId($input); return $input;
    }
    public static function planAction(array $input): string
    {
        $allowed = ['plan_id', 'csrf_token']; $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported plan action field(s): ' . implode(', ', $unknown) . '.']);
        return self::planId($input);
    }
}

final class UthengaTiePlanLifecycle
{
    public const DRAFT = 'DRAFT'; public const UPDATED = 'UPDATED'; public const VALIDATED = 'VALIDATED'; public const READY = 'READY_FOR_APPROVAL'; public const APPROVED = 'APPROVED'; public const EXPORTED = 'EXPORTED'; public const ARCHIVED = 'ARCHIVED';
    public static function transition(string $from, string $to): bool
    {
        $allowed = [self::DRAFT => [self::UPDATED, self::VALIDATED, self::ARCHIVED], self::UPDATED => [self::UPDATED, self::VALIDATED, self::ARCHIVED], self::VALIDATED => [self::UPDATED, self::READY, self::ARCHIVED], self::READY => [self::UPDATED, self::APPROVED, self::ARCHIVED], self::APPROVED => [self::UPDATED, self::EXPORTED, self::ARCHIVED], self::EXPORTED => [self::UPDATED, self::ARCHIVED], self::ARCHIVED => []];
        return in_array($to, $allowed[$from] ?? [], true);
    }
}

/** Only this repository writes TIE draft fields; it never accesses bookings. */
final class UthengaTiePlanRepository
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function create(string $userId, array $plan): void
    {
        $trip = $plan['trip_summary'];
        $stmt = $this->db->prepare('INSERT INTO trip_itineraries (itinerary_code, user_id, title, destination, duration_days, travel_date, budget_mwk, group_size, itinerary_data, ai_generated, tie_lifecycle, tie_plan_version, tie_preferences, tie_diagnostics, tie_provenance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)');
        $stmt->execute([$plan['plan_id'], $userId, $plan['title'], $trip['destination'], $trip['days'], $trip['start_date'], $trip['budget'], $trip['travellers'], json_encode($plan, JSON_UNESCAPED_SLASHES), $plan['lifecycle'], $plan['schema_version'], json_encode($plan['planning_preferences'], JSON_UNESCAPED_SLASHES), json_encode($plan['diagnostics'], JSON_UNESCAPED_SLASHES), json_encode($plan['provenance'], JSON_UNESCAPED_SLASHES)]);
    }
    // itinerary_code is globally unique, so a single plan is addressable by
    // code alone — access (owner vs. collaborator, and which role) is
    // resolved separately by the engine via UthengaTieTripCollaborationService,
    // not baked into this query.
    public function find(string $planId): ?array
    {
        $stmt = $this->db->prepare('SELECT itinerary_data FROM trip_itineraries WHERE itinerary_code = ? LIMIT 1'); $stmt->execute([$planId]);
        $raw = $stmt->fetchColumn(); $plan = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($plan) ? $plan : null;
    }
    // A lightweight summary query for "My Trips" — the flat columns are kept
    // in sync on every create()/replace(), so a list view never needs to
    // decode the full itinerary_data JSON blob for each row.
    public function list(string $userId): array
    {
        $stmt = $this->db->prepare('SELECT itinerary_code, title, destination, duration_days, travel_date, budget_mwk, group_size, tie_lifecycle, updated_at FROM trip_itineraries WHERE user_id = ? ORDER BY updated_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    // The same summary shape as list(), for a caller-supplied set of plan
    // codes — used to fold trips a user collaborates on (but doesn't own)
    // into "My Trips" alongside their own.
    public function listByCodes(array $planIds): array
    {
        if ($planIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        $stmt = $this->db->prepare("SELECT itinerary_code, title, destination, duration_days, travel_date, budget_mwk, group_size, tie_lifecycle, updated_at FROM trip_itineraries WHERE itinerary_code IN ($placeholders)");
        $stmt->execute(array_values($planIds));
        return $stmt->fetchAll();
    }
    // Every real caller already resolves the plan via find() (which throws
    // not_found itself) immediately before calling replace() — this used to
    // also throw not_found when PDO's rowCount() was 0, but MySQL reports
    // affected (changed) rows here, not matched rows: two replace() calls in
    // a row with byte-identical data (e.g. approve()'s internal
    // re-validate()) legitimately report 0 affected rows even though the
    // row exists and the write is a no-op. Caught by real end-to-end testing.
    public function replace(string $planId, array $plan): void
    {
        $stmt = $this->db->prepare('UPDATE trip_itineraries SET itinerary_data = ?, tie_lifecycle = ?, tie_diagnostics = ?, tie_provenance = ? WHERE itinerary_code = ?');
        $stmt->execute([json_encode($plan, JSON_UNESCAPED_SLASHES), $plan['lifecycle'], json_encode($plan['diagnostics'], JSON_UNESCAPED_SLASHES), json_encode($plan['provenance'], JSON_UNESCAPED_SLASHES), $planId]);
    }
}

final class UthengaTieTimelineComposer
{
    public function compose(array $recommendations, array $trip, int $maximum): array
    {
        $days = $this->days($trip['start_date'] ?? null, $trip['end_date'] ?? null); $activities = []; $selected = [];
        $byCategory = [];
        foreach ($recommendations as $item) $byCategory[(string) ($item['candidate']['category']['code'] ?? 'other')][] = $item;
        $this->addFixed($activities, $selected, $byCategory['transport'] ?? [], 0, '09:00', '12:00', 'TRAVEL_SEGMENT', $days);
        $this->addFixed($activities, $selected, $byCategory['accommodation'] ?? [], 0, '15:00', '16:00', 'ACCOMMODATION_CHECK_IN', $days);
        $pool = array_merge($byCategory['tour'] ?? [], $byCategory['activity'] ?? [], $byCategory['event'] ?? [], $byCategory['experience'] ?? []);
        $perDay = array_fill(0, count($days), 0); $slot = 0;
        foreach ($pool as $item) {
            if (count($selected) >= count($days) * $maximum) break;
            $day = $slot % count($days); if ($perDay[$day] >= $maximum) { $slot++; continue; }
            $startHour = $perDay[$day] === 0 ? '10:00' : '14:00'; $endHour = $perDay[$day] === 0 ? '12:00' : '16:00';
            $activities[] = $this->activity($item, $days[$day], $startHour, $endHour, 'ACTIVITY_TIME_PROPOSED'); $selected[(string) $item['candidate']['service_id']] = true; $perDay[$day]++; $slot++;
        }
        usort($activities, static fn(array $a, array $b): int => strcmp($a['start_at'], $b['start_at']) ?: strcmp($a['service_id'], $b['service_id']));
        foreach ($activities as $index => &$activity) $activity['sequence'] = $index + 1; unset($activity);
        return $activities;
    }
    private function addFixed(array &$activities, array &$selected, array $items, int $day, string $start, string $end, string $kind, array $days): void
    { if ($items !== []) { $activities[] = $this->activity($items[0], $days[$day], $start, $end, $kind); $selected[(string) $items[0]['candidate']['service_id']] = true; } }
    private function activity(array $item, string $day, string $start, string $end, string $timing): array
    {
        $candidate = $item['candidate']; return ['activity_id' => 'plan-item-' . bin2hex(random_bytes(6)), 'service_id' => (string) $candidate['service_id'], 'title' => (string) $candidate['title'], 'category' => (string) ($candidate['category']['code'] ?? 'other'), 'start_at' => $day . 'T' . $start . ':00', 'end_at' => $day . 'T' . $end . ':00', 'timing_status' => $timing, 'location' => array_intersect_key((array) ($candidate['location'] ?? []), array_flip(['display_name', 'city', 'district', 'region', 'country'])), 'price' => array_intersect_key((array) ($candidate['price'] ?? []), array_flip(['amount', 'currency', 'unit'])), 'recommendation_score' => (float) ($item['recommendation_score']['weighted_score'] ?? 0), 'eligibility' => ['eligible' => (bool) ($item['eligibility']['eligible'] ?? false), 'checked_at' => $item['eligibility']['checked_at'] ?? null], 'status' => 'PROPOSED'];
    }
    private function days(?string $start, ?string $end): array
    {
        $first = $start !== null ? new DateTimeImmutable($start) : new DateTimeImmutable('today'); $last = $end !== null ? new DateTimeImmutable($end) : $first;
        $dates = []; for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) $dates[] = $date->format('Y-m-d'); return $dates;
    }
}

final class UthengaTiePlanConstraintEvaluator
{
    public function evaluate(array $activities, array $trip, int $maximum): array
    {
        $issues = []; $seen = []; $daily = [];
        foreach ($activities as $activity) {
            $id = (string) $activity['service_id']; if (isset($seen[$id])) $issues[] = $this->issue('DUPLICATE_SERVICE', 'blocking', 'A service appears more than once in the plan.'); $seen[$id] = true;
            $day = substr((string) $activity['start_at'], 0, 10); $daily[$day] = ($daily[$day] ?? 0) + 1;
            if (($activity['start_at'] ?? '') >= ($activity['end_at'] ?? '')) $issues[] = $this->issue('INVALID_TIMELINE', 'blocking', 'An activity has an invalid time range.');
        }
        foreach ($daily as $day => $count) if ($count > $maximum + 2) $issues[] = $this->issue('MAXIMUM_DAILY_ACTIVITIES', 'warning', 'The plan exceeds the preferred daily activity limit on ' . $day . '.');
        if (!array_filter($activities, static fn(array $item): bool => $item['category'] === 'accommodation')) $issues[] = $this->issue('MISSING_ACCOMMODATION', 'warning', 'No validated accommodation was available for this proposal.');
        return $issues;
    }
    private function issue(string $code, string $severity, string $message): array { return ['code' => $code, 'severity' => $severity, 'message' => $message]; }
}

final class UthengaTieTripPlanningEngine implements UthengaTiePlanModule
{
    private ?UthengaTiePlanRepository $repository; private UthengaTieTravelContextService $context; private UthengaTieRecommendationModule $recommendation; private UthengaTieQueryModule $query; private UthengaTieAvailabilityModule $availability; private UthengaTieBudgetModule $budget; private UthengaTieConflictModule $conflicts; private UthengaTieTimelineComposer $timeline; private UthengaTieTripCollaborationService $collaboration;
    public function __construct(?PDO $db, UthengaTieTravelContextService $context, UthengaTieRecommendationModule $recommendation, UthengaTieQueryModule $query, UthengaTieAvailabilityModule $availability, UthengaTieBudgetModule $budget, UthengaTieConflictModule $conflicts, UthengaTieTripCollaborationService $collaboration)
    { $this->repository = $db instanceof PDO ? new UthengaTiePlanRepository($db) : null; $this->context = $context; $this->recommendation = $recommendation; $this->query = $query; $this->availability = $availability; $this->budget = $budget; $this->conflicts = $conflicts; $this->timeline = new UthengaTieTimelineComposer(); $this->collaboration = $collaboration; }
    public function create(UthengaTiePlanCreateRequest $request, string $userId): UthengaTieTripPlanResult
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database'); $started = microtime(true);
        $context = $this->context->build($userId, $request->recommendationRequest->contextRequest); $recommendation = $this->recommendation->rank($request->recommendationRequest, $context)->toArray();
        $trip = $request->recommendationRequest->contextRequest->trip->data; $activities = $this->timeline->compose($recommendation['recommendations'], $trip, $request->maxDailyActivities); $budget = $this->budget->summarize($activities, new UthengaTieTripRequest($trip)); $conflicts = $this->conflicts->analyze($activities, $trip, $request->maxDailyActivities, $budget); $issues = $conflicts['issues'];
        $plan = ['schema_version' => UthengaTieTripPlanResult::SCHEMA_VERSION, 'plan_id' => 'TIEPLAN-' . strtoupper(bin2hex(random_bytes(8))), 'title' => $request->title, 'lifecycle' => UthengaTiePlanLifecycle::DRAFT, 'request' => $request->recommendationRequest->toArray(), 'trip_summary' => ['destination' => $trip['destination'], 'origin' => $trip['origin'], 'start_date' => $trip['start_date'], 'end_date' => $trip['end_date'], 'days' => count(array_unique(array_map(static fn(array $a): string => substr($a['start_at'], 0, 10), $activities))) ?: 1, 'travellers' => $trip['travellers'], 'budget' => $trip['budget'], 'currency' => $trip['currency']], 'daily_itinerary' => $this->group($activities), 'activities' => $activities, 'budget_analysis' => $budget, 'conflict_analysis' => $conflicts, 'explanation' => $this->explanation($activities, $issues, $budget), 'warnings' => array_values(array_filter($issues, static fn(array $i): bool => $i['severity'] !== 'blocking')), 'diagnostics' => ['recommendations_consumed' => count($recommendation['recommendations']), 'conflicts_detected' => count($issues), 'optimization_actions' => ['ranked_services_selected_before_lower_ranked_services', 'activities_distributed_across_trip_days'], 'duration_ms' => round((microtime(true) - $started) * 1000, 2)], 'planning_preferences' => ['max_daily_activities' => $request->maxDailyActivities, 'minimum_connection_minutes' => UthengaTieConfig::integer('TIE_PLAN_MIN_CONNECTION_MINUTES', 30)], 'provenance' => ['recommendations' => UthengaTieRecommendationResult::SCHEMA_VERSION, 'travel_context' => 'travel-context/v1', 'availability' => 'phase-4-validation', 'budget' => UthengaTieBudgetService::VERSION, 'conflicts' => UthengaTieConflictService::VERSION, 'marketplace_facts' => 'server_authoritative', 'booking_effect' => 'none']];
        $this->repository->create($userId, $plan); return new UthengaTieTripPlanResult($plan);
    }
    // "My Trips" summaries. Status is derived only from two real, always-in-
    // sync fields — tie_lifecycle and travel_date/duration_days — never
    // invented: a plan isn't a real dated trip (upcoming/active/completed)
    // until it's actually been approved.
    public function list(string $userId): array
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $owned = array_map(static fn(array $row): array => ['row' => $row, 'role' => 'owner'], $this->repository->list($userId));
        $sharedRoles = $this->collaboration->sharedPlanRoles($userId);
        $sharedRows = $sharedRoles !== [] ? $this->repository->listByCodes(array_keys($sharedRoles)) : [];
        $shared = array_map(static fn(array $row): array => ['row' => $row, 'role' => $sharedRoles[(string) $row['itinerary_code']] ?? 'viewer'], $sharedRows);
        $trips = array_map(function (array $entry) use ($today): array {
            $row = $entry['row']; $lifecycle = (string) $row['tie_lifecycle'];
            return [
                'plan_id' => (string) $row['itinerary_code'], 'title' => (string) $row['title'], 'destination' => (string) $row['destination'],
                'duration_days' => (int) $row['duration_days'], 'travel_date' => $row['travel_date'],
                'budget' => $row['budget_mwk'] !== null ? (float) $row['budget_mwk'] : null, 'travellers' => (int) $row['group_size'],
                'lifecycle' => $lifecycle, 'status' => $this->displayStatus($lifecycle, $row['travel_date'] !== null ? (string) $row['travel_date'] : null, (int) $row['duration_days'], $today),
                'updated_at' => $row['updated_at'], 'role' => $entry['role'],
            ];
        }, array_merge($owned, $shared));
        usort($trips, static fn(array $a, array $b): int => strcmp((string) $b['updated_at'], (string) $a['updated_at']));
        return ['schema_version' => 'tie-trip-plan-list/v1', 'trips' => $trips];
    }
    private function displayStatus(string $lifecycle, ?string $travelDate, int $durationDays, DateTimeImmutable $today): string
    {
        if ($lifecycle === UthengaTiePlanLifecycle::ARCHIVED) return 'archived';
        if (!in_array($lifecycle, [UthengaTiePlanLifecycle::APPROVED, UthengaTiePlanLifecycle::EXPORTED], true)) return 'draft';
        if ($travelDate === null) return 'approved';
        $start = new DateTimeImmutable($travelDate, new DateTimeZone('UTC'));
        $end = $start->modify('+' . max(0, $durationDays - 1) . ' days');
        if ($today < $start) return 'upcoming';
        if ($today > $end) return 'completed';
        return 'active';
    }
    public function view(string $planId, string $userId): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->find($planId, $userId)); }
    public function update(string $planId, string $userId, array $input): UthengaTieTripPlanResult
    {
        $plan = $this->find($planId, $userId); $this->requireWriteAccess($planId, $userId); if ($plan['lifecycle'] === UthengaTiePlanLifecycle::ARCHIVED) throw UthengaTieErrors::validation(['plan' => 'Archived plans cannot be edited.']);
        $operation = strtolower(trim((string) ($input['operation'] ?? ''))); $serviceId = trim((string) ($input['service_id'] ?? ''));
        if (!in_array($operation, ['remove', 'reorder', 'replace'], true) || $serviceId === '') throw UthengaTieErrors::validation(['operation' => 'Use remove, reorder, or replace with a planned service ID.']);
        $matches = array_keys(array_filter($plan['activities'], static fn(array $activity): bool => (string) $activity['service_id'] === $serviceId));
        if ($matches === []) throw UthengaTieErrors::validation(['service_id' => 'The service is not part of this plan.']);
        if ($operation === 'remove') $plan['activities'] = array_values(array_filter($plan['activities'], static fn(array $activity): bool => (string) $activity['service_id'] !== $serviceId));
        if ($operation === 'reorder') {
            $date = trim((string) ($input['target_date'] ?? '')); if (!$this->withinTrip($date, $plan['trip_summary'])) throw UthengaTieErrors::validation(['target_date' => 'Target date must be within the plan dates.']);
            foreach ($matches as $index) { $plan['activities'][$index]['start_at'] = $date . substr($plan['activities'][$index]['start_at'], 10); $plan['activities'][$index]['end_at'] = $date . substr($plan['activities'][$index]['end_at'], 10); $plan['activities'][$index]['timing_status'] = 'USER_REORDERED_PROPOSAL'; }
        }
        if ($operation === 'replace') {
            $replacement = trim((string) ($input['replacement_service_id'] ?? '')); $fresh = $this->freshRecommendations($plan, $userId); $entry = null;
            foreach ($fresh as $candidate) if ((string) ($candidate['candidate']['service_id'] ?? '') === $replacement) { $entry = $candidate; break; }
            if ($entry === null) throw UthengaTieErrors::validation(['replacement_service_id' => 'Replacement must be in the current validated recommendation set.']);
            foreach ($matches as $index) { $old = $plan['activities'][$index]; $new = (new UthengaTieTimelineComposer())->compose([$entry], $plan['trip_summary'], 1)[0]; $new['activity_id'] = $old['activity_id']; $new['start_at'] = $old['start_at']; $new['end_at'] = $old['end_at']; $new['timing_status'] = 'REPLACED_FROM_CURRENT_RECOMMENDATION'; $plan['activities'][$index] = $new; }
        }
        usort($plan['activities'], static fn(array $a, array $b): int => strcmp($a['start_at'], $b['start_at']) ?: strcmp($a['service_id'], $b['service_id'])); foreach ($plan['activities'] as $index => &$activity) $activity['sequence'] = $index + 1; unset($activity);
        $plan['daily_itinerary'] = $this->group($plan['activities']); $plan['lifecycle'] = UthengaTiePlanLifecycle::UPDATED; $plan['diagnostics']['last_edit'] = ['operation' => $operation, 'at' => gmdate('c')]; $this->repository->replace($planId, $plan);
        return $this->validate($planId, $userId);
    }
    public function validate(string $planId, string $userId): UthengaTieTripPlanResult
    {
        $plan = $this->find($planId, $userId); $this->requireWriteAccess($planId, $userId); $trip = $plan['trip_summary']; $budget = $this->budget->summarize($plan['activities'], new UthengaTieTripRequest($trip)); $conflicts = $this->conflicts->analyze($plan['activities'], $trip, (int) ($plan['planning_preferences']['max_daily_activities'] ?? 3), $budget); $issues = $conflicts['issues'];
        foreach ($plan['activities'] as $activity) {
            $candidate = $this->query->serviceForValidation((string) $activity['service_id']);
            if ($candidate === null) { $issues[] = ['code' => 'SERVICE_NOT_FOUND', 'severity' => 'blocking', 'message' => 'A planned service is no longer available in the marketplace.']; continue; }
            $check = $this->availability->validate(new UthengaTieAvailabilityRequest(['service_id' => $candidate->data['service_id'], 'quantity' => $trip['travellers'], 'start_date' => $trip['start_date'], 'end_date' => $trip['end_date'], 'origin' => $trip['origin'], 'destination' => $trip['destination'], 'inventory_option' => 'standard']));
            if (($check['eligible'] ?? false) !== true) $issues[] = ['code' => 'AVAILABILITY_REVALIDATION_FAILED', 'severity' => 'blocking', 'message' => 'A planned service no longer passes availability validation.'];
        }
        $conflicts['issues'] = $issues; $conflicts['summary'] = ['blocking' => count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'blocking')), 'warnings' => count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'warning'))]; $blocking = array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'blocking'); $target = $blocking === [] ? UthengaTiePlanLifecycle::VALIDATED : UthengaTiePlanLifecycle::UPDATED;
        if (UthengaTiePlanLifecycle::transition($plan['lifecycle'], $target)) $plan['lifecycle'] = $target; $plan['budget_analysis'] = $budget; $plan['conflict_analysis'] = $conflicts; $plan['explanation'] = $this->explanation($plan['activities'], $issues, $budget); $plan['warnings'] = array_values(array_filter($issues, static fn(array $i): bool => $i['severity'] !== 'blocking')); $plan['diagnostics']['validation'] = ['checked_at' => gmdate('c'), 'blocking_conflicts' => count($blocking), 'conflicts_detected' => count($issues)]; $this->repository->replace($planId, $plan); UthengaTieMetrics::record('plan_conflicts', count($issues), UthengaTieObservability::requestId(), ['module' => 'planning', 'feature' => 'plans', 'status' => $blocking === [] ? 'valid' : 'conflict']); return new UthengaTieTripPlanResult($plan);
    }
    public function approve(string $planId, string $userId): UthengaTieTripPlanResult
    {
        $this->requireWriteAccess($planId, $userId);
        $validated = $this->validate($planId, $userId)->toArray(); if ($validated['lifecycle'] !== UthengaTiePlanLifecycle::VALIDATED) throw UthengaTieErrors::validation(['plan' => 'Only a fully validated plan can be approved.']);
        $validated['lifecycle'] = UthengaTiePlanLifecycle::READY; $this->repository->replace($planId, $validated); $validated['lifecycle'] = UthengaTiePlanLifecycle::APPROVED; $validated['diagnostics']['approved_at'] = gmdate('c'); $this->repository->replace($planId, $validated); return new UthengaTieTripPlanResult($validated);
    }
    public function export(string $planId, string $userId): array
    { $plan = $this->find($planId, $userId); if ($plan['lifecycle'] === UthengaTiePlanLifecycle::APPROVED && UthengaTiePlanLifecycle::transition($plan['lifecycle'], UthengaTiePlanLifecycle::EXPORTED)) { $this->requireWriteAccess($planId, $userId); $plan['lifecycle'] = UthengaTiePlanLifecycle::EXPORTED; $this->repository->replace($planId, $plan); } return ['schema_version' => 'trip-plan-export/v1', 'format' => 'json', 'plan' => $plan, 'booking_effect' => 'none']; }
    private function find(string $planId, string $userId): array
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        if ($this->collaboration->accessFor($planId, $userId) === null) throw new UthengaTieException('not_found', 'The requested trip plan was not found.', 404);
        $plan = $this->repository->find($planId); if ($plan === null) throw new UthengaTieException('not_found', 'The requested trip plan was not found.', 404); return $plan;
    }
    private function requireWriteAccess(string $planId, string $userId): void
    {
        if (!$this->collaboration->canWrite($planId, $userId)) throw UthengaTieErrors::authorization();
    }
    private function withinTrip(string $date, array $trip): bool { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false; return ($trip['start_date'] === null || $date >= $trip['start_date']) && ($trip['end_date'] === null || $date <= $trip['end_date']); }
    private function freshRecommendations(array $plan, string $userId): array
    {
        $saved = $plan['request']['trip'] ?? null; if (!is_array($saved)) return [];
        $saved['user_id'] = $userId; $trip = new UthengaTieTripRequest($saved); $request = new UthengaTieRecommendationRequest(new UthengaTieContextBuildRequest($trip, null, $plan['request']['nearby_radius_km'] ?? null), $plan['request']['category'] ?? null, (int) ($plan['request']['limit'] ?? 10));
        return $this->recommendation->rank($request, $this->context->build($userId, $request->contextRequest))->toArray()['recommendations'];
    }
    private function group(array $activities): array { $days = []; foreach ($activities as $activity) { $day = substr($activity['start_at'], 0, 10); $days[$day][] = $activity; } return array_map(static fn(array $items, string $day): array => ['date' => $day, 'activities' => $items], $days, array_keys($days)); }
    private function explanation(array $activities, array $issues, array $budget): array { return ['summary' => count($activities) . ' proposed activity or activities were composed from validated recommendations.', 'facts' => ['ranking_used' => true, 'availability_used' => true, 'budget_status' => $budget['status'] ?? 'BUDGET_NOT_PROVIDED', 'estimated_total' => $budget['estimated_total'] ?? null, 'booking_created' => false], 'attention_needed' => array_values(array_map(static fn(array $issue): string => $issue['message'], array_filter($issues, static fn(array $issue): bool => $issue['severity'] !== 'info')))]; }
}

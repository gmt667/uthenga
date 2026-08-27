<?php
/** Phase 13: explicit deterministic plan conflict analysis and resolutions. */

final class UthengaTieConflictService implements UthengaTieConflictModule
{
    public const VERSION = 'plan-conflicts/v1';
    public function analyze(array $activities, array $trip, int $maximumDailyActivities, array $budget): array
    {
        $issues = []; $seen = []; $daily = []; $ordered = $activities;
        usort($ordered, static fn(array $a, array $b): int => strcmp((string) ($a['start_at'] ?? ''), (string) ($b['start_at'] ?? '')));
        foreach ($ordered as $index => $activity) {
            $serviceId = (string) ($activity['service_id'] ?? '');
            if ($serviceId !== '' && isset($seen[$serviceId])) $issues[] = $this->issue('DUPLICATE_SERVICE', 'blocking', 'A service appears more than once in the plan.', 'Remove or replace the duplicate service.');
            $seen[$serviceId] = true; $day = substr((string) ($activity['start_at'] ?? ''), 0, 10); $daily[$day] = ($daily[$day] ?? 0) + 1;
            $start = $this->time($activity['start_at'] ?? null); $end = $this->time($activity['end_at'] ?? null);
            if ($start === null || $end === null || $start >= $end) $issues[] = $this->issue('INVALID_TIMELINE', 'blocking', 'An activity has an invalid time range.', 'Choose a valid start and end time.');
            if ($index > 0 && $start !== null) {
                $previous = $ordered[$index - 1]; $previousEnd = $this->time($previous['end_at'] ?? null);
                if ($previousEnd !== null && $start < $previousEnd) $issues[] = $this->issue('SCHEDULE_OVERLAP', 'blocking', 'Two planned activities overlap.', 'Move, remove, or replace one of the overlapping activities.');
                elseif ($previousEnd !== null && $this->differentLocations($previous, $activity) && (($start->getTimestamp() - $previousEnd->getTimestamp()) / 60) < $this->minimumConnection()) $issues[] = $this->issue('INSUFFICIENT_CONNECTION_TIME', 'warning', 'Two activities at different listed locations have less than the preferred connection time.', 'Allow more connection time or confirm the locations before booking.');
            }
            if ($trip['start_date'] !== null && $day !== '' && $day < $trip['start_date']) $issues[] = $this->issue('ACTIVITY_BEFORE_TRIP', 'blocking', 'An activity is scheduled before the trip start date.', 'Move the activity within the trip dates.');
            if ($trip['end_date'] !== null && $day !== '' && $day > $trip['end_date']) $issues[] = $this->issue('ACTIVITY_AFTER_TRIP', 'blocking', 'An activity is scheduled after the trip end date.', 'Move the activity within the trip dates.');
        }
        foreach ($daily as $day => $count) if ($count > $maximumDailyActivities + 2) $issues[] = $this->issue('MAXIMUM_DAILY_ACTIVITIES', 'warning', 'The plan exceeds the preferred daily activity limit on ' . $day . '.', 'Reduce activities or distribute them across more days.');
        if ($this->requiresAccommodation($trip) && !array_filter($activities, static fn(array $item): bool => ($item['category'] ?? '') === 'accommodation')) $issues[] = $this->issue('MISSING_ACCOMMODATION', 'warning', 'No validated accommodation is included for this multi-day trip.', 'Add or replace with an accommodation recommendation.');
        if (($budget['status'] ?? '') === 'OVER_BUDGET') $issues[] = $this->issue('BUDGET_EXCEEDED', 'blocking', 'The estimated plan cost exceeds the stated budget.', 'Reduce costs, change preferences, or increase the budget.');
        foreach (($budget['warnings'] ?? []) as $warning) if (($warning['code'] ?? '') === 'PRICE_UNAVAILABLE') $issues[] = $this->issue('INCOMPLETE_PRICE_ESTIMATE', 'warning', 'One or more selected services have no normalized price.', 'Review those listings before relying on the total estimate.');
        return ['schema_version' => self::VERSION, 'issues' => $this->unique($issues), 'summary' => ['blocking' => count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'blocking')), 'warnings' => count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'warning'))], 'policy' => ['minimum_connection_minutes' => $this->minimumConnection(), 'maximum_daily_activities' => $maximumDailyActivities], 'provenance' => ['timeline' => 'trip-plan-result/v1', 'budget' => UthengaTieBudgetService::VERSION, 'arithmetic' => 'deterministic_server_side']];
    }
    private function requiresAccommodation(array $trip): bool { return !empty($trip['start_date']) && !empty($trip['end_date']) && $trip['start_date'] !== $trip['end_date']; }
    private function time($value): ?DateTimeImmutable { try { return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null; } catch (Throwable $error) { return null; } }
    private function differentLocations(array $a, array $b): bool { $one = strtolower(trim((string) ($a['location']['display_name'] ?? ''))); $two = strtolower(trim((string) ($b['location']['display_name'] ?? ''))); return $one !== '' && $two !== '' && $one !== $two; }
    private function minimumConnection(): int { return max(0, UthengaTieConfig::integer('TIE_PLAN_MIN_CONNECTION_MINUTES', 30)); }
    private function issue(string $code, string $severity, string $message, string $resolution): array { return ['code' => $code, 'severity' => $severity, 'message' => $message, 'resolution' => $resolution]; }
    private function unique(array $issues): array { $seen = []; return array_values(array_filter($issues, static function (array $issue) use (&$seen): bool { $key = $issue['code'] . '|' . $issue['message']; if (isset($seen[$key])) return false; $seen[$key] = true; return true; })); }
}

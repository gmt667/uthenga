<?php
/** Phase 12: deterministic, policy-configured trip budget analysis. */

final class UthengaTieBudgetService implements UthengaTieBudgetModule
{
    public const VERSION = 'budget-intelligence/v1';

    public function summarize(array $items, UthengaTieTripRequest $request): array
    {
        $trip = $request->data; $currency = (string) ($trip['currency'] ?? APP_CURRENCY);
        $days = $this->days($trip['start_date'] ?? null, $trip['end_date'] ?? null);
        $travellers = max(1, (int) ($trip['travellers'] ?? 1)); $warnings = []; $lines = [];
        $totals = ['transport' => 0.0, 'accommodation' => 0.0, 'activities' => 0.0];
        foreach ($items as $item) {
            $normal = $this->item($item); $category = $normal['category']; $price = $normal['price'];
            if (!is_numeric($price['amount'] ?? null)) { $warnings[] = $this->warning('PRICE_UNAVAILABLE', 'A selected service has no normalized price and is excluded from the estimate.'); continue; }
            if (($price['currency'] ?? $currency) !== $currency) { $warnings[] = $this->warning('CURRENCY_MISMATCH', 'A selected service uses a different currency and is excluded from this estimate.'); continue; }
            $multiplier = $this->multiplier($category, (string) ($price['unit'] ?? 'service'), $days, $travellers);
            $amount = round((float) $price['amount'] * $multiplier, 2); $bucket = $this->bucket($category); $totals[$bucket] += $amount;
            $lines[] = ['service_id' => $normal['service_id'], 'title' => $normal['title'], 'category' => $category, 'unit_price' => round((float) $price['amount'], 2), 'unit' => (string) ($price['unit'] ?? 'service'), 'quantity' => $multiplier, 'estimated_total' => $amount, 'source' => 'published_marketplace_price'];
        }
        $mealPerTravellerDay = max(0.0, UthengaTieConfig::decimal('TIE_BUDGET_MEAL_ALLOWANCE_PER_TRAVELLER_DAY', 0.0));
        $meal = round($mealPerTravellerDay * $travellers * $days, 2);
        if ($mealPerTravellerDay <= 0) $warnings[] = $this->warning('MEAL_ALLOWANCE_NOT_CONFIGURED', 'Meals are not included because no meal allowance policy is configured.');
        $subtotal = array_sum($totals) + $meal;
        $taxRate = max(0.0, UthengaTieConfig::decimal('TIE_BUDGET_TAX_RATE_PERCENT', 0.0));
        $tax = round($subtotal * ($taxRate / 100), 2);
        $contingencyRate = max(0.0, UthengaTieConfig::decimal('TIE_BUDGET_CONTINGENCY_RATE_PERCENT', 0.0));
        $contingency = round(($subtotal + $tax) * ($contingencyRate / 100), 2);
        $estimated = round($subtotal + $tax + $contingency, 2); $budget = $trip['budget'] ?? null;
        $remaining = is_numeric($budget) ? round((float) $budget - $estimated, 2) : null;
        return [
            'schema_version' => self::VERSION, 'currency' => $currency, 'trip_days' => $days, 'travellers' => $travellers,
            'components' => [
                $this->component('transport', $totals['transport'], true), $this->component('accommodation', $totals['accommodation'], true),
                $this->component('activities', $totals['activities'], true), $this->component('meals', $meal, $mealPerTravellerDay > 0, $mealPerTravellerDay > 0 ? 'configured_daily_allowance' : 'not_configured'),
                $this->component('taxes', $tax, $taxRate > 0, $taxRate > 0 ? 'configured_percentage' : 'not_configured'),
                $this->component('contingency', $contingency, $contingencyRate > 0, $contingencyRate > 0 ? 'configured_percentage' : 'not_configured'),
            ],
            'line_items' => $lines, 'estimated_total' => $estimated, 'budget' => is_numeric($budget) ? round((float) $budget, 2) : null,
            'remaining_budget' => $remaining, 'status' => $budget === null || $budget === '' ? 'BUDGET_NOT_PROVIDED' : ($remaining >= 0 ? 'WITHIN_BUDGET' : 'OVER_BUDGET'),
            'shortfall' => $remaining !== null && $remaining < 0 ? abs($remaining) : 0.0, 'warnings' => $this->uniqueWarnings($warnings),
            'policy' => ['meal_allowance_per_traveller_day' => $mealPerTravellerDay, 'tax_rate_percent' => $taxRate, 'contingency_rate_percent' => $contingencyRate],
            'provenance' => ['marketplace_prices' => 'published_listing_prices', 'non_marketplace_allowances' => 'configured_policy', 'arithmetic' => 'deterministic_server_side'],
        ];
    }

    private function item(array $item): array
    {
        $candidate = is_array($item['candidate'] ?? null) ? $item['candidate'] : $item;
        return ['service_id' => (string) ($candidate['service_id'] ?? $item['service_id'] ?? ''), 'title' => (string) ($candidate['title'] ?? $item['title'] ?? 'Uthenga service'), 'category' => (string) ($candidate['category']['code'] ?? $item['category'] ?? 'other'), 'price' => is_array($candidate['price'] ?? null) ? $candidate['price'] : (is_array($item['price'] ?? null) ? $item['price'] : [])];
    }
    private function days(?string $start, ?string $end): int
    {
        if ($start === null || $end === null) return 1;
        try { $nights = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days; return max(1, (int) $nights); } catch (Throwable $error) { return 1; }
    }
    private function multiplier(string $category, string $unit, int $days, int $travellers): int
    {
        $unit = strtolower($unit);
        if ($category === 'accommodation' || $unit === 'night') return $days;
        if (in_array($unit, ['person', 'ticket', 'seat', 'traveller'], true) || in_array($category, ['transport', 'event', 'tour', 'activity'], true)) return $travellers;
        return 1;
    }
    private function bucket(string $category): string { return $category === 'transport' ? 'transport' : ($category === 'accommodation' ? 'accommodation' : 'activities'); }
    private function component(string $code, float $amount, bool $included, string $source = 'marketplace_prices'): array { return ['code' => $code, 'amount' => round($amount, 2), 'included' => $included, 'source' => $source]; }
    private function warning(string $code, string $message): array { return ['code' => $code, 'message' => $message]; }
    private function uniqueWarnings(array $warnings): array { $seen = []; return array_values(array_filter($warnings, static function (array $warning) use (&$seen): bool { $code = $warning['code']; if (isset($seen[$code])) return false; $seen[$code] = true; return true; })); }
}

<?php
/** Phase 17: read-only journey views derived from confirmed marketplace bookings. */

final class UthengaTieJourneyRepository
{
    public function __construct(private PDO $db) {}
    public function current(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT id, booking_code, listing_id, listing_title, listing_type, details, booking_status, payment_status, currency, total_price, booked_at, confirmed_at, cancelled_at FROM bookings WHERE customer_id=? AND LOWER(booking_status) NOT IN ('cancelled','refunded') AND LOWER(payment_status) IN ('paid','success') ORDER BY COALESCE(confirmed_at, booked_at) DESC LIMIT 100"); $stmt->execute([$userId]);
        $journeys = []; foreach ($stmt->fetchAll() as $row) $journeys[] = $this->normalize($row); return ['schema_version' => 'journey-dashboard/v1', 'journeys' => $journeys, 'counts' => array_count_values(array_column($journeys, 'status')), 'provenance' => ['bookings' => 'marketplace_authoritative', 'tracking' => 'not_available']];
    }
    public function journey(string $journeyId, string $userId): ?UthengaTieJourneyState
    {
        if (!preg_match('/^JRN-([A-Za-z0-9_-]{1,30})$/', $journeyId, $matches)) return null; $stmt = $this->db->prepare('SELECT id, booking_code, listing_id, listing_title, listing_type, details, booking_status, payment_status, currency, total_price, booked_at, confirmed_at, cancelled_at FROM bookings WHERE id=? AND customer_id=? LIMIT 1'); $stmt->execute([$matches[1], $userId]); $row = $stmt->fetch(); return is_array($row) ? new UthengaTieJourneyState($this->normalize($row)) : null;
    }
    private function normalize(array $booking): array
    {
        $details = json_decode((string) ($booking['details'] ?? '{}'), true) ?: []; $start = $details['check_in_date'] ?? $details['tour_date'] ?? $details['travel_date'] ?? null; $end = $details['check_out_date'] ?? $start; $today = gmdate('Y-m-d');
        $status = $start === null ? 'UPCOMING' : ($end < $today ? 'COMPLETED' : ($start <= $today && $end >= $today ? 'CURRENT' : 'UPCOMING'));
        return ['journey_id' => 'JRN-' . $booking['id'], 'booking_id' => (string) $booking['id'], 'booking_code' => (string) ($booking['booking_code'] ?? $booking['id']), 'title' => (string) $booking['listing_title'], 'category' => (string) $booking['listing_type'], 'status' => $status, 'start_date' => $start, 'end_date' => $end, 'booking_status' => (string) $booking['booking_status'], 'payment_status' => (string) $booking['payment_status'], 'timeline' => [['status' => 'BOOKED', 'at' => $booking['confirmed_at'] ?? $booking['booked_at'], 'message' => 'Booking confirmed by Uthenga marketplace.'], ['status' => $status, 'at' => $start, 'message' => $status === 'CURRENT' ? 'Journey is currently active.' : ($status === 'COMPLETED' ? 'Journey date has passed.' : 'Journey is upcoming.')]], 'live_tracking' => ['available' => false, 'reason' => 'No vendor live-position provider is configured.'], 'provenance' => ['booking' => 'marketplace_authoritative', 'journey_state' => 'derived_read_only']];
    }
}

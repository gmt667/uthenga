<?php
/**
 * Customer-facing "Bookings" — a real, read-only view over the marketplace
 * `bookings` table (event/accommodation/tour/transport, already unifying
 * booking_status and payment_status as separate fields) merged with this
 * customer's Quick Taxi payment history (a structurally different model:
 * tie_transport_payments/sessions/runs), tagged by `source` so the two are
 * never conflated into a fake shared schema. Cancellation reuses the
 * existing, already-working legacy `cancel_booking` action in
 * request_api.php directly from the frontend (same PHP session + CSRF
 * token as every TIE endpoint) — this service never writes bookings.
 */
final class UthengaTieCustomerBookingsService
{
    public function __construct(private ?PDO $db)
    {
    }

    public function list(string $customerId, string $status = ''): array
    {
        $this->db();
        $marketplace = $this->db->prepare(
            'SELECT id, listing_type, listing_title, listing_image, booking_date, booked_at, currency, total_price, payment_status, booking_status, confirmed_at, cancelled_at, completed_at
             FROM bookings WHERE customer_id = ? AND deleted_at IS NULL ORDER BY booked_at DESC LIMIT 200'
        );
        $marketplace->execute([$customerId]);
        $fromMarketplace = array_map(fn(array $row): array => [
            'id' => (string) $row['id'], 'source' => 'marketplace', 'category' => (string) $row['listing_type'],
            'title' => (string) $row['listing_title'], 'image' => $row['listing_image'] !== '' ? $row['listing_image'] : null,
            'date' => (string) $row['booking_date'], 'booked_at' => $this->utcIso($row['booked_at']),
            'currency' => (string) $row['currency'], 'amount' => (float) $row['total_price'],
            'booking_status' => strtolower((string) $row['booking_status']), 'payment_status' => strtolower((string) $row['payment_status']),
            'confirmed_at' => $this->utcIso($row['confirmed_at']), 'cancelled_at' => $this->utcIso($row['cancelled_at']), 'completed_at' => $this->utcIso($row['completed_at']),
        ], $marketplace->fetchAll());

        $quickTaxi = $this->db->prepare(
            'SELECT p.id, p.amount, p.currency, p.method, p.state, p.confirmed_at, p.created_at, s.destination, r.loading_location, r.planned_departure_at, r.status AS run_status
             FROM tie_transport_payments p
             JOIN tie_transport_sessions s ON s.id = p.session_id
             JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE s.customer_id = ? ORDER BY p.created_at DESC LIMIT 200'
        );
        $quickTaxi->execute([$customerId]);
        $fromQuickTaxi = array_map(function (array $row): array {
            $runStatus = strtolower((string) $row['run_status']);
            return [
                'id' => (string) $row['id'], 'source' => 'quick_taxi', 'category' => 'transport',
                'title' => 'Quick Taxi · ' . ((string) $row['loading_location'] ?: 'Uthenga transport') . ($row['destination'] ? ' → ' . $row['destination'] : ''),
                'image' => null, 'date' => $row['planned_departure_at'] !== null ? substr((string) $row['planned_departure_at'], 0, 10) : null,
                'booked_at' => $this->utcIso($row['created_at']), 'currency' => (string) $row['currency'], 'amount' => (float) $row['amount'],
                'booking_status' => $runStatus === 'travelling' ? 'in_progress' : $runStatus,
                'payment_status' => strtolower((string) $row['state']),
                'confirmed_at' => $this->utcIso($row['confirmed_at']), 'cancelled_at' => null,
                'completed_at' => $runStatus === 'completed' ? $this->utcIso($row['planned_departure_at']) : null,
            ];
        }, $quickTaxi->fetchAll());

        $all = array_merge($fromMarketplace, $fromQuickTaxi);
        if ($status !== '') $all = array_values(array_filter($all, static fn(array $row): bool => $row['booking_status'] === $status));
        usort($all, static fn(array $a, array $b): int => strcmp((string) $b['booked_at'], (string) $a['booked_at']));
        return ['schema_version' => 'tie-customer-bookings-list/v1', 'bookings' => $all];
    }

    public function detail(string $customerId, string $bookingId): array
    {
        $this->db();
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ? AND customer_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$bookingId, $customerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new UthengaTieException('not_found', 'Booking not found.', 404);
        $details = $row['details'] ? json_decode((string) $row['details'], true) : null;
        return [
            'schema_version' => 'tie-customer-booking-detail/v1',
            'booking' => [
                'id' => (string) $row['id'], 'category' => (string) $row['listing_type'], 'title' => (string) $row['listing_title'],
                'image' => $row['listing_image'] !== '' ? $row['listing_image'] : null, 'booked_at' => $this->utcIso($row['booked_at']),
                'currency' => (string) $row['currency'], 'amount' => (float) $row['total_price'],
                'booking_status' => strtolower((string) $row['booking_status']), 'payment_status' => strtolower((string) $row['payment_status']),
                'confirmed_at' => $this->utcIso($row['confirmed_at']), 'cancelled_at' => $this->utcIso($row['cancelled_at']), 'completed_at' => $this->utcIso($row['completed_at']),
                'qr_code' => $row['qr_code'] ?: null, 'transaction_id' => $row['transaction_id'] ?: null,
                'details' => is_array($details) ? $details : [],
            ],
        ];
    }

    private function utcIso($value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('customer_bookings');
    }
}

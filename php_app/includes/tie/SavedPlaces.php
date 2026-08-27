<?php
/**
 * Trip Planning Assistant — Saved Places. A real, read-only view over the
 * existing `wishlist` table (already live and wired end-to-end via
 * request_api.php's `toggle_wishlist` action, wishlist.php, and the
 * marketplace listing pages' save/heart control). This service adds nothing
 * new to storage — saving/unsaving still goes through toggle_wishlist
 * directly; this only joins a customer's wishlist with real listing facts
 * for the Trip Planner's Saved Places tab.
 */
final class UthengaTieSavedPlacesService
{
    public function __construct(private ?PDO $db)
    {
    }

    public function list(string $userId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            'SELECT l.id, l.listing_type, l.title, l.location, l.image, l.rating, w.created_at AS saved_at
             FROM wishlist w JOIN listings l ON l.id = w.listing_id
             WHERE w.user_id = ? AND l.is_active = 1
             ORDER BY w.created_at DESC LIMIT 200'
        );
        $stmt->execute([$userId]);
        return ['schema_version' => 'tie-saved-places/v1', 'places' => array_map(fn(array $row): array => [
            'listing_id' => (string) $row['id'], 'category' => (string) $row['listing_type'], 'title' => (string) $row['title'],
            'location' => (string) $row['location'], 'image' => $row['image'] !== '' ? $row['image'] : null,
            'rating' => $row['rating'] !== null ? (float) $row['rating'] : null, 'saved_at' => $this->utcIso($row['saved_at']),
        ], $stmt->fetchAll())];
    }

    private function utcIso($value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('saved_places');
    }
}

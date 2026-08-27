<?php
/**
 * Uthenga — Event Ticket Types API
 * Real ticket types for a given event listing, from the `ticket_types` table,
 * falling back to the legacy listings.meta VIP/Standard fields for listings
 * that predate that table — the exact same fallback request_api.php's
 * create_booking already uses when charging for a ticket.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$listingId = trim((string) ($_GET['event_id'] ?? $_GET['listing_id'] ?? ''));
if ($listingId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required field: event_id.']);
    exit;
}

$listing = dbQueryOne("SELECT * FROM listings WHERE id = ? AND is_active = 1", [$listingId]);
if (!$listing || $listing['listing_type'] !== 'event') {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

$ticketTypes = [];

if (uthenga_table_exists('ticket_types')) {
    $rows = dbQuery(
        "SELECT id, name, price, remaining_quantity FROM ticket_types WHERE listing_id = ? AND is_active = 1 ORDER BY price ASC",
        [$listingId]
    ) ?: [];
    foreach ($rows as $r) {
        $ticketTypes[] = [
            'ticket_type_id' => (int) $r['id'],
            'name'           => (string) $r['name'],
            'price'          => (float) $r['price'],
            'remaining'      => (int) $r['remaining_quantity'],
            'available'      => (int) $r['remaining_quantity'] > 0,
        ];
    }
}

if (empty($ticketTypes)) {
    $meta = json_decode((string) ($listing['meta'] ?? '{}'), true) ?: [];
    $standardPrice = (float) ($meta['standardTicketPrice'] ?? 0);
    $vipPrice = (float) ($meta['vipTicketPrice'] ?? 0);
    if ($standardPrice > 0) {
        $ticketTypes[] = [
            'ticket_type_id' => 0,
            'name'           => 'Standard',
            'price'          => $standardPrice,
            'remaining'      => (int) ($meta['standardAvailable'] ?? 0),
            'available'      => (int) ($meta['standardAvailable'] ?? 0) > 0,
        ];
    }
    if ($vipPrice > 0) {
        $ticketTypes[] = [
            'ticket_type_id' => 0,
            'name'           => 'VIP',
            'price'          => $vipPrice,
            'remaining'      => (int) ($meta['vipAvailable'] ?? 0),
            'available'      => (int) ($meta['vipAvailable'] ?? 0) > 0,
        ];
    }
}

echo json_encode([
    'success'      => true,
    'listing_id'   => $listingId,
    'listing_title'=> $listing['title'],
    'ticket_types' => $ticketTypes,
]);

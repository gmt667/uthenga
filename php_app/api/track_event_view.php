<?php
/**
 * Uthenga — Event Analytics Tracking Endpoint
 * Increments the specified metric counter for an event.
 *
 * POST/GET parameters:
 *   event_id  (string, required) — the event primary key
 *   metric    (string, optional) — view|booking|wishlist|click (default: view)
 *
 * Returns JSON: { "success": true } or { "success": false, "error": "..." }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
// Allow same-origin requests; on production this can be tightened further
header('Cache-Control: no-store');

// Only accept POST (or GET for simplicity from <img> beacon / sendBeacon fallback)
$eventId = trim((string)($_POST['event_id'] ?? $_GET['event_id'] ?? ''));
$metric  = trim((string)($_POST['metric']   ?? $_GET['metric']   ?? 'view'));

if ($eventId === '') {
    echo json_encode(['success' => false, 'error' => 'event_id required']);
    exit;
}

$allowed = ['view', 'booking', 'wishlist', 'click'];
if (!in_array($metric, $allowed, true)) {
    $metric = 'view';
}

marketplace_track_event_metric($eventId, $metric);

if ($metric === 'view' && isLoggedIn() && uthenga_table_exists('recent_views')) {
    try {
        dbExecute(
            "INSERT INTO recent_views (user_id, session_id, view_type, reference_id, viewed_at)
             VALUES (?, ?, 'event', ?, NOW())",
            [
                $_SESSION['user_id'],
                null,
                $eventId
            ]
        );
    } catch (Throwable $e) {
        // Non-fatal: analytics should still succeed even if recent views cannot be logged.
    }
}

echo json_encode(['success' => true]);

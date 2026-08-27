<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Dashboard.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $dash = new UthengaDashboard($service->db());
    $vid  = $user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string)($_GET['action'] ?? 'overview'));
        $result = match($action) {
            'overview'   => $dash->overview($vid),
            'live'       => $dash->liveEvent($vid),
            'upcoming'   => $dash->upcomingEvents($vid, (int)($_GET['limit'] ?? 3)),
            'schedule'   => $dash->todaysSchedule($vid, (int)($_GET['limit'] ?? 5)),
            'bookings'   => $dash->recentBookings($vid, (int)($_GET['limit'] ?? 6)),
            'week'       => $dash->weekOverview($vid),
            'insights'   => $dash->insights($vid),
            'spark'      => $dash->sparkData($vid),
            default      => throw UthengaTieErrors::validation(['action' => 'Unknown action.']),
        };
        events_v2_respond($requestId, 'dashboard_result', is_array($result) ? $result : ['data' => $result]);
        exit;
    }
    throw UthengaTieErrors::validation(['method' => 'GET required.']);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}

<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/EventReviews.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $reviews = new UthengaEventReviewsService($service->db());

    $filters = [
        'event_id' => (string) ($_GET['event_id'] ?? ($_POST['event_id'] ?? 'all')),
        'rating' => (int) ($_GET['rating'] ?? ($_POST['rating'] ?? 0)),
        'status' => (string) ($_GET['status'] ?? ($_POST['status'] ?? 'all')),
        'theme' => (string) ($_GET['theme'] ?? ($_POST['theme'] ?? '')),
        'from' => (string) ($_GET['from'] ?? ($_POST['from'] ?? '')),
        'to' => (string) ($_GET['to'] ?? ($_POST['to'] ?? '')),
        'q' => (string) ($_GET['q'] ?? ($_POST['q'] ?? '')),
        'sort' => (string) ($_GET['sort'] ?? ($_POST['sort'] ?? 'newest')),
        'page' => (int) ($_GET['page'] ?? ($_POST['page'] ?? 1)),
        'trend_days' => (int) ($_GET['trend_days'] ?? ($_POST['trend_days'] ?? 30)),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'events' => $reviews->eventsList($user['id']),
            'overview' => $reviews->overview($user['id'], $filters),
            'list' => $reviews->list($user['id'], array_merge($filters, ['limit' => (int) ($_GET['limit'] ?? 20)])),
            'detail' => $reviews->detail($user['id'], (string) ($_GET['id'] ?? '')),
            'ai_draft' => $reviews->aiDraft($user['id'], (string) ($_GET['id'] ?? '')),
            'requests' => $reviews->requests($user['id'], $filters),
            'config' => $reviews->config($user['id']),
            'ask' => $reviews->ask($user['id'], (string) ($_GET['q'] ?? ''), $filters),
            'export' => $reviews->export($user['id'], $filters),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown reviews action.']),
        };
        events_v2_respond($requestId, 'reviews_result', $result);
    }

    $input = events_v2_write('reviews_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'respond' => $reviews->respond($user, $input),
        'flag' => $reviews->flag($user, $input),
        'resolve_flag' => $reviews->resolveFlag($user, $input),
        'save_config' => $reviews->saveConfig($user, $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown reviews action.']),
    };
    events_v2_respond($requestId, 'reviews_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
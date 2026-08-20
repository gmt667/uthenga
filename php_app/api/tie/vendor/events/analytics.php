<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Analytics.php';
require_once __DIR__ . '/../../../../includes/tie/Finance.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $anl = new UthengaAnalytics($service->db());

    $filters = [
        'event_id' => (string) ($_GET['event_id'] ?? ($_POST['event_id'] ?? 'all')),
        'range'    => (string) ($_GET['range'] ?? ($_POST['range'] ?? '30d')),
        'from'     => (string) ($_GET['from'] ?? ($_POST['from'] ?? '')),
        'to'       => (string) ($_GET['to'] ?? ($_POST['to'] ?? '')),
        'metric'   => (string) ($_GET['metric'] ?? ($_POST['metric'] ?? 'gross')),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $anl->overview($user['id'], $filters),
            'events' => $anl->events($user['id']),
            'funnel' => $anl->funnel($user['id'], $filters),
            'revenue' => $anl->revenue($user['id'], $filters),
            'velocity' => $anl->velocity($user['id'], $filters),
            'tickets' => $anl->tickets($user['id'], $filters),
            'attendance' => $anl->attendance($user['id'], $filters),
            'checkins' => $anl->checkins($user['id'], $filters),
            'customers' => $anl->customers($user['id'], $filters),
            'marketing' => $anl->marketing($user['id'], $filters),
            'comparison' => $anl->comparison($user['id'], $filters),
            'health' => $anl->health($user['id'], $filters),
            'forecast' => $anl->forecast($user['id'], $filters),
            'insights' => $anl->insights($user['id'], $filters),
            'alerts' => $anl->alerts($user['id'], $filters),
            'alert_config' => $anl->alertConfig($user['id']),
            'ask' => $anl->ask($user['id'], (string) ($_GET['q'] ?? ''), $filters),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown analytics action.']),
        };
        events_v2_respond($requestId, 'analytics_result', $result);
    }

    $input = events_v2_write('analytics_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    if ($action === 'csv_export') {
        $csv = $anl->exportCsv($user['id'], $filters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="uthenga-analytics-' . date('Ymd-His') . '.csv"');
        echo $csv;
        exit;
    }
    $result = match ($action) {
        'ask' => $anl->ask($user['id'], (string) ($input['q'] ?? ''), $filters),
        'save_alert_config' => $anl->saveAlertConfig($user['id'], $user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown analytics action.']),
    };
    events_v2_respond($requestId, 'analytics_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
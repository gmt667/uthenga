<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $service = (new UthengaTieKernel())->busOperations;

    $action = strtolower((string) ($_GET['action'] ?? 'overview'));
    $result = match ($action) {
        'overview' => $service->analyticsOverview($user['id']),
        'trend' => $service->analyticsTrend($user['id'], (int) ($_GET['days'] ?? 30)),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown analytics action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }

<?php
/**
 * Uthenga — Commercial Growth & Marketing Control Center API (Events V2).
 *
 * GET  ?action=overview|campaigns|promotions|promocodes
 * POST {action: create_campaign|toggle_campaign_status|create_promotion|update_promotion|toggle_promotion_status|create_promocode|save_adcard}
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Marketing.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $eventsService, $requestId] = events_v2_context();
    global $pdo;
    $marketing = new UthengaMarketingService($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $marketing->overview($user['id'], (string) ($_GET['date_range'] ?? '30')),
            'campaigns' => ['campaigns' => $marketing->campaignsList($user['id'], (string) ($_GET['status'] ?? 'all'), (string) ($_GET['channel'] ?? 'all'), (string) ($_GET['q'] ?? ''))],
            'promotions' => ['promotions' => $marketing->promotionsList($user['id'])],
            'promocodes' => ['promocodes' => $marketing->promocodesList($user['id'])],
            default => throw UthengaTieErrors::validation(['action' => 'Unknown marketing action.']),
        };
        events_v2_respond($requestId, 'result', $result);
    }

    $input = events_v2_write('marketing_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));

    $result = match ($action) {
        'create_campaign' => ['campaigns' => $marketing->createCampaign($user['id'], $input)],
        'toggle_campaign_status' => ['campaigns' => $marketing->toggleCampaignStatus($user['id'], (string) ($input['campaign_id'] ?? ''))],
        'create_promotion' => ['promotions' => $marketing->createPromotion($user['id'], $input)],
        'update_promotion' => ['promotions' => $marketing->updatePromotion($user['id'], $input)],
        'toggle_promotion_status' => ['promotions' => $marketing->togglePromotionStatus($user['id'], (string) ($input['promotion_id'] ?? ''))],
        'create_promocode' => ['promocodes' => $marketing->createPromoCode($user['id'], $input)],
        'save_adcard' => ['adcard' => $marketing->saveAdCard($user['id'], $input)],
        default => throw UthengaTieErrors::validation(['action' => 'Unknown marketing action.']),
    };
    events_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}

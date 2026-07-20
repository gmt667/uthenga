<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/restoration_helpers.php';

uthenga_json_response([
    'ok' => true,
    'service' => APP_NAME . ' API',
    'endpoints' => [
        'auth' => 'api/auth/index.php',
        'bookings' => 'api/bookings/index.php',
        'vendors' => 'api/vendors/index.php',
        'notifications' => 'api/notifications.php',
        'trip_planner' => 'api/trip_planner.php',
        'ai_chat' => 'api/ai/chat.php',
    ],
]);

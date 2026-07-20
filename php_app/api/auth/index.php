<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/restoration_helpers.php';

if (!isLoggedIn()) {
    uthenga_json_response(['ok' => false, 'logged_in' => false], 401);
}

$user = currentUser() ?: [];
uthenga_json_response([
    'ok' => true,
    'logged_in' => true,
    'user' => [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? null,
        'email' => $user['email'] ?? null,
        'role' => $user['role'] ?? null,
        'balance' => $user['balance'] ?? null,
    ],
]);

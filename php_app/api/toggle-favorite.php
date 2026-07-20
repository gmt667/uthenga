<?php
/**
 * Uthenga API — Toggle Favorite / Wishlist Item
 * Body JSON: { item_id, item_type }
 * Supports both the legacy `wishlist` table and the newer `favorites` table.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$itemId   = trim($data['item_id']   ?? ($_POST['item_id']   ?? ''));
$itemType = trim($data['item_type'] ?? ($_POST['item_type'] ?? 'listing'));
$userId   = $_SESSION['user_id'];

if ($itemType === 'accommodation') {
    $itemType = 'property';
}

if (empty($itemId)) {
    http_response_code(400);
    echo json_encode(['error' => 'item_id is required']);
    exit;
}

$favoritesTable = uthenga_first_existing_table(['favorites', 'wishlist']);
if ($favoritesTable === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Favorites storage is unavailable']);
    exit;
}

if ($favoritesTable === 'favorites') {
    $existing = dbQueryOne(
        "SELECT id FROM favorites WHERE user_id = ? AND reference_id = ? AND favorite_type = ?",
        [$userId, $itemId, $itemType]
    );
} else {
    $existing = dbQueryOne(
        "SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?",
        [$userId, $itemId]
    );
}

if ($existing) {
    if ($favoritesTable === 'favorites') {
        dbExecute(
            "DELETE FROM favorites WHERE user_id = ? AND reference_id = ? AND favorite_type = ?",
            [$userId, $itemId, $itemType]
        );
    } else {
        dbExecute(
            "DELETE FROM wishlist WHERE user_id = ? AND listing_id = ?",
            [$userId, $itemId]
        );
    }
    $favorited = false;
    $message = 'Removed from favorites';
} else {
    if ($favoritesTable === 'favorites') {
        dbExecute(
            "INSERT INTO favorites (user_id, favorite_type, reference_id, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()",
            [$userId, $itemType, $itemId]
        );
    } else {
        dbExecute(
            "INSERT INTO wishlist (user_id, listing_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = NOW()",
            [$userId, $itemId]
        );
    }
    $favorited = true;
    $message = 'Added to favorites!';
}

if ($favoritesTable === 'favorites') {
    $count = (int)(dbQueryOne(
        "SELECT COUNT(*) AS cnt FROM favorites WHERE reference_id = ? AND favorite_type = ?",
        [$itemId, $itemType]
    )['cnt'] ?? 0);
} else {
    $count = (int)(dbQueryOne(
        "SELECT COUNT(*) AS cnt FROM wishlist WHERE listing_id = ?",
        [$itemId]
    )['cnt'] ?? 0);
}

echo json_encode([
    'success'   => true,
    'favorited' => $favorited,
    'message'   => $message,
    'count'     => $count,
]);

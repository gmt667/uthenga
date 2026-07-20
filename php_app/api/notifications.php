<?php
/**
 * Uthenga API — In-App Notifications
 * GET  /api/notifications.php           → list unread + recent notifications
 * POST /api/notifications.php           → mark notification(s) as read
 * POST /api/notifications.php?action=mark_all → mark all as read
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

// ── GET — list notifications ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $notifications = dbQuery(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30",
        [$userId]
    );
    $unreadCount = (int)(dbQueryOne(
        "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['cnt'] ?? 0);

    echo json_encode([
        'success'      => true,
        'notifications'=> $notifications,
        'unread_count' => $unreadCount,
    ]);
    exit;
}

// ── POST — mark as read ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? $action;

    if ($action === 'mark_all') {
        dbExecute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        exit;
    }

    $notifId = $data['notification_id'] ?? '';
    if (empty($notifId)) {
        http_response_code(400);
        echo json_encode(['error' => 'notification_id is required']);
        exit;
    }

    dbExecute(
        "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
        [$notifId, $userId]
    );

    $unreadCount = (int)(dbQueryOne(
        "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['cnt'] ?? 0);

    echo json_encode([
        'success'      => true,
        'message'      => 'Notification marked as read',
        'unread_count' => $unreadCount,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

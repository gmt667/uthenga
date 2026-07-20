<?php
/**
 * Uthenga — Customer Review Submission API
 * Supports both legacy `reviews` and newer `customer_reviews`.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userName = trim($_SESSION['user_name'] ?? '');
$referenceId = trim($_POST['reference_id'] ?? '');
$type = trim($_POST['type'] ?? 'property');
$type = ($type === 'accommodation') ? 'property' : $type;
$rating = (int)($_POST['rating'] ?? 5);
$title = trim($_POST['title'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($referenceId === '' || $comment === '') {
    echo json_encode(['success' => false, 'message' => 'Missing reference ID or review comment.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars.']);
    exit;
}

$useCustomerReviews = uthenga_table_exists('customer_reviews');
$useLegacyReviews = uthenga_table_exists('reviews');

if (!$useCustomerReviews && !$useLegacyReviews) {
    echo json_encode(['success' => false, 'message' => 'Review storage is unavailable.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($useCustomerReviews) {
        dbExecute(
            "INSERT INTO customer_reviews (user_id, review_type, reference_id, rating, title, comment, status)
             VALUES (?, ?, ?, ?, ?, ?, 'published')",
            [$userId, $type, $referenceId, $rating, $title, $comment]
        );
    }

    if ($useLegacyReviews) {
        $legacyTitle = $title !== '' ? $title : 'Review';
        dbExecute(
            "INSERT INTO reviews (listing_id, user_name, rating, comment, review_date)
             VALUES (?, ?, ?, ?, NOW())",
            [$referenceId, $userName !== '' ? $userName : 'Guest', $rating, $comment]
        );
    }

    $stats = null;
    if ($useCustomerReviews) {
        $stats = dbQueryOne(
            "SELECT COUNT(*) as cnt, AVG(rating) as avg
             FROM customer_reviews
             WHERE reference_id = ? AND status = 'published'",
            [$referenceId]
        );
    } elseif ($useLegacyReviews) {
        $stats = dbQueryOne(
            "SELECT COUNT(*) as cnt, AVG(rating) as avg
             FROM reviews
             WHERE listing_id = ?",
            [$referenceId]
        );
    }

    if ($stats) {
        $avg = (float) ($stats['avg'] ?? $rating);
        if (uthenga_column_exists('listings', 'rating')) {
            dbExecute("UPDATE listings SET rating = ? WHERE id = ?", [$avg, $referenceId]);
        } elseif (uthenga_column_exists('listings', 'avg_rating')) {
            dbExecute("UPDATE listings SET avg_rating = ? WHERE id = ?", [$avg, $referenceId]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully!',
        'rating' => $rating,
        'rating_count' => (int)($stats['cnt'] ?? 1),
        'rating_average' => number_format((float)($stats['avg'] ?? $rating), 1)
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to save review. Error: ' . $e->getMessage()]);
}
exit;

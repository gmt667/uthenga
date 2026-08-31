<?php
/**
 * Uthenga — Gate Session API
 * JSON endpoint for all gate session actions
 * PHP 7.3+ compatible
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$readActions = ['session_stats', 'scan_activity'];
requireAdminPermission(in_array($action, $readActions, true) ? 'events.view' : 'events.manage');

header('Content-Type: application/json; charset=utf-8');
$csrfToken  = $_POST['csrf_token'] ?? '';
$userId     = $_SESSION['user_id'] ?? '';
$userName   = $_SESSION['user_name'] ?? 'Admin';

// CSRF check for all POST actions
$safePosts  = $readActions;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $safePosts, true)) {
    if (!validateCsrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

// ─── Helper: send JSON ────────────────────────────────────────────────────────
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ─── Action Dispatcher ───────────────────────────────────────────────────────
switch ($action) {

    // ── Start a new gate session ──────────────────────────────────────────────
    case 'start_session':
        $listingId = trim($_POST['listing_id'] ?? '');
        if (empty($listingId)) {
            jsonResponse(['success' => false, 'message' => 'No event selected.']);
        }

        $listing = dbQueryOne('SELECT id, title FROM listings WHERE id = ? AND listing_type = \'event\' AND is_active = 1', [$listingId]);
        if (!$listing) {
            jsonResponse(['success' => false, 'message' => 'Event not found.']);
        }

        // Check for an existing active/paused session — resume it instead
        $existing = dbQueryOne(
            "SELECT * FROM gate_sessions WHERE listing_id = ? AND status IN ('active','paused') ORDER BY started_at DESC LIMIT 1",
            [$listingId]
        );
        if ($existing) {
            dbExecute(
                "UPDATE gate_sessions SET status = 'active', paused_at = NULL, updated_at = NOW() WHERE id = ?",
                [$existing['id']]
            );
            logAction('Gate Session Resumed', 'Session ' . $existing['id'] . ' resumed for event: ' . $listing['title']);
            jsonResponse(['success' => true, 'session_id' => $existing['id'], 'message' => 'Session resumed.', 'resumed' => true]);
        }

        $sessionId = generateSessionId();
        dbExecute(
            "INSERT INTO gate_sessions (id, listing_id, listing_title, started_by, started_name, status, started_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())",
            [$sessionId, $listingId, $listing['title'], $userId, $userName]
        );
        logAction('Gate Session Started', 'Session ' . $sessionId . ' started for event: ' . $listing['title']);
        jsonResponse(['success' => true, 'session_id' => $sessionId, 'message' => 'Gate session started.', 'resumed' => false]);
        break;

    // ── Pause a gate session ──────────────────────────────────────────────────
    case 'pause_session':
        $sessionId = trim($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            jsonResponse(['success' => false, 'message' => 'No session ID.']);
        }
        $session = dbQueryOne("SELECT * FROM gate_sessions WHERE id = ?", [$sessionId]);
        if (!$session) {
            jsonResponse(['success' => false, 'message' => 'Session not found.']);
        }
        dbExecute("UPDATE gate_sessions SET status = 'paused', paused_at = NOW() WHERE id = ?", [$sessionId]);
        logAction('Gate Session Paused', 'Session ' . $sessionId);
        jsonResponse(['success' => true, 'message' => 'Session paused.']);
        break;

    // ── Stop a gate session ───────────────────────────────────────────────────
    case 'stop_session':
        $sessionId = trim($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            jsonResponse(['success' => false, 'message' => 'No session ID.']);
        }
        dbExecute("UPDATE gate_sessions SET status = 'stopped', stopped_at = NOW() WHERE id = ?", [$sessionId]);
        logAction('Gate Session Stopped', 'Session ' . $sessionId);
        jsonResponse(['success' => true, 'message' => 'Session stopped.']);
        break;

    // ── Scan / validate a QR code ─────────────────────────────────────────────
    case 'scan_ticket':
        $sessionId = trim($_POST['session_id'] ?? '');
        $qrCode    = trim($_POST['qr_code'] ?? '');

        if (empty($sessionId) || empty($qrCode)) {
            jsonResponse(['success' => false, 'message' => 'Missing session or QR code.']);
        }

        $session = dbQueryOne("SELECT * FROM gate_sessions WHERE id = ? AND status = 'active'", [$sessionId]);
        if (!$session) {
            jsonResponse(['success' => false, 'message' => 'No active session found.']);
        }

        try {
            if ($pdo instanceof PDO) {
                $pdo->beginTransaction();
            }

            $booking = dbQueryOne(
                "SELECT b.id, b.customer_name, b.customer_email, b.listing_title, b.listing_type, b.created_at,
                        COALESCE(NULLIF(b.quantity, 0), 1) AS quantity,
                        COALESCE(NULLIF(b.tickets_used, 0), 0) AS tickets_used,
                        JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.ticket_type')) AS ticket_type,
                        b.booking_status, b.payment_status
                 FROM bookings b
                 WHERE b.qr_code = ? AND b.listing_id = ?
                 FOR UPDATE",
                [$qrCode, $session['listing_id']]
            );

            if (!$booking) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                dbExecute(
                    "INSERT INTO gate_scans (session_id, qr_code, scan_result, scanned_by, scanned_name)
                     VALUES (?, ?, 'invalid', ?, ?)",
                    [$sessionId, $qrCode, $userId, $userName]
                );
                dbExecute(
                    "UPDATE gate_sessions SET total_scanned = total_scanned + 1, total_invalid = total_invalid + 1 WHERE id = ?",
                    [$sessionId]
                );
                jsonResponse([
                    'success'     => true,
                    'scan_result' => 'invalid',
                    'message'     => 'Invalid ticket â€” not found or wrong event.',
                    'icon'        => 'âŒ',
                    'css_class'   => 'scan-invalid',
                ]);
            }

            if (strtolower((string) $booking['payment_status']) !== 'paid') {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                dbExecute(
                    "INSERT INTO gate_scans (session_id, qr_code, booking_id, scan_result, notes, scanned_by, scanned_name)
                     VALUES (?, ?, ?, 'invalid', 'Payment not completed', ?, ?)",
                    [$sessionId, $qrCode, $booking['id'], $userId, $userName]
                );
                dbExecute(
                    "UPDATE gate_sessions SET total_scanned = total_scanned + 1, total_invalid = total_invalid + 1 WHERE id = ?",
                    [$sessionId]
                );
                jsonResponse([
                    'success'     => true,
                    'scan_result' => 'invalid',
                    'message'     => 'Payment not completed for this ticket.',
                    'icon'        => 'âŒ',
                    'css_class'   => 'scan-invalid',
                ]);
            }

            $ticketsPurchased = max(1, (int) ($booking['quantity'] ?? 1));
            $ticketsUsed = max(0, (int) ($booking['tickets_used'] ?? 0));
            $countedValidScans = (int) dbCount(
                "SELECT COUNT(*) FROM gate_scans WHERE booking_id = ? AND scan_result = 'valid'",
                [$booking['id']]
            );
            $ticketsUsed = max($ticketsUsed, $countedValidScans);

            if ($ticketsUsed >= $ticketsPurchased) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                dbExecute(
                    "INSERT INTO gate_scans (session_id, qr_code, booking_id, customer_name, ticket_type, scan_result, notes, scanned_by, scanned_name, tickets_in_booking, tickets_used_after)
                     VALUES (?, ?, ?, ?, ?, 'duplicate', 'All purchased tickets have already been used.', ?, ?, ?, ?)",
                    [$sessionId, $qrCode, $booking['id'], $booking['customer_name'], $booking['ticket_type'] ?? 'Standard', $userId, $userName, $ticketsPurchased, $ticketsUsed]
                );
                dbExecute(
                    "UPDATE gate_sessions SET total_scanned = total_scanned + 1, total_duplicate = total_duplicate + 1 WHERE id = ?",
                    [$sessionId]
                );
                jsonResponse([
                    'success'            => true,
                    'scan_result'        => 'duplicate',
                    'message'            => 'All purchased tickets have already been used.',
                    'icon'               => 'âš ï¸',
                    'css_class'          => 'scan-duplicate',
                    'ticket_id'          => $booking['id'],
                    'customer_name'      => $booking['customer_name'],
                    'ticket_type'        => $booking['ticket_type'] ?? 'Standard',
                    'tickets_purchased'  => $ticketsPurchased,
                    'tickets_used'       => $ticketsUsed,
                    'tickets_remaining'  => 0,
                    'purchase_date'      => $booking['created_at'] ?? null,
                    'ticket_status'      => 'used',
                ]);
            }

            $newUsedCount = $ticketsUsed + 1;
            $newStatus = ($newUsedCount >= $ticketsPurchased) ? 'fully_used' : 'partially_used';

            dbExecute(
                "INSERT INTO gate_scans (session_id, qr_code, booking_id, customer_name, ticket_type, scan_result, scanned_by, scanned_name, tickets_in_booking, tickets_used_after)
                 VALUES (?, ?, ?, ?, ?, 'valid', ?, ?, ?, ?)",
                [$sessionId, $qrCode, $booking['id'], $booking['customer_name'], $booking['ticket_type'] ?? 'Standard', $userId, $userName, $ticketsPurchased, $newUsedCount]
            );

            dbExecute(
                "UPDATE bookings SET tickets_used = ?, ticket_status = ? WHERE id = ?",
                [$newUsedCount, $newStatus, $booking['id']]
            );

            dbExecute(
                "UPDATE gate_sessions SET total_scanned = total_scanned + 1, total_valid = total_valid + 1 WHERE id = ?",
                [$sessionId]
            );

            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->commit();
            }

            jsonResponse([
                'success'       => true,
                'scan_result'   => 'valid',
                'message'       => 'Valid ticket â€” entry granted!',
                'icon'          => 'âœ…',
                'css_class'     => 'scan-valid',
                'ticket_id'     => $booking['id'],
                'customer_name' => $booking['customer_name'],
                'ticket_type'   => $booking['ticket_type'] ?? 'Standard',
                'booking_id'    => $booking['id'],
                'tickets_purchased' => $ticketsPurchased,
                'tickets_used'      => $newUsedCount,
                'tickets_remaining' => max(0, $ticketsPurchased - $newUsedCount),
                'purchase_date'     => $booking['created_at'] ?? null,
                'ticket_status'     => $newStatus,
            ]);
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Uthenga gate scan] ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Unable to validate the ticket right now.']);
        }
        break;

    case 'session_stats':
        $sessionId = trim($_GET['session_id'] ?? $_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            jsonResponse(['success' => false, 'message' => 'No session ID.']);
        }
        $session = dbQueryOne("SELECT * FROM gate_sessions WHERE id = ?", [$sessionId]);
        if (!$session) {
            jsonResponse(['success' => false, 'message' => 'Session not found.']);
        }

        // Total tickets sold for this event
        $soldData = dbQueryOne(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(quantity, 0), 1)), 0) AS sold,
                    COALESCE(SUM(total_price), 0) AS revenue
             FROM bookings
             WHERE listing_id = ? AND LOWER(payment_status) = 'paid' AND booking_status NOT IN ('cancelled','refunded')",
            [$session['listing_id']]
        );
        $ticketsSold   = (int) ($soldData['sold'] ?? 0);
        $revenue       = (float) ($soldData['revenue'] ?? 0);
        $scanned       = (int) $session['total_scanned'];
        $valid         = (int) $session['total_valid'];
        $remaining     = max(0, $ticketsSold - $valid);

        jsonResponse([
            'success'       => true,
            'status'        => $session['status'],
            'tickets_sold'  => $ticketsSold,
            'scanned'       => $scanned,
            'valid'         => $valid,
            'invalid'       => (int) $session['total_invalid'],
            'duplicate'     => (int) $session['total_duplicate'],
            'remaining'     => $remaining,
            'revenue'       => formatMWK($revenue),
        ]);
        break;

    // ── Get recent scan activity ──────────────────────────────────────────────
    case 'scan_activity':
        $sessionId = trim($_GET['session_id'] ?? '');
        $limit     = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        if (empty($sessionId)) {
            jsonResponse(['success' => false, 'message' => 'No session ID.']);
        }
        $scans = dbQuery(
            "SELECT scan_result, customer_name, ticket_type, qr_code, scanned_at
             FROM gate_scans WHERE session_id = ? ORDER BY scanned_at DESC LIMIT $limit",
            [$sessionId]
        );
        jsonResponse(['success' => true, 'scans' => $scans]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}


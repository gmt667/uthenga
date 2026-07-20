<?php
/**
 * Uthenga — Security Helper
 * Contains utility functions for device fingerprinting, session management, and login alerts.
 */

require_once __DIR__ . '/../db.php';

/**
 * Gets the device OS, Browser, and IP for fingerprinting.
 */
function getDeviceFingerprint(): array {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';
    $deviceType = 'desktop';

    // Basic OS parsing
    if (preg_match('/windows|win32/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/android/i', $ua)) {
        $os = 'Android';
        $deviceType = 'mobile';
    } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
        $os = 'iOS';
        $deviceType = 'mobile';
        if (preg_match('/ipad/i', $ua)) {
            $deviceType = 'tablet';
        }
    } elseif (preg_match('/linux/i', $ua)) {
        $os = 'Linux';
    }

    // Basic Browser parsing
    if (preg_match('/firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/chrome|crios/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/msie|trident/i', $ua)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/edge/i', $ua)) {
        $browser = 'Edge';
    }

    return [
        'device_name' => "$browser on $os",
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $ua
    ];
}

/**
 * Registers a new device session in database and checks for login alerts.
 */
function registerDeviceSession(string $userId): void {
    $fingerprint = getDeviceFingerprint();
    $sessionToken = bin2hex(random_bytes(32));

    // Clear current flag for previous sessions of this user
    dbExecute('UPDATE device_sessions SET is_current = 0 WHERE user_id = ?', [$userId]);

    // Insert new session
    dbExecute("
        INSERT INTO device_sessions 
        (user_id, session_token, device_name, device_type, browser, os, ip_address, is_current, last_active_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ", [
        $userId,
        $sessionToken,
        $fingerprint['device_name'],
        $fingerprint['device_type'],
        $fingerprint['browser'],
        $fingerprint['os'],
        $fingerprint['ip_address']
    ]);

    $_SESSION['device_session_token'] = $sessionToken;

    // Check if this is a new device or location
    $exists = dbQueryOne("
        SELECT id FROM device_sessions 
        WHERE user_id = ? AND browser = ? AND os = ? AND ip_address = ? AND session_token != ?
        LIMIT 1
    ", [
        $userId,
        $fingerprint['browser'],
        $fingerprint['os'],
        $fingerprint['ip_address'],
        $sessionToken
    ]);

    if (!$exists) {
        // This is a new device login! Trigger alert/log
        dbExecute("
            INSERT INTO login_alerts (user_id, alert_type, ip_address, user_agent, details, is_read, created_at)
            VALUES (?, 'new_device', ?, ?, ?, 0, NOW())
        ", [
            $userId,
            $fingerprint['ip_address'],
            $fingerprint['user_agent'],
            json_encode(['device' => $fingerprint['device_name']])
        ]);

        // Add a notification for user
        try {
            dbExecute("
                INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
                VALUES (?, 'security', 'New Device Login Alert', ?, 0, NOW())
            ", [
                $userId,
                "A new login was detected on device: " . $fingerprint['device_name'] . " from IP: " . $fingerprint['ip_address']
            ]);
        } catch (Throwable $e) {
            // Try inserting without type or title in case schema differs, but catch errors to prevent login blocking
            try {
                dbExecute("
                    INSERT INTO notifications (user_id, message, is_read, created_at)
                    VALUES (?, ?, 0, NOW())
                ", [
                    $userId,
                    "New Login Alert: A login was detected on " . $fingerprint['device_name'] . " from IP " . $fingerprint['ip_address']
                ]);
            } catch (Throwable $e2) {
                // Log notification failure instead of crashing the login flow
                try {
                    dbExecute("
                        INSERT INTO audit_logs (user_id, user_name, user_role, action, details)
                        VALUES (?, 'System', 'System', 'Notification Error', ?)
                    ", [
                        $userId,
                        "Failed to insert login alert notification: " . $e2->getMessage()
                    ]);
                } catch (Throwable $e3) {
                    // Do nothing if everything fails
                }
            }
        }
    }
}

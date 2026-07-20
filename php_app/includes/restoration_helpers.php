<?php
/**
 * Uthenga - Restoration helpers
 * Shared auth, JSON, and catalogue rendering helpers used by the restored pages.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/catalog.php';

if (!function_exists('uthenga_json_response')) {
    function uthenga_json_response(array $payload, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('uthenga_auth_session_from_user')) {
    function uthenga_auth_session_from_user(array $user): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_balance'] = (string)($user['balance'] ?? '0.00');
        $_SESSION['user_avatar'] = $user['avatar'] ?? '';
        $_SESSION['last_login_at'] = date('Y-m-d H:i:s');
    }
}

if (!function_exists('uthenga_auth_clear_session')) {
    function uthenga_auth_clear_session(): void {
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}

if (!function_exists('uthenga_auth_redirect_target')) {
    function uthenga_auth_redirect_target(string $default = ''): string {
        $target = uthenga_safe_redirect_url((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''), '');
        if ($target !== '') {
            return $target;
        }

        $raw = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($raw !== '' && preg_match('~^[/?A-Za-z0-9._~=-]+(?:\?[A-Za-z0-9._~=&%-]*)?$~', $raw)) {
            return $raw;
        }

        return $default;
    }
}

if (!function_exists('uthenga_auth_login_user')) {
    function uthenga_auth_login_user(array $user): void {
        uthenga_auth_session_from_user($user);

        if (function_exists('registerDeviceSession')) {
            try {
                registerDeviceSession((string)$user['id']);
            } catch (Throwable $e) {
                error_log('[Uthenga Auth] Device session registration failed: ' . $e->getMessage());
            }
        }

        try {
            dbExecute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        } catch (Throwable $e) {
            // Ignore audit-only failures.
        }
    }
}

if (!function_exists('uthenga_auth_find_user_by_email')) {
    function uthenga_auth_find_user_by_email(string $email): ?array {
        $row = dbQueryOne('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [trim($email)]);
        return $row ?: null;
    }
}

if (!function_exists('uthenga_auth_generate_user_id')) {
    function uthenga_auth_generate_user_id(string $prefix = 'U'): string {
        return generateId($prefix);
    }
}

if (!function_exists('uthenga_render_card_grid')) {
    function uthenga_render_card_grid(array $items, string $emptyMessage = 'No listings found.', bool $showBookCta = false): void {
        if (empty($items)) {
            echo '<div class="card" style="padding:2rem;text-align:center;"><h3>' . e($emptyMessage) . '</h3></div>';
            return;
        }

        echo '<div class="grid grid-cols-4 gap-3">';
        foreach ($items as $item) {
            $detailUrl = e($item['detail_url'] ?? '#');
            $actionLabel = $showBookCta
                ? uthenga_booking_btn_label((string)($item['type'] ?? $item['listing_type'] ?? 'event'))
                : 'View Details';
            $trackAttr = (($item['listing_type'] ?? $item['type'] ?? '') === 'event')
                ? ' data-track-event-click="' . e($item['id']) . '"'
                : '';
            echo '<article class="card">';
            echo '<div class="card-img-wrap">';
            echo '<img src="' . e($item['image'] ?? '') . '" alt="' . e($item['title'] ?? '') . '" class="card-img" loading="lazy">';
            echo '<span class="card-badge ' . e($item['badge_class'] ?? '') . '">' . e($item['type_label'] ?? '') . '</span>';
            if (!empty($item['is_trending'])) {
                echo '<span class="card-badge badge-trending" style="left:auto;right:0.75rem;">Trending</span>';
            }
            echo '</div>';
            echo '<div class="card-body">';
            echo '<div class="card-title">' . e($item['title'] ?? '') . '</div>';
            echo '<div class="card-loc">' . e($item['location'] ?? '') . '</div>';
            if (!empty($item['vendor_name'])) {
                echo '<div class="text-xs text-muted" style="margin-top:0.35rem;">By ' . e($item['vendor_name']) . '</div>';
            }
            echo '<div class="card-price">' . e($item['price_label'] ?? '') . '</div>';
            echo '</div>';
            echo '<div class="card-footer">';
            echo '<a href="' . $detailUrl . '" class="btn btn-secondary btn-sm" style="width:100%;"' . $trackAttr . '>' . e($actionLabel) . '</a>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
    }
}

if (!function_exists('uthenga_render_section_header')) {
    function uthenga_render_section_header(string $label, string $title, string $ctaHref = '', string $ctaText = ''): void {
        echo '<div class="section-header">';
        echo '<div><div class="section-label">' . e($label) . '</div><h2>' . e($title) . '</h2></div>';
        if ($ctaHref !== '' && $ctaText !== '') {
            echo '<a href="' . e($ctaHref) . '" class="btn btn-secondary btn-sm">' . e($ctaText) . '</a>';
        }
        echo '</div>';
    }
}

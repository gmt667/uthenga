<?php
/**
 * Shared helpers for the Shop module.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';

if (!function_exists('uthenga_shop_defaults')) {
    function uthenga_shop_defaults(): array {
        return [
            'delivery_fee_mwk' => 1500,
            'free_delivery_threshold_mwk' => 25000,
            'tax_rate_percent' => 0,
            'order_hold_minutes' => 15,
            'cod_enabled' => 1,
            'bank_transfer_enabled' => 1,
            'tnm_mpamba_enabled' => 1,
            'airtel_money_enabled' => 1,
            'paychangu_enabled' => 0,
            'shop_name' => 'Uthenga Shop',
            'shop_tagline' => 'Everyday essentials, drinks, groceries, and more',
        ];
    }
}

if (!function_exists('uthenga_shop_settings')) {
    function uthenga_shop_settings(): array {
        $settings = uthenga_shop_defaults();
        if (!uthenga_table_exists('shop_settings')) {
            return $settings;
        }

        try {
            $rows = dbQuery('SELECT setting_key, setting_value, value_type FROM shop_settings');
            foreach ($rows as $row) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key === '' || !array_key_exists($key, $settings)) {
                    continue;
                }

                $value = (string) ($row['setting_value'] ?? '');
                $type = (string) ($row['value_type'] ?? 'string');
                if ($type === 'number') {
                    $settings[$key] = (float) $value;
                } elseif ($type === 'boolean') {
                    $settings[$key] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
                } else {
                    $settings[$key] = $value;
                }
            }
        } catch (Throwable $e) {
            // Fall back to defaults.
        }

        return $settings;
    }
}

if (!function_exists('uthenga_shop_money')) {
    function uthenga_shop_money(float $amount): string {
        return formatMWK($amount);
    }
}

if (!function_exists('uthenga_shop_slugify')) {
    function uthenga_shop_slugify(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}

if (!function_exists('uthenga_shop_unique_slug')) {
    function uthenga_shop_unique_slug(string $table, string $baseSlug, string $slugColumn = 'slug'): string {
        $slug = $baseSlug !== '' ? $baseSlug : 'item';
        $candidate = $slug;
        $counter = 2;
        while (true) {
            $exists = dbQueryOne("SELECT id FROM {$table} WHERE {$slugColumn} = ? LIMIT 1", [$candidate]);
            if (!$exists) {
                return $candidate;
            }
            $candidate = $slug . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('uthenga_shop_session_token')) {
    function uthenga_shop_session_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                @session_start();
            }
        }

        if (empty($_SESSION['shop_cart_token'])) {
            $_SESSION['shop_cart_token'] = bin2hex(random_bytes(24));
        }

        return (string) $_SESSION['shop_cart_token'];
    }
}

if (!function_exists('uthenga_shop_cart_state')) {
    function uthenga_shop_cart_state(): array {
        if (empty($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
            $_SESSION['shop_cart'] = [];
        }
        return $_SESSION['shop_cart'];
    }
}

if (!function_exists('uthenga_shop_cart_save_state')) {
    function uthenga_shop_cart_save_state(array $items): void {
        $_SESSION['shop_cart'] = $items;
        if (!isLoggedIn() || !uthenga_table_exists('shop_cart_items')) {
            return;
        }

        $token = uthenga_shop_session_token();
        dbExecute('DELETE FROM shop_cart_items WHERE user_id = ? OR session_token = ?', [$_SESSION['user_id'], $token]);
        foreach ($items as $productId => $item) {
            dbExecute(
                'INSERT INTO shop_cart_items (user_id, session_token, product_id, quantity, unit_price) VALUES (?, ?, ?, ?, ?)',
                [
                    $_SESSION['user_id'],
                    $token,
                    (int) $productId,
                    max(1, (int) ($item['quantity'] ?? 1)),
                    (float) ($item['unit_price'] ?? 0),
                ]
            );
        }
    }
}

if (!function_exists('uthenga_shop_product_image_urls')) {
    function uthenga_shop_product_image_urls(array $product): array {
        $urls = [];
        foreach (['primary_image_url', 'secondary_image_url'] as $key) {
            if (!empty($product[$key])) {
                $urls[] = (string) $product[$key];
            }
        }

        if (!empty($product['id']) && uthenga_table_exists('shop_product_images')) {
            try {
                $rows = dbQuery(
                    'SELECT image_url FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC',
                    [$product['id']]
                );
                foreach ($rows as $row) {
                    if (!empty($row['image_url'])) {
                        $urls[] = (string) $row['image_url'];
                    }
                }
            } catch (Throwable $e) {
                // Ignore missing table / data issues.
            }
        }

        $urls = array_values(array_unique(array_filter($urls)));
        return $urls;
    }
}

if (!function_exists('uthenga_shop_category_tree')) {
    function uthenga_shop_category_tree(bool $activeOnly = true): array {
        if (!uthenga_table_exists('shop_categories')) {
            return [];
        }

        $sql = 'SELECT * FROM shop_categories';
        $params = [];
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        return dbQuery($sql, $params);
    }
}

if (!function_exists('uthenga_shop_products')) {
    function uthenga_shop_products(array $filters = []): array {
        if (!uthenga_table_exists('shop_products')) {
            return [];
        }

        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['query'])) {
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
            $query = '%' . $filters['query'] . '%';
            array_push($params, $query, $query, $query, $query);
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }
        if (!empty($filters['new'])) {
            $where[] = 'p.is_new_arrival = 1';
        }
        if (!empty($filters['best'])) {
            $where[] = 'p.is_best_seller = 1';
        }
        if (!empty($filters['promotion'])) {
            $where[] = 'p.is_promotion = 1';
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock_quantity > 0';
        }

        $sort = (string) ($filters['sort'] ?? 'featured');
        $orderBy = match ($sort) {
            'price_low' => 'p.price ASC',
            'price_high' => 'p.price DESC',
            'newest' => 'p.created_at DESC',
            'stock' => 'p.stock_quantity DESC, p.name ASC',
            'name' => 'p.name ASC',
            default => 'p.is_featured DESC, p.is_best_seller DESC, p.is_promotion DESC, p.created_at DESC',
        };

        $sql = "
            SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   CASE WHEN p.stock_quantity > 0 THEN 'In Stock' ELSE 'Out of Stock' END AS stock_label
            FROM shop_products p
            LEFT JOIN shop_categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
        ";

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $filters['limit']);
        }

        return dbQuery($sql, $params);
    }
}

if (!function_exists('uthenga_shop_product_by_slug')) {
    function uthenga_shop_product_by_slug(string $slug): ?array {
        if (!uthenga_table_exists('shop_products')) {
            return null;
        }

        $product = dbQueryOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM shop_products p
             LEFT JOIN shop_categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.deleted_at IS NULL
             LIMIT 1",
            [$slug]
        );

        return $product ?: null;
    }
}

if (!function_exists('uthenga_shop_cart_enrich')) {
    function uthenga_shop_cart_enrich(array $items): array {
        $enriched = [];
        foreach ($items as $productId => $item) {
            $product = is_array($item['product'] ?? null) ? $item['product'] : null;
            if (!$product && uthenga_table_exists('shop_products')) {
                $product = dbQueryOne(
                    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                     FROM shop_products p
                     LEFT JOIN shop_categories c ON c.id = p.category_id
                     WHERE p.id = ? LIMIT 1",
                    [(int) $productId]
                ) ?: null;
            }

            if (!$product) {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? $product['price'] ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $product['quantity'] = $quantity;
            $product['unit_price'] = $unitPrice;
            $product['line_total'] = $lineTotal;
            $product['image_urls'] = uthenga_shop_product_image_urls($product);
            $enriched[$productId] = $product;
        }

        return $enriched;
    }
}

if (!function_exists('uthenga_shop_cart_items')) {
    function uthenga_shop_cart_items(): array {
        $items = uthenga_shop_cart_state();
        return uthenga_shop_cart_enrich($items);
    }
}

if (!function_exists('uthenga_shop_cart_add')) {
    function uthenga_shop_cart_add(int $productId, int $quantity = 1): array {
        $product = uthenga_shop_product_by_id($productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Product not found.'];
        }

        if ((int) $product['stock_quantity'] <= 0) {
            return ['ok' => false, 'message' => 'This product is currently out of stock.'];
        }

        $items = uthenga_shop_cart_state();
        $quantity = max(1, $quantity);
        $current = (int) ($items[$productId]['quantity'] ?? 0);
        $items[$productId] = [
            'quantity' => min((int) $product['stock_quantity'], $current + $quantity),
            'unit_price' => (float) $product['price'],
            'added_at' => date('c'),
        ];
        uthenga_shop_cart_save_state($items);

        return ['ok' => true, 'message' => 'Added to cart.'];
    }
}

if (!function_exists('uthenga_shop_cart_update')) {
    function uthenga_shop_cart_update(int $productId, int $quantity): array {
        $items = uthenga_shop_cart_state();
        if ($quantity <= 0) {
            unset($items[$productId]);
            uthenga_shop_cart_save_state($items);
            return ['ok' => true, 'message' => 'Item removed.'];
        }

        $product = uthenga_shop_product_by_id($productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Product not found.'];
        }

        $items[$productId] = [
            'quantity' => min((int) $product['stock_quantity'], $quantity),
            'unit_price' => (float) $product['price'],
            'added_at' => $items[$productId]['added_at'] ?? date('c'),
        ];
        uthenga_shop_cart_save_state($items);

        return ['ok' => true, 'message' => 'Cart updated.'];
    }
}

if (!function_exists('uthenga_shop_cart_remove')) {
    function uthenga_shop_cart_remove(int $productId): void {
        $items = uthenga_shop_cart_state();
        unset($items[$productId]);
        uthenga_shop_cart_save_state($items);
    }
}

if (!function_exists('uthenga_shop_cart_clear')) {
    function uthenga_shop_cart_clear(): void {
        $_SESSION['shop_cart'] = [];
        if (uthenga_table_exists('shop_cart_items') && isLoggedIn()) {
            dbExecute('DELETE FROM shop_cart_items WHERE user_id = ? OR session_token = ?', [$_SESSION['user_id'], uthenga_shop_session_token()]);
        }
    }
}

if (!function_exists('uthenga_shop_cart_sync_from_db')) {
    function uthenga_shop_cart_sync_from_db(): void {
        if (!isLoggedIn() || !uthenga_table_exists('shop_cart_items')) {
            return;
        }

        $token = uthenga_shop_session_token();
        $rows = dbQuery(
            'SELECT product_id, quantity, unit_price FROM shop_cart_items WHERE user_id = ? OR session_token = ?',
            [$_SESSION['user_id'], $token]
        );

        if (empty($rows)) {
            return;
        }

        $items = uthenga_shop_cart_state();
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $items[$productId] = [
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'added_at' => date('c'),
            ];
        }

        $_SESSION['shop_cart'] = $items;
    }
}

if (!function_exists('uthenga_shop_product_by_id')) {
    function uthenga_shop_product_by_id(int $productId): ?array {
        if (!uthenga_table_exists('shop_products')) {
            return null;
        }

        $product = dbQueryOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM shop_products p
             LEFT JOIN shop_categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.deleted_at IS NULL
             LIMIT 1",
            [$productId]
        );

        return $product ?: null;
    }
}

if (!function_exists('uthenga_shop_order_number')) {
    function uthenga_shop_order_number(): string {
        return 'SO-' . strtoupper(bin2hex(random_bytes(5)));
    }
}

if (!function_exists('uthenga_shop_payment_methods')) {
    function uthenga_shop_payment_methods(): array {
        $settings = uthenga_shop_settings();
        return array_values(array_filter([
            'cash_on_delivery' => !empty($settings['cod_enabled']) ? 'Cash on Delivery' : null,
            'bank_transfer' => !empty($settings['bank_transfer_enabled']) ? 'Bank Transfer' : null,
            'tnm_mpamba' => !empty($settings['tnm_mpamba_enabled']) ? 'TNM Mpamba' : null,
            'airtel_money' => !empty($settings['airtel_money_enabled']) ? 'Airtel Money' : null,
            'paychangu' => !empty($settings['paychangu_enabled']) ? 'PayChangu' : null,
        ]));
    }
}

if (!function_exists('uthenga_shop_payment_methods_map')) {
    function uthenga_shop_payment_methods_map(): array {
        $settings = uthenga_shop_settings();
        return array_filter([
            'cash_on_delivery' => !empty($settings['cod_enabled']) ? 'Cash on Delivery' : null,
            'bank_transfer' => !empty($settings['bank_transfer_enabled']) ? 'Bank Transfer' : null,
            'tnm_mpamba' => !empty($settings['tnm_mpamba_enabled']) ? 'TNM Mpamba' : null,
            'airtel_money' => !empty($settings['airtel_money_enabled']) ? 'Airtel Money' : null,
            'paychangu' => !empty($settings['paychangu_enabled']) ? 'PayChangu' : null,
        ]);
    }
}

if (!function_exists('uthenga_shop_delivery_fee')) {
    function uthenga_shop_delivery_fee(float $subtotal): float {
        $settings = uthenga_shop_settings();
        $threshold = (float) ($settings['free_delivery_threshold_mwk'] ?? 0);
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }
        return (float) ($settings['delivery_fee_mwk'] ?? 0);
    }
}

if (!function_exists('uthenga_shop_tax_amount')) {
    function uthenga_shop_tax_amount(float $subtotal): float {
        $settings = uthenga_shop_settings();
        $rate = (float) ($settings['tax_rate_percent'] ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }
        return round($subtotal * ($rate / 100), 2);
    }
}

if (!function_exists('uthenga_shop_order_totals')) {
    function uthenga_shop_order_totals(array $cartItems): array {
        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $subtotal += (float) ($item['line_total'] ?? 0);
        }

        $deliveryFee = uthenga_shop_delivery_fee($subtotal);
        $taxAmount = uthenga_shop_tax_amount($subtotal);
        $discountAmount = 0.0;
        $total = max(0, $subtotal + $deliveryFee + $taxAmount - $discountAmount);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ];
    }
}

if (!function_exists('uthenga_shop_notify_user')) {
    function uthenga_shop_notify_user(string $userId, string $type, string $title, string $message): void {
        if (!uthenga_table_exists('notifications')) {
            return;
        }

        try {
            dbExecute(
                'INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())',
                [$userId, $type, $title, $message]
            );
        } catch (Throwable $e) {
            try {
                dbExecute(
                    'INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())',
                    [$userId, $title . ': ' . $message]
                );
            } catch (Throwable $ignored) {
            }
        }
    }
}

if (!function_exists('uthenga_shop_notify_admins')) {
    function uthenga_shop_notify_admins(string $title, string $message): void {
        if (!uthenga_table_exists('notifications')) {
            return;
        }

        try {
            $admins = dbQuery("SELECT id FROM users WHERE role IN ('Administrator','Super Administrator')");
            foreach ($admins as $admin) {
                if (!empty($admin['id'])) {
                    uthenga_shop_notify_user((string) $admin['id'], 'shop', $title, $message);
                }
            }
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('uthenga_shop_status_badge')) {
    function uthenga_shop_status_badge(string $status): string {
        $status = strtolower(trim($status));
        return match ($status) {
            'delivered', 'completed', 'paid', 'confirmed', 'active', 'authorized' => 'badge-approved',
            'cancelled', 'failed', 'refunded', 'inactive', 'archived' => 'badge-cancelled',
            'pending', 'draft', 'preparing', 'assigned', 'assigned_to_rider', 'out_for_delivery', 'busy' => 'badge-pending',
            default => 'badge-confirmed',
        };
    }
}

if (!function_exists('uthenga_shop_order_by_number')) {
    function uthenga_shop_order_by_number(string $orderNumber): ?array {
        if (!uthenga_table_exists('shop_orders')) {
            return null;
        }

        $order = dbQueryOne('SELECT * FROM shop_orders WHERE order_number = ? LIMIT 1', [$orderNumber]);
        return $order ?: null;
    }
}

if (!function_exists('uthenga_shop_order_items')) {
    function uthenga_shop_order_items(int $orderId): array {
        if (!uthenga_table_exists('shop_order_items')) {
            return [];
        }

        return dbQuery(
            'SELECT * FROM shop_order_items WHERE order_id = ? ORDER BY id ASC',
            [$orderId]
        );
    }
}

if (!function_exists('uthenga_shop_riders')) {
    function uthenga_shop_riders(bool $onlyAvailable = false): array {
        if (!uthenga_table_exists('delivery_riders')) {
            return [];
        }

        $sql = 'SELECT * FROM delivery_riders';
        if ($onlyAvailable) {
            $sql .= " WHERE status = 'active' AND availability = 'available'";
        }
        $sql .= ' ORDER BY name ASC';
        return dbQuery($sql);
    }
}

if (!function_exists('uthenga_shop_cancel_order')) {
    function uthenga_shop_cancel_order(int $orderId, string $userId = ''): array {
        $order = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        if ($userId !== '' && (string) ($order['user_id'] ?? '') !== $userId) {
            return ['ok' => false, 'message' => 'You cannot cancel this order.'];
        }

        $status = strtolower((string) ($order['order_status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'confirmed', 'preparing'], true)) {
            return ['ok' => false, 'message' => 'This order can no longer be cancelled.'];
        }

        dbExecute(
            "UPDATE shop_orders
             SET order_status = 'cancelled',
                 fulfillment_status = 'cancelled',
                 payment_status = CASE WHEN payment_status = 'paid' THEN 'refunded' ELSE payment_status END,
                 cancelled_at = NOW()
             WHERE id = ?",
            [$orderId]
        );

        if (!empty($order['user_id'])) {
            uthenga_shop_notify_user((string) $order['user_id'], 'shop', 'Order Cancelled', 'Your order ' . ($order['order_number'] ?? '') . ' has been cancelled.');
        }
        uthenga_shop_notify_admins('Order Cancelled', 'Order ' . ($order['order_number'] ?? '') . ' was cancelled by the customer or staff.');

        return ['ok' => true, 'message' => 'Order cancelled.'];
    }
}

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
            'shop_tagline' => 'Beers, spirits, soft drinks, and chilled beverages',
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

        $where = ["p.deleted_at IS NULL", "p.status = 'active'"];
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
             WHERE p.slug = ? AND p.deleted_at IS NULL AND p.status = 'active'
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
                     WHERE p.id = ? AND p.deleted_at IS NULL AND p.status = 'active' LIMIT 1",
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
             WHERE p.id = ? AND p.deleted_at IS NULL AND p.status = 'active'
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

if (!function_exists('uthenga_shop_payment_reference')) {
    function uthenga_shop_payment_reference(): string {
        return 'SOP-' . strtoupper(bin2hex(random_bytes(5)));
    }
}

if (!function_exists('uthenga_shop_split_name')) {
    function uthenga_shop_split_name(string $fullName): array {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $first = $parts[0] ?? 'Uthenga';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';
        return [$first, $last];
    }
}

if (!function_exists('uthenga_shop_paychangu_ready')) {
    function uthenga_shop_paychangu_ready(): bool {
        return (PAYCHANGU_PUBLIC_KEY !== '' || PAYCHANGU_SECRET_KEY !== '');
    }
}

if (!function_exists('uthenga_shop_payment_methods')) {
    function uthenga_shop_payment_methods(): array {
        $settings = uthenga_shop_settings();
        $paychanguReady = !empty($settings['paychangu_enabled']) && uthenga_shop_paychangu_ready();
        return array_values(array_filter([
            'cash_on_delivery' => !empty($settings['cod_enabled']) ? 'Cash on Delivery' : null,
            'bank_transfer' => !empty($settings['bank_transfer_enabled']) ? 'Bank Transfer' : null,
            'tnm_mpamba' => !empty($settings['tnm_mpamba_enabled']) ? 'TNM Mpamba' : null,
            'airtel_money' => !empty($settings['airtel_money_enabled']) ? 'Airtel Money' : null,
            'paychangu' => $paychanguReady ? 'PayChangu' : null,
        ]));
    }
}

if (!function_exists('uthenga_shop_payment_methods_map')) {
    function uthenga_shop_payment_methods_map(): array {
        $settings = uthenga_shop_settings();
        $paychanguReady = !empty($settings['paychangu_enabled']) && uthenga_shop_paychangu_ready();
        return array_filter([
            'cash_on_delivery' => !empty($settings['cod_enabled']) ? 'Cash on Delivery' : null,
            'bank_transfer' => !empty($settings['bank_transfer_enabled']) ? 'Bank Transfer' : null,
            'tnm_mpamba' => !empty($settings['tnm_mpamba_enabled']) ? 'TNM Mpamba' : null,
            'airtel_money' => !empty($settings['airtel_money_enabled']) ? 'Airtel Money' : null,
            'paychangu' => $paychanguReady ? 'PayChangu' : null,
        ]);
    }
}

if (!function_exists('uthenga_shop_payment_by_reference')) {
    function uthenga_shop_payment_by_reference(string $reference): ?array {
        if (!uthenga_table_exists('shop_payments') || trim($reference) === '') {
            return null;
        }

        $payment = dbQueryOne(
            "SELECT p.*, o.order_number, o.user_id, o.customer_name, o.customer_email, o.customer_phone, o.total_amount, o.currency, o.order_status, o.payment_status AS order_payment_status
             FROM shop_payments p
             INNER JOIN shop_orders o ON o.id = p.order_id
             WHERE p.payment_reference = ? OR p.id = ? LIMIT 1",
            [$reference, $reference]
        );

        return $payment ?: null;
    }
}

if (!function_exists('uthenga_shop_payment_by_order_id')) {
    function uthenga_shop_payment_by_order_id(int $orderId): ?array {
        if (!uthenga_table_exists('shop_payments') || $orderId <= 0) {
            return null;
        }

        $payment = dbQueryOne(
            "SELECT * FROM shop_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [$orderId]
        );

        return $payment ?: null;
    }
}

if (!function_exists('uthenga_shop_paychangu_initialize')) {
    function uthenga_shop_paychangu_initialize(array $order, array $payment, string $customerEmail, string $customerName, string $phone): array {
        $apiKey = PAYCHANGU_PUBLIC_KEY !== '' ? PAYCHANGU_PUBLIC_KEY : PAYCHANGU_SECRET_KEY;
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'PayChangu credentials are not configured.'];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL is not available on this server.'];
        }

        $reference = trim((string) ($payment['payment_reference'] ?? ''));
        if ($reference === '') {
            $reference = uthenga_shop_payment_reference();
        }

        $amount = (float) ($payment['amount'] ?? $order['total_amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid payment amount.'];
        }

        [$firstName, $lastName] = uthenga_shop_split_name($customerName);
        $payload = [
            'amount' => $amount,
            'currency' => $payment['currency'] ?? $order['currency'] ?? APP_CURRENCY,
            'email' => $customerEmail,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'tx_ref' => $reference,
            'callback_url' => BASE_URL . 'payments/shop-paychangu-callback.php',
            'return_url' => BASE_URL . 'shop-order.php?order=' . urlencode((string) ($order['order_number'] ?? '')) . '&payment=paychangu&reference=' . urlencode($reference),
            'description' => 'Uthenga Shop order ' . ($order['order_number'] ?? $reference),
            'customization' => [
                'title' => APP_NAME . ' Shop',
                'description' => 'Secure checkout for physical product delivery',
            ],
            'metadata' => [
                'order_id' => (int) ($order['id'] ?? 0),
                'order_number' => (string) ($order['order_number'] ?? ''),
                'payment_id' => (int) ($payment['id'] ?? 0),
                'payment_reference' => $reference,
                'payment_method' => 'paychangu',
                'phone' => $phone !== '' ? $phone : null,
            ],
        ];

        $ch = curl_init(rtrim(PAYCHANGU_API_BASE_URL, '/') . PAYCHANGU_INIT_PATH);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $curlCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($responseBody === false || $responseBody === null) {
            return ['success' => false, 'message' => $curlErr ?: 'Unable to initialize PayChangu payment.'];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => 'PayChangu returned an invalid response.'];
        }

        $gatewayRef = trim((string) ($decoded['reference'] ?? $decoded['data']['reference'] ?? $decoded['data']['tx_ref'] ?? $reference));
        $checkoutUrl = trim((string) ($decoded['checkout_url'] ?? $decoded['data']['checkout_url'] ?? $decoded['data']['link'] ?? $decoded['payment_url'] ?? $decoded['url'] ?? ''));
        $success = $curlCode >= 200 && $curlCode < 300 && ($checkoutUrl !== '' || !empty($decoded['success']));
        $message = (string) ($decoded['message'] ?? $decoded['data']['message'] ?? ($success ? 'PayChangu checkout ready.' : 'PayChangu could not start the payment.'));

        return [
            'success' => $success,
            'checkout_url' => $checkoutUrl,
            'reference' => $gatewayRef !== '' ? $gatewayRef : $reference,
            'message' => $message,
            'response' => $decoded,
            'payload' => $payload,
        ];
    }
}

if (!function_exists('uthenga_shop_confirm_payment')) {
    function uthenga_shop_confirm_payment(array $order, array $payment = [], array $gatewayPayload = [], string $status = 'paid'): void {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        dbExecute(
            "UPDATE shop_orders
             SET payment_status = 'paid',
                 order_status = CASE WHEN order_status IN ('cancelled', 'delivered') THEN order_status ELSE 'confirmed' END,
                 fulfillment_status = CASE WHEN fulfillment_status IN ('cancelled', 'delivered') THEN fulfillment_status ELSE 'confirmed' END,
                 confirmed_at = COALESCE(confirmed_at, NOW()),
                 updated_at = NOW()
             WHERE id = ?",
            [$orderId]
        );

        if (!empty($payment)) {
            $paymentId = (int) ($payment['id'] ?? 0);
            $paymentReference = (string) ($payment['payment_reference'] ?? '');
            $payloadJson = !empty($gatewayPayload) ? json_encode($gatewayPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            if ($paymentId > 0) {
                dbExecute(
                    "UPDATE shop_payments
                     SET payment_status = ?,
                         paid_at = COALESCE(paid_at, ?),
                         gateway_payload = COALESCE(?, gateway_payload),
                         updated_at = NOW()
                     WHERE id = ?",
                    [$status === 'paid' ? 'paid' : $status, $now, $payloadJson, $paymentId]
                );
            } elseif ($paymentReference !== '') {
                dbExecute(
                    "UPDATE shop_payments
                     SET payment_status = ?,
                         paid_at = COALESCE(paid_at, ?),
                         gateway_payload = COALESCE(?, gateway_payload),
                         updated_at = NOW()
                     WHERE payment_reference = ?",
                    [$status === 'paid' ? 'paid' : $status, $now, $payloadJson, $paymentReference]
                );
            }
        }

        if (!empty($order['user_id'])) {
            uthenga_shop_notify_user((string) $order['user_id'], 'shop', 'Payment Confirmed', 'Your order ' . ($order['order_number'] ?? '') . ' has been paid successfully.');
        }
        uthenga_shop_notify_admins('Shop Payment Confirmed', 'Payment for order ' . ($order['order_number'] ?? '') . ' has been confirmed.');
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

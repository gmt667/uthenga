<?php
/**
 * Uthenga — Central API Endpoint
 * All AJAX/API requests route through here
 * Always returns JSON
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!function_exists('uthenga_restore_booking_inventory')) {
    function uthenga_restore_booking_inventory(array $booking): void {
        $listingType  = strtolower((string)($booking['listing_type'] ?? ''));
        $listingId    = (string)($booking['listing_id'] ?? '');
        $details      = json_decode((string)($booking['details'] ?? '{}'), true);
        $details      = is_array($details) ? $details : [];
        $qty          = max(1, (int)($booking['quantity'] ?? ($details['quantity'] ?? 1)));
        $ticketTypeId = (int)($booking['ticket_type_id'] ?? ($details['ticket_type_id'] ?? 0));
        $seatClassId  = (int)($booking['seat_class_id'] ?? ($details['seat_class_id'] ?? 0));
        $roomTypeId   = (int)($booking['room_type_id'] ?? ($details['room_type_id'] ?? 0));

        if ($listingType === 'event') {
            if ($ticketTypeId > 0) {
                dbExecute(
                    "UPDATE ticket_types SET remaining_quantity = remaining_quantity + ? WHERE id = ? AND listing_id = ? AND is_active = 1",
                    [$qty, $ticketTypeId, $listingId]
                );
                return;
            }

            $listing = dbQueryOne('SELECT meta FROM listings WHERE id = ?', [$listingId]);
            if (!$listing) {
                return;
            }

            $meta = json_decode((string)($listing['meta'] ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            $ticketType = strtoupper((string)($details['ticket_type'] ?? 'Standard'));
            if ($ticketType === 'VIP') {
                $meta['vipAvailable'] = (int)($meta['vipAvailable'] ?? 0) + $qty;
            } else {
                $meta['standardAvailable'] = (int)($meta['standardAvailable'] ?? 0) + $qty;
            }
            dbExecute('UPDATE listings SET meta = ? WHERE id = ?', [json_encode($meta), $listingId]);
            return;
        }

        if ($listingType === 'transport' && $seatClassId > 0) {
            dbExecute(
                "UPDATE seat_classes SET remaining_seats = remaining_seats + ? WHERE id = ? AND listing_id = ? AND is_active = 1",
                [$qty, $seatClassId, $listingId]
            );
            return;
        }

        if ($listingType === 'accommodation' && $roomTypeId > 0) {
            dbExecute(
                "UPDATE room_types SET available_rooms = available_rooms + 1 WHERE id = ? AND listing_id = ? AND is_active = 1",
                [$roomTypeId, $listingId]
            );
        }
    }
}

// CSRF validation for state-changing actions
$stateChanging = ['create_booking','cancel_booking','admin_update_booking',
                  'toggle_user_status','refund_booking','reply_ticket','create_listing','toggle_wishlist',
                  'create_support_ticket', 'create_blog_post', 'update_blog_post', 'delete_blog_post',
                  'update_settings', 'create_coupon', 'toggle_coupon', 'delete_coupon'];
$action = $_POST['action'] ?? '';

if (in_array($action, $stateChanging) && !validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

// ─── Route ────────────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Get Listings ─────────────────────────────────────────────────────
        case 'get_listings':
            $type   = $_POST['type'] ?? 'all';
            $search = trim($_POST['q'] ?? '');
            if ($type === 'event') {
                $rows = marketplace_fetch_events($search, 0, false);
            } elseif ($type === 'property') {
                $rows = marketplace_fetch_properties($search, 0, false);
            } elseif ($type === 'tour') {
                $rows = marketplace_fetch_tours($search, 0, false);
            } elseif ($type === 'transport') {
                $rows = marketplace_fetch_transport_routes($search, 0, false);
            } else {
                $rows = marketplace_fetch_home_feed($search, 0);
            }
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ── Create Booking ────────────────────────────────────────────────────
        case 'create_booking':
            if (!isLoggedIn() || !hasRole([ROLE_CUSTOMER])) {
                echo json_encode(['success' => false, 'message' => 'Please log in as a customer to book.']);
                break;
            }

            $listingId    = trim($_POST['listing_id']   ?? '');
            $listingType  = trim($_POST['listing_type'] ?? '');
            $gatewayInput = trim($_POST['gateway']      ?? '');
            $couponCode   = strtoupper(trim($_POST['coupon_code'] ?? ''));
            $qty          = max(1, (int)($_POST['quantity'] ?? $_POST['seats'] ?? 1));
            $ticketTypeId = (int)($_POST['ticket_type_id'] ?? 0);
            $seatClassId  = (int)($_POST['seat_class_id']  ?? 0);
            $roomTypeId   = (int)($_POST['room_type_id']   ?? 0);
            $ticketType   = trim($_POST['ticket_type'] ?? 'Standard');

            $gatewayMap = [
                'airtel' => 'Airtel Money',
                'airtel money' => 'Airtel Money',
                'tnm' => 'TNM Mpamba',
                'tnm mpamba' => 'TNM Mpamba',
                'card' => 'Bank Card',
                'bank card' => 'Bank Card',
                'direct nbs transfer' => 'Direct NBS Transfer',
                'uthenga pay' => 'Uthenga Pay',
            ];
            $gatewayKey = strtolower($gatewayInput);
            $gatewayLabel = $gatewayMap[$gatewayKey] ?? $gatewayInput;
            $gatewayLabel = trim($gatewayLabel);
            $allowedGateways = ['Airtel Money', 'TNM Mpamba', 'Bank Card', 'Direct NBS Transfer', 'Uthenga Pay'];

            // Validate listing exists
            $listing = dbQueryOne('SELECT * FROM listings WHERE id = ? AND is_active = 1', [$listingId]);
            if (!$listing) {
                echo json_encode(['success' => false, 'message' => 'Listing not found.']);
                break;
            }
            $listingType = $listing['listing_type'];

            if (empty($gatewayLabel) || !in_array($gatewayLabel, $allowedGateways, true)) {
                echo json_encode(['success' => false, 'message' => 'Please select a valid payment method.']);
                break;
            }

            $unitPrice = 0.0;
            if ($listingType === 'event') {
                if ($ticketTypeId > 0) {
                    $ticketRow = dbQueryOne('SELECT price FROM ticket_types WHERE id = ? AND listing_id = ? AND is_active = 1', [$ticketTypeId, $listingId]);
                    $unitPrice = (float)($ticketRow['price'] ?? 0);
                }
                if ($unitPrice <= 0) {
                    $meta = json_decode($listing['meta'], true) ?? [];
                    $unitPrice = strtolower($ticketType) === 'vip'
                        ? (float)($meta['vipTicketPrice'] ?? 0)
                        : (float)($meta['standardTicketPrice'] ?? 0);
                }
            } elseif ($listingType === 'transport') {
                if ($seatClassId > 0) {
                    $seatRow = dbQueryOne('SELECT price FROM seat_classes WHERE id = ? AND listing_id = ? AND is_active = 1', [$seatClassId, $listingId]);
                    $unitPrice = (float)($seatRow['price'] ?? 0);
                }
                if ($unitPrice <= 0) {
                    $meta = json_decode($listing['meta'], true) ?? [];
                    $unitPrice = (float)($meta['pricePerSeat'] ?? $meta['baseFare'] ?? 0);
                }
            } elseif ($listingType === 'accommodation') {
                if ($roomTypeId > 0) {
                    $roomRow = dbQueryOne('SELECT price_per_night FROM room_types WHERE id = ? AND listing_id = ? AND is_active = 1', [$roomTypeId, $listingId]);
                    $unitPrice = (float)($roomRow['price_per_night'] ?? 0);
                }
                if ($unitPrice <= 0) {
                    $meta = json_decode($listing['meta'], true) ?? [];
                    $rooms = $meta['rooms'] ?? [];
                    $unitPrice = (float)($rooms[0]['pricePerNight'] ?? 0);
                }
            } elseif ($listingType === 'tour') {
                $meta = json_decode($listing['meta'], true) ?? [];
                $unitPrice = (float)($meta['pricePerPerson'] ?? $meta['base_price'] ?? 0);
            }

            if ($unitPrice <= 0) {
                echo json_encode(['success' => false, 'message' => 'Unable to determine booking price.']);
                break;
            }

            $grossTotal = round($unitPrice * $qty, 2);
            $listingMeta = json_decode((string)($listing['meta'] ?? '{}'), true);
            $listingMeta = is_array($listingMeta) ? $listingMeta : [];
            $isModernSchema = uthenga_column_exists('bookings', 'booking_channel');

            // Apply coupon discount
            $discount = 0;
            if ($couponCode) {
                $coupon = dbQueryOne('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND expiry_date >= CURDATE()', [$couponCode]);
                if ($coupon) {
                    if ($coupon['discount_type'] === 'percentage') {
                        $discount = ($grossTotal * $coupon['value']) / 100;
                    } else {
                        $discount = (float)$coupon['value'];
                    }
                }
            }

            $netAmount = max(0, round($grossTotal - $discount, 2));
            $split = uthenga_finance_split_amounts($netAmount, $listingType);
            $commission = (float)($split['commission_amount'] ?? 0);
            $serviceFee = (float)($split['service_fee'] ?? 0);
            $vendorNet = (float)($split['vendor_net_amount'] ?? 0);
            $totalPrice = (float)($split['customer_total'] ?? $netAmount);
            $bookingRef   = 'BKG-' . strtoupper(bin2hex(random_bytes(3)));
            $txnRef       = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
            $receiptNum   = 'REC-CT-' . rand(1000000, 9999999);
            $customerId   = $_SESSION['user_id'];
            $customerName = $_SESSION['user_name'];
            $customerEmail= $_SESSION['user_email'];
            $typeShort    = strtoupper(substr($listingType, 0, 2));
            $qrCode       = "UTHENGA-$typeShort-$bookingRef-" . strtoupper(explode(' ', $customerName)[0]);
            $pdo->beginTransaction();

            // ── Inventory Decrement (atomic — prevents overselling) ─────────────
            if ($listingType === 'event') {
                if ($ticketTypeId > 0) {
                    // New Phase 2: ticket_types table
                    $rows = dbExecuteAffected(
                        "UPDATE ticket_types SET remaining_quantity = remaining_quantity - ?
                         WHERE id = ? AND listing_id = ? AND remaining_quantity >= ? AND is_active = 1",
                        [$qty, $ticketTypeId, $listingId, $qty]
                    );
                    if ($rows === 0) {
                        $tt = dbQueryOne("SELECT name, remaining_quantity FROM ticket_types WHERE id=?", [$ticketTypeId]);
                        $left = $tt ? (int)$tt['remaining_quantity'] : 0;
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        echo json_encode(['success' => false, 'message' => $left === 0
                            ? "Sorry, that ticket type is sold out."
                            : "Only $left tickets remaining. Please reduce your quantity."]);
                        break;
                    }
                } else {
                    // Legacy fallback: JSON meta field
                    $meta = json_decode($listing['meta'], true) ?? [];
                    if ($ticketType === 'VIP') {
                        $available = (int)($meta['vipAvailable'] ?? 0);
                        if ($available < $qty) {
                            echo json_encode(['success' => false, 'message' => "Only $available VIP tickets left."]);
                            break;
                        }
                        $meta['vipAvailable'] = max(0, $available - $qty);
                    } else {
                        $available = (int)($meta['standardAvailable'] ?? 0);
                        if ($available < $qty) {
                            echo json_encode(['success' => false, 'message' => "Only $available Standard tickets left."]);
                            break;
                        }
                        $meta['standardAvailable'] = max(0, $available - $qty);
                    }
                    dbExecute('UPDATE listings SET meta = ? WHERE id = ?', [json_encode($meta), $listingId]);
                }
            } elseif ($listingType === 'transport' && $seatClassId > 0) {
                $rows = dbExecuteAffected(
                    "UPDATE seat_classes SET remaining_seats = remaining_seats - ?
                     WHERE id = ? AND listing_id = ? AND remaining_seats >= ? AND is_active = 1",
                    [$qty, $seatClassId, $listingId, $qty]
                );
                if ($rows === 0) {
                    $sc = dbQueryOne("SELECT class_name, remaining_seats FROM seat_classes WHERE id=?", [$seatClassId]);
                    $left = $sc ? (int)$sc['remaining_seats'] : 0;
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode(['success' => false, 'message' => $left === 0
                        ? "Sorry, this seat class is fully booked."
                        : "Only $left seats remaining."]);
                    break;
                }
            } elseif ($listingType === 'accommodation' && $roomTypeId > 0) {
                $rows = dbExecuteAffected(
                    "UPDATE room_types SET available_rooms = available_rooms - 1
                     WHERE id = ? AND listing_id = ? AND available_rooms >= 1 AND is_active = 1",
                    [$roomTypeId, $listingId]
                );
                if ($rows === 0) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode(['success' => false, 'message' => "Sorry, no rooms of that type are currently available."]);
                    break;
                }
            }

            // Build details JSON
            $ticketFormat = strtolower(trim((string) (
                $listingMeta['ticket_code_format']
                ?? $listingMeta['ticketCodeFormat']
                ?? $listingMeta['scan_format']
                ?? $listingMeta['scanFormat']
                ?? 'qr'
            )));
            if (!in_array($ticketFormat, ['qr', 'barcode', 'code'], true)) {
                $ticketFormat = 'qr';
            }

            $details = [
                'quantity'       => $qty,
                'ticket_type'    => $ticketType,
                'ticket_type_id' => $ticketTypeId ?: null,
                'seat_class_id'  => $seatClassId  ?: null,
                'room_type_id'   => $roomTypeId   ?: null,
                'ticket_format'  => $ticketFormat,
                'check_in_date'  => $_POST['check_in_date']  ?? null,
                'check_out_date' => $_POST['check_out_date'] ?? null,
                'tour_date'      => $_POST['tour_date']      ?? null,
                'seats'          => $_POST['seats']          ?? null,
                'gateway'        => $gatewayLabel,
            ];
            $details = array_filter($details, function($v) { return $v !== null && $v !== ''; });

            $bookingInsertId = $bookingRef;
            $bookingNumericId = 0;
            if ($isModernSchema) {
                $bookingCode = 'BK-' . strtoupper(bin2hex(random_bytes(4)));
                dbExecute(
                    'INSERT INTO bookings (
                        booking_code, customer_id, booking_channel, booking_status, payment_status, currency,
                        total_amount, discount_amount, tax_amount, commission_amount, grand_total,
                        reference_name, customer_notes, vendor_notes, booked_at, confirmed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $bookingCode,
                        $customerId,
                        'web',
                        'confirmed',
                        'paid',
                        APP_CURRENCY,
                        $netAmount,
                        $discount,
                        $serviceFee,
                        $commission,
                        $totalPrice,
                        $listing['title'],
                        null,
                        null,
                    ]
                );
                $bookingInsertId = (string)dbLastId();
                $bookingNumericId = (int) $bookingInsertId;
            } else {
                $ttIdParam = $ticketTypeId ?: null;
                $scIdParam = $seatClassId  ?: null;
                $rtIdParam = $roomTypeId   ?: null;
                dbExecute(
                    'INSERT INTO bookings (
                        id, listing_id, listing_title, listing_image, listing_type,
                        customer_id, customer_name, customer_email, details, total_price, commission_paid,
                        payment_status, booking_status, transaction_id, qr_code
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $bookingRef,
                        $listingId,
                        $listing['title'],
                        $listing['image'],
                        $listingType,
                        $customerId,
                        $customerName,
                        $customerEmail,
                        json_encode($details),
                        $totalPrice,
                        $commission,
                        'Paid',
                        'confirmed',
                        $txnRef,
                        $qrCode,
                    ]
                );
                $details['ticket_type_id'] = $ttIdParam;
                $details['seat_class_id'] = $scIdParam;
                $details['room_type_id'] = $rtIdParam;
            }

            $transactionReference = $isModernSchema ? $txnRef : $txnRef;
            if ($isModernSchema) {
                if ($bookingNumericId > 0 && uthenga_table_exists('booking_items')) {
                    $serviceDate = $details['check_in_date'] ?? ($details['travel_date'] ?? ($details['tour_date'] ?? null));
                    $itemTypeMap = [
                        'event' => 'event_ticket',
                        'accommodation' => 'property_room',
                        'property' => 'property_room',
                        'tour' => 'tour_package',
                        'transport' => 'transport_seat',
                    ];
                    $itemType = $itemTypeMap[$listingType] ?? 'vendor_service';
                    dbExecute(
                        'INSERT INTO booking_items (booking_id, vendor_id, item_type, reference_id, item_name, quantity, unit_price, subtotal, service_date, metadata)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $bookingNumericId,
                            (int)($listing['vendor_id'] ?? 0) ?: null,
                            $itemType,
                            $listingId,
                            $listing['title'],
                            $qty,
                            $unitPrice,
                            $netAmount,
                            $serviceDate,
                            json_encode([
                                'discount_amount' => $discount,
                                'service_fee' => $serviceFee,
                                'commission_amount' => $commission,
                                'vendor_net_amount' => $vendorNet,
                                'transaction_reference' => $txnRef,
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]
                    );
                }

                dbExecute(
                    'INSERT INTO transactions (
                        transaction_reference, booking_id, user_id, vendor_id, amount, currency,
                        gateway_name, transaction_type, status, metadata, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $transactionReference,
                        $bookingNumericId,
                        $customerId,
                        (int)($listing['vendor_id'] ?? 0) ?: null,
                        $totalPrice,
                        APP_CURRENCY,
                        $gatewayLabel,
                        'booking_payment',
                        'success',
                        json_encode([
                            'source' => 'booking',
                            'booking_id' => $bookingNumericId,
                            'booking_reference' => $bookingRef,
                            'receipt_number' => $receiptNum,
                            'listing_id' => $listingId,
                            'listing_type' => $listingType,
                            'gross_amount' => $grossTotal,
                            'discount_amount' => $discount,
                            'service_fee' => $serviceFee,
                            'commission_rate' => $split['commission_rate'],
                            'commission_amount' => $commission,
                            'vendor_net_amount' => $vendorNet,
                            'platform_revenue' => $split['platform_revenue'],
                            'customer_total' => $totalPrice,
                            'gateway' => $gatewayLabel,
                            'details' => $details,
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]
                );
                uthenga_finance_record_sale([
                    'booking_id' => $bookingNumericId,
                    'vendor_id' => (int)($listing['vendor_id'] ?? 0),
                    'listing_type' => $listingType,
                    'currency' => APP_CURRENCY,
                    'gross_amount' => $grossTotal,
                    'discount_amount' => $discount,
                    'transaction_reference' => $txnRef,
                    'transaction_id' => $txnRef,
                    'status' => 'success',
                    'wallet_bucket' => 'available',
                ]);
            } else {
                dbExecute(
                    'INSERT INTO transactions (id, booking_id, customer_id, customer_name, amount, gateway, status, receipt_number)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$txnRef, $bookingRef, $customerId, $customerName, $totalPrice, $gatewayLabel, 'Success', $receiptNum]
                );
            }

            // Audit log
            logAction('Authorized Payment', "Paid " . formatMWK($totalPrice) . " via $gatewayLabel for {$listing['title']}. Ref: $bookingRef");

            echo json_encode([
                'success' => true,
                'message' => 'Booking confirmed!',
                'booking' => [
                    'id'           => $bookingInsertId,
                    'booking_id'   => $bookingInsertId,
                    'booking_code' => $bookingRef,
                    'ticket_id'    => $bookingInsertId,
                    'quantity'     => $qty,
                    'qr_code'      => $qrCode,
                    'ticket_format'=> $ticketFormat,
                    'total_price'  => $totalPrice,
                    'transaction_id' => $txnRef,
                    'transaction_reference' => $transactionReference,
                    'receipt_number' => $receiptNum,
                    'gateway' => $gatewayLabel
                ]
            ]);
            $pdo->commit();
            uthenga_cache_invalidate();
            break;

        // ── Cancel Booking ────────────────────────────────────────────────────
        case 'cancel_booking':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                break;
            }
            $bookingId = trim($_POST['booking_id'] ?? '');
            $booking   = dbQueryOne('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
            if (!$booking) {
                echo json_encode(['success' => false, 'message' => 'Booking not found.']);
                break;
            }
            $currentStatus = strtolower(trim((string)($booking['booking_status'] ?? '')));
            $paymentStatus = strtolower(trim((string)($booking['payment_status'] ?? '')));
            if ($currentStatus === 'cancelled' || $paymentStatus === 'refunded') {
                echo json_encode(['success' => true, 'message' => 'Booking is already cancelled.']);
                break;
            }
            // Customer can only cancel their own; admin can cancel any
            if ($booking['customer_id'] !== $_SESSION['user_id'] && !hasRole(ADMIN_ROLES)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied.']);
                break;
            }
            $pdo->beginTransaction();
            dbExecute("UPDATE bookings SET booking_status='cancelled', payment_status='refunded' WHERE id=?", [$bookingId]);
            uthenga_restore_booking_inventory($booking);
            uthenga_finance_reverse_sale($booking);
            logAction('Cancelled Booking', "Booking $bookingId cancelled. Refund: " . formatMWK($booking['total_price']));
            $pdo->commit();
            uthenga_cache_invalidate();
            echo json_encode(['success' => true, 'message' => 'Booking cancelled and refunded.']);
            break;

        // ── Admin: Update Booking ─────────────────────────────────────────────
        case 'admin_update_booking':
            requireAdmin();
            $bookingId = trim($_POST['booking_id'] ?? '');
            $field     = $_POST['field'] ?? '';
            $value     = $_POST['value'] ?? '';
            $allowed   = ['booking_status','payment_status'];
            if (!in_array($field, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid field.']);
                break;
            }
            dbExecute("UPDATE bookings SET $field = ? WHERE id = ?", [$value, $bookingId]);
            logAction('Admin Booking Update', "Updated $field to '$value' for booking $bookingId");
            echo json_encode(['success' => true]);
            break;

        // ── Refund Booking ────────────────────────────────────────────────────
        case 'refund_booking':
            requireAdmin();
            $bookingId = trim($_POST['booking_id'] ?? '');
            $booking   = dbQueryOne('SELECT * FROM bookings WHERE id=?', [$bookingId]);
            if (!$booking) { echo json_encode(['success' => false, 'message' => 'Booking not found.']); break; }
            $currentStatus = strtolower(trim((string)($booking['booking_status'] ?? '')));
            $paymentStatus = strtolower(trim((string)($booking['payment_status'] ?? '')));
            if ($currentStatus === 'cancelled' || $paymentStatus === 'refunded') {
                echo json_encode(['success' => true, 'message' => 'Booking is already refunded.']);
                break;
            }
            $pdo->beginTransaction();
            dbExecute("UPDATE bookings SET payment_status='refunded', booking_status='cancelled' WHERE id=?", [$bookingId]);
            uthenga_restore_booking_inventory($booking);
            uthenga_finance_reverse_sale($booking);
            logAction('Refund Authorization', "Refunded " . formatMWK($booking['total_price']) . " for booking $bookingId to {$booking['customer_name']}.");
            $pdo->commit();
            uthenga_cache_invalidate();
            echo json_encode(['success' => true]);
            break;

        // ── Support Tickets ──────────────────────────────────────────────────
        case 'create_support_ticket':
            if (!isLoggedIn() || !hasRole([ROLE_CUSTOMER])) {
                echo json_encode(['success' => false, 'message' => 'Please log in as a customer to submit a ticket.']);
                break;
            }
            $subject  = trim($_POST['subject']  ?? '');
            $message  = trim($_POST['ticket_message'] ?? $_POST['message'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $validCats = ['Billing','Booking issue','Vendor help','Technical'];
            if (empty($subject) || empty($message) || !in_array($category, $validCats)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required. Please select a valid category.']);
                break;
            }
            $userId = $_SESSION['user_id'];
            $user   = dbQueryOne('SELECT name FROM users WHERE id = ?', [$userId]);
            $ticketId = 'TCK-' . rand(1000, 9999);
            dbExecute(
                'INSERT INTO support_tickets (id, customer_id, customer_name, subject, message, category) VALUES (?,?,?,?,?,?)',
                [$ticketId, $userId, $user['name'], $subject, $message, $category]
            );
            logAction('Created Support Ticket', "Customer submitted ticket: \"$subject\" (Category: $category)");
            echo json_encode(['success' => true, 'ticket_id' => $ticketId, 'message' => 'Support ticket submitted successfully.']);
            break;

        // ── Blog CRUD ────────────────────────────────────────────────────────
        case 'create_blog_post':
            requireAdmin();
            $title    = trim($_POST['title'] ?? '');
            $excerpt  = trim($_POST['excerpt'] ?? '');
            $content  = trim($_POST['content'] ?? '');
            $image    = trim($_POST['image'] ?? '');
            $author   = trim($_POST['author'] ?? '');
            $category = $_POST['category'] ?? 'Tips';
            
            if (empty($title) || empty($excerpt) || empty($content) || empty($image) || empty($author)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required to create a blog post.']);
                break;
            }
            $postId = 'POST-' . rand(1000, 9999);
            dbExecute(
                "INSERT INTO blog_posts (id, title, excerpt, content, image, author, category, post_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())",
                [$postId, $title, $excerpt, $content, $image, $author, $category]
            );
            logAction('Created Blog Post', "Admin created blog post: \"$title\"");
            echo json_encode(['success' => true, 'post_id' => $postId, 'message' => 'Blog post published successfully.']);
            break;

        case 'update_blog_post':
            requireAdmin();
            $postId   = $_POST['post_id'] ?? '';
            $title    = trim($_POST['title'] ?? '');
            $excerpt  = trim($_POST['excerpt'] ?? '');
            $content  = trim($_POST['content'] ?? '');
            $image    = trim($_POST['image'] ?? '');
            $author   = trim($_POST['author'] ?? '');
            $category = $_POST['category'] ?? 'Tips';
            
            if (empty($postId) || empty($title) || empty($excerpt) || empty($content) || empty($image) || empty($author)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required to update a blog post.']);
                break;
            }
            dbExecute(
                "UPDATE blog_posts SET title = ?, excerpt = ?, content = ?, image = ?, author = ?, category = ? WHERE id = ?",
                [$title, $excerpt, $content, $image, $author, $category, $postId]
            );
            logAction('Updated Blog Post', "Admin updated blog post: $postId");
            echo json_encode(['success' => true, 'message' => 'Blog post updated successfully.']);
            break;

        case 'delete_blog_post':
            requireAdmin();
            $postId = $_POST['post_id'] ?? '';
            if (empty($postId)) {
                echo json_encode(['success' => false, 'message' => 'Post ID is required.']);
                break;
            }
            dbExecute("DELETE FROM blog_posts WHERE id = ?", [$postId]);
            logAction('Deleted Blog Post', "Admin deleted blog post: $postId");
            echo json_encode(['success' => true, 'message' => 'Blog post deleted successfully.']);
            break;

        // ── Platform Settings ────────────────────────────────────────────────
        case 'update_settings':
            requireAdmin();
            $pName   = trim($_POST['platform_name'] ?? '');
            $pEmail  = trim($_POST['platform_email'] ?? '');
            $vReg    = isset($_POST['allow_vendor_registration']) ? ($_POST['allow_vendor_registration'] ? '1' : '0') : '0';
            
            if (empty($pName) || empty($pEmail)) {
                echo json_encode(['success' => false, 'message' => 'Platform name and email are required.']);
                break;
            }
            setSetting('platform_name', $pName, $_SESSION['user_id'] ?? null);
            setSetting('platform_email', $pEmail, $_SESSION['user_id'] ?? null);
            setSetting('allow_vendor_registration', $vReg, $_SESSION['user_id'] ?? null);
            
            logAction('Updated Platform Settings', "Admin updated site configurations via API");
            echo json_encode(['success' => true, 'message' => 'Platform settings updated successfully.']);
            break;

        // ── Coupon CRUD ──────────────────────────────────────────────────────
        case 'create_coupon':
            requireAdmin();
            $code      = strtoupper(trim($_POST['code'] ?? ''));
            $type      = $_POST['discount_type'] ?? 'percentage';
            $val       = (float)($_POST['value'] ?? 0);
            $minSpend  = !empty($_POST['min_spend']) ? (float)$_POST['min_spend'] : null;
            $expiry    = $_POST['expiry_date'] ?? '';
            
            if (empty($code) || $val <= 0 || empty($expiry)) {
                echo json_encode(['success' => false, 'message' => 'Coupon code, value, and expiry date are required.']);
                break;
            }
            $exists = dbCount("SELECT COUNT(*) FROM coupons WHERE code = ?", [$code]);
            if ($exists > 0) {
                echo json_encode(['success' => false, 'message' => "Coupon code '$code' already exists."]);
                break;
            }
            dbExecute(
                "INSERT INTO coupons (code, discount_type, value, min_spend, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                [$code, $type, $val, $minSpend, $expiry]
            );
            logAction('Created Coupon Code', "Admin created coupon: $code");
            echo json_encode(['success' => true, 'message' => "Coupon code '$code' created successfully."]);
            break;

        case 'toggle_coupon':
            requireAdmin();
            $code = $_POST['coupon_code'] ?? '';
            $state = isset($_POST['state']) ? (int)$_POST['state'] : 0;
            if (empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Coupon code is required.']);
                break;
            }
            dbExecute("UPDATE coupons SET is_active = ? WHERE code = ?", [$state, $code]);
            logAction('Toggled Coupon', "Admin toggled coupon status for: $code to $state");
            echo json_encode(['success' => true, 'message' => 'Coupon status updated.']);
            break;

        case 'delete_coupon':
            requireAdmin();
            $code = $_POST['coupon_code'] ?? '';
            if (empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Coupon code is required.']);
                break;
            }
            dbExecute("DELETE FROM coupons WHERE code = ?", [$code]);
            logAction('Deleted Coupon', "Admin deleted coupon: $code");
            echo json_encode(['success' => true, 'message' => 'Coupon code deleted successfully.']);
            break;

        // ── Validate Coupon ───────────────────────────────────────────────────
        case 'validate_coupon':
            $code  = strtoupper(trim($_POST['code'] ?? ''));
            $spend = (float)($_POST['spend'] ?? 0);
            $coupon = dbQueryOne('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND expiry_date >= CURDATE()', [$code]);
            if (!$coupon) {
                echo json_encode(['valid' => false, 'message' => 'Coupon not found or expired.']);
                break;
            }
            if ($coupon['min_spend'] && $spend < $coupon['min_spend']) {
                echo json_encode(['valid' => false, 'message' => 'Minimum spend of ' . formatMWK($coupon['min_spend']) . ' required.']);
                break;
            }
            $discount = $coupon['discount_type'] === 'percentage'
                ? ($spend * $coupon['value']) / 100
                : (float)$coupon['value'];
            echo json_encode([
                'valid'       => true,
                'discount'    => $discount,
                'description' => ($coupon['discount_type'] === 'percentage' ? $coupon['value'] . '% off' : formatMWK($coupon['value']) . ' off') . " ({$coupon['code']})"
            ]);
            break;

        // ── Reply Support Ticket ──────────────────────────────────────────────
        case 'reply_ticket':
            requireAdmin();
            $ticketId = trim($_POST['ticket_id'] ?? '');
            $message  = trim($_POST['message']   ?? '');
            if (empty($message)) { echo json_encode(['success' => false, 'message' => 'Reply cannot be empty.']); break; }
            dbExecute("INSERT INTO ticket_responses (ticket_id, sender, message) VALUES (?, 'System Administrator', ?)", [$ticketId, $message]);
            dbExecute("UPDATE support_tickets SET status='resolved', closed_at = NOW() WHERE id=?", [$ticketId]);
            logAction('Resolved Support Ticket', "Replied to ticket $ticketId and marked Resolved.");
            echo json_encode(['success' => true]);
            break;

        // ── Toggle Wishlist Item ─────────────────────────────────────────────
        case 'toggle_wishlist':
            if (!isLoggedIn() || !hasRole([ROLE_CUSTOMER])) {
                echo json_encode(['success' => false, 'message' => 'Please log in as a customer to save items.']);
                break;
            }
            $listingId = trim($_POST['listing_id'] ?? '');
            if (empty($listingId)) {
                echo json_encode(['success' => false, 'message' => 'Item ID is required.']);
                break;
            }
            $userId = $_SESSION['user_id'];
            $favoritesTable = uthenga_first_existing_table(['favorites', 'wishlist']);
            if ($favoritesTable === '') {
                echo json_encode(['success' => false, 'message' => 'Wishlist storage is unavailable.']);
                break;
            }

            if ($favoritesTable === 'favorites') {
                $itemType = trim($_POST['listing_type'] ?? 'listing');
                if ($itemType === 'accommodation') {
                    $itemType = 'property';
                }
                $exists = dbCount(
                    "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND reference_id = ? AND favorite_type = ?",
                    [$userId, $listingId, $itemType]
                );
                if ($exists > 0) {
                    dbExecute(
                        "DELETE FROM favorites WHERE user_id = ? AND reference_id = ? AND favorite_type = ?",
                        [$userId, $listingId, $itemType]
                    );
                    uthenga_cache_invalidate();
                    echo json_encode(['success' => true, 'added' => false, 'message' => 'Removed from wishlist.']);
                } else {
                    dbExecute(
                        "INSERT INTO favorites (user_id, favorite_type, reference_id, created_at) VALUES (?, ?, ?, NOW())",
                        [$userId, $itemType, $listingId]
                    );
                    uthenga_cache_invalidate();
                    echo json_encode(['success' => true, 'added' => true, 'message' => 'Added to wishlist!']);
                }
            } else {
                $exists = dbCount("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND listing_id = ?", [$userId, $listingId]);
                if ($exists > 0) {
                    dbExecute("DELETE FROM wishlist WHERE user_id = ? AND listing_id = ?", [$userId, $listingId]);
                    uthenga_cache_invalidate();
                    echo json_encode(['success' => true, 'added' => false, 'message' => 'Removed from wishlist.']);
                } else {
                    dbExecute("INSERT INTO wishlist (user_id, listing_id) VALUES (?, ?)", [$userId, $listingId]);
                    uthenga_cache_invalidate();
                    echo json_encode(['success' => true, 'added' => true, 'message' => 'Added to wishlist!']);
                }
            }
            break;

        // ── Toggle User Status (Admin) ─────────────────────────────────────────
        case 'toggle_user_status':
            requireAdmin();
            $userId = trim($_POST['user_id'] ?? '');
            if (empty($userId)) {
                echo json_encode(['success' => false, 'message' => 'User ID is required.']);
                break;
            }
            if ($userId === $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'You cannot suspend your own admin account.']);
                break;
            }
            $user = dbQueryOne("SELECT is_approved, name, role FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                break;
            }
            $newStatus = $user['is_approved'] ? 0 : 1;
            dbExecute("UPDATE users SET is_approved = ? WHERE id = ?", [$newStatus, $userId]);
            
            $act = $newStatus ? 'Activated User' : 'Suspended User';
            logAction($act, "Admin toggled status of user: {$user['name']} ({$user['role']}) to " . ($newStatus ? 'Active' : 'Suspended'));
            
            echo json_encode(['success' => true, 'is_approved' => $newStatus]);
            break;

        // ── Get User Bookings (JSON for AJAX) ──────────────────────────────────
        case 'get_my_bookings':
            if (!isLoggedIn() || !hasRole([ROLE_CUSTOMER])) { echo json_encode(['success' => false]); break; }
            $rows = dbQuery('SELECT * FROM bookings WHERE customer_id = ? ORDER BY created_at DESC', [$_SESSION['user_id']]);
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ── Create Listing (Vendor) ────────────────────────────────────────────
        case 'create_listing':
            requireVendor();
            $title       = trim($_POST['title']       ?? '');
            $description = trim($_POST['description'] ?? '');
            $location    = trim($_POST['location']    ?? '');
            $image       = trim($_POST['image']       ?? '');
            $listingType = trim($_POST['listing_type'] ?? '');

            if (empty($title) || empty($description) || empty($location) || empty($image)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                break;
            }

            $validListingTypes = ['event','accommodation','tour','transport'];
            if (!in_array($listingType, $validListingTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid listing type.']);
                break;
            }

            // Build meta from POST
            $meta = [];
            switch ($listingType) {
                case 'event':
                    $meta = [
                        'date'                  => $_POST['event_date'] ?? '',
                        'time'                  => $_POST['event_time'] ?? '',
                        'category'              => $_POST['event_category'] ?? 'Festival',
                        'vipTicketPrice'        => (int)($_POST['vip_price'] ?? 0),
                        'standardTicketPrice'   => (int)($_POST['std_price'] ?? 0),
                        'vipAvailable'          => (int)($_POST['vip_seats'] ?? 0),
                        'standardAvailable'     => (int)($_POST['std_seats'] ?? 0),
                        'vipTotal'              => (int)($_POST['vip_seats'] ?? 0),
                        'standardTotal'         => (int)($_POST['std_seats'] ?? 0),
                        'venueCapacity'         => (int)($_POST['capacity'] ?? 0),
                    ];
                    break;
                case 'accommodation':
                    $meta = [
                        'category'    => $_POST['accom_category'] ?? 'Hotel',
                        'amenities'   => array_filter(array_map('trim', explode(',', $_POST['amenities'] ?? ''))),
                        'rooms'       => [[
                            'id'            => 'room-' . bin2hex(random_bytes(3)),
                            'name'          => $_POST['room_name']  ?? 'Standard Room',
                            'pricePerNight' => (int)($_POST['price_per_night'] ?? 0),
                            'capacity'      => (int)($_POST['room_capacity'] ?? 2),
                            'availableRooms'=> (int)($_POST['available_rooms'] ?? 5),
                        ]],
                    ];
                    break;
                case 'tour':
                    $meta = [
                        'durationDays'   => (int)($_POST['duration_days'] ?? 1),
                        'maxGroupSize'   => (int)($_POST['max_group']     ?? 10),
                        'pricePerPerson' => (int)($_POST['price_per_person'] ?? 0),
                        'datesAvailable' => array_filter(array_map('trim', explode(',', $_POST['dates_available'] ?? ''))),
                        'itinerary'      => [],
                    ];
                    break;
                case 'transport':
                    $meta = [
                        'vehicleType'    => $_POST['vehicle_type']  ?? 'Coach Bus',
                        'routeFrom'      => $_POST['route_from']    ?? '',
                        'routeTo'        => $_POST['route_to']      ?? '',
                        'departureTime'  => $_POST['depart_time']   ?? '',
                        'arrivalTime'    => $_POST['arrive_time']   ?? '',
                        'pricePerSeat'   => (int)($_POST['price_per_seat'] ?? 0),
                        'totalSeats'     => (int)($_POST['total_seats'] ?? 0),
                        'availableSeats' => (int)($_POST['total_seats'] ?? 0),
                        'scheduleDays'   => array_filter(array_map('trim', explode(',', $_POST['schedule_days'] ?? ''))),
                    ];
                    break;
            }

            $listingId = strtolower(substr($listingType, 0, 3)) . '-' . strtolower(bin2hex(random_bytes(3)));
            dbExecute(
                'INSERT INTO listings (id, listing_type, title, description, location, image, vendor_id, vendor_name, meta)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $listingId, $listingType, $title, $description, $location, $image,
                    $_SESSION['user_id'], $_SESSION['user_name'],
                    json_encode($meta)
                ]
            );
            logAction('Created Listing', "Vendor created new $listingType listing: \"$title\" (ID: $listingId)");
            echo json_encode(['success' => true, 'listing_id' => $listingId, 'message' => 'Listing created successfully!']);
            break;

        // ── Unknown action ─────────────────────────────────────────────────────
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Unknown action: " . e($action)]);
            break;
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $msg = APP_ENV === 'development' ? $e->getMessage() : 'A database error occurred.';
    echo json_encode(['success' => false, 'message' => $msg]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $msg = APP_ENV === 'development' ? $e->getMessage() : 'A server error occurred.';
    echo json_encode(['success' => false, 'message' => $msg]);
}

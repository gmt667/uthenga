<?php
/**
 * Uthenga — Ticket Commerce demo data seed (one-time, idempotent).
 *
 * Generates realistic orders, issued tickets, check-ins, transfers and refunds
 * for published event listings so the Ticket Operations console shows live
 * data. Skips any listing that already has issued tickets.
 *
 * Run: /opt/lampp/bin/php api/tie/vendor/events/_seed_ticket_demo.php
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function seed_val(PDO $db, string $sql, array $params): mixed
{
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

$gateways = ['Airtel Money', 'TNM Mpamba', 'Bank Card', 'Uthenga Pay'];
$reasons = ['Customer cancellation', 'Event time conflict', 'Duplicate purchase', 'Unable to attend', 'Organizer discretion'];
$phoneBase = ['99', '88', '98', '99', '88', '99', '99', '88'];

$customers = [];
$st = $db->query("SELECT id, name, email FROM users WHERE role='Customer' ORDER BY id LIMIT 8");
foreach ($st->fetchAll() as $c) {
    $customers[] = ['id' => $c['id'], 'name' => $c['name'], 'email' => $c['email']];
}
if (count($customers) < 4) {
    $fallback = [
        ['id' => 'demo-c1', 'name' => 'Patrick Byamungu', 'email' => 'patrick@example.mw'],
        ['id' => 'demo-c2', 'name' => 'Mary Moyo', 'email' => 'mary@example.mw'],
        ['id' => 'demo-c3', 'name' => 'John Phiri', 'email' => 'john@example.mw'],
        ['id' => 'demo-c4', 'name' => 'Christopher Banda', 'email' => 'chris@example.mw'],
        ['id' => 'demo-c5', 'name' => 'Grace Malunga', 'email' => 'grace@example.mw'],
        ['id' => 'demo-c6', 'name' => 'David Tembo', 'email' => 'david@example.mw'],
    ];
    $hash = password_hash('DemoPass2026!', PASSWORD_BCRYPT);
    foreach ($fallback as $fc) {
        $exists = seed_val($db, 'SELECT COUNT(*) FROM users WHERE id=?', [$fc['id']]);
        if ((int) $exists === 0) {
            $db->prepare("INSERT INTO users (id, name, email, role, password_hash, created_at) VALUES (?,?,?,'Customer',?,NOW())")
                ->execute([$fc['id'], $fc['name'], $fc['email'], $hash]);
        }
    }
    $customers = $fallback;
}

$listings = $db->query(
    "SELECT l.id, l.title, l.image, l.vendor_id, e.id AS event_id, e.start_date
     FROM listings l
     JOIN tie_events_events e ON e.listing_id = l.id
     WHERE l.listing_type='event' AND l.is_active=1
     ORDER BY l.id"
)->fetchAll();

if (!$listings) { echo "No published event listings found.\n"; exit; }

$insertOrder = $db->prepare(
    "INSERT INTO bookings (id, listing_id, ticket_type_id, quantity, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, details, currency, total_price, commission_paid, discount_amount, tax_amount, commission_amount, payment_status, payment_gateway, booking_status, reference_name, transaction_id, qr_code, booked_at, confirmed_at)
     VALUES (?,?,?,?,?,?,'event',?,?,?,?,?,?,0,0,0,0,?,?,?,?,?,?,?,?)"
);
$insertTicket = $db->prepare(
    "INSERT INTO event_tickets (id, listing_id, ticket_type_id, booking_id, holder_name, holder_email, holder_phone, qr_token, verification_signature, status, checked_in_at, checked_in_by, last_sent_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
);
$insertRefund = $db->prepare(
    "INSERT INTO event_ticket_refunds (id, listing_id, booking_id, ticket_id, amount, currency, reason, status, requested_by, requested_at, decided_at, decided_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
);
$insertTransfer = $db->prepare(
    "INSERT INTO event_ticket_transfers (id, listing_id, ticket_id, from_holder_name, to_holder_name, to_phone, to_email, initiated_by, initiated_by_type, reason, status, created_at, completed_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$insertAudit = $db->prepare(
    "INSERT INTO event_ticket_audit (listing_id, ticket_type_id, ticket_id, booking_id, actor_id, actor_name, action, details, created_at)
     VALUES (?,?,?,?,?,?,?,?,?)"
);
$updateRemaining = $db->prepare('UPDATE ticket_types SET remaining_quantity=?, ticket_status=? WHERE id=?');

$refundSeq = 1;
$transferSeq = 1;
$orderSeq = (int) (seed_val($db, 'SELECT COUNT(*) FROM bookings WHERE id LIKE "BKG-%"', []) + 1);

foreach ($listings as $l) {
    $hasTickets = seed_val($db, 'SELECT COUNT(*) FROM event_tickets WHERE listing_id=?', [$l['id']]);
    if ((int) $hasTickets > 0) { echo "skip {$l['id']} ({$l['title']}) — already seeded\n"; continue; }

    $types = $db->prepare('SELECT * FROM ticket_types WHERE listing_id=? ORDER BY id');
    $types->execute([$l['id']]);
    $types = $types->fetchAll();
    if (!$types) { echo "skip {$l['id']} — no ticket types\n"; continue; }

    mt_srand(crc32($l['id']));
    echo "seeding {$l['id']} ({$l['title']}) — " . count($types) . " types\n";

    $today = new DateTime('today');
    $orders = [];          // bookings rows
    $typeSold = [];        // ticket_type_id => sold
    $typeSeq = [];         // ticket_type_id => issued seq base

    foreach ($types as $t) {
        $cap = (int) $t['total_quantity'];
        $targetPct = $cap > 0 ? mt_rand(35, 92) / 100 : 0;
        $sold = (int) round($cap * $targetPct);
        if ($sold < 0) $sold = 0;
        if ($sold > $cap) $sold = $cap;
        $typeSold[(int) $t['id']] = $sold;
        $typeSeq[(int) $t['id']] = seed_val($db, 'SELECT COUNT(*) FROM event_tickets WHERE ticket_type_id=?', [(int) $t['id']]) + 1;
    }

    $evtCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $l['title']) ?: 'EVT', 0, 3));
    $digest = strtoupper(substr(hash('crc32b', $l['id']), 0, 4));
    $typeRows = array_column($types, null, 'id');

    foreach ($typeSold as $typeId => $totalSold) {
        $t = $typeRows[$typeId];
        $typeCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $t['name']) ?: 'TKT', 0, 3));
        $price = (float) $t['price'];
        $perOrder = array_merge([mt_rand(1, 4)], array_fill(0, 6, mt_rand(1, 5)));
        $placed = 0;
        $orderCount = 0;
        $guard = 0;

        while ($placed < $totalSold && $guard++ < 60) {
            $qty = min($perOrder[$orderCount % count($perOrder)], $totalSold - $placed);
            if ($qty <= 0) break;
            $orderCount++;
            $placed += $qty;
            $c = $customers[array_rand($customers)];
            $phone = '+265 ' . $phoneBase[array_rand($phoneBase)] . ' ' . mt_rand(1000000, 9999999);

            $daysAgo = mt_rand(0, 14);
            $bookedAt = (clone $today)->modify("-{$daysAgo} days " . mt_rand(7, 21) . ":" . mt_rand(0, 59) . ":" . mt_rand(0, 59));
            $gateway = $gateways[array_rand($gateways)];
            $roll = mt_rand(1, 100);
            $payment = $roll <= 88 ? 'Paid' : ($roll <= 95 ? 'Pending' : 'Failed');
            $bookingId = 'BKG-' . strtoupper(bin2hex(random_bytes(4)));
            $amount = $price * $qty;

            $insertOrder->execute([
                $bookingId, $l['id'], $typeId, $qty, $l['title'], $l['image'],
                $c['id'], $c['name'], $c['email'],
                json_encode(['quantity' => $qty, 'ticket_type_id' => $typeId, 'phone' => $phone], JSON_UNESCAPED_SLASHES),
                'MWK', $amount,
                $payment, $gateway, 'confirmed', $l['title'],
                'TXN-' . strtoupper(bin2hex(random_bytes(4))),
                'UTHENGA-' . $bookingId,
                $bookedAt->format('Y-m-d H:i:s'),
                $payment === 'Paid' ? $bookedAt->format('Y-m-d H:i:s') : null,
            ]);

            if ($payment === 'Paid') {
                $orders[] = ['booking_id' => $bookingId, 'type_id' => $typeId, 'type_code' => $typeCode, 'qty' => $qty, 'customer' => $c, 'phone' => $phone, 'booked_at' => $bookedAt];
            }
        }
    }

    // Issue tickets for paid orders
    $checkedIn = [];
    foreach ($orders as $o) {
        $seqBase = $typeSeq[$o['type_id']];
        for ($i = 0; $i < $o['qty']; $i++) {
            $seq = $typeSeq[$o['type_id']]++;
            $ticketId = 'UTH-' . $evtCode . '-' . $o['type_code'] . '-' . $digest . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(24));
            $sig = hash_hmac('sha256', $ticketId . '.' . $token, 'uthenga-tie-ticket-v1');

            $roll = mt_rand(1, 100);
            $checkIn = null;
            if ($roll <= 60) {
                $checkIn = (clone $o['booked_at'])->modify('+' . mt_rand(1, 4) . ' days ' . mt_rand(9, 20) . ':' . mt_rand(0, 59) . ':00');
                if ($checkIn > new DateTime('now')) $checkIn = null;
            }
            $insertTicket->execute([
                $ticketId, $l['id'], $o['type_id'], $o['booking_id'],
                $o['customer']['name'], $o['customer']['email'], $o['phone'],
                $token, $sig,
                $checkIn ? 'CHECKED_IN' : 'ISSUED',
                $checkIn ? $checkIn->format('Y-m-d H:i:s') : null,
                $checkIn ? 'venue-staff-demo' : null,
            ]);
            if ($checkIn) $checkedIn[] = $ticketId;

            $insertAudit->execute([$l['id'], $o['type_id'], $ticketId, $o['booking_id'], null, 'System (payment engine)', 'ticket.issued', json_encode(['ticket_id' => $ticketId]), $o['booked_at']->format('Y-m-d H:i:s')]);
            if ($checkIn) {
                $insertAudit->execute([$l['id'], $o['type_id'], $ticketId, $o['booking_id'], null, 'venue-staff-demo', 'ticket.checked_in', json_encode(['ticket_id' => $ticketId]), $checkIn->format('Y-m-d H:i:s')]);
            }
        }
    }

    // One completed transfer + one processed refund (if enough tickets)
    $issuedAll = $db->prepare("SELECT * FROM event_tickets WHERE listing_id=? AND status='ISSUED' AND checked_in_at IS NULL ORDER BY created_at LIMIT 1");
    $issuedAll->execute([$l['id']]);
    $firstUnused = $issuedAll->fetch();
    if (is_array($firstUnused)) {
        $toName = $customers[array_rand($customers)]['name'];
        $trfId = 'TRF-' . strtoupper(bin2hex(random_bytes(5)));
        $phone = '+265 ' . $phoneBase[array_rand($phoneBase)] . ' ' . mt_rand(1000000, 9999999);
        $insertTransfer->execute([
            $trfId, $l['id'], $firstUnused['id'], $firstUnused['holder_name'], $toName, $phone, null,
            $firstUnused['booking_id'], 'CUSTOMER', 'Attending on behalf of a friend', 'COMPLETED',
            (new DateTime())->modify('-2 days')->format('Y-m-d H:i:s'), (new DateTime())->modify('-1 days')->format('Y-m-d H:i:s'),
        ]);
        $db->prepare('UPDATE event_tickets SET holder_name=? WHERE id=?')->execute([$toName, $firstUnused['id']]);
        $insertAudit->execute([$l['id'], $firstUnused['ticket_type_id'], $firstUnused['id'], $firstUnused['booking_id'], null, 'System', 'ticket.transferred', json_encode(['transfer_id' => $trfId, 'from' => $firstUnused['holder_name'], 'to' => $toName]), (new DateTime())->modify('-1 days')->format('Y-m-d H:i:s')]);
    }

    $issuedSecond = $db->prepare("SELECT * FROM event_tickets WHERE listing_id=? AND status='ISSUED' AND checked_in_at IS NULL ORDER BY created_at DESC LIMIT 1");
    $issuedSecond->execute([$l['id']]);
    $refundTicket = $issuedSecond->fetch();
    if (is_array($refundTicket)) {
        $type = $typeRows[$refundTicket['ticket_type_id']] ?? null;
        $amount = $type ? (float) $type['price'] : 0;
        $rfdId = 'RFD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $when = (new DateTime())->modify('-3 days');
        $insertRefund->execute([
            $rfdId, $l['id'], $refundTicket['booking_id'], $refundTicket['id'], $amount, 'MWK',
            $reasons[array_rand($reasons)], 'PROCESSED', null,
            $when->format('Y-m-d H:i:s'), (new DateTime())->modify('-2 days')->format('Y-m-d H:i:s'), 'finance-engine-demo',
        ]);
        $db->prepare("UPDATE event_tickets SET status='REFUNDED' WHERE id=?")->execute([$refundTicket['id']]);
        $db->prepare("UPDATE bookings SET payment_status='Refunded' WHERE id=?")->execute([$refundTicket['booking_id']]);
        $db->prepare('UPDATE ticket_types SET remaining_quantity = LEAST(remaining_quantity + 1, total_quantity) WHERE id=?')->execute([(int) $refundTicket['ticket_type_id']]);
        $insertAudit->execute([$l['id'], $refundTicket['ticket_type_id'], $refundTicket['id'], $refundTicket['booking_id'], null, 'finance-engine-demo', 'refund.processed', json_encode(['refund_id' => $rfdId, 'amount' => $amount]), (new DateTime())->modify('-2 days')->format('Y-m-d H:i:s')]);
    }

    // Sync inventory to actual sold counts + SOLD_OUT status
    foreach ($typeRows as $t) {
        $sold = seed_val($db, 'SELECT COALESCE(SUM(quantity),0) FROM bookings WHERE listing_id=? AND ticket_type_id=? AND deleted_at IS NULL AND payment_status IN ("Paid","Pending")', [$l['id'], $t['id']]);
        $sold = (int) $sold;
        $remaining = max((int) $t['total_quantity'] - $sold, 0);
        $status = $remaining === 0 && $sold > 0 ? 'SOLD_OUT' : 'ACTIVE';
        $updateRemaining->execute([$remaining, $status, $t['id']]);
    }
}

echo "Done.\n";
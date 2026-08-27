<?php
/**
 * Customer-facing Accommodation checkout — the missing "hold a room, then pay"
 * bridge between the real enterprise inventory engine (UthengaAccommodationService)
 * and the one authoritative Uthenga Payment Engine (UthengaPaymentEngine).
 *
 * Mirrors the hold-then-pay-then-consume sequencing already proven by the TIE
 * trip-planner checkout (includes/tie/Payment.php + Inventory.php), but against
 * UthengaPaymentEngine instead of the TIE payment gateway — no second payment
 * engine is introduced.
 */

final class UthengaAccommodationCheckout
{
    private const HOLD_SECONDS = 900;

    public static function hold(
        string $customerId,
        string $customerName,
        string $customerEmail,
        string $listingId,
        int $roomTypeId,
        int $quantity,
        string $checkIn,
        string $checkOut
    ): array {
        global $pdo;
        $service = new UthengaAccommodationService($pdo);

        $pdo->beginTransaction();
        try {
            self::sweepExpiredHolds($pdo, $service, $roomTypeId);

            $listing = dbQueryOne('SELECT title, image FROM listings WHERE id = ?', [$listingId]);
            if (!$listing) {
                throw UthengaTieErrors::validation(['listing_id' => 'That property could not be found.']);
            }

            $holdId = self::uuidv4();
            $bookingId = 'BKG-' . strtoupper(bin2hex(random_bytes(3)));

            $quote = $service->acquireExternalHold($holdId, $listingId, $roomTypeId, $quantity, $checkIn, $checkOut);
            // The customer is only charged now what the property's rate plan
            // actually requires up front (deposit_required — equal to the full
            // total for a FULL-payment-mode plan). The rest of this method
            // preserves the true subtotal/balance so a later balance payment
            // (see payRemainingBalance()) can be tracked correctly instead of
            // the reservation silently recording the deposit as if it were
            // the whole stay's price.
            $totalPrice = (float) $quote['deposit_required'];
            $subtotal = (float) $quote['total'];
            $paymentMode = strtoupper((string) ($quote['rate_plan']['payment_mode'] ?? 'FULL'));

            $details = json_encode([
                'room_type_id'    => $roomTypeId,
                'check_in_date'   => $checkIn,
                'check_out_date'  => $checkOut,
                'quantity'        => $quantity,
                'nights'          => $quote['nights'],
                'hold_id'         => $holdId,
                'subtotal'        => $subtotal,
                'deposit_required' => $totalPrice,
                'payment_mode'    => $paymentMode,
            ]);

            dbExecute("
                INSERT INTO bookings (
                    id, listing_id, listing_title, listing_image, listing_type,
                    customer_id, customer_name, customer_email, details, total_price, commission_paid,
                    payment_status, booking_status, room_type_id, quantity
                ) VALUES (?,?,?,?,'accommodation',?,?,?,?,?,0,'Pending','pending',?,?)
            ", [
                $bookingId,
                $listingId,
                $listing['title'],
                $listing['image'],
                $customerId,
                $customerName,
                $customerEmail,
                $details,
                $subtotal,
                $roomTypeId,
                $quantity,
            ]);

            // Uses PHP's local time, not gmdate()/UTC — this must match whatever
            // wall-clock MySQL's NOW() returns (its session time_zone is 'SYSTEM'
            // here), since expiry is compared against NOW() in the DB, not in PHP.
            $expiresAt = date('Y-m-d H:i:s', time() + self::HOLD_SECONDS);
            dbExecute("
                INSERT INTO tie_inventory_holds
                    (id, user_id, plan_id, resource_type, resource_id, listing_id, quantity, start_date, end_date, status, booking_id, expires_at)
                VALUES (?, ?, 'direct-accommodation', 'room_type', ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
            ", [
                $holdId,
                $customerId,
                $roomTypeId,
                $listingId,
                $quantity,
                $checkIn,
                $checkOut,
                $bookingId,
                $expiresAt,
            ]);

            $pdo->commit();

            return [
                'success'       => true,
                'booking_id'    => $bookingId,
                'listing_id'    => $listingId,
                'listing_title' => $listing['title'],
                'total_price'   => $totalPrice,
                'subtotal'      => $subtotal,
                'balance_due'   => round($subtotal - $totalPrice, 2),
                'payment_mode'  => $paymentMode,
                'currency'      => $quote['currency'],
                'nights'        => $quote['nights'],
                'expires_at'    => $expiresAt,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Called once UthengaPaymentEngine has verified real payment for this booking. */
    public static function confirmFromPayment(array $intent): void
    {
        global $pdo;
        $bookingId = (string) $intent['booking_id'];
        $service = new UthengaAccommodationService($pdo);

        $pdo->beginTransaction();
        try {
            $hold = dbQueryOne("SELECT id FROM tie_inventory_holds WHERE booking_id = ? AND status = 'ACTIVE' LIMIT 1 FOR UPDATE", [$bookingId]);
            if (!$hold) {
                // No active hold left to consume. Either this booking predates
                // an active hold entirely (a real error), or this is a SECOND
                // payment against the SAME booking — a DEPOSIT-mode reservation
                // paying off the remainder of its balance after the first
                // payment already consumed the hold. Distinguish the two by
                // whether a reservation with an outstanding balance exists.
                $reservation = dbQueryOne("SELECT id, balance_due FROM tie_accommodation_reservations WHERE booking_id = ? LIMIT 1", [$bookingId]);
                if ($reservation && (float) $reservation['balance_due'] > 0.009) {
                    $pdo->rollBack();
                    self::payRemainingBalance($intent, $reservation);
                    return;
                }
                $pdo->rollBack();
                error_log("[AccommodationCheckout] No active hold found for booking $bookingId at payment confirmation — payment was captured but inventory could not be auto-committed. Needs manual reconciliation.");
                return;
            }

            // Merge the ACTUAL amount charged (which for a DEPOSIT-mode rate
            // plan is less than the booking's full subtotal) into the
            // booking's own details JSON — createReservationFromBooking()
            // reads details.amount_paid to record a correct partial payment
            // instead of defaulting to "fully paid".
            $booking = dbQueryOne('SELECT details FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            $bookingDetails = json_decode((string) ($booking['details'] ?? '{}'), true) ?: [];
            $bookingDetails['amount_paid'] = (float) $intent['gross_amount'];
            dbExecute('UPDATE bookings SET details = ? WHERE id = ?', [json_encode($bookingDetails), $bookingId]);

            // confirmUnderlyingBooking() already set bookings.payment_status to
            // 'Paid' unconditionally before calling here — createReservationFromBooking()
            // (invoked by consumeExternalHold() below) copies that column
            // VERBATIM into the reservation's own payment_status, so this must
            // be corrected BEFORE consuming the hold, not after, or the
            // reservation permanently records "Paid" for a deposit-only payment.
            $subtotal = (float) ($bookingDetails['subtotal'] ?? $intent['gross_amount']);
            if ((float) $intent['gross_amount'] < $subtotal - 0.009) {
                dbExecute("UPDATE bookings SET payment_status = 'Partially Paid' WHERE id = ?", [$bookingId]);
            }

            $service->consumeExternalHold($hold['id'], $bookingId, (string) $intent['customer_id']);

            dbExecute("
                UPDATE tie_inventory_holds
                SET status = 'CONSUMED', payment_intent_id = ?, consumed_at = NOW()
                WHERE id = ?
            ", [$intent['id'], $hold['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[AccommodationCheckout] Failed to consume hold for booking $bookingId after verified payment: " . $e->getMessage());
        }
    }

    /**
     * Applies a second (or later) verified payment against a reservation
     * whose deposit already consumed the room hold — the DEPOSIT/PARTIAL
     * "pay the rest of the balance" path. No hold to consume here; this only
     * updates the money already-confirmed booking is tracking.
     */
    private static function payRemainingBalance(array $intent, array $reservation): void
    {
        global $pdo;
        $amount = (float) $intent['gross_amount'];
        $pdo->beginTransaction();
        try {
            $row = dbQueryOne('SELECT * FROM tie_accommodation_reservations WHERE id = ? FOR UPDATE', [$reservation['id']]);
            if (!$row) {
                $pdo->rollBack();
                return;
            }
            // Never trust the caller's balance snapshot once locked — re-read
            // it inside the transaction and clamp so a race can't overpay.
            $applied = min($amount, (float) $row['balance_due']);
            $newPaid = round((float) $row['amount_paid'] + $applied, 2);
            $newBalance = max(0, round((float) $row['subtotal'] - $newPaid, 2));
            $paymentStatus = $newBalance <= 0.009 ? 'paid' : 'partially_paid';

            // No separate transactions row here — confirmUnderlyingBooking()'s
            // generic branch already inserted one for this exact intent_ref
            // (booking_id, customer, gross_amount, receipt) before calling
            // into here; a second INSERT under the same intent_ref would
            // collide on its primary key.
            dbExecute('UPDATE tie_accommodation_reservations SET amount_paid = ?, balance_due = ?, payment_status = ?, version = version + 1 WHERE id = ?', [$newPaid, $newBalance, $paymentStatus, $row['id']]);

            if ($newBalance <= 0.009 && !empty($row['booking_id'])) {
                dbExecute("UPDATE bookings SET payment_status = 'Paid', updated_at = NOW() WHERE id = ?", [$row['booking_id']]);
            }

            $pdo->commit();

            if (function_exists('uthenga_notify_user')) {
                $noun = $newBalance <= 0.009 ? 'in full' : 'in part';
                uthenga_notify_user((string) $intent['customer_id'], 'accommodation', 'Balance payment received', 'MWK ' . number_format($applied, 2) . ' received ' . $noun . ' towards your reservation. ' . ($newBalance > 0.009 ? 'Remaining balance: MWK ' . number_format($newBalance, 2) . '.' : 'Your reservation is now fully paid.'));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[AccommodationCheckout] payRemainingBalance failed for reservation ' . $reservation['id'] . ': ' . $e->getMessage());
        }
    }

    /** Called when UthengaPaymentEngine determines the payment failed/was not verified. */
    public static function releaseFromFailedPayment(array $intent): void
    {
        global $pdo;
        $bookingId = (string) ($intent['booking_id'] ?? '');
        if ($bookingId === '') {
            return;
        }

        $service = new UthengaAccommodationService($pdo);
        $pdo->beginTransaction();
        try {
            $hold = dbQueryOne("SELECT id FROM tie_inventory_holds WHERE booking_id = ? AND status = 'ACTIVE' LIMIT 1 FOR UPDATE", [$bookingId]);
            if ($hold) {
                $service->releaseExternalHold($hold['id'], 'RELEASED');
                dbExecute("UPDATE tie_inventory_holds SET status = 'RELEASED', released_at = NOW() WHERE id = ?", [$hold['id']]);
            }
            dbExecute("UPDATE bookings SET booking_status = 'cancelled', payment_status = 'Failed', updated_at = NOW() WHERE id = ?", [$bookingId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[AccommodationCheckout] Failed to release hold for booking $bookingId after failed payment: " . $e->getMessage());
        }
    }

    /** Lazily releases this room type's own abandoned holds — same on-demand expiry pattern Inventory.php uses. */
    private static function sweepExpiredHolds(PDO $pdo, UthengaAccommodationService $service, int $roomTypeId): void
    {
        $stmt = $pdo->prepare("
            SELECT * FROM tie_inventory_holds
            WHERE resource_type = 'room_type' AND resource_id = ? AND status = 'ACTIVE' AND expires_at <= NOW()
            FOR UPDATE
        ");
        $stmt->execute([$roomTypeId]);
        foreach ($stmt->fetchAll() as $row) {
            $service->releaseExternalHold($row['id'], 'EXPIRED');
            dbExecute("UPDATE tie_inventory_holds SET status = 'EXPIRED', released_at = NOW() WHERE id = ?", [$row['id']]);
            if (!empty($row['booking_id'])) {
                dbExecute("UPDATE bookings SET booking_status = 'cancelled', payment_status = 'Failed', updated_at = NOW() WHERE id = ?", [$row['booking_id']]);
            }
        }
    }

    private static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

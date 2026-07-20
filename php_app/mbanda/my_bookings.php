<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$passengerId = $_SESSION['user_id'];
$msg = '';
$hasRideSharingTables = uthenga_table_exists('ride_sharing_trips') && uthenga_table_exists('ride_sharing_bookings');

if (!$hasRideSharingTables) {
    $msg = 'Mbanda ride-sharing tables are not installed yet.';
} elseif (isset($_POST['cancel_booking_id'])) {
    if (!validateCsrf()) {
        $msg = 'Invalid security token.';
    } else {
        $bookingId = trim($_POST['cancel_booking_id']);
        
        // Fetch booking and verify passenger ownership
        $booking = dbQueryOne("SELECT * FROM ride_sharing_bookings WHERE id = ? AND passenger_id = ? AND status = 'confirmed'", [$bookingId, $passengerId]);
        
        if ($booking) {
            $tripId = $booking['trip_id'];
            $seatsBooked = (int)$booking['seats_booked'];
            
            $pdo = getDB();
            $pdo->beginTransaction();
            try {
                // Cancel booking
                dbExecute("UPDATE ride_sharing_bookings SET status = 'cancelled' WHERE id = ?", [$bookingId]);
                
                // Get current trip stats
                $trip = dbQueryOne("SELECT booked_seats, available_seats FROM ride_sharing_trips WHERE id = ?", [$tripId]);
                if ($trip) {
                    $newBooked = max(0, (int)$trip['booked_seats'] - $seatsBooked);
                    dbExecute("UPDATE ride_sharing_trips SET booked_seats = ?, status = 'open' WHERE id = ?", [$newBooked, $tripId]);
                }
                
                $pdo->commit();
                $msg = 'Booking cancelled successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Cancellation failed: ' . $e->getMessage();
            }
        } else {
            $msg = 'Booking not found or already cancelled.';
        }
    }
}

// Fetch bookings
$bookings = [];
if ($hasRideSharingTables) {
    $sql = "SELECT b.*, t.pickup_location, t.destination, t.departure_datetime, t.driver_name, t.driver_phone, t.price_per_seat 
            FROM ride_sharing_bookings b
            JOIN ride_sharing_trips t ON t.id = b.trip_id
            WHERE b.passenger_id = ?
            ORDER BY t.departure_datetime DESC";
    $bookings = dbQuery($sql, [$passengerId]);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  <div style="margin-bottom: 2rem;">
    <a href="index.php" class="text-muted" style="text-decoration: none;">➔ Back to Ride Sharing</a>
    <h1 class="page-title" style="margin-top: 0.5rem;">My Booked Seats</h1>
    <p class="text-muted">Manage your ride sharing reservations.</p>
  </div>

  <?php if ($msg): ?>
    <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border);">
      <?= e($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="card" style="padding: 3rem; text-align: center; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg);">
      <h3><?= $hasRideSharingTables ? 'No Bookings Found' : 'Mbanda Unavailable' ?></h3>
      <p class="text-muted" style="margin-top: 0.5rem;">
        <?= $hasRideSharingTables ? "You haven't booked any seats yet." : 'Ride-sharing tables are not installed yet.' ?>
      </p>
      <?php if ($hasRideSharingTables): ?>
        <a href="index.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Trips</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive glass-panel" style="padding: 1rem;">
      <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="border-bottom: 1px solid var(--clr-border); font-weight: bold; color: var(--clr-text-soft);">
            <th style="padding: 1rem;">Booking Code</th>
            <th style="padding: 1rem;">Route</th>
            <th style="padding: 1rem;">Driver</th>
            <th style="padding: 1rem;">Departure</th>
            <th style="padding: 1rem; text-align: center;">Seats</th>
            <th style="padding: 1rem;">Total Price</th>
            <th style="padding: 1rem;">Status</th>
            <th style="padding: 1rem; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr style="border-bottom: 1px solid var(--clr-border);">
              <td style="padding: 1rem; font-family: monospace; font-weight: bold; color: var(--clr-cyan);">
                <?= e($b['booking_code']) ?>
              </td>
              <td style="padding: 1rem; font-weight: 500;">
                <?= e($b['pickup_location']) ?> ➔ <?= e($b['destination']) ?>
              </td>
              <td style="padding: 1rem;">
                <?= e($b['driver_name']) ?> <?php if ($b['driver_phone']): ?><br><span style="font-size: 0.8rem; color: var(--clr-text-muted);"><?= e($b['driver_phone']) ?></span><?php endif; ?>
              </td>
              <td style="padding: 1rem;">
                <?= date('d M Y, h:i A', strtotime($b['departure_datetime'])) ?>
              </td>
              <td style="padding: 1rem; text-align: center;">
                <?= e($b['seats_booked']) ?>
              </td>
              <td style="padding: 1rem; font-weight: bold;">
                <?= formatMWK($b['total_price']) ?>
              </td>
              <td style="padding: 1rem;">
                <span class="role-badge" style="background: <?= $b['status'] === 'confirmed' ? 'rgba(16,185,129,0.15); color: #34d399;' : 'rgba(239,68,68,0.15); color: #f87171;' ?>">
                  <?= strtoupper(e($b['status'])) ?>
                </span>
              </td>
              <td style="padding: 1rem; text-align: right;">
                <?php if ($b['status'] === 'confirmed' && strtotime($b['departure_datetime']) > time()): ?>
                  <form method="POST" action="my_bookings.php" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="cancel_booking_id" value="<?= e($b['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel Booking</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted" style="font-size: 0.85rem;">None</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

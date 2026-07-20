<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$driverId = $_SESSION['user_id'];
$msg = '';
$hasRideSharingTables = uthenga_table_exists('ride_sharing_trips');

if (!$hasRideSharingTables) {
    $msg = 'Mbanda ride-sharing tables are not installed yet.';
} elseif (isset($_POST['cancel_trip_id'])) {
    if (!validateCsrf()) {
        $msg = 'Invalid security token.';
    } else {
        $cancelId = trim($_POST['cancel_trip_id']);
        // Verify owner
        $trip = dbQueryOne("SELECT * FROM ride_sharing_trips WHERE id = ? AND driver_id = ?", [$cancelId, $driverId]);
        if ($trip) {
            // Update trip status
            dbExecute("UPDATE ride_sharing_trips SET status = 'cancelled' WHERE id = ?", [$cancelId]);
            // Cancel all bookings
            dbExecute("UPDATE ride_sharing_bookings SET status = 'cancelled' WHERE trip_id = ?", [$cancelId]);
            $msg = 'Trip cancelled successfully.';
        } else {
            $msg = 'Trip not found or unauthorized access.';
        }
    }
}

$trips = $hasRideSharingTables ? dbQuery("SELECT * FROM ride_sharing_trips WHERE driver_id = ? ORDER BY departure_datetime DESC", [$driverId]) : [];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  <div style="margin-bottom: 2rem;">
    <a href="index.php" class="text-muted" style="text-decoration: none;">➔ Back to Mbanda Rides</a>
    <h1 class="page-title" style="margin-top: 0.5rem;">My Offered Rides</h1>
    <p class="text-muted">Manage trips you are driving.</p>
  </div>

  <?php if ($msg): ?>
    <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border);">
      <?= e($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($trips)): ?>
    <div class="card" style="padding: 3rem; text-align: center; background: var(--clr-surface); border: 1px solid var(--clr-border);-radius: var(--radius-lg);">
      <h3><?= $hasRideSharingTables ? 'No Rides Offered Yet' : 'Mbanda Unavailable' ?></h3>
      <p class="text-muted" style="margin-top: 0.5rem;">
        <?= $hasRideSharingTables ? "You haven't posted any ride sharing offers yet." : 'Ride-sharing tables are not installed yet.' ?>
      </p>
      <?php if ($hasRideSharingTables): ?>
        <a href="create_trip.php" class="btn btn-primary" style="margin-top: 1rem;">Offer a Ride</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive glass-panel" style="padding: 1rem;">
      <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="border-bottom: 1px solid var(--clr-border); font-weight: bold; color: var(--clr-text-soft);">
            <th style="padding: 1rem;">Route</th>
            <th style="padding: 1rem;">Departure</th>
            <th style="padding: 1rem;">Price</th>
            <th style="padding: 1rem;">Seats Booked</th>
            <th style="padding: 1rem;">Status</th>
            <th style="padding: 1rem; text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($trips as $trip): ?>
            <tr style="border-bottom: 1px solid var(--clr-border);">
              <td style="padding: 1rem; font-weight: 500;">
                <?= e($trip['pickup_location']) ?> ➔ <?= e($trip['destination']) ?>
              </td>
              <td style="padding: 1rem;">
                <?= date('d M Y, h:i A', strtotime($trip['departure_datetime'])) ?>
              </td>
              <td style="padding: 1rem;">
                <?= formatMWK($trip['price_per_seat']) ?>
              </td>
              <td style="padding: 1rem;">
                <?= e($trip['booked_seats']) ?> / <?= e($trip['available_seats']) ?>
              </td>
              <td style="padding: 1rem;">
                <span class="role-badge" style="background: <?= $trip['status'] === 'open' ? 'rgba(16,185,129,0.15); color: #34d399;' : ($trip['status'] === 'cancelled' ? 'rgba(239,68,68,0.15); color: #f87171;' : 'rgba(59,130,246,0.15); color: #60a5fa;') ?>">
                  <?= strtoupper(e($trip['status'])) ?>
                </span>
              </td>
              <td style="padding: 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                <a href="trip_detail.php?id=<?= e($trip['id']) ?>" class="btn btn-secondary btn-sm">View Passengers</a>
                <?php if ($trip['status'] === 'open'): ?>
                  <form method="POST" action="my_trips.php" onsubmit="return confirm('Are you sure you want to cancel this trip? Passengers will be notified.');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="cancel_trip_id" value="<?= e($trip['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                  </form>
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

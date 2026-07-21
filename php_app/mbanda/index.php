<?php
require_once __DIR__ . '/../config.php';

$pageTitle = 'Mbanda Ride Sharing';
$activeNav = 'mbanda';

if (!function_exists('mbanda_trip_image')) {
    function mbanda_trip_image(array $trip): string {
        $seed = strtolower(trim(
            ($trip['vehicle_make'] ?? '') . ' ' .
            ($trip['vehicle_model'] ?? '') . ' ' .
            ($trip['pickup_location'] ?? '') . ' ' .
            ($trip['destination'] ?? '')
        ));

        if (strpos($seed, 'bus') !== false || strpos($seed, 'coach') !== false) {
            return 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=1200&fit=crop&q=80';
        }
        if (strpos($seed, 'minivan') !== false || strpos($seed, 'hiace') !== false) {
            return 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?w=1200&fit=crop&q=80';
        }
        if (strpos($seed, 'suv') !== false || strpos($seed, '4x4') !== false) {
            return 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1200&fit=crop&q=80';
        }
        return 'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=1200&fit=crop&q=80';
    }
}

$pickup = trim($_GET['pickup'] ?? '');
$dest = trim($_GET['destination'] ?? '');

$hasRideSharingTables = uthenga_table_exists('ride_sharing_trips');
$params = [];
$trips = [];
if ($hasRideSharingTables) {
    $sql = "SELECT * FROM ride_sharing_trips WHERE status = 'open' AND departure_datetime >= NOW()";
    if ($pickup !== '') {
        $sql .= " AND pickup_location LIKE ?";
        $params[] = '%' . $pickup . '%';
    }
    if ($dest !== '') {
        $sql .= " AND destination LIKE ?";
        $params[] = '%' . $dest . '%';
    }
    $sql .= " ORDER BY departure_datetime ASC";
    $trips = dbQuery($sql, $params);
}

if (empty($trips) && $pickup === '' && $dest === '') {
    $trips = [
        [
            'id' => 'mbanda-mock-1',
            'driver_name' => 'Patrick Banda',
            'pickup_location' => 'Lilongwe',
            'destination' => 'Blantyre',
            'departure_datetime' => date('Y-m-d H:i:s', strtotime('+1 day 07:30')),
            'vehicle_color' => 'Silver',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Hiace',
            'vehicle_reg' => 'LL-1234',
            'price_per_seat' => 18000,
            'available_seats' => 14,
            'booked_seats' => 6,
        ],
        [
            'id' => 'mbanda-mock-2',
            'driver_name' => 'Madalitso Phiri',
            'pickup_location' => 'Blantyre',
            'destination' => 'Mangochi',
            'departure_datetime' => date('Y-m-d H:i:s', strtotime('+2 days 09:00')),
            'vehicle_color' => 'White',
            'vehicle_make' => 'Nissan',
            'vehicle_model' => 'Urvan',
            'vehicle_reg' => 'BT-5678',
            'price_per_seat' => 22000,
            'available_seats' => 12,
            'booked_seats' => 4,
        ],
        [
            'id' => 'mbanda-mock-3',
            'driver_name' => 'Grace Chibwe',
            'pickup_location' => 'Mzuzu',
            'destination' => 'Lilongwe',
            'departure_datetime' => date('Y-m-d H:i:s', strtotime('+3 days 06:45')),
            'vehicle_color' => 'Blue',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Noah',
            'vehicle_reg' => 'MZ-9012',
            'price_per_seat' => 25000,
            'available_seats' => 10,
            'booked_seats' => 5,
        ],
    ];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">Mbanda Ride Sharing</h1>
      <p class="text-muted">Offer or find rides across Malawi. Share costs, travel together.</p>
    </div>
    <div class="dashboard-head-meta">
      <?php if (isLoggedIn()): ?>
        <a href="my_trips.php" class="btn btn-secondary btn-sm">My Offered Rides</a>
        <a href="my_bookings.php" class="btn btn-secondary btn-sm">My Booked Seats</a>
      <?php endif; ?>
      <a href="create_trip.php" class="btn btn-primary">Offer a Ride</a>
    </div>
  </div>

  <!-- Hero Banner -->
  <div style="position:relative; overflow:hidden; border-radius:var(--radius-lg); margin-bottom:2rem; min-height:300px; border:1px solid var(--clr-border);">
    <img
      src="<?= e(!empty($trips) ? mbanda_trip_image($trips[0]) : 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1200&fit=crop&q=80') ?>"
      alt="Mbanda Ride Sharing"
      style="width:100%;height:300px;object-fit:cover;display:block;filter:brightness(0.55);">
    <div style="
      position:absolute;inset:0;
      display:flex;flex-direction:column;justify-content:center;align-items:flex-start;
      padding:2.5rem 2.75rem;
      background:linear-gradient(100deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.15) 100%);
    ">
      <span style="
        display:inline-block;font-size:0.7rem;font-weight:700;letter-spacing:.12em;
        text-transform:uppercase;color:var(--clr-accent);background:rgba(230,57,70,0.15);
        border:1px solid rgba(230,57,70,0.35);border-radius:100px;
        padding:.25rem .85rem;margin-bottom:.85rem;
      ">Mbanda Ride Network</span>
      <h2 style="font-size:clamp(1.5rem,3.5vw,2.4rem);font-weight:800;color:#fff;margin:0 0 .6rem;line-height:1.15;max-width:600px;">
        Welcome - Share a Ride,<br>Share the Journey
      </h2>
      <p style="color:rgba(255,255,255,0.78);font-size:1rem;margin:0 0 1.4rem;max-width:500px;line-height:1.55;">
        Find or offer rides across Malawi. Split costs, meet fellow travellers, and arrive together.
      </p>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="create_trip.php" class="btn btn-primary" style="font-weight:700;">Offer a Ride</a>
        <a href="#trips-list" class="btn btn-secondary" style="font-weight:600;">Browse Available Trips</a>
      </div>
    </div>
  </div>

  <!-- Search Filter -->
  <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-md);">
    <form method="GET" action="index.php" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
      <div class="form-group" style="margin: 0;">
        <label class="form-label" for="pickup" style="margin-bottom: 0.5rem; display: block; font-weight: 500;">Pickup Location</label>
        <input type="text" name="pickup" id="pickup" class="form-control" value="<?= e($pickup) ?>" placeholder="e.g. Lilongwe" style="width: 100%; padding: 0.6rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--clr-border); background: var(--clr-surface2); color: var(--clr-text);">
      </div>
      <div class="form-group" style="margin: 0;">
        <label class="form-label" for="destination" style="margin-bottom: 0.5rem; display: block; font-weight: 500;">Destination</label>
        <input type="text" name="destination" id="destination" class="form-control" value="<?= e($dest) ?>" placeholder="e.g. Blantyre" style="width: 100%; padding: 0.6rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--clr-border); background: var(--clr-surface2); color: var(--clr-text);">
      </div>
      <div>
        <button type="submit" class="btn btn-primary" style="height: 42px; padding-left: 2rem; padding-right: 2rem;">Search Trips</button>
      </div>
    </form>
  </div>

  <?php if (!$hasRideSharingTables): ?>
    <div class="card" style="padding:1rem 1.25rem;margin-bottom:1.5rem;border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.08);">
      Mbanda ride-sharing tables are not installed yet. Run the base installer or the ride-sharing migration to enable this feature.
    </div>
  <?php endif; ?>

  <!-- Trip List -->
  <h2 id="trips-list" style="margin-bottom: 1.5rem; font-size: 1.5rem;">Available Trips</h2>

  <?php if (empty($trips)): ?>
    <div class="card" style="padding: 3rem; text-align: center; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg);">
      <div style="font-size: 3rem; margin-bottom: 1rem;">Ride</div>
      <h3>No Trips Available</h3>
      <p class="text-muted" style="margin-top: 0.5rem;">No active trips match your search or date criteria. Be the first to offer one!</p>
      <a href="create_trip.php" class="btn btn-primary" style="margin-top: 1rem;">Offer a Ride</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-3 gap-3">
      <?php foreach ($trips as $trip):
        $seatsLeft = (int)$trip['available_seats'] - (int)$trip['booked_seats'];
        $tripImage = mbanda_trip_image($trip);
      ?>
        <div class="card" style="overflow:hidden; padding: 0; display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
          <img src="<?= e($tripImage) ?>" alt="<?= e($trip['pickup_location'] . ' to ' . $trip['destination']) ?>" style="width:100%;height:170px;object-fit:cover;">
          <div style="padding: 1.5rem; display:flex; flex-direction:column; justify-content:space-between; height:100%;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
              <span class="role-badge role-customer" style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">Driver: <?= e($trip['driver_name']) ?></span>
              <span style="font-weight: 800; color: var(--clr-accent); font-size: 1.25rem;"><?= formatMWK($trip['price_per_seat']) ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--clr-text-muted);">/seat</span></span>
            </div>
            
            <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem;"><?= e($trip['pickup_location']) ?> &rarr; <?= e($trip['destination']) ?></h3>
            
            <div style="font-size: 0.85rem; color: var(--clr-text-soft); margin-bottom: 0.75rem;">
              Date: <?= date('M d, Y', strtotime($trip['departure_datetime'])) ?> at <?= date('h:i A', strtotime($trip['departure_datetime'])) ?>
            </div>
            
            <p style="font-size: 0.85rem; margin-bottom: 1rem; color: var(--clr-text-muted);">
              <strong>Vehicle:</strong> <?= e($trip['vehicle_color']) ?> <?= e($trip['vehicle_make']) ?> <?= e($trip['vehicle_model']) ?> (<?= e($trip['vehicle_reg']) ?>)
            </p>
          </div>

          <div style="border-top: 1px solid var(--clr-border); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <div style="font-size: 0.85rem;">
              <strong>Seats Left:</strong> <span class="<?= $seatsLeft > 0 ? 'text-green' : 'text-red' ?>" style="font-weight: bold;"><?= $seatsLeft ?></span> / <?= e($trip['available_seats']) ?>
            </div>
            <a href="trip_detail.php?id=<?= e($trip['id']) ?>" class="btn btn-secondary btn-sm">Details & Book</a>
          </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'mbanda/create_trip.php';
    redirect(BASE_URL . 'login.php');
}

$hasRideSharingTables = uthenga_table_exists('ride_sharing_trips');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasRideSharingTables) {
    $error = 'Mbanda ride-sharing tables are not installed yet.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $pickup = trim($_POST['pickup'] ?? '');
        $dest = trim($_POST['destination'] ?? '');
        $departDate = trim($_POST['depart_date'] ?? '');
        $departTime = trim($_POST['depart_time'] ?? '');
        $seats = (int)($_POST['seats'] ?? 1);
        $price = (float)($_POST['price'] ?? 0.00);
        $make = trim($_POST['vehicle_make'] ?? '');
        $model = trim($_POST['vehicle_model'] ?? '');
        $color = trim($_POST['vehicle_color'] ?? '');
        $reg = trim($_POST['vehicle_reg'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($pickup === '' || $dest === '' || $departDate === '' || $departTime === '') {
            $error = 'Please fill in pickup, destination, departure date and time.';
        } elseif ($seats < 1) {
            $error = 'Available seats must be at least 1.';
        } elseif ($price < 0) {
            $error = 'Price per seat cannot be negative.';
        } else {
            $departDatetime = $departDate . ' ' . $departTime;
            $tripId = generateId('TRP');
            $driverId = $_SESSION['user_id'];
            $driverName = $_SESSION['user_name'];
            
            // Get driver phone from DB
            $user = dbQueryOne("SELECT phone FROM users WHERE id = ?", [$driverId]);
            $driverPhone = $user['phone'] ?? '';

            $sql = "INSERT INTO ride_sharing_trips (id, driver_id, driver_name, driver_phone, pickup_location, destination, departure_datetime, available_seats, booked_seats, price_per_seat, vehicle_make, vehicle_model, vehicle_color, vehicle_reg, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'open')";
            
            $done = dbExecute($sql, [
                $tripId, $driverId, $driverName, $driverPhone, $pickup, $dest, $departDatetime, $seats, $price, $make, $model, $color, $reg, $desc
            ]);

            if ($done) {
                $success = 'Your ride sharing offer has been successfully created!';
                // Redirect after success
                header('Refresh: 2; URL=my_trips.php');
            } else {
                $error = 'Failed to create trip. Please try again.';
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem; max-width: 680px;">
  <div style="margin-bottom: 2rem;">
    <a href="index.php" class="text-muted" style="font-size: 0.9rem; text-decoration: none;">➔ Back to Ride Sharing</a>
    <h1 class="page-title" style="margin-top: 0.5rem;">Offer a Ride</h1>
    <p class="text-muted">Fill in your travel and vehicle details to list your trip.</p>
  </div>

  <?php if ($error): ?>
    <div class="card text-red" style="padding: 1rem; margin-bottom: 1.5rem; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="card text-green" style="padding: 1rem; margin-bottom: 1.5rem; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2);">
      <?= e($success) ?>. Redirecting to your trips...
    </div>
  <?php endif; ?>

  <?php if (!$hasRideSharingTables): ?>
    <div class="card" style="padding:1rem 1.25rem;margin-bottom:1.5rem;border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.08);">
      Mbanda ride-sharing tables are not installed yet. Run the base installer or the ride-sharing migration to enable ride offers.
    </div>
  <?php endif; ?>

  <form method="POST" action="create_trip.php" class="card" style="padding: 2rem; background: var(--clr-surface); border: 1px solid var(--clr-border);">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

    <h3 style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem; font-size: 1.15rem;">Route & Schedule</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group">
        <label class="form-label" for="pickup">Pickup Location *</label>
        <input type="text" name="pickup" id="pickup" class="form-control" required placeholder="e.g. Lilongwe (Area 18)">
      </div>
      <div class="form-group">
        <label class="form-label" for="destination">Destination *</label>
        <input type="text" name="destination" id="destination" class="form-control" required placeholder="e.g. Blantyre (Limbe)">
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group">
        <label class="form-label" for="depart_date">Departure Date *</label>
        <input type="date" name="depart_date" id="depart_date" class="form-control" required min="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="depart_time">Departure Time *</label>
        <input type="time" name="depart_time" id="depart_time" class="form-control" required>
      </div>
    </div>

    <h3 style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem; font-size: 1.15rem; margin-top: 1.5rem;">Pricing & Capacity</h3>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
      <div class="form-group">
        <label class="form-label" for="seats">Available Seats *</label>
        <input type="number" name="seats" id="seats" class="form-control" min="1" max="15" value="4" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="price">Price Per Seat (MK) *</label>
        <input type="number" name="price" id="price" class="form-control" min="0" step="100" placeholder="e.g. 15000" required>
      </div>
    </div>

    <h3 style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem; font-size: 1.15rem; margin-top: 1.5rem;">Vehicle Details</h3>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group">
        <label class="form-label" for="vehicle_make">Vehicle Make</label>
        <input type="text" name="vehicle_make" id="vehicle_make" class="form-control" placeholder="e.g. Toyota">
      </div>
      <div class="form-group">
        <label class="form-label" for="vehicle_model">Vehicle Model</label>
        <input type="text" name="vehicle_model" id="vehicle_model" class="form-control" placeholder="e.g. Hiace or Vitz">
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group">
        <label class="form-label" for="vehicle_color">Vehicle Color</label>
        <input type="text" name="vehicle_color" id="vehicle_color" class="form-control" placeholder="e.g. White">
      </div>
      <div class="form-group">
        <label class="form-label" for="vehicle_reg">Registration Plate</label>
        <input type="text" name="vehicle_reg" id="vehicle_reg" class="form-control" placeholder="e.g. BU 9982">
      </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
      <label class="form-label" for="description">Additional Notes (e.g. luggage size, route details)</label>
      <textarea name="description" id="description" class="form-control" rows="3" placeholder="Tell passengers about specific stopovers, luggage limits, etc."></textarea>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;" <?= !$hasRideSharingTables ? 'disabled' : '' ?>>Post Offer</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


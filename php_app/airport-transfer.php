<?php
/**
 * Uthenga — Airport Transfer Booking
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pageTitle = 'Book Airport Transfer';
$activeNav = 'transport';

// Check if user is logged in
$isLoggedIn = isLoggedIn();

// Handle form submission
$successMsg = '';
$errorMsg = '';
$bookingCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = 'CSRF token validation failed.';
    } else {
        $transferType = $_POST['transfer_type'] ?? 'arrival';
        $airport = $_POST['airport'] ?? 'Chileka International Airport';
        $destination = trim($_POST['destination_address'] ?? '');
        $pickupDate = $_POST['pickup_date'] ?? '';
        $pickupTime = $_POST['pickup_time'] ?? '';
        $passengers = (int)($_POST['passengers'] ?? 1);
        $luggage = (int)($_POST['luggage_count'] ?? 1);
        $vehicleType = $_POST['vehicle_type'] ?? 'sedan';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($destination === '' || $pickupDate === '' || $pickupTime === '') {
            $errorMsg = 'Please fill in all required fields.';
        } else {
            // Calculate mock fare based on vehicle type and type of transfer
            $baseFare = 35000; // 35k MK default
            if ($vehicleType === 'minivan') $baseFare = 55000;
            if ($vehicleType === 'suv') $baseFare = 75000;
            if ($vehicleType === 'bus') $baseFare = 120000;
            if ($transferType === 'round_trip') $baseFare *= 1.8; // discount for round trip

            $pickupDatetime = $pickupDate . ' ' . $pickupTime . ':00';
            
            // Create core booking record
            $bookingCode = 'AP-' . strtoupper(substr(uniqid(), 7, 5)) . rand(10, 99);
            $userId = $_SESSION['user_id'];
            
            try {
                $hasBChannel = uthenga_column_exists('bookings', 'booking_channel');
                $dbConnection = uthenga_db_is_available() ? $pdo : null;
                
                if ($dbConnection instanceof PDO) {
                    $dbConnection->beginTransaction();
                }
                
                if ($hasBChannel) {
                    // Modern bookings schema
                    dbExecute("INSERT INTO bookings (booking_code, customer_id, booking_channel, booking_status, payment_status, currency, total_amount, grand_total, reference_name) 
                               VALUES (?, ?, 'web', 'pending', 'pending', 'MWK', ?, ?, ?)",
                              [$bookingCode, $userId, $baseFare, $baseFare, 'Airport Transfer (' . $airport . ')']);
                } else {
                    // Traditional bookings schema
                    $detailsJson = json_encode([
                        'destination' => $destination,
                        'pickup_datetime' => $pickupDatetime,
                        'vehicle_type' => $vehicleType,
                        'passengers' => $passengers,
                        'luggage' => $luggage
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    
                    dbExecute("INSERT INTO bookings (id, listing_id, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, details, total_price, commission_paid, payment_status, booking_status) 
                               VALUES (?, ?, ?, ?, 'transport', ?, ?, ?, ?, ?, 0, 'Pending', 'pending')",
                              [
                                  $bookingCode,
                                  'airport_transfer',
                                  'Airport Transfer (' . $airport . ')',
                                  'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&fit=crop&q=80',
                                  $userId,
                                  $_SESSION['user_name'] ?? 'Customer',
                                  $_SESSION['user_email'] ?? 'customer@uthenga.co',
                                  $detailsJson,
                                  $baseFare
                              ]);
                }
                
                $bookingId = dbLastId();
                if ($bookingId === '0' || $bookingId === '') {
                    $bookingId = $bookingCode;
                }
                
                // Create booking item
                dbExecute("INSERT INTO booking_items (booking_id, item_type, reference_id, item_name, quantity, unit_price, subtotal, service_date) 
                           VALUES (?, 'transport_seat', ?, ?, 1, ?, ?, ?)",
                          [$bookingId, 'airport_transfer_' . $bookingId, 'Airport Transfer - ' . $vehicleType, $baseFare, $baseFare, $pickupDate]);
                
                // Create airport transfer detail record
                dbExecute("INSERT INTO airport_transfers (booking_id, passenger_id, transfer_type, airport, destination_address, pickup_datetime, passengers, luggage_count, vehicle_type, fare_mwk, status, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
                          [$bookingId, $userId, $transferType, $airport, $destination, $pickupDatetime, $passengers, $luggage, $vehicleType, $baseFare, $notes]);
                
                if ($dbConnection instanceof PDO) {
                    $dbConnection->commit();
                }
                $successMsg = 'Airport transfer booked successfully! Reference code: ' . $bookingCode;
            } catch (Throwable $e) {
                if ($dbConnection instanceof PDO && $dbConnection->inTransaction()) {
                    $dbConnection->rollBack();
                }
                $errorMsg = 'Booking failed. Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .transfer-hero {
      background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
      color: #fff;
      padding: 3rem 0;
      text-align: center;
      margin-bottom: 2.5rem;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="transfer-hero">
  <div class="container">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">✈️ Airport Transfer Booking</h1>
    <p>Reliable, comfortable, and timely private airport transfers across Malawi.</p>
  </div>
</section>

<main class="container" style="max-width: 800px; padding-bottom: 5rem;">
  <?php if ($successMsg !== ''): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;">
      <div>
        <strong>Success!</strong> <?= e($successMsg) ?><br>
        <span class="text-sm">Please proceed to your dashboard to complete the payment.</span>
        <div style="margin-top: 1rem;">
          <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg !== ''): ?>
    <div class="alert alert-danger" style="margin-bottom: 2rem;">
      <div><strong>Error:</strong> <?= e($errorMsg) ?></div>
    </div>
  <?php endif; ?>

  <?php if (!$isLoggedIn): ?>
    <div class="card" style="padding: 2.5rem; text-align: center;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
      <h3>Authentication Required</h3>
      <p class="text-muted" style="margin-bottom: 1.5rem;">You need to be logged in to book an airport transfer.</p>
      <div style="display: flex; gap: 1rem; justify-content: center;">
        <a href="<?= BASE_URL ?>login.php" class="btn btn-primary">Sign In</a>
        <a href="<?= BASE_URL ?>register.php" class="btn btn-secondary">Register</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card" style="padding: 2rem;">
      <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem;">Schedule Your Transfer</h2>
      
      <form method="POST" action="airport-transfer.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        
        <div class="grid grid-cols-2 gap-4">
          <div class="form-group">
            <label class="form-label" for="transfer_type">Transfer Type</label>
            <select name="transfer_type" id="transfer_type" class="form-control" onchange="updateFares()">
              <option value="arrival">Arrival (Airport to City)</option>
              <option value="departure">Departure (City to Airport)</option>
              <option value="round_trip">Round Trip</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="airport">Select Airport</label>
            <select name="airport" id="airport" class="form-control">
              <option value="Chileka International Airport (BLZ)">Chileka International Airport (Blantyre)</option>
              <option value="Kamuzu International Airport (LLW)">Kamuzu International Airport (Lilongwe)</option>
              <option value="Mzuzu Airport (ZZU)">Mzuzu Airport (Mzuzu)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="destination_address">City Destination / Pickup Address *</label>
          <input type="text" name="destination_address" id="destination_address" class="form-control" required placeholder="e.g. Ryalls Hotel, Blantyre or Area 10, Lilongwe">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="form-group">
            <label class="form-label" for="pickup_date">Pickup Date *</label>
            <input type="date" name="pickup_date" id="pickup_date" class="form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          
          <div class="form-group">
            <label class="form-label" for="pickup_time">Pickup Time *</label>
            <input type="time" name="pickup_time" id="pickup_time" class="form-control" required>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="form-group">
            <label class="form-label" for="vehicle_type">Vehicle Type</label>
            <select name="vehicle_type" id="vehicle_type" class="form-control" onchange="updateFares()">
              <option value="sedan">Sedan (Max 3 seats) - MK 35,000</option>
              <option value="minivan">Minivan (Max 7 seats) - MK 55,000</option>
              <option value="suv">SUV Executive (Max 4 seats) - MK 75,000</option>
              <option value="bus">Coaster Bus (Max 22 seats) - MK 120,000</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="passengers">Passengers</label>
            <input type="number" name="passengers" id="passengers" class="form-control" min="1" max="25" value="1">
          </div>

          <div class="form-group">
            <label class="form-label" for="luggage_count">Luggage Count</label>
            <input type="number" name="luggage_count" id="luggage_count" class="form-control" min="0" max="20" value="1">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="notes">Special Requirements / Flight Number</label>
          <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="e.g. Flight ET821 arriving at 12:30 PM. Need child car seat."></textarea>
        </div>

        <div class="glass-panel" style="padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
          <div class="text-sm text-muted">Estimated Fare</div>
          <div id="estimated-fare" style="font-size: 2rem; font-weight: 800; color: var(--clr-accent);">MK 35,000</div>
          <div class="text-xs text-muted" style="margin-top: 0.25rem;">Includes direct private driver, toll fees, airport entry, and insurance.</div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Book Transfer Now</button>
      </form>
    </div>
  <?php endif; ?>
</main>

<script>
function updateFares() {
  var vType = document.getElementById('vehicle_type').value;
  var tType = document.getElementById('transfer_type').value;
  
  var fare = 35000;
  if (vType === 'minivan') fare = 55000;
  if (vType === 'suv') fare = 75000;
  if (vType === 'bus') fare = 120000;
  
  if (tType === 'round_trip') {
    fare = fare * 1.8;
  }
  
  document.getElementById('estimated-fare').textContent = 'MK ' + fare.toLocaleString();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

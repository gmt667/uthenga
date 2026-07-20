<?php
/**
 * Uthenga — Car Rental System
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pageTitle = 'Car Rental System';
$activeNav = 'transport';

$isLoggedIn = isLoggedIn();
$carRentalTableExists = uthenga_table_exists('car_rental_listings');
$bookingsTableExists = uthenga_table_exists('bookings');
$bookingItemsTableExists = uthenga_table_exists('booking_items');
$carBookingsTableExists = uthenga_table_exists('car_rental_bookings');

// Search / filters
$locationFilter = trim($_GET['location'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$allowedTypes = ['sedan', 'suv', '4x4', 'minivan', 'pickup', 'van', 'hatchback', 'luxury', 'truck'];
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

$cars = [];
$locations = [];
$inventoryAvailable = false;

$mockCars = [
    [
        'id' => 1,
        'vehicle_name' => 'Toyota Land Cruiser Prado',
        'vehicle_type' => '4x4',
        'location' => 'Lilongwe',
        'seats' => 7,
        'transmission' => 'automatic',
        'fuel_type' => 'diesel',
        'price_per_day' => 85000,
        'image_url' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&fit=crop&q=80',
        'features' => json_encode(['4WD Off-Road', 'Air Conditioning', 'Leather Seats', 'USB Port']),
        'with_driver' => 0,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'vehicle_name' => 'Toyota Rav4 SUV',
        'vehicle_type' => 'suv',
        'location' => 'Blantyre',
        'seats' => 5,
        'transmission' => 'automatic',
        'fuel_type' => 'petrol',
        'price_per_day' => 55000,
        'image_url' => 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=600&fit=crop&q=80',
        'features' => json_encode(['SUV Height', 'Air Conditioning', 'Large Trunk', 'Bluetooth']),
        'with_driver' => 0,
        'status' => 'active'
    ],
    [
        'id' => 3,
        'vehicle_name' => 'Toyota Corolla Sedan',
        'vehicle_type' => 'sedan',
        'location' => 'Lilongwe',
        'seats' => 5,
        'transmission' => 'automatic',
        'fuel_type' => 'petrol',
        'price_per_day' => 35000,
        'image_url' => 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&fit=crop&q=80',
        'features' => json_encode(['Fuel Efficient', 'Air Conditioning', 'Comfortable Ride']),
        'with_driver' => 0,
        'status' => 'active'
    ],
];

if ($carRentalTableExists) {
    try {
        $queryStr = "SELECT * FROM car_rental_listings WHERE is_available = 1 AND status = 'active'";
        $params = [];

        if ($locationFilter !== '') {
            $queryStr .= " AND location = ?";
            $params[] = $locationFilter;
        }
        if ($typeFilter !== '') {
            $queryStr .= " AND vehicle_type = ?";
            $params[] = $typeFilter;
        }

        $cars = dbQuery($queryStr, $params) ?: [];
        $locations = dbQuery("SELECT DISTINCT location FROM car_rental_listings WHERE is_available = 1 AND location IS NOT NULL AND location <> '' ORDER BY location") ?: [];
        $inventoryAvailable = true;
    } catch (Throwable $e) {
        error_log('[Car rental] ' . $e->getMessage());
        $cars = [];
        $locations = [];
    }
}

// Fallback to mock cars if DB is missing or empty
if (empty($cars)) {
    $filteredMockCars = [];
    foreach ($mockCars as $car) {
        if ($locationFilter !== '' && stripos($car['location'], $locationFilter) === false) {
            continue;
        }
        if ($typeFilter !== '' && $car['vehicle_type'] !== $typeFilter) {
            continue;
        }
        $filteredMockCars[] = $car;
    }
    $cars = $filteredMockCars;
    $locations = [
        ['location' => 'Lilongwe'],
        ['location' => 'Blantyre'],
        ['location' => 'Zomba'],
        ['location' => 'Mzuzu'],
    ];
    $inventoryAvailable = true;
}

// Handle Booking Submit
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = 'CSRF validation failed.';
    } else {
        $carId = (int)($_POST['car_id'] ?? 0);
        $pickupDate = $_POST['pickup_date'] ?? '';
        $returnDate = $_POST['return_date'] ?? '';
        $pickupLoc = trim($_POST['pickup_location'] ?? '');
        $withDriver = isset($_POST['driver_option']) ? 1 : 0;

        // Fetch vehicle details from DB or mock
        $car = null;
        if ($carRentalTableExists) {
            $car = dbQueryOne("SELECT * FROM car_rental_listings WHERE id = ? AND is_available = 1 AND status = 'active'", [$carId]);
        }
        if (!$car) {
            foreach ($mockCars as $mc) {
                if ($mc['id'] === $carId) {
                    $car = $mc;
                    break;
                }
            }
        }
        
        if (!$car) {
            $errorMsg = 'Vehicle not found.';
        } elseif ($pickupDate === '' || $returnDate === '' || strtotime($pickupDate) === false || strtotime($returnDate) === false) {
            $errorMsg = 'Please specify pickup and return dates.';
        } else {
            $pickupTs = strtotime($pickupDate);
            $returnTs = strtotime($returnDate);
            if ($returnTs <= $pickupTs) {
                $errorMsg = 'Return date must be after pickup date.';
            } elseif (!$bookingsTableExists || !$bookingItemsTableExists || !$carBookingsTableExists) {
                $errorMsg = 'Car rental bookings are currently unavailable because the database tables are missing.';
            } else {
                $days = max(1, (int)(($returnTs - $pickupTs) / 86400));
                $dailyRate = (float)$car['price_per_day'];
                if ($withDriver) {
                    $dailyRate += 15000; // Extra 15k MWK per day for driver
                }
                $totalFare = $dailyRate * $days;
                
                // Generate Booking Code
                $bookingCode = 'CR-' . strtoupper(substr(uniqid(), 7, 5)) . rand(10, 99);
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
                                  [$bookingCode, $userId, $totalFare, $totalFare, 'Car Rental (' . $car['vehicle_name'] . ')']);
                    } else {
                        // Traditional bookings schema
                        $detailsJson = json_encode([
                            'pickup_date' => $pickupDate,
                            'return_date' => $returnDate,
                            'pickup_location' => $pickupLoc,
                            'with_driver' => $withDriver,
                            'vehicle' => $car['vehicle_name']
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        
                        dbExecute("INSERT INTO bookings (id, listing_id, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, details, total_price, commission_paid, payment_status, booking_status) 
                                   VALUES (?, ?, ?, ?, 'transport', ?, ?, ?, ?, ?, 0, 'Pending', 'pending')",
                                  [
                                      $bookingCode,
                                      'car_rental_' . $carId,
                                      'Car Rental (' . $car['vehicle_name'] . ')',
                                      $car['image_url'] ?? '',
                                      $userId,
                                      $_SESSION['user_name'] ?? 'Customer',
                                      $_SESSION['user_email'] ?? 'customer@uthenga.co',
                                      $detailsJson,
                                      $totalFare
                                  ]);
                    }
                    
                    $bookingId = dbLastId();
                    if ($bookingId === '0' || $bookingId === '') {
                        $bookingId = $bookingCode;
                    }
                    
                    // Item
                    dbExecute("INSERT INTO booking_items (booking_id, item_type, reference_id, item_name, quantity, unit_price, subtotal, service_date) 
                               VALUES (?, 'transport_seat', ?, ?, ?, ?, ?, ?)",
                              [$bookingId, 'car_rental_' . $carId, $car['vehicle_name'], $days, $dailyRate, $totalFare, $pickupDate]);
                    
                    // Car booking details
                    dbExecute("INSERT INTO car_rental_bookings (booking_id, car_id, renter_id, pickup_date, return_date, pickup_location, total_days, total_fare, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                              [$bookingId, $carId, $userId, $pickupDate, $returnDate, $pickupLoc, $days, $totalFare]);
                    
                    if ($dbConnection instanceof PDO) {
                        $dbConnection->commit();
                    }
                    $successMsg = 'Car rental booking request submitted successfully! Reference: ' . $bookingCode;
                } catch (Throwable $e) {
                    if ($dbConnection instanceof PDO && $dbConnection->inTransaction()) {
                        $dbConnection->rollBack();
                    }
                    $errorMsg = 'Car rental booking failed. Error: ' . $e->getMessage();
                }
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
    .rental-hero {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #fff;
      padding: 3rem 0;
      text-align: center;
      margin-bottom: 2rem;
    }
    .car-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .car-img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    .car-body {
      padding: 1.5rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .car-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .car-spec-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--clr-text-soft);
      margin-bottom: 1rem;
    }
    .car-price {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--clr-accent);
      margin-top: auto;
      margin-bottom: 1rem;
    }
    .feature-badge {
      display: inline-block;
      padding: 0.15rem 0.5rem;
      background: rgba(6, 182, 212, 0.1);
      color: var(--clr-cyan);
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-right: 0.35rem;
      margin-bottom: 0.35rem;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="rental-hero">
  <div class="container">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">🚗 Car Rental System</h1>
    <p>Rent cars, SUVs, and 4x4 off-roaders in Malawi. Driver and Self-Drive options available.</p>
  </div>
</section>

<div class="container" style="padding-bottom: 5rem;">
  
  <?php if ($successMsg !== ''): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;">
      <div>
        <strong>Booking Created!</strong> <?= e($successMsg) ?><br>
        <span class="text-sm">Check your dashboard to view payment instructions or complete checkout.</span>
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

  <!-- Filters Row -->
  <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="car-rental.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
      <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 180px;">
        <label class="form-label">Location</label>
        <select name="location" class="form-control">
          <option value="">All Locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= e($loc['location']) ?>" <?= $locationFilter === $loc['location'] ? 'selected' : '' ?>><?= e($loc['location']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 180px;">
        <label class="form-label">Vehicle Type</label>
        <select name="type" class="form-control">
          <option value="">All Types</option>
          <option value="sedan" <?= $typeFilter === 'sedan' ? 'selected' : '' ?>>Sedan</option>
          <option value="suv" <?= $typeFilter === 'suv' ? 'selected' : '' ?>>SUV</option>
          <option value="4x4" <?= $typeFilter === '4x4' ? 'selected' : '' ?>>4x4 Off-Road</option>
          <option value="minivan" <?= $typeFilter === 'minivan' ? 'selected' : '' ?>>Minivan</option>
        </select>
      </div>
      
      <button type="submit" class="btn btn-primary">Apply Filters</button>
      <a href="car-rental.php" class="btn btn-secondary">Reset</a>
    </form>
  </div>

  <!-- Listings Grid -->
  <?php if (!$inventoryAvailable): ?>
    <div style="text-align: center; padding: 4rem 0;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">🚗</div>
      <h3>Car rental inventory is not available yet</h3>
      <p class="text-muted">The rental listings table is missing or not configured on this server.</p>
    </div>
  <?php elseif (empty($cars)): ?>
    <div style="text-align: center; padding: 4rem 0;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
      <h3>No vehicles available</h3>
      <p class="text-muted">Try selecting a different location or vehicle class, or check back soon for new inventory.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-3 gap-4">
      <?php foreach ($cars as $car): 
        $features = json_decode($car['features'], true) ?: [];
      ?>
        <div class="car-card">
          <img src="<?= e($car['image_url']) ?>" alt="<?= e($car['vehicle_name']) ?>" class="car-img">
          <div class="car-body">
            <span class="text-xs text-muted" style="text-transform: uppercase; font-weight: bold;"><?= e($car['vehicle_type']) ?></span>
            <h3 class="car-title"><?= e($car['vehicle_name']) ?></h3>
            <div class="car-spec-grid">
              <div>📍 Location: <strong><?= e($car['location']) ?></strong></div>
              <div>👤 Capacity: <strong><?= e($car['seats']) ?> Seats</strong></div>
              <div>⚙️ Gearbox: <strong><?= ucfirst(e($car['transmission'])) ?></strong></div>
              <div>⛽ Fuel: <strong><?= ucfirst(e($car['fuel_type'])) ?></strong></div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <?php foreach ($features as $f): ?>
                <span class="feature-badge"><?= e($f) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="car-price">MK <?= number_format($car['price_per_day']) ?> / day</div>
            
            <?php if ($isLoggedIn): ?>
              <button class="btn btn-primary" style="width: 100%;" onclick='openRentalModal(<?= (int)$car["id"] ?>, <?= json_encode((string)$car["vehicle_name"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= (float)$car["price_per_day"] ?>, <?= (int)$car["with_driver"] ?>)'>Rent This Car</button>
            <?php else: ?>
              <a href="login.php" class="btn btn-primary" style="width: 100%; text-align: center;">Sign In to Rent</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<!-- Rental Modal -->
<?php if ($isLoggedIn): ?>
<div class="modal-overlay" id="rental-modal" role="dialog" aria-hidden="true" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 500;">
  <div class="modal" style="background: var(--clr-surface); padding: 2rem; border-radius: var(--radius-lg); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">
      <h3 id="modal-car-title">Rent Vehicle</h3>
      <button style="background: none; border: none; cursor: pointer; font-size: 1.25rem;" onclick="closeRentalModal()">✕</button>
    </div>
    
    <form method="POST" action="car-rental.php">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="car_id" id="modal-car-id">
      
      <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Pickup Date *</label>
          <input type="date" name="pickup_date" id="r-pickup" class="form-control" required min="<?= date('Y-m-d') ?>" onchange="calculateRentalTotal()">
        </div>
        <div class="form-group">
          <label class="form-label">Return Date *</label>
          <input type="date" name="return_date" id="r-return" class="form-control" required min="<?= date('Y-m-d') ?>" onchange="calculateRentalTotal()">
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label">Pickup / Handover Address *</label>
        <input type="text" name="pickup_location" class="form-control" required placeholder="e.g. City Centre Mall, Lilongwe or Hotel reception">
      </div>
      
      <div class="form-group" id="driver-option-wrap" style="margin-bottom: 1.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
          <input type="checkbox" name="driver_option" id="r-driver" onchange="calculateRentalTotal()">
          <span>Add professional chauffeur / driver (+ MK 15,000 / day)</span>
        </label>
      </div>
      
      <div class="glass-panel" style="padding: 1.25rem; text-align: center; margin-bottom: 1.5rem;">
        <div class="text-xs text-muted">Total Cost Estimation</div>
        <div id="rental-total" style="font-size: 1.8rem; font-weight: 800; color: var(--clr-accent);">MK 0</div>
        <div id="rental-days-calc" class="text-xs text-muted" style="margin-top: 0.25rem;">0 Days at MK 0 / day</div>
      </div>
      
      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Submit Rental Booking</button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
var activePricePerDay = 0;
var activeRequiresDriver = 0;

function openRentalModal(id, title, price, driverRequired) {
  activePricePerDay = price;
  activeRequiresDriver = driverRequired;
  
  document.getElementById('modal-car-id').value = id;
  document.getElementById('modal-car-title').textContent = 'Rent: ' + title;
  
  var driverCheckbox = document.getElementById('r-driver');
  if (driverRequired === 1) {
    driverCheckbox.checked = true;
    driverCheckbox.disabled = true;
  } else {
    driverCheckbox.checked = false;
    driverCheckbox.disabled = false;
  }
  
  document.getElementById('rental-modal').style.display = 'flex';
  calculateRentalTotal();
}

function closeRentalModal() {
  document.getElementById('rental-modal').style.display = 'none';
}

function calculateRentalTotal() {
  var pDate = document.getElementById('r-pickup').value;
  var rDate = document.getElementById('r-return').value;
  var withDriver = document.getElementById('r-driver').checked;
  
  if (!pDate || !rDate) {
    document.getElementById('rental-total').textContent = 'MK 0';
    document.getElementById('rental-days-calc').textContent = 'Please select dates';
    return;
  }
  
  var d1 = new Date(pDate);
  var d2 = new Date(rDate);
  var diffTime = d2 - d1;
  var diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
  
  var baseRate = activePricePerDay;
  if (withDriver) {
    baseRate += 15000;
  }
  
  var total = baseRate * diffDays;
  
  document.getElementById('rental-total').textContent = 'MK ' + total.toLocaleString();
  document.getElementById('rental-days-calc').textContent = diffDays + ' Day(s) at MK ' + baseRate.toLocaleString() + ' / day';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

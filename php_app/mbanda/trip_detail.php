<?php
require_once __DIR__ . '/../config.php';

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

$tripId = trim($_GET['id'] ?? '');
if ($tripId === '') {
    redirect('index.php');
}

$hasRideSharingTables = uthenga_table_exists('ride_sharing_trips') && uthenga_table_exists('ride_sharing_bookings');
if (!$hasRideSharingTables) {
    redirect('index.php');
}

$trip = dbQueryOne("SELECT * FROM ride_sharing_trips WHERE id = ?", [$tripId]);
if (!$trip) {
    redirect('index.php');
}

$seatsLeft = (int)$trip['available_seats'] - (int)$trip['booked_seats'];
$isDriver = isLoggedIn() && ($_SESSION['user_id'] === $trip['driver_id']);

$error = '';
$success = '';

// Handle seat booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_seats'])) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = 'mbanda/trip_detail.php?id=' . $tripId;
        redirect(BASE_URL . 'login.php');
    }
    
    if (!validateCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $seatsBooked = (int)($_POST['seats_booked'] ?? 1);
        $notes = trim($_POST['notes'] ?? '');

        if ($seatsBooked < 1) {
            $error = 'You must book at least 1 seat.';
        } elseif ($seatsBooked > $seatsLeft) {
            $error = 'Only ' . $seatsLeft . ' seats left on this ride.';
        } else {
            $totalPrice = $seatsBooked * (float)$trip['price_per_seat'];
            $bookingId = generateId('RSB');
            $passengerId = $_SESSION['user_id'];
            $passengerName = $_SESSION['user_name'];
            
            // Get phone
            $user = dbQueryOne("SELECT phone FROM users WHERE id = ?", [$passengerId]);
            $passengerPhone = $user['phone'] ?? '';
            $bookingCode = 'MB-' . strtoupper(bin2hex(random_bytes(3)));

            // Begin transaction
            $pdo = getDB();
            $pdo->beginTransaction();

            try {
                // Insert booking
                $sqlInsert = "INSERT INTO ride_sharing_bookings (id, trip_id, passenger_id, passenger_name, passenger_phone, seats_booked, total_price, status, booking_code, notes)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)";
                dbExecute($sqlInsert, [$bookingId, $tripId, $passengerId, $passengerName, $passengerPhone, $seatsBooked, $totalPrice, $bookingCode, $notes]);

                // Update booked seats count
                $newBookedCount = (int)$trip['booked_seats'] + $seatsBooked;
                $newStatus = ($newBookedCount >= (int)$trip['available_seats']) ? 'full' : 'open';
                
                dbExecute("UPDATE ride_sharing_trips SET booked_seats = ?, status = ? WHERE id = ?", [$newBookedCount, $newStatus, $tripId]);

                $pdo->commit();
                $success = 'Successfully booked ' . $seatsBooked . ' seat(s)!';
                $seatsLeft = (int)$trip['available_seats'] - $newBookedCount;
                $trip['booked_seats'] = $newBookedCount;
                $trip['status'] = $newStatus;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Booking failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch passengers if viewing driver-side details
$bookings = [];
if ($isDriver) {
    $bookings = dbQuery("SELECT * FROM ride_sharing_bookings WHERE trip_id = ? AND status <> 'cancelled'", [$tripId]);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .seat-map {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.75rem;
      margin: 1rem 0 1.5rem;
      padding: 1.25rem;
      background: var(--clr-surface2);
      border-radius: var(--radius-md);
      justify-items: center;
      border: 1px solid var(--clr-border);
    }
    .seat-item {
      width: 44px;
      height: 44px;
      background: var(--clr-bg);
      border: 2px solid var(--clr-border);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }
    .seat-item.booked {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.3);
      color: var(--clr-text-soft);
      cursor: not-allowed;
    }
    .seat-item.selected {
      background: var(--clr-primary);
      border-color: var(--clr-primary);
      color: #fff;
      box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
    }
    .seat-item.driver {
      background: #374151;
      border-color: #374151;
      color: #fff;
      cursor: not-allowed;
    }
  </style>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem; max-width: 900px;">
  <div style="margin-bottom: 2rem;">
    <a href="index.php" class="text-muted" style="text-decoration: none;">âž” Back to Ride Sharing</a>
    <h1 class="page-title" style="margin-top: 0.5rem;"><?= e($trip['pickup_location']) ?> to <?= e($trip['destination']) ?></h1>
  </div>

  <?php if ($error): ?>
    <div class="card text-red" style="padding: 1rem; margin-bottom: 1.5rem; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="card text-green" style="padding: 1rem; margin-bottom: 1.5rem; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2);">
      <?= e($success) ?> Keep this tab open or check <a href="my_bookings.php" style="font-weight: bold; color: inherit;">My Booked Seats</a> for details.
    </div>
  <?php endif; ?>

  <div class="glass-panel" style="padding:0; overflow:hidden; margin-bottom:1.5rem; border:1px solid var(--clr-border);">
    <div style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,0.9fr);align-items:stretch;">
      <div style="padding:1.5rem;">
        <span class="section-label">MBANDA TRIP</span>
        <h2 style="font-size:1.6rem;margin:0.5rem 0 0.75rem;"><?= e($trip['pickup_location']) ?> to <?= e($trip['destination']) ?></h2>
        <p class="text-muted" style="margin:0;">View trip details, passenger seats, and route notes with a visual preview of the ride.</p>
      </div>
      <img src="<?= e(mbanda_trip_image($trip)) ?>" alt="<?= e($trip['pickup_location'] . ' to ' . $trip['destination']) ?>" style="width:100%;height:100%;min-height:220px;object-fit:cover;">
    </div>
  </div>

  <div class="grid grid-cols-3 gap-3">
    <!-- Left details panel -->
    <div class="card" style="padding: 1.5rem; grid-column: span 2; background: var(--clr-surface); border: 1px solid var(--clr-border);">
      <h3 style="margin-bottom: 1rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Trip Information</h3>
      
      <div style="margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
        <div><strong>Driver Name:</strong> <?= e($trip['driver_name']) ?></div>
        <?php if (isLoggedIn() && $trip['driver_phone']): ?>
          <div><strong>Driver Contact:</strong> <?= e($trip['driver_phone']) ?></div>
        <?php endif; ?>
        <div><strong>Departure:</strong> <?= date('d M Y, h:i A', strtotime($trip['departure_datetime'])) ?></div>
        <div><strong>Price:</strong> <?= formatMWK($trip['price_per_seat']) ?> per seat</div>
        <div><strong>Vehicle Info:</strong> <?= e($trip['vehicle_color']) ?> <?= e($trip['vehicle_make']) ?> <?= e($trip['vehicle_model']) ?> (<?= e($trip['vehicle_reg'] ?: 'N/A') ?>)</div>
      </div>

      <?php if ($trip['description']): ?>
        <div style="margin-top: 1rem; padding: 1rem; background: var(--clr-surface2); border-radius: var(--radius-sm);">
          <strong>Driver's Note:</strong>
          <p style="margin-top: 0.5rem; font-size: 0.9rem;"><?= nl2br(e($trip['description'])) ?></p>
        </div>
      <?php endif; ?>

      <!-- Passenger details if logged in driver -->
      <?php if ($isDriver): ?>
        <h3 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Passengers (<?= count($bookings) ?>)</h3>
        <?php if (empty($bookings)): ?>
          <p class="text-muted">No seats booked yet.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
              <tr style="border-bottom: 1px solid var(--clr-border); font-weight: bold; color: var(--clr-text-soft);">
                <th style="padding: 0.5rem 0;">Passenger Name</th>
                <th style="padding: 0.5rem 0;">Phone</th>
                <th style="padding: 0.5rem 0; text-align: center;">Seats</th>
                <th style="padding: 0.5rem 0; text-align: right;">Total Paid</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <tr style="border-bottom: 1px solid var(--clr-border);">
                  <td style="padding: 0.5rem 0;"><?= e($b['passenger_name']) ?></td>
                  <td style="padding: 0.5rem 0;"><?= e($b['passenger_phone'] ?: 'N/A') ?></td>
                  <td style="padding: 0.5rem 0; text-align: center;"><?= e($b['seats_booked']) ?></td>
                  <td style="padding: 0.5rem 0; text-align: right;"><?= formatMWK($b['total_price']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Booking panel -->
    <div>
      <?php if (!$isDriver): ?>
        <div class="card" style="padding: 1.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border);">
          <h3 style="margin-bottom: 1rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Book Seats</h3>
          
          <?php if ($trip['status'] !== 'open' || $seatsLeft <= 0): ?>
            <div style="text-align: center; padding: 1.5rem 0;">
              <span class="role-badge" style="background: rgba(239,68,68,0.15); color: #f87171; padding: 0.4rem 1rem; border-radius: 20px; font-weight: bold;">RIDE FULL / CLOSED</span>
              <p class="text-muted" style="margin-top: 1rem; font-size: 0.85rem;">No seats are currently available for this trip.</p>
            </div>
          <?php else: ?>
            <form method="POST" action="trip_detail.php?id=<?= e($tripId) ?>" id="mbanda-booking-form">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
              <input type="hidden" name="seats_booked" id="hidden_seats_booked" value="1">
              
              <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Select Your Seat(s)</label>
                <div class="seat-map">
                  <!-- Driver Cabin Row -->
                  <div class="seat-item driver" title="Driver seat">D</div>
                  <div style="width: 44px; height: 44px;"></div> <!-- gap -->
                  
                  <?php 
                  $totalSeats = (int)$trip['available_seats'];
                  $bookedCount = (int)$trip['booked_seats'];
                  
                  // Render passenger seats
                  for ($i = 1; $i <= $totalSeats; $i++): 
                    $isBooked = ($i <= $bookedCount);
                    $class = $isBooked ? 'booked' : 'available';
                    $title = $isBooked ? 'Booked' : 'Seat ' . $i;
                  ?>
                    <div class="seat-item <?= $class ?>" data-seat-num="<?= $i ?>" title="<?= $title ?>">
                      <?= $i ?>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>

              <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="notes">Notes for Driver (Optional)</label>
                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="e.g. luggage info or stopover request"></textarea>
              </div>

              <div style="background: var(--clr-surface2); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <span>Total Cost:</span>
                <strong id="total_price_display" style="font-size: 1.2rem; color: var(--clr-accent);"><?= formatMWK($trip['price_per_seat']) ?></strong>
              </div>

              <button type="submit" name="book_seats" class="btn btn-primary" style="width: 100%;">Book Seats Now</button>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="card" style="padding: 1.5rem; background: rgba(59,130,246,0.05); border: 1px dashed var(--clr-blue); text-align: center;">
          <div style="font-size: 2rem; margin-bottom: 0.5rem;">ðŸ”‘</div>
          <strong>You are the driver</strong>
          <p class="text-muted" style="font-size: 0.85rem; margin-top: 0.5rem;">You are managing this trip. You cannot book seats on your own ride.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var pricePerSeat = <?= (float)$trip['price_per_seat'] ?>;
var selectedSeats = [];

document.querySelectorAll('.seat-item.available').forEach(seat => {
  // Select first available seat by default on load
  if (selectedSeats.length === 0) {
    seat.classList.add('selected');
    selectedSeats.push(seat.dataset.seatNum);
  }
  
  seat.addEventListener('click', function() {
    var seatNum = this.dataset.seatNum;
    var idx = selectedSeats.indexOf(seatNum);
    
    if (idx > -1) {
      // Toggle off only if we have more than 1 selected
      if (selectedSeats.length > 1) {
        this.classList.remove('selected');
        selectedSeats.splice(idx, 1);
      }
    } else {
      this.classList.add('selected');
      selectedSeats.push(seatNum);
    }
    
    // Update hidden inputs and display totals
    document.getElementById('hidden_seats_booked').value = selectedSeats.length;
    var total = pricePerSeat * selectedSeats.length;
    document.getElementById('total_price_display').textContent = 'MK ' + total.toLocaleString();
  });
});

// Initial update
var total = pricePerSeat * selectedSeats.length;
document.getElementById('total_price_display').textContent = 'MK ' + total.toLocaleString();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


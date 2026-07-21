<?php
/**
 * Uthenga — Driver Profile & Ratings Verification
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/malawi_locations.php';

$pageTitle = 'Driver Profile & Verification';
$activeNav = 'transport';

$driverId = (int)($_GET['id'] ?? 0);

// Populate mock driver if none exist
$driverCount = dbCount("SELECT COUNT(*) FROM driver_profiles");
if ($driverCount === 0) {
    // Get users with role Vendor or Customer
    $users = dbQuery("SELECT id, full_name, email FROM users LIMIT 3");
    if (!empty($users)) {
        $vendor = dbQueryOne("SELECT id FROM vendors LIMIT 1");
        $vendorId = $vendor ? (int)$vendor['id'] : 1;
        
        $names = ['Chimwemwe Phiri', 'Limbani Banda', 'Wongani Msiska'];
        $licenses = ['LL-9831A', 'BT-7721B', 'MZ-1122C'];
        
        foreach ($users as $idx => $u) {
            $code = 'DRV-' . rand(100, 999);
            $name = $names[$idx % count($names)];
            dbExecute("INSERT INTO driver_profiles (user_id, vendor_id, driver_code, license_number, license_class, years_experience, bio, photo_url, rating_average, rating_count, total_trips, is_verified, status) 
                       VALUES (?, ?, ?, ?, 'Class PG', ?, ?, ?, 4.8, 12, 142, 1, 'active')",
                      [$u['id'], $vendorId, $code, $licenses[$idx], 5 + $idx, "Professional driver with over " . (5 + $idx) . " years of experience navigating Malawi inter-city roads.", "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&fit=crop&q=60", 4 + ($idx * 0.4)]);
        }
    }
}

$driver = null;
if ($driverId > 0) {
    $driver = dbQueryOne("
        SELECT d.*, u.full_name, u.email, u.phone, v.business_name
        FROM driver_profiles d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN vendors v ON v.id = d.vendor_id
        WHERE d.id = ?
    ", [$driverId]);
} else {
    // Fetch all drivers
    $drivers = dbQuery("
        SELECT d.*, u.full_name, v.business_name
        FROM driver_profiles d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN vendors v ON v.id = d.vendor_id
        WHERE d.status = 'active'
    ") ?: [];
}

// Handle review submit
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $ratingVal = (int)($_POST['rating'] ?? 5);
    $commentVal = trim($_POST['comment'] ?? '');
    $targetDriverId = (int)($_POST['driver_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if ($targetDriverId > 0 && $ratingVal >= 1 && $ratingVal <= 5) {
        dbExecute("INSERT INTO driver_ratings (driver_id, rater_id, rating, comment) VALUES (?, ?, ?, ?)",
                  [$targetDriverId, $userId, $ratingVal, $commentVal]);
        
        // Recalculate average rating
        $stats = dbQueryOne("SELECT COUNT(*) as cnt, AVG(rating) as avg FROM driver_ratings WHERE driver_id = ?", [$targetDriverId]);
        if ($stats) {
            dbExecute("UPDATE driver_profiles SET rating_average = ?, rating_count = ? WHERE id = ?",
                      [$stats['avg'], $stats['cnt'], $targetDriverId]);
        }
        
        $successMsg = 'Thank you for submitting your driver rating!';
        // Refresh page data
        if ($driverId === $targetDriverId) {
            $driver = dbQueryOne("
                SELECT d.*, u.full_name, u.email, u.phone, v.business_name
                FROM driver_profiles d
                JOIN users u ON u.id = d.user_id
                LEFT JOIN vendors v ON v.id = d.vendor_id
                WHERE d.id = ?
            ", [$driverId]);
        }
    } else {
        $errorMsg = 'Invalid rating score.';
    }
}

// Fetch driver ratings
$ratings = [];
if ($driver) {
    $ratings = dbQuery("
        SELECT r.*, u.full_name 
        FROM driver_ratings r
        JOIN users u ON u.id = r.rater_id
        WHERE r.driver_id = ?
        ORDER BY r.created_at DESC
    ", [$driver['id']]) ?: [];
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
    .drv-hero {
      background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);
      color: #fff;
      padding: 3rem 0;
      text-align: center;
      margin-bottom: 2rem;
    }
    .profile-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
    }
    .badge-verified {
      background: #10b981;
      color: #fff;
      padding: 0.25rem 0.75rem;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }
    .badge-pending {
      background: #f59e0b;
      color: #fff;
      padding: 0.25rem 0.75rem;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 700;
    }
    .review-item {
      padding: 1rem 0;
      border-bottom: 1px solid var(--clr-border);
    }
    .review-item:last-child {
      border-bottom: none;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="drv-hero">
  <div class="container">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Verified Driver Portal</h1>
    <p>Ensuring passenger safety through verification checklists, driver documents review, and ratings.</p>
  </div>
</section>

<div class="container" style="padding-bottom: 5rem;">

  <div class="glass-panel" style="padding:1.5rem;margin-bottom:2rem;">
    <div class="page-header">
      <div>
        <h2 class="page-title" style="font-size:1.35rem;">Popular Routes & City Hubs</h2>
        <p class="text-muted">Mock district cards with images for the transport section and route inspiration.</p>
      </div>
    </div>
    <div class="grid grid-cols-5 gap-4">
      <?php foreach (array_slice(uthenga_malawi_featured_cities(), 0, 5) as $city): ?>
        <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($city['city']) ?>" class="card" style="overflow:hidden;display:block;text-decoration:none;color:inherit;">
          <img src="<?= e($city['image']) ?>" alt="<?= e($city['city']) ?>" loading="lazy" style="width:100%;height:120px;object-fit:cover;">
          <div style="padding:0.9rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--clr-accent);"><?= e($city['district']) ?></div>
            <strong style="display:block;margin-top:.25rem;"><?= e($city['city']) ?></strong>
            <p class="text-xs text-muted" style="margin:0.35rem 0 0;"><?= e($city['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($successMsg !== ''): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= e($successMsg) ?></div>
  <?php endif; ?>
  
  <?php if ($errorMsg !== ''): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;"><?= e($errorMsg) ?></div>
  <?php endif; ?>

  <?php if ($driver): ?>
    
    <!-- Driver Profile Details -->
    <div class="profile-card">
      <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        <img src="<?= e($driver['photo_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400') ?>" alt="<?= e($driver['full_name']) ?>" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--clr-border);">
        
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
            <h2 style="margin: 0; font-size: 1.8rem;"><?= e($driver['full_name']) ?></h2>
            <?php if ($driver['is_verified']): ?>
              <span class="badge-verified">✓ Verified Driver</span>
            <?php else: ?>
              <span class="badge-pending">Pending Verification</span>
            <?php endif; ?>
          </div>
          
          <div class="text-sm text-muted" style="margin-bottom: 1rem;">Driver Code: <strong><?= e($driver['driver_code']) ?></strong> | Associated Provider: <strong><?= e($driver['business_name'] ?: 'Independent') ?></strong></div>
          
          <p style="font-size: 1rem; margin-bottom: 1.5rem; max-width: 600px;"><?= e($driver['bio']) ?></p>
          
          <div class="grid grid-cols-4 gap-4" style="text-align: center; background: var(--clr-surface2); padding: 1rem; border-radius: var(--radius-md);">
            <div>
              <div style="font-size: 1.5rem; font-weight: 800; color: var(--clr-primary);"><?= e($driver['years_experience']) ?></div>
              <div class="text-xs text-muted">Years Exp.</div>
            </div>
            <div>
              <div style="font-size: 1.5rem; font-weight: 800; color: var(--clr-primary);"><?= e($driver['total_trips']) ?></div>
              <div class="text-xs text-muted">Total Trips</div>
            </div>
            <div>
              <div style="font-size: 1.5rem; font-weight: 800; color: var(--clr-accent);">★ <?= number_format((float)$driver['rating_average'], 1) ?></div>
              <div class="text-xs text-muted">Rating (<?= e($driver['rating_count']) ?>)</div>
            </div>
            <div>
              <div style="font-size: 1.5rem; font-weight: 800; color: var(--clr-primary);"><?= e($driver['license_class'] ?: 'N/A') ?></div>
              <div class="text-xs text-muted">License Class</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Verification Checklist Section -->
    <div class="card" style="padding: 2rem; margin-bottom: 2rem;">
      <h3 style="margin-bottom: 1rem;">🛡️ Safety & Verification Standards</h3>
      <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1.5rem;">Uthenga verifies drivers through a multi-stage security check before allowing bookings.</p>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(16, 182, 129, 0.05); border-radius: var(--radius-sm); border: 1px solid rgba(16, 182, 129, 0.2);">
          <span style="font-size: 1.25rem;">✅</span>
          <div>
            <strong>Identity & Criminal Record Check</strong>
            <div class="text-xs text-muted">Verified by Malawian Police Service records.</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(16, 182, 129, 0.05); border-radius: var(--radius-sm); border: 1px solid rgba(16, 182, 129, 0.2);">
          <span style="font-size: 1.25rem;">✅</span>
          <div>
            <strong>License Validity Check</strong>
            <div class="text-xs text-muted">Chauffeur license is valid and active.</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(16, 182, 129, 0.05); border-radius: var(--radius-sm); border: 1px solid rgba(16, 182, 129, 0.2);">
          <span style="font-size: 1.25rem;">✅</span>
          <div>
            <strong>Road Safety & Vehicle Inspection</strong>
            <div class="text-xs text-muted">Checked for standard road safety equipment.</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(16, 182, 129, 0.05); border-radius: var(--radius-sm); border: 1px solid rgba(16, 182, 129, 0.2);">
          <span style="font-size: 1.25rem;">✅</span>
          <div>
            <strong>Customer Service & Ethics Training</strong>
            <div class="text-xs text-muted">Completed local hospitality and ethics training.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Passenger Feedback & Rating submission -->
    <div class="grid grid-cols-2 gap-4">
      
      <!-- Ratings List -->
      <div class="card" style="padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Reviews & Feedback</h3>
        <?php if (empty($ratings)): ?>
          <p class="text-muted" style="text-align: center; padding: 2rem 0;">No passenger reviews submitted yet.</p>
        <?php else: ?>
          <div>
            <?php foreach ($ratings as $r): ?>
              <div class="review-item">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <strong><?= e($r['full_name']) ?></strong>
                  <span style="color: var(--clr-accent); font-weight: 700;">★ <?= e($r['rating']) ?>/5</span>
                </div>
                <p style="font-size: 0.9rem; margin-top: 0.25rem; color: var(--clr-text-soft);"><?= e($r['comment']) ?></p>
                <div class="text-xs text-muted" style="margin-top: 0.25rem;"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Add Review Form -->
      <div class="card" style="padding: 2rem;">
        <h3 style="margin-bottom: 1rem;">Rate Driver</h3>
        <?php if (isLoggedIn()): ?>
          <form method="POST" action="driver-profile.php?id=<?= $driver['id'] ?>">
            <input type="hidden" name="driver_id" value="<?= $driver['id'] ?>">
            
            <div class="form-group">
              <label class="form-label">Select Star Rating</label>
              <select name="rating" class="form-control">
                <option value="5">★★★★★ (5 Stars)</option>
                <option value="4">★★★★☆ (4 Stars)</option>
                <option value="3">★★★☆☆ (3 Stars)</option>
                <option value="2">★★☆☆☆ (2 Stars)</option>
                <option value="1">★☆☆☆☆ (1 Star)</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Review / Comment</label>
              <textarea name="comment" class="form-control" rows="4" required placeholder="Describe your travel experience with this driver..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Rating</button>
          </form>
        <?php else: ?>
          <div style="text-align: center; padding: 2rem 0;">
            <p class="text-muted">You must be logged in to leave reviews.</p>
            <a href="login.php" class="btn btn-secondary btn-sm">Sign In</a>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <div style="margin-top: 2rem; text-align: center;">
      <a href="driver-profile.php" class="btn btn-secondary">← Back to Driver Registry</a>
    </div>

  <?php else: ?>

    <!-- Driver Registry List -->
    <h2 style="margin-bottom: 1.5rem;">Registered & Verified Drivers</h2>
    
    <div class="grid grid-cols-3 gap-4">
      <?php foreach ($drivers as $d): ?>
        <div class="card" style="padding: 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center;">
          <img src="<?= e($d['photo_url'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400') ?>" alt="<?= e($d['full_name']) ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--clr-border); margin-bottom: 1rem;">
          
          <div style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.25rem;"><?= e($d['full_name']) ?></div>
          <div class="text-xs text-muted" style="margin-bottom: 0.5rem;">Code: <?= e($d['driver_code']) ?></div>
          
          <div style="font-size: 1.1rem; font-weight: bold; color: var(--clr-accent); margin-bottom: 0.5rem;">★ <?= number_format((float)$d['rating_average'], 1) ?> (<?= e($d['rating_count']) ?>)</div>
          
          <p class="text-sm text-muted" style="margin-bottom: 1.5rem; line-height: 1.5; flex: 1;"><?= e(substr($d['bio'], 0, 100)) ?>...</p>
          
          <a href="driver-profile.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%;">View Credentials & Reviews</a>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

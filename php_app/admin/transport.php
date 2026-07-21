<?php
/**
 * Uthenga — Admin Transport Management (Phase 2)
 * Full implementation: listings table, seat class management
 */
$pageTitle = 'Transport Management';
$activeNav = 'admin-transport';

require_once __DIR__ . '/includes/admin_header.php';

$search    = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$actionMsg = '';
$actionErr = '';

$tab = $_GET['tab'] ?? 'commercial';

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $postAction = $_POST['post_action'] ?? '';
    
    if ($postAction === 'cancel_mbanda_trip') {
        $tripId = trim($_POST['trip_id'] ?? '');
        if ($tripId) {
            dbExecute("UPDATE ride_sharing_trips SET status = 'cancelled' WHERE id = ?", [$tripId]);
            dbExecute("UPDATE ride_sharing_bookings SET status = 'cancelled' WHERE trip_id = ?", [$tripId]);
            logAction('Cancel Mbanda Trip', "Trip ID: $tripId");
            $actionMsg = 'Mbanda Ride Sharing trip cancelled.';
        }
    } else {
        $lid  = trim($_POST['listing_id'] ?? '');
        if ($postAction === 'toggle_active' && $lid) {
            $cur = dbQueryOne('SELECT is_active FROM listings WHERE id=? AND listing_type="transport"', [$lid]);
            if ($cur) {
                $new = $cur['is_active'] ? 0 : 1;
                dbExecute('UPDATE listings SET is_active=? WHERE id=?', [$new, $lid]);
                logAction('Transport Status', "Listing $lid active=" . ($new ? 'Yes' : 'No'));
                $actionMsg = 'Transport status updated.';
            }
        } elseif ($postAction === 'add_seat_class' && $lid) {
            $scName  = trim($_POST['sc_name'] ?? '');
            $scPrice = (float)($_POST['sc_price'] ?? 0);
            $scSeats = max(0, (int)($_POST['sc_seats'] ?? 0));
            $scDesc  = trim($_POST['sc_desc'] ?? '');
            if ($scName) {
                dbExecute(
                    "INSERT INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats) VALUES (?,?,?,?,?,?)",
                    [$lid, $scName, $scDesc, $scPrice, $scSeats, $scSeats]
                );
                logAction('Added Seat Class', "Added $scName to transport $lid");
                $actionMsg = "Seat class \"$scName\" added.";
            } else { $actionErr = 'Class name required.'; }
        } elseif ($postAction === 'delete_seat_class') {
            $scId = (int)($_POST['sc_id'] ?? 0);
            if ($scId) {
                dbExecute('DELETE FROM seat_classes WHERE id=?', [$scId]);
                $actionMsg = 'Seat class removed.';
            }
        }
    }
}

// ── Query ─────────────────────────────────────────────────────────────────────
if ($tab === 'mbanda') {
    $where  = ["1 = 1"];
    $params = [];
    if ($search) {
        $where[] = '(driver_name LIKE ? OR pickup_location LIKE ? OR destination LIKE ? OR vehicle_reg LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereStr   = implode(' AND ', $where);
    $totalCount = dbCount("SELECT COUNT(*) FROM ride_sharing_trips WHERE $whereStr", $params);
    $totalPages = max(1, ceil($totalCount / $perPage));
    $offset     = ($page - 1) * $perPage;
    $mbandaTrips = dbQuery("SELECT * FROM ride_sharing_trips WHERE $whereStr ORDER BY departure_datetime DESC LIMIT $perPage OFFSET $offset", $params);
} else {
    $where  = ["listing_type = 'transport'"];
    $params = [];
    if ($search) { $where[] = '(title LIKE ? OR location LIKE ? OR vendor_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $whereStr   = implode(' AND ', $where);
    $totalCount = dbCount("SELECT COUNT(*) FROM listings WHERE $whereStr", $params);
    $totalPages = max(1, ceil($totalCount / $perPage));
    $offset     = ($page - 1) * $perPage;
    $listings   = dbQuery("SELECT * FROM listings WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

    // Pre-load seat classes
    $seatMap = [];
    foreach ($listings as $l) {
        $seatMap[$l['id']] = dbQuery("SELECT * FROM seat_classes WHERE listing_id=? ORDER BY sort_order, id", [$l['id']]);
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('transport') ?> Transport Management</h1>
    <p class="text-muted">Manage transport listings, seat classes, and Mbanda ride sharing.</p>
  </div>
  <div style="display:flex;gap:0.75rem;">
    <a href="<?= BASE_URL ?>admin/events.php" class="btn btn-secondary btn-sm">All Listings</a>
  </div>
</div>

<!-- Tab Nav -->
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;border-bottom:1px solid var(--clr-border);padding-bottom:0.75rem;">
  <a href="transport.php?tab=commercial" class="btn btn-sm <?= $tab === 'commercial' ? 'btn-primary' : 'btn-ghost' ?>"><?= admin_icon_svg('transport') ?> Commercial Transport</a>
  <a href="transport.php?tab=mbanda" class="btn btn-sm <?= $tab === 'mbanda' ? 'btn-primary' : 'btn-ghost' ?>"><?= uthenga_public_icon_svg('car') ?> Mbanda Ride Sharing</a>
</div>

<?php if ($actionMsg): ?><div class="alert alert-success"><?= uthenga_public_icon_svg('check') ?> <?= e($actionMsg) ?></div><?php endif; ?>
<?php if ($actionErr): ?><div class="alert alert-error"><?= uthenga_public_icon_svg('x') ?> <?= e($actionErr) ?></div><?php endif; ?>



<!-- Toolbar -->
<form method="GET" id="transport-filter-form">
  <div class="table-toolbar">
    <div class="search-wrap">
      <span class="search-icon"><?= uthenga_public_icon_svg('search') ?></span>
      <input type="text" name="q" placeholder="Search routes, vendors…" value="<?= e($search) ?>" id="transport-search" autocomplete="off">
    </div>
    <div class="export-group">
      <a href="?q=<?= urlencode($search) ?>&export=csv" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> CSV</a>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="transport.php" class="btn btn-ghost btn-sm">Clear</a>
    </div>
  </div>
</form>

<?php
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transport-' . date('Y-m-d') . '.csv"');
    $all = dbQuery("SELECT id, title, vendor_name, location, rating, is_active, created_at FROM listings WHERE $whereStr ORDER BY created_at DESC", $params);
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'Route', 'Vendor', 'Location', 'Rating', 'Active', 'Created']);
    foreach ($all as $row) fputcsv($fh, array_values($row));
    fclose($fh); exit;
}
?>

<?php if ($tab === 'mbanda'): ?>
<div class="glass-panel" style="padding:1.25rem;">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Route</th>
          <th>Departure</th>
          <th>Seats (Booked/Total)</th>
          <th>Price/Seat</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($mbandaTrips)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--clr-text-muted);">No Mbanda ride sharing trips found.</td></tr>
        <?php else: ?>
          <?php foreach ($mbandaTrips as $trip): ?>
          <tr>
            <td>
              <div style="font-weight:700;"><?= e($trip['driver_name']) ?></div>
              <div class="text-xs text-muted"><?= e($trip['driver_phone']) ?></div>
            </td>
            <td>
              <div style="font-weight:600;"><?= e($trip['pickup_location']) ?> &rarr; <?= e($trip['destination']) ?></div>
              <div class="text-xs text-muted"><?= e($trip['vehicle_color']) ?> <?= e($trip['vehicle_make']) ?> <?= e($trip['vehicle_model']) ?> (<?= e($trip['vehicle_reg'] ?: 'N/A') ?>)</div>
            </td>
            <td><?= date('d M Y, h:i A', strtotime($trip['departure_datetime'])) ?></td>
            <td><?= (int)$trip['booked_seats'] ?> / <?= (int)$trip['available_seats'] ?></td>
            <td><?= formatMWK($trip['price_per_seat']) ?></td>
            <td>
              <?php
                $statusColors = ['open' => '#34d399', 'full' => '#60a5fa', 'cancelled' => '#f87171', 'completed' => '#a78bfa'];
                $sc = $statusColors[$trip['status']] ?? '#9ca3af';
              ?>
              <span class="role-badge" style="background: rgba(0,0,0,0.15); color: <?= $sc ?>; border: 1px solid <?= $sc ?>;"><?= strtoupper(e($trip['status'])) ?></span>
            </td>
            <td style="text-align:right;">
              <?php if ($trip['status'] === 'open'): ?>
              <form method="POST" onsubmit="return confirm('Cancel this Mbanda trip and all its bookings?');" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="post_action" value="cancel_mbanda_trip">
                <input type="hidden" name="trip_id" value="<?= e($trip['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Cancel Trip</button>
              </form>
              <?php else: ?>
              <span class="text-muted text-xs">No actions</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: // Commercial Transport Tab ?>
<div class="glass-panel" style="padding:1.25rem;">
  <div class="table-responsive">
    <table class="admin-table" id="transport-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Route / Title</th>
          <th>Vendor</th>
          <th>Origin &rarr; Dest</th>
          <th>Seat Classes</th>
          <th>Rating</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listings)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--clr-text-muted);">No transport listings found.</td></tr>
        <?php else: ?>
          <?php foreach ($listings as $l):
            $meta  = json_decode($l['meta'] ?? '{}', true);
            $seats = $seatMap[$l['id']] ?? [];
            $totalSeats     = array_sum(array_column($seats, 'total_seats'));
            $remainingSeats = array_sum(array_column($seats, 'remaining_seats'));
            $pct = $totalSeats > 0 ? round(($remainingSeats / $totalSeats) * 100) : 0;
            $fillCls = $pct < 20 ? 'low' : ($pct < 50 ? 'medium' : 'good');
          ?>

          <tr id="t-row-<?= e($l['id']) ?>">
            <td><img src="<?= e($l['image'] ?: '/assets/images/placeholder.png') ?>" alt="" style="width:64px;height:46px;object-fit:cover;border-radius:6px;"></td>
            <td>
              <div style="font-weight:700;font-size:0.875rem;"><?= e($l['title']) ?></div>
              <div class="text-xs text-muted"><?= uthenga_public_icon_svg('pin') ?> <?= e($l['location']) ?></div>
            </td>
            <td class="text-xs"><?= e($l['vendor_name']) ?></td>
            <td class="text-xs">
              <?= e($meta['origin'] ?? $meta['departure_city'] ?? '—') ?>
              <?php if (!empty($meta['destination'])): ?> &rarr; <?= e($meta['destination']) ?><?php endif; ?>
            </td>
            <td>
              <?php if (count($seats) > 0): ?>
                <div class="text-xs" style="margin-bottom:4px;">
                  <?php foreach ($seats as $sc): ?>
                    <span class="badge badge-transport" style="margin-right:3px;"><?= e($sc['class_name']) ?></span>
                  <?php endforeach; ?>
                </div>
                <div class="text-xs text-muted"><?= number_format($remainingSeats) ?>/<?= number_format($totalSeats) ?> seats</div>
                <div class="availability-bar"><div class="availability-bar-fill <?= $fillCls ?>" style="width:<?= $pct ?>%"></div></div>
              <?php else: ?>
              <span class="text-xs text-muted">-</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--clr-yellow);font-weight:700;"><?= e($l['rating']) ?> ★</td>
            <td>
              <form method="POST" style="display:inline;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                <input type="hidden" name="post_action" value="toggle_active">
                <button type="submit" class="btn btn-sm <?= $l['is_active'] ? 'btn-cyan' : 'btn-secondary' ?>">
                  <?= $l['is_active'] ? uthenga_public_icon_svg('check') . ' Live' : uthenga_public_icon_svg('x') . ' Hidden' ?>
                </button>
              </form>
            </td>
            <td style="text-align:right;">
              <button class="btn btn-sm btn-secondary"
                onclick="openSeatModal('<?= e($l['id']) ?>','<?= e(addslashes($l['title'])) ?>')">
                <?= uthenga_public_icon_svg('ticket') ?> Manage Seats
              </button>
            </td>
          </tr>
          <!-- Seat Management Inline Row -->
          <tr id="seat-manage-<?= e($l['id']) ?>" style="display:none;">
            <td colspan="8" style="background:var(--clr-surface2);padding:1.25rem;">
              <h4 style="margin-bottom:0.75rem;color:var(--clr-cyan);"><?= uthenga_public_icon_svg('ticket') ?> Seat Classes - <?= e($l['title']) ?></h4>
              <?php if (count($seats) > 0): ?>
                <table class="ticket-admin-table" style="margin-bottom:1rem;">
                  <thead><tr><th>Class</th><th>Price</th><th>Total Seats</th><th>Remaining</th><th>Availability</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($seats as $sc):
                      $sp = $sc['total_seats'] > 0 ? round(($sc['remaining_seats'] / $sc['total_seats']) * 100) : 0;
                    ?>
                    <tr>
                      <td><strong><?= e($sc['class_name']) ?></strong></td>
                      <td>MWK <?= number_format((float)$sc['price']) ?></td>
                      <td class="ticket-qty-display"><?= number_format($sc['total_seats']) ?></td>
                      <td class="<?= $sp < 20 ? 'ticket-qty-low' : 'ticket-qty-remaining' ?>"><?= number_format($sc['remaining_seats']) ?></td>
                      <td style="min-width:100px;">
                        <div class="availability-bar">
                          <div class="availability-bar-fill <?= $sp < 20 ? 'low' : ($sp < 50 ? 'medium' : 'good') ?>" style="width:<?= $sp ?>%"></div>
                        </div>
                        <div class="text-xs text-muted" style="margin-top:2px;"><?= $sp ?>% available</div>
                      </td>
                      <td>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this seat class?');">
                          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                          <input type="hidden" name="post_action" value="delete_seat_class">
                          <input type="hidden" name="sc_id" value="<?= $sc['id'] ?>">
                          <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                          <button type="submit" class="btn btn-danger btn-sm"><?= uthenga_public_icon_svg('x') ?></button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="text-muted text-xs" style="margin-bottom:1rem;">No seat classes yet.</p>
              <?php endif; ?>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="post_action" value="add_seat_class">
                <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:0.75rem;align-items:flex-end;">
                  <div><label class="form-label text-xs">Class Name*</label><input type="text" name="sc_name" class="form-control" placeholder="Standard, VIP..." required></div>
                  <div><label class="form-label text-xs">Price (MWK)</label><input type="number" name="sc_price" class="form-control" min="0" step="0.01" placeholder="0"></div>
                  <div><label class="form-label text-xs">Total Seats</label><input type="number" name="sc_seats" class="form-control" min="0" placeholder="65"></div>
                  <div><label class="form-label text-xs">Description</label><input type="text" name="sc_desc" class="form-control" placeholder="Optional"></div>
                  <button type="submit" class="btn btn-cyan btn-sm">+ Add</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // end tab ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&tab=<?= e($tab) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<p class="text-xs text-muted" style="text-align:center;margin-top:0.75rem;">
  <?= number_format($totalCount) ?> <?= $tab === 'mbanda' ? 'Mbanda trips' : 'transport listings' ?>
</p>

<script>
function openSeatModal(id, title) {
  const row = document.getElementById('seat-manage-' + id);
  if (!row) return;
  const visible = row.style.display !== 'none';
  document.querySelectorAll('[id^="seat-manage-"]').forEach(r => r.style.display = 'none');
  row.style.display = visible ? 'none' : 'table-row';
}
// Live filter — only on commercial tab
const searchEl = document.getElementById('transport-search');
if (searchEl) {
  searchEl.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#transport-table tbody tr[id^="t-row-"]').forEach(row => {
      const match = row.textContent.toLowerCase().includes(q);
      const nextRow = row.nextElementSibling;
      row.style.display = match ? '' : 'none';
      if (nextRow && nextRow.id.startsWith('seat-manage-')) nextRow.style.display = 'none';
    });
  });
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>


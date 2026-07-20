<?php
/**
 * Uthenga — Admin Accommodation / Stays Management (Phase 2)
 * Full implementation: listings table, room type management
 */
$pageTitle = 'Accommodation Management';
$activeNav = 'admin-stays';

require_once __DIR__ . '/includes/admin_header.php';

$search    = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$actionMsg = '';
$actionErr = '';

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $postAction = $_POST['post_action'] ?? '';
    $lid = trim($_POST['listing_id'] ?? '');

    if ($postAction === 'toggle_active' && $lid) {
        $cur = dbQueryOne('SELECT is_active FROM listings WHERE id=?', [$lid]);
        if ($cur) {
            $new = $cur['is_active'] ? 0 : 1;
            dbExecute('UPDATE listings SET is_active=? WHERE id=?', [$new, $lid]);
            logAction('Stay Status', "Listing $lid active=" . ($new ? 'Yes' : 'No'));
            $actionMsg = 'Listing status updated.';
        }
    } elseif ($postAction === 'add_room_type' && $lid) {
        $rtName  = trim($_POST['rt_name'] ?? '');
        $rtPrice = (float)($_POST['rt_price'] ?? 0);
        $rtRooms = max(0, (int)($_POST['rt_rooms'] ?? 0));
        $rtOcc   = max(1, (int)($_POST['rt_occ'] ?? 2));
        $rtDesc  = trim($_POST['rt_desc'] ?? '');
        $rtAmen  = trim($_POST['rt_amenities'] ?? '');
        if ($rtName) {
            $amenities = array_filter(array_map('trim', explode(',', $rtAmen)));
            dbExecute(
                "INSERT INTO room_types (listing_id, room_name, description, price_per_night, total_rooms, available_rooms, max_occupancy, amenities) VALUES (?,?,?,?,?,?,?,?)",
                [$lid, $rtName, $rtDesc, $rtPrice, $rtRooms, $rtRooms, $rtOcc, json_encode(array_values($amenities))]
            );
            logAction('Added Room Type', "Added $rtName to property $lid");
            $actionMsg = "Room type \"$rtName\" added.";
        } else { $actionErr = 'Room name required.'; }
    } elseif ($postAction === 'delete_room_type') {
        $rtId = (int)($_POST['rt_id'] ?? 0);
        if ($rtId) {
            dbExecute('DELETE FROM room_types WHERE id=?', [$rtId]);
            $actionMsg = 'Room type removed.';
        }
    }
}

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ["listing_type = 'accommodation'"];
$params = [];
if ($search) { $where[] = '(title LIKE ? OR location LIKE ? OR vendor_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr   = implode(' AND ', $where);
$totalCount = dbCount("SELECT COUNT(*) FROM listings WHERE $whereStr", $params);
$totalPages = max(1, ceil($totalCount / $perPage));
$offset     = ($page - 1) * $perPage;
$listings   = dbQuery("SELECT * FROM listings WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Pre-load room types
$roomMap = [];
foreach ($listings as $l) {
    $roomMap[$l['id']] = dbQuery("SELECT * FROM room_types WHERE listing_id=? ORDER BY sort_order, id", [$l['id']]);
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">🏨 Accommodation Management</h1>
    <p class="text-muted">Manage hotels, lodges, guesthouses and room type inventory.</p>
  </div>
  <a href="<?= BASE_URL ?>admin/events.php" class="btn btn-secondary btn-sm">All Listings</a>
</div>

<?php if ($actionMsg): ?><div class="alert alert-success">✓ <?= e($actionMsg) ?></div><?php endif; ?>
<?php if ($actionErr): ?><div class="alert alert-error">✕ <?= e($actionErr) ?></div><?php endif; ?>

<!-- Toolbar -->
<form method="GET" id="stays-filter-form">
  <div class="table-toolbar">
    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" name="q" placeholder="Search properties, vendors…" value="<?= e($search) ?>" id="stays-search" autocomplete="off">
    </div>
    <div class="export-group">
      <a href="?q=<?= urlencode($search) ?>&export=csv" class="btn btn-secondary btn-sm">⬇ CSV</a>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="stays.php" class="btn btn-ghost btn-sm">Clear</a>
    </div>
  </div>
</form>

<?php
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stays-' . date('Y-m-d') . '.csv"');
    $all = dbQuery("SELECT id, title, vendor_name, location, rating, is_active, created_at FROM listings WHERE $whereStr ORDER BY created_at DESC", $params);
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'Property', 'Vendor', 'Location', 'Rating', 'Active', 'Created']);
    foreach ($all as $row) fputcsv($fh, array_values($row));
    fclose($fh); exit;
}
?>

<div class="glass-panel" style="padding:1.25rem;">
  <div class="table-responsive">
    <table class="admin-table" id="stays-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Property</th>
          <th>Vendor</th>
          <th>Location</th>
          <th>Room Types</th>
          <th>Rating</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listings)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--clr-text-muted);">No accommodation listings found.</td></tr>
        <?php else: ?>
          <?php foreach ($listings as $l):
            $meta  = json_decode($l['meta'] ?? '{}', true);
            $rooms = $roomMap[$l['id']] ?? [];
            $totalRooms     = array_sum(array_column($rooms, 'total_rooms'));
            $availableRooms = array_sum(array_column($rooms, 'available_rooms'));
            $pct = $totalRooms > 0 ? round(($availableRooms / $totalRooms) * 100) : 0;
          ?>
          <tr id="s-row-<?= e($l['id']) ?>">
            <td><img src="<?= e($l['image'] ?: '/assets/images/placeholder.png') ?>" alt="" style="width:64px;height:46px;object-fit:cover;border-radius:6px;"></td>
            <td>
              <div style="font-weight:700;font-size:0.875rem;"><?= e($l['title']) ?></div>
              <?php $stars = (int)($meta['stars'] ?? 0); if ($stars > 0): ?>
                <div class="text-xs" style="color:var(--clr-yellow);"><?= str_repeat('★', $stars) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-xs"><?= e($l['vendor_name']) ?></td>
            <td class="text-xs text-muted">📍 <?= e($l['location']) ?></td>
            <td>
              <?php if (count($rooms) > 0): ?>
                <div class="text-xs" style="margin-bottom:4px;">
                  <?php foreach (array_slice($rooms, 0, 3) as $rt): ?>
                    <span class="badge badge-stay" style="margin-right:3px;"><?= e($rt['room_name']) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($rooms) > 3): ?><span class="text-xs text-muted">+<?= count($rooms)-3 ?> more</span><?php endif; ?>
                </div>
                <div class="text-xs text-muted"><?= number_format($availableRooms) ?>/<?= number_format($totalRooms) ?> available</div>
                <div class="availability-bar">
                  <div class="availability-bar-fill <?= $pct < 20 ? 'low' : ($pct < 50 ? 'medium' : 'good') ?>" style="width:<?= $pct ?>%"></div>
                </div>
              <?php else: ?>
                <span class="text-xs text-muted">No room types</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--clr-yellow);font-weight:700;"><?= e($l['rating']) ?> ★</td>
            <td>
              <form method="POST" style="display:inline;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                <input type="hidden" name="post_action" value="toggle_active">
                <button type="submit" class="btn btn-sm <?= $l['is_active'] ? 'btn-cyan' : 'btn-secondary' ?>">
                  <?= $l['is_active'] ? '✓ Live' : '✕ Hidden' ?>
                </button>
              </form>
            </td>
            <td style="text-align:right;">
              <button class="btn btn-sm btn-secondary" onclick="toggleRoomManage('<?= e($l['id']) ?>')">🛏 Rooms</button>
            </td>
          </tr>
          <!-- Room Management Inline Row -->
          <tr id="room-manage-<?= e($l['id']) ?>" style="display:none;">
            <td colspan="8" style="background:var(--clr-surface2);padding:1.25rem;">
              <h4 style="margin-bottom:0.75rem;color:var(--clr-cyan);">🛏 Room Types — <?= e($l['title']) ?></h4>
              <?php if (count($rooms) > 0): ?>
                <table class="ticket-admin-table" style="margin-bottom:1rem;">
                  <thead><tr><th>Room Type</th><th>Price/Night</th><th>Occupancy</th><th>Total</th><th>Available</th><th>Amenities</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($rooms as $rt):
                      $amenities = is_string($rt['amenities']) ? json_decode($rt['amenities'], true) : ($rt['amenities'] ?? []);
                    ?>
                    <tr>
                      <td><strong><?= e($rt['room_name']) ?></strong></td>
                      <td>MWK <?= number_format((float)$rt['price_per_night']) ?></td>
                      <td class="text-xs">Up to <?= $rt['max_occupancy'] ?> guests</td>
                      <td class="ticket-qty-display"><?= number_format($rt['total_rooms']) ?></td>
                      <td class="<?= $rt['available_rooms'] == 0 ? 'ticket-qty-sold' : 'ticket-qty-remaining' ?>"><?= number_format($rt['available_rooms']) ?></td>
                      <td class="text-xs text-muted">
                        <?php if (!empty($amenities)): ?>
                          <?= implode(', ', array_slice((array)$amenities, 0, 3)) ?>
                          <?php if (count((array)$amenities) > 3): ?>+<?= count((array)$amenities)-3 ?> more<?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                      </td>
                      <td>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this room type?');">
                          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                          <input type="hidden" name="post_action" value="delete_room_type">
                          <input type="hidden" name="rt_id" value="<?= $rt['id'] ?>">
                          <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                          <button type="submit" class="btn btn-danger btn-sm">✕</button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="text-muted text-xs" style="margin-bottom:1rem;">No room types configured yet.</p>
              <?php endif; ?>
              <!-- Add Room Type Form -->
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="post_action" value="add_room_type">
                <input type="hidden" name="listing_id" value="<?= e($l['id']) ?>">
                <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                  <div><label class="form-label text-xs">Room Name*</label><input type="text" name="rt_name" class="form-control" placeholder="Standard, Suite, Family…" required></div>
                  <div><label class="form-label text-xs">Price/Night (MWK)</label><input type="number" name="rt_price" class="form-control" min="0" step="0.01" placeholder="0"></div>
                  <div><label class="form-label text-xs">Total Rooms</label><input type="number" name="rt_rooms" class="form-control" min="0" placeholder="10"></div>
                  <div><label class="form-label text-xs">Max Guests</label><input type="number" name="rt_occ" class="form-control" min="1" max="20" placeholder="2" value="2"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                  <div><label class="form-label text-xs">Amenities (comma-separated)</label><input type="text" name="rt_amenities" class="form-control" placeholder="WiFi, AC, TV, Breakfast…"></div>
                  <div><label class="form-label text-xs">Description</label><input type="text" name="rt_desc" class="form-control" placeholder="Optional"></div>
                </div>
                <button type="submit" class="btn btn-cyan btn-sm">+ Add Room Type</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
function toggleRoomManage(id) {
  const row = document.getElementById('room-manage-' + id);
  if (!row) return;
  const visible = row.style.display !== 'none';
  document.querySelectorAll('[id^="room-manage-"]').forEach(r => r.style.display = 'none');
  row.style.display = visible ? 'none' : 'table-row';
}
document.getElementById('stays-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#stays-table tbody tr[id^="s-row-"]').forEach(row => {
    const match = row.textContent.toLowerCase().includes(q);
    const nextRow = row.nextElementSibling;
    row.style.display = match ? '' : 'none';
    if (nextRow && nextRow.id.startsWith('room-manage-')) nextRow.style.display = 'none';
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

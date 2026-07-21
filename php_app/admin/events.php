<?php
/**
 * Uthenga - Admin Events Management
 */
$pageTitle = 'Event Management';
$activeNav = 'admin-events';

require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$actionMsg = '';
$actionErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $postAction = $_POST['post_action'] ?? '';
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    if ($postAction === 'toggle_active' && $listingId > 0) {
        $current = dbQueryOne("SELECT is_active FROM listings WHERE id = ? AND listing_type = 'event'", [$listingId]);
        if ($current) {
            $newValue = !empty($current['is_active']) ? 0 : 1;
            dbExecute("UPDATE listings SET is_active = ? WHERE id = ? AND listing_type = 'event'", [$newValue, $listingId]);
            $actionMsg = 'Event status updated.';
        }
    } elseif ($postAction === 'set_ticket_format' && $listingId > 0) {
        $format = strtolower(trim((string)($_POST['ticket_format'] ?? 'qr')));
        if (!in_array($format, ['qr', 'barcode', 'code'], true)) {
            $actionErr = 'Invalid ticket format selected.';
        } else {
            $current = dbQueryOne("SELECT meta FROM listings WHERE id = ? AND listing_type = 'event'", [$listingId]);
            if ($current) {
                $meta = json_decode($current['meta'] ?? '{}', true) ?: [];
                $meta['ticketCodeFormat'] = $format;
                $meta['ticket_code_format'] = $format;
                $meta['scanFormat'] = $format;
                $meta['scan_format'] = $format;
                dbExecute("UPDATE listings SET meta = ? WHERE id = ? AND listing_type = 'event'", [json_encode($meta), $listingId]);
                $actionMsg = 'Ticket scan format updated.';
            }
        }
    }
}

$where = ["listing_type = 'event'"];
$params = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR location LIKE ? OR vendor_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereStr = implode(' AND ', $where);

$totalCount = dbCount("SELECT COUNT(*) FROM listings WHERE $whereStr", $params);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$offset = ($page - 1) * $perPage;

$events = dbQuery(
    "SELECT * FROM listings WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$stats = [
    'total' => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'event'"),
    'active' => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'event' AND is_active = 1"),
    'inactive' => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'event' AND is_active = 0"),
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Event Management</h1>
    <p class="text-muted">Browse, activate, and review event listings.</p>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>admin/event_report.php" class="btn btn-secondary btn-sm">Event Report</a>
    <a href="<?= BASE_URL ?>admin/bookings.php?type=event" class="btn btn-secondary btn-sm">Event Bookings</a>
  </div>
</div>

<?php if ($actionMsg): ?><div class="alert alert-success" style="margin-bottom:1.5rem;"><?= uthenga_public_icon_svg('check') ?> <?= e($actionMsg) ?></div><?php endif; ?>
<?php if ($actionErr): ?><div class="alert alert-error" style="margin-bottom:1.5rem;">✗ <?= e($actionErr) ?></div><?php endif; ?>

<div class="grid grid-cols-3 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('calendar') ?></div>
    <div><div class="stat-value"><?= number_format($stats['total']) ?></div><div class="stat-label">Total Events</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('toggle') ?></div>
    <div><div class="stat-value"><?= number_format($stats['active']) ?></div><div class="stat-label">Active Events</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('activity') ?></div>
    <div><div class="stat-value"><?= number_format($stats['inactive']) ?></div><div class="stat-label">Inactive Events</div></div>
  </div>
</div>

<form method="GET" action="events.php">
  <div class="table-toolbar">
    <div class="search-wrap">
      <span class="search-icon"><?= uthenga_public_icon_svg('search') ?></span>
      <input type="text" name="q" placeholder="Search events, locations, vendors..." value="<?= e($search) ?>" autocomplete="off">
    </div>
    <div class="export-group">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="events.php" class="btn btn-ghost btn-sm">Clear</a>
    </div>
  </div>
</form>

<div class="glass-panel" style="padding:1rem;">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Event</th>
          <th>Vendor</th>
          <th>Location</th>
          <th>Scan Format</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($events)): ?>
          <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No events found.</td></tr>
        <?php else: ?>
          <?php foreach ($events as $event): ?>
            <?php
              $eventMeta = json_decode($event['meta'] ?? '{}', true) ?: [];
              $eventFormat = strtolower(trim((string)($eventMeta['ticketCodeFormat'] ?? $eventMeta['ticket_code_format'] ?? $eventMeta['scanFormat'] ?? $eventMeta['scan_format'] ?? 'qr')));
              if (!in_array($eventFormat, ['qr', 'barcode', 'code'], true)) {
                  $eventFormat = 'qr';
              }
            ?>
            <tr>
              <td>
                <div style="font-weight:700;"><?= e($event['title'] ?? '') ?></div>
                <div class="text-xs text-muted"><?= e(substr((string) ($event['description'] ?? ''), 0, 90)) ?></div>
              </td>
              <td class="text-xs"><?= e($event['vendor_name'] ?? '') ?></td>
              <td class="text-xs text-muted"><?= e($event['location'] ?? '') ?></td>
              <td>
                <form method="POST" style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="post_action" value="set_ticket_format">
                  <input type="hidden" name="listing_id" value="<?= e($event['id']) ?>">
                  <select name="ticket_format" class="form-control" style="min-width:110px;padding:.45rem .65rem;">
                    <option value="qr" <?= $eventFormat === 'qr' ? 'selected' : '' ?>>QR</option>
                    <option value="barcode" <?= $eventFormat === 'barcode' ? 'selected' : '' ?>>Barcode</option>
                    <option value="code" <?= $eventFormat === 'code' ? 'selected' : '' ?>>Code</option>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </form>
              </td>
              <td>
                <span class="badge <?= !empty($event['is_active']) ? 'badge-approved' : 'badge-cancelled' ?>">
                  <?= !empty($event['is_active']) ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="text-align:right;white-space:nowrap;">
                <a href="<?= BASE_URL ?>admin/event_report.php?event_id=<?= urlencode((string) ($event['id'] ?? '')) ?>" class="btn btn-secondary btn-sm">Report</a>
                <form method="POST" style="display:inline;margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="post_action" value="toggle_active">
                  <input type="hidden" name="listing_id" value="<?= e($event['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-primary"><?= !empty($event['is_active']) ? 'Disable' : 'Enable' ?></button>
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
  <div class="pagination" style="margin-top:1.5rem;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="page-btn">Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

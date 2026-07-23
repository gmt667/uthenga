<?php
/**
 * Uthenga — Event Organizer Analytics Dashboard
 * Shows sales performance, ticket revenue, promo code usage for organizer-owned events
 */
$pageTitle = 'Event Organizer Analytics';
$activeNav = 'event-analytics';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

// ── Filters ─────────────────────────────────────────────────────────────────
$organizerId = trim($_GET['organizer_id'] ?? '');
$period      = in_array($_GET['period'] ?? '30', ['7','30','90','365']) ? (int)$_GET['period'] : 30;
$eventId     = trim($_GET['event_id'] ?? '');

// ── Organizer list (vendors) ─────────────────────────────────────────────────
$organizers = dbQuery("
    SELECT DISTINCT u.id, u.name
    FROM users u
    INNER JOIN listings l ON l.vendor_id = u.id AND l.listing_type = 'event'
    WHERE u.role IN (?, ?)
    ORDER BY u.name ASC
", [ROLE_EVENT_ORG, ROLE_VENDOR]);

// ── Events filter list ───────────────────────────────────────────────────────
$eventsFilter = dbQuery("
    SELECT id, title, vendor_name FROM listings
    WHERE listing_type = 'event' AND is_active = 1
    " . ($organizerId ? " AND vendor_id = ?" : "") . "
    ORDER BY title ASC
    LIMIT 100
", $organizerId ? [$organizerId] : []);

// ── Date boundary ────────────────────────────────────────────────────────────
$since = date('Y-m-d', strtotime("-{$period} days"));

// ── Build WHERE clause fragments ─────────────────────────────────────────────
$whereParams = ["$since 00:00:00"];
$whereSQL = "b.created_at >= ?";

if ($eventId) {
    $whereSQL   .= " AND b.listing_id = ?";
    $whereParams[] = $eventId;
} elseif ($organizerId) {
    $whereSQL   .= " AND l.vendor_id = ?";
    $whereParams[] = $organizerId;
}

// ── Headline KPIs ─────────────────────────────────────────────────────────────
$totalBookings = (int)dbCount("
    SELECT COUNT(*) FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND $whereSQL
", $whereParams);

$totalRevenue = (float)(dbQueryOne("
    SELECT COALESCE(SUM(b.total_price), 0) AS rev FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND LOWER(b.payment_status) = 'paid' AND $whereSQL
", $whereParams)['rev'] ?? 0);

$paidBookings = (int)dbCount("
    SELECT COUNT(*) FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND LOWER(b.payment_status) = 'paid' AND $whereSQL
", $whereParams);

$cancelledBookings = (int)dbCount("
    SELECT COUNT(*) FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND LOWER(b.booking_status) = 'cancelled' AND $whereSQL
", $whereParams);

$avgTicketPrice = $paidBookings > 0 ? $totalRevenue / $paidBookings : 0;

// ── Top performing events ─────────────────────────────────────────────────────
$topEvents = dbQuery("
    SELECT b.listing_id, b.listing_title,
           COUNT(*) AS total_bookings,
           SUM(CASE WHEN LOWER(b.payment_status) = 'paid' THEN b.total_price ELSE 0 END) AS revenue
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND $whereSQL
    GROUP BY b.listing_id, b.listing_title
    ORDER BY revenue DESC
    LIMIT 10
", $whereParams);

// ── Daily revenue trend (chart data) ─────────────────────────────────────────
$dailyTrend = dbQuery("
    SELECT DATE(b.created_at) AS day, COUNT(*) AS bookings, SUM(b.total_price) AS revenue
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND LOWER(b.payment_status) = 'paid' AND $whereSQL
    GROUP BY DATE(b.created_at)
    ORDER BY day ASC
", $whereParams);

// ── Ticket type breakdown ─────────────────────────────────────────────────────
$ticketBreakdown = dbQuery("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.ticket_type')), 'Standard') AS ticket_type,
        COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.quantity')) AS UNSIGNED), 1) AS quantity,
        b.total_price AS revenue
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND LOWER(b.payment_status) = 'paid' AND $whereSQL
", $whereParams);

$ticketTypeStats = ['Standard' => ['count' => 0, 'revenue' => 0.0], 'VIP' => ['count' => 0, 'revenue' => 0.0]];
foreach ($ticketBreakdown as $row) {
    $type = trim((string)($row['ticket_type'] ?? 'Standard')) ?: 'Standard';
    if (!isset($ticketTypeStats[$type])) {
        $ticketTypeStats[$type] = ['count' => 0, 'revenue' => 0.0];
    }
    $ticketTypeStats[$type]['count'] += (int)($row['quantity'] ?? 1);
    $ticketTypeStats[$type]['revenue'] += (float)($row['revenue'] ?? 0);
}

// ── Promo code usage ──────────────────────────────────────────────────────────
$hasPromoCodes = uthenga_table_exists('event_promo_codes');
$hasBookingPromo = uthenga_column_exists('bookings', 'promo_code');
$promoUsage = ($hasPromoCodes && $hasBookingPromo) ? dbQuery("
    SELECT p.code, p.discount_type, p.discount_value,
           COUNT(b.id) AS uses, COALESCE(SUM(b.discount_amount), 0) AS total_discount,
           COALESCE(SUM(b.total_price), 0) AS gross_rev
    FROM event_promo_codes p
    LEFT JOIN bookings b ON b.promo_code = p.code AND LOWER(b.payment_status) = 'paid'
    GROUP BY p.code, p.discount_type, p.discount_value
    ORDER BY uses DESC
    LIMIT 20
") : [];

// ── Recent bookings ───────────────────────────────────────────────────────────
$recentBookings = dbQuery("
    SELECT
        b.id, b.listing_title, b.customer_name,
        COALESCE(b.quantity, 1) AS quantity,
        b.total_price, b.payment_status,
        COALESCE(b.payment_gateway, '—') AS gateway,
        b.created_at,
        " . ($hasBookingPromo ? "b.promo_code AS coupon_code," : "NULL AS coupon_code,") . "
        COALESCE(b.discount_amount, 0) AS discount
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    WHERE b.listing_type = 'event' AND $whereSQL
    ORDER BY b.created_at DESC
    LIMIT 50
", $whereParams);

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
  .analytics-kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1.25rem; margin-bottom:2rem; }
  .kpi-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-lg); padding:1.25rem 1.5rem; }
  .kpi-label { font-size:0.73rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-text-soft); margin-bottom:.35rem; }
  .kpi-value { font-size:1.7rem; font-weight:800; color:var(--clr-accent); line-height:1.1; }
  .kpi-sub   { font-size:0.73rem; color:var(--clr-text-soft); margin-top:.25rem; }
  .analytics-chart-grid { display:grid; grid-template-columns:1fr 340px; gap:1.5rem; margin-bottom:2rem; }
  .chart-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-lg); padding:1.5rem; }
  .section-heading { font-size:1rem; font-weight:700; margin-bottom:1.25rem; padding-bottom:.5rem; border-bottom:1px solid var(--clr-border); display:flex; align-items:center; gap:.5rem; }
  canvas { max-width:100%; }
  .promo-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .55rem; border-radius:100px; font-size:.72rem; font-weight:700; background:rgba(6,182,212,.12); color:var(--clr-primary); border:1px solid rgba(6,182,212,.25); }
  @media(max-width:900px){ .analytics-chart-grid{grid-template-columns:1fr;} }
  .filter-bar { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-lg); padding:1rem 1.5rem; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; }
  .filter-bar .form-group { margin:0; min-width:160px; }
  .progress-bar-wrap { height:6px; background:var(--clr-surface2); border-radius:3px; overflow:hidden; margin-top:4px; }
  .progress-bar-fill { height:100%; background:var(--clr-primary); border-radius:3px; }
</style>

<main class="dashboard-main">
  <div class="dashboard-content-area">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:2rem;flex-wrap:wrap;">
      <div>
        <h1 style="font-size:1.5rem;font-weight:800;margin:0;display:flex;align-items:center;gap:0.5rem;"><?= admin_icon_svg('chart') ?> Event Organizer Analytics</h1>
        <div class="text-sm text-muted" style="margin-top:.25rem;">Sales performance · Ticket metrics · Promo tracking</div>
      </div>
      <a href="<?= BASE_URL ?>admin/event_report.php" class="btn btn-secondary btn-sm" style="margin-left:auto;">&larr; Event Reports</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
      <div class="form-group">
        <label class="form-label" for="period-filter">Period</label>
        <select name="period" id="period-filter" class="form-control" onchange="this.form.submit()">
          <option value="7"   <?= $period==7   ? 'selected':'' ?>>Last 7 days</option>
          <option value="30"  <?= $period==30  ? 'selected':'' ?>>Last 30 days</option>
          <option value="90"  <?= $period==90  ? 'selected':'' ?>>Last 90 days</option>
          <option value="365" <?= $period==365 ? 'selected':'' ?>>Last 12 months</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="organizer-filter">Organizer</label>
        <select name="organizer_id" id="organizer-filter" class="form-control" onchange="this.form.submit()">
          <option value="">All Organizers</option>
          <?php foreach ($organizers as $org): ?>
            <option value="<?= e($org['id']) ?>" <?= $organizerId === $org['id'] ? 'selected' : '' ?>>
              <?= e($org['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="event-filter">Event</label>
        <select name="event_id" id="event-filter" class="form-control" onchange="this.form.submit()">
          <option value="">All Events</option>
          <?php foreach ($eventsFilter as $ev): ?>
            <option value="<?= e($ev['id']) ?>" <?= $eventId === $ev['id'] ? 'selected' : '' ?>>
              <?= e(mb_strimwidth($ev['title'], 0, 45, '…')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- KPI Cards -->
    <div class="analytics-kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">Total Bookings</div>
        <div class="kpi-value"><?= number_format($totalBookings) ?></div>
        <div class="kpi-sub">All statuses · last <?= $period ?> days</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Paid Bookings</div>
        <div class="kpi-value"><?= number_format($paidBookings) ?></div>
        <div class="kpi-sub"><?= $totalBookings > 0 ? round($paidBookings / $totalBookings * 100) : 0 ?>% conversion</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Gross Revenue</div>
        <div class="kpi-value" style="font-size:1.3rem;"><?= formatMWK($totalRevenue) ?></div>
        <div class="kpi-sub">Paid tickets only</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Avg Ticket Value</div>
        <div class="kpi-value" style="font-size:1.3rem;"><?= formatMWK($avgTicketPrice) ?></div>
        <div class="kpi-sub">Per paid booking</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Cancellations</div>
        <div class="kpi-value" style="color:var(--clr-red);"><?= number_format($cancelledBookings) ?></div>
        <div class="kpi-sub"><?= $totalBookings > 0 ? round($cancelledBookings / $totalBookings * 100) : 0 ?>% cancel rate</div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="analytics-chart-grid">
      <div class="chart-card">
        <div class="section-heading"><?= admin_icon_svg('chart') ?> Revenue Trend</div>
        <canvas id="revenueTrendChart" height="80"></canvas>
      </div>
      <div class="chart-card">
        <div class="section-heading"><?= uthenga_public_icon_svg('ticket') ?> Ticket Types</div>
        <canvas id="ticketDonut" height="200"></canvas>
        <div style="margin-top:1rem;">
          <?php foreach ($ticketTypeStats as $ttype => $tdata): ?>
            <?php if ($tdata['count'] > 0): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid var(--clr-border);font-size:.85rem;">
              <span><?= e($ttype) ?></span>
              <span class="text-muted"><?= number_format($tdata['count']) ?> sold · <?= formatMWK($tdata['revenue']) ?></span>
            </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Top Events & Promo Codes Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

      <!-- Top Events -->
      <div class="chart-card">
        <div class="section-heading"><?= uthenga_public_icon_svg('star') ?> Top Events by Revenue</div>
        <?php if (empty($topEvents)): ?>
          <div class="text-sm text-muted" style="padding:1rem;text-align:center;">No event bookings in this period.</div>
        <?php else: ?>
          <?php
            $maxRev = max(array_column($topEvents, 'revenue') ?: [1]);
          ?>
          <?php foreach ($topEvents as $i => $ev): ?>
            <div style="margin-bottom:.85rem;">
              <div style="display:flex;align-items:center;justify-content:space-between;font-size:.82rem;margin-bottom:.3rem;">
                <span style="font-weight:600;"><?= e(mb_strimwidth($ev['listing_title'], 0, 38, '…')) ?></span>
                <span class="text-muted"><?= formatMWK($ev['revenue']) ?></span>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:<?= $maxRev > 0 ? round($ev['revenue'] / $maxRev * 100) : 0 ?>%;"></div>
              </div>
              <div class="text-xs text-muted" style="margin-top:.2rem;"><?= number_format($ev['total_bookings']) ?> bookings</div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Promo Code Performance -->
      <div class="chart-card">
        <div class="section-heading"><?= uthenga_public_icon_svg('sparkles') ?> Promo Code Performance</div>
        <?php if (empty($promoUsage)): ?>
          <div class="text-sm text-muted" style="padding:1rem;text-align:center;">No promo codes configured yet.</div>
          <a href="<?= BASE_URL ?>admin/settings.php" class="btn btn-sm btn-secondary" style="margin-top:.75rem;">Manage Promo Codes</a>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
              <thead>
                <tr style="border-bottom:1px solid var(--clr-border);">
                  <th style="padding:.5rem .75rem;text-align:left;font-weight:600;">Code</th>
                  <th style="padding:.5rem .75rem;text-align:center;">Uses</th>
                  <th style="padding:.5rem .75rem;text-align:right;">Total Discount</th>
                  <th style="padding:.5rem .75rem;text-align:right;">Gross Rev</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($promoUsage as $promo): ?>
                <tr style="border-bottom:1px solid var(--clr-border);">
                  <td style="padding:.5rem .75rem;">
                    <span class="promo-badge"><?= e($promo['code']) ?></span>
                    <div class="text-xs text-muted" style="margin-top:2px;">
                      <?= $promo['discount_type'] === 'percentage' ? $promo['discount_value'] . '%' : formatMWK($promo['discount_value']) ?> off
                    </div>
                  </td>
                  <td style="padding:.5rem .75rem;text-align:center;"><?= number_format($promo['uses']) ?></td>
                  <td style="padding:.5rem .75rem;text-align:right;color:var(--clr-red);">-<?= formatMWK($promo['total_discount'] ?? 0) ?></td>
                  <td style="padding:.5rem .75rem;text-align:right;color:var(--clr-green);"><?= formatMWK($promo['gross_rev'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

        <?php if (empty($promoUsage)): ?>
          <div class="text-sm text-muted" style="padding:1rem;text-align:center;">No promo codes configured yet.</div>
          <a href="<?= BASE_URL ?>admin/settings.php" class="btn btn-sm btn-secondary" style="margin-top:.75rem;">Manage Promo Codes</a>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
              <thead>
                <tr style="border-bottom:1px solid var(--clr-border);">
                  <th style="padding:.5rem .75rem;text-align:left;font-weight:600;">Code</th>
                  <th style="padding:.5rem .75rem;text-align:center;">Uses</th>
                  <th style="padding:.5rem .75rem;text-align:right;">Total Discount</th>
                  <th style="padding:.5rem .75rem;text-align:right;">Gross Rev</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($promoUsage as $promo): ?>
                <tr style="border-bottom:1px solid var(--clr-border);">
                  <td style="padding:.5rem .75rem;">
                    <span class="promo-badge"><?= e($promo['code']) ?></span>
                    <div class="text-xs text-muted" style="margin-top:2px;">
                      <?= $promo['discount_type'] === 'percentage' ? $promo['discount_value'] . '%' : formatMWK($promo['discount_value']) ?> off
                    </div>
                  </td>
                  <td style="padding:.5rem .75rem;text-align:center;"><?= number_format($promo['uses']) ?></td>
                  <td style="padding:.5rem .75rem;text-align:right;color:var(--clr-red);">-<?= formatMWK($promo['total_discount'] ?? 0) ?></td>
                  <td style="padding:.5rem .75rem;text-align:right;color:var(--clr-green);"><?= formatMWK($promo['gross_rev'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Bookings -->
    <div class="chart-card">
      <div class="section-heading"><?= uthenga_public_icon_svg('calendar') ?> Recent Event Bookings</div>

  const trendCtx = document.getElementById('revenueTrendChart')?.getContext('2d');
  if (trendCtx && trendLabels.length > 0) {
    const gradient = trendCtx.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(6,182,212,0.35)');
    gradient.addColorStop(1, 'rgba(6,182,212,0.0)');

    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [
          {
            label: 'Revenue (MK)',
            data: trendRevenue,
            borderColor: accent,
            backgroundColor: gradient,
            borderWidth: 2,
            pointRadius: 3,
            fill: true,
            tension: 0.4,
            yAxisID: 'yRev'
          },
          {
            label: 'Bookings',
            data: trendCount,
            borderColor: green,
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            pointRadius: 2,
            borderDash: [4,3],
            yAxisID: 'yCnt'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: textClr, font: { size: 11 } } }, tooltip: { callbacks: {
          label: ctx => ctx.datasetIndex === 0 ? 'MK ' + ctx.raw.toLocaleString() : ctx.raw + ' bookings'
        }}},
        scales: {
          x: { grid: { color: gridClr }, ticks: { color: textClr, maxTicksLimit: 8 }},
          yRev: { position: 'left', grid: { color: gridClr }, ticks: { color: textClr, callback: v => 'MK ' + v.toLocaleString() }},
          yCnt: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: textClr }}
        }
      }
    });
  } else if (trendCtx) {
    const p = document.createElement('p');
    p.textContent = 'No revenue data for this period.';
    p.style.cssText = 'text-align:center;color:#64748b;padding:2rem;';
    trendCtx.canvas.replaceWith(p);
  }

  // ── Ticket Type Donut ───────────────────────────────────────────────────────
  const ticketStats = <?= json_encode($ticketTypeStats) ?>;
  const donutCtx    = document.getElementById('ticketDonut')?.getContext('2d');
  if (donutCtx) {
    const labels  = Object.keys(ticketStats).filter(k => ticketStats[k].count > 0);
    const counts  = labels.map(k => ticketStats[k].count);
    const colours = [accent, purple, green, red];

    if (counts.reduce((a, b) => a + b, 0) > 0) {
      new Chart(donutCtx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{ data: counts, backgroundColor: colours, borderWidth: 2, borderColor: '#0f0f1a' }]
        },
        options: {
          cutout: '65%',
          plugins: {
            legend: { position: 'bottom', labels: { color: textClr, font: { size: 11 }, padding: 12 } },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + ' tickets' }}
          }
        }
      });
    } else {
      donutCtx.canvas.replaceWith(Object.assign(document.createElement('p'), {
        textContent: 'No ticket data available.', style: 'text-align:center;color:#64748b;padding:1.5rem;'
      }));
    }
  }
})();
</script>

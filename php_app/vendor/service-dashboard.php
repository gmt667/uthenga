<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireApprovedVendor();

$type = strtolower((string) ($_GET['type'] ?? ''));
$catalogue = [
    'transport' => ['Transport Services Hub', 'transport service'],
    'accommodation' => ['Accommodation management', 'stay or property'],
    'event' => ['Event control', 'event'],
    'tour' => ['Tour operations', 'tour or activity'],
];
if (!isset($catalogue[$type])) { http_response_code(404); exit('Unknown service dashboard.'); }

if ($type === 'accommodation') {
    header('Location: ' . BASE_URL . 'vendor/accommodation-control-center.php', true, 302);
    exit;
}
if ($type === 'event') {
    header('Location: ' . BASE_URL . 'vendor/events-control-center.php', true, 302);
    exit;
}
if ($type === 'tour') {
    header('Location: ' . BASE_URL . 'vendor/tours-control-center.php', true, 302);
    exit;
}

$title = $catalogue[$type][0];
require_once __DIR__ . '/../includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => currentRole(),
    'title' => $title,
    'active' => 'vendor/service-dashboard.php?type=' . $type,
    'search' => false,
    'status' => 'Mobility operations hub',
]);
?>
<div class="container dashboard-content-frame" style="padding:2rem 0 4rem">
  <div class="page-header" style="margin-bottom:2rem;">
    <div>
      <p class="text-muted" style="font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--clr-accent);">TRANSPORT SERVICES HUB</p>
      <h1 class="page-title" style="font-size:2rem;font-weight:900;">Manage Your Mobility Operations</h1>
      <p class="text-muted">Select the operational workspace for your transport product line.</p>
    </div>
    <a href="<?= BASE_URL ?>vendor/portal.php" class="btn btn-secondary">← Back to Command Center</a>
  </div>

  <!-- TWO LARGE SERVICE PANELS -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
    
    <!-- 🚕 QUICK TAXI CARD -->
    <div class="glass-panel" style="padding:2.5rem;border-radius:16px;border-left:5px solid #f59e0b;display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:0.75rem;font-weight:800;color:#f59e0b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">ON-DEMAND TRANSPORT</div>
        <div style="font-size:3rem;margin-bottom:1rem;">🚕</div>
        <h2 style="font-size:1.6rem;font-weight:900;margin-bottom:0.5rem;">Quick Taxi</h2>
        <p class="text-muted" style="font-size:0.95rem;line-height:1.6;margin-bottom:1.5rem;">Accept nearby ride requests, manage taxi drivers, monitor active trips, and handle live driver dispatch operations.</p>
        
        <ul style="list-style:none;padding:0;margin:0 0 2rem 0;font-size:0.88rem;display:flex;flex-direction:column;gap:0.5rem;color:var(--clr-text-soft);">
          <li>✓ Accept nearby customer ride requests</li>
          <li>✓ Driver roster &amp; online dispatch status</li>
          <li>✓ Live GPS journey tracking</li>
          <li>✓ On-demand fare calculation</li>
        </ul>
      </div>

      <a href="<?= BASE_URL ?>ai.php#/driver" class="btn btn-primary" style="padding:0.9rem 1.5rem;font-weight:800;font-size:1rem;justify-content:center;background:#f59e0b;color:#090d16;border:none;">
        <span>Open Quick Taxi Operations</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>

    <!-- 🚌 BUS OPERATIONS CARD -->
    <div class="glass-panel" style="padding:2.5rem;border-radius:16px;border-left:5px solid var(--clr-accent);display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:0.75rem;font-weight:800;color:var(--clr-accent);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">SCHEDULED TRANSPORT</div>
        <div style="font-size:3rem;margin-bottom:1rem;">🚌</div>
        <h2 style="font-size:1.6rem;font-weight:900;margin-bottom:0.5rem;">Bus Operations</h2>
        <p class="text-muted" style="font-size:0.95rem;line-height:1.6;margin-bottom:1.5rem;">Manage bus fleets, create routes &amp; intermediate stops, publish trip schedules, sell seats, and verify tickets at boarding.</p>

        <ul style="list-style:none;padding:0;margin:0 0 2rem 0;font-size:0.88rem;display:flex;flex-direction:column;gap:0.5rem;color:var(--clr-text-soft);">
          <li>✓ Fleet asset management &amp; seat layouts</li>
          <li>✓ Route builder &amp; departure schedules</li>
          <li>✓ Seat inventory &amp; ticket sales</li>
          <li>✓ Station QR boarding scanner &amp; manifests</li>
        </ul>
      </div>

      <a href="<?= BASE_URL ?>vendor/bus-control-center.php" class="btn btn-primary" style="padding:0.9rem 1.5rem;font-weight:800;font-size:1rem;justify-content:center;">
        <span>Open Bus Operations Control Center</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>

  </div>
</div>
<?php renderDashboardChromeEnd(); ?>

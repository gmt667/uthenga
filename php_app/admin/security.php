<?php
/**
 * Uthenga - Security Center
 */
$pageTitle = 'Security Center';
$activeNav = 'admin-security';

require_once __DIR__ . '/includes/admin_header.php';

$userId = $_SESSION['user_id'] ?? null;
$success = '';
$error = '';

$hasFraudAlerts = uthenga_table_exists('fraud_alerts');
$hasLoginAlerts = uthenga_table_exists('login_alerts');
$hasAuditLogs = uthenga_table_exists('audit_logs');
$hasTwoFactor = uthenga_table_exists('two_factor_auth');
$coreHealth = ($hasAuditLogs && $hasFraudAlerts && $hasLoginAlerts) ? 'Healthy' : 'Needs Migration';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please try again.';
    } elseif (isset($_POST['resolve_fraud'])) {
        $fraudId = (int)($_POST['fraud_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'reviewed';
        if (!$hasFraudAlerts) {
            $error = 'Fraud alerts table is missing. Please run the database migrations first.';
        } elseif (in_array($newStatus, ['reviewed', 'dismissed', 'escalated'], true)) {
            dbExecute(
                'UPDATE fraud_alerts SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
                [$newStatus, $userId, $fraudId]
            );
            $success = 'Fraud alert status updated to: ' . ucfirst($newStatus);
        }
    } elseif (isset($_POST['mark_anomaly_read'])) {
        $anomalyId = (int)($_POST['anomaly_id'] ?? 0);
        if (!$hasLoginAlerts) {
            $error = 'Login alerts table is missing. Please run the database migrations first.';
        } else {
            dbExecute('UPDATE login_alerts SET is_read = 1 WHERE id = ?', [$anomalyId]);
            $success = 'Anomaly alert marked as read.';
        }
    }
}

$failedLogins = $hasAuditLogs ? dbCount("SELECT COUNT(*) FROM audit_logs WHERE action IN ('Admin Login Failed','Failed Login','Admin Access Denied')") : 0;
$suspiciousEvents = $hasAuditLogs ? dbCount("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%Suspicious%' OR action LIKE '%Lockout%' OR action LIKE '%Denied%'") : 0;
$openFraudAlerts = $hasFraudAlerts ? dbCount("SELECT COUNT(*) FROM fraud_alerts WHERE status = 'open'") : 0;
$unreadAnomalies = $hasLoginAlerts ? dbCount("SELECT COUNT(*) FROM login_alerts WHERE is_read = 0") : 0;

$healthRows = [
    ['label' => 'Database', 'value' => $hasAuditLogs ? 'Healthy' : 'Needs Migration'],
    ['label' => 'API', 'value' => file_exists(__DIR__ . '/../request_api.php') ? 'Healthy' : 'Warning'],
    ['label' => 'OPcache', 'value' => function_exists('opcache_get_status') && opcache_get_status() ? 'Enabled' : 'Disabled'],
    ['label' => '2FA Protection', 'value' => $hasTwoFactor ? 'Active' : 'Missing Table'],
    ['label' => 'Auth Alerts', 'value' => ($hasFraudAlerts && $hasLoginAlerts) ? 'Enabled' : 'Needs Migration'],
];

$fraudAlerts = $hasFraudAlerts ? dbQuery(
    'SELECT f.*, u.name AS user_name, b.booking_code
     FROM fraud_alerts f
     LEFT JOIN users u ON u.id = f.user_id
     LEFT JOIN bookings b ON b.id = f.booking_id
     ORDER BY f.created_at DESC
     LIMIT 15'
) : [];

$loginAnomalies = $hasLoginAlerts ? dbQuery(
    'SELECT l.*, u.name AS user_name
     FROM login_alerts l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 15'
) : [];

$bruteForceAttempts = $hasAuditLogs ? dbQuery(
    "SELECT
        SUBSTRING_INDEX(SUBSTRING_INDEX(details, 'IP: ', -1), ' ', 1) AS ip_address,
        COUNT(*) AS attempts_count,
        MAX(created_at) AS last_attempt_at
     FROM audit_logs
     WHERE action IN ('Failed Login', 'Admin Login Failed')
     GROUP BY ip_address
     HAVING attempts_count >= 3
     ORDER BY attempts_count DESC"
) : [];
?>
<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('shield') ?><span>Security Center</span></h1>
    <p class="text-muted">Monitor fraud risk alerts, authentication anomalies, service health, and login reports.</p>
  </div>
  <div style="display:flex; gap:0.5rem;">
    <a href="<?= BASE_URL ?>admin/audit-logs.php" class="btn btn-secondary btn-sm">System Audit Logs</a>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:1.5rem;">Success: <?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" style="margin-bottom:1.5rem;">Error: <?= e($error) ?></div><?php endif; ?>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('lock') ?></div>
    <div><div class="stat-value"><?= number_format($failedLogins) ?></div><div class="stat-label">Failed Logins</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-red"><?= admin_icon_svg('shield') ?></div>
    <div><div class="stat-value"><?= number_format($openFraudAlerts) ?></div><div class="stat-label">Open Fraud Alerts</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-purple"><?= admin_icon_svg('notification') ?></div>
    <div><div class="stat-value"><?= number_format($unreadAnomalies) ?></div><div class="stat-label">Unread Anomalies</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('database') ?></div>
    <div><div class="stat-value"><?= e($coreHealth) ?></div><div class="stat-label">Platform Core</div></div>
  </div>
</div>

<div class="grid grid-cols-3 gap-3" style="margin-bottom:1.5rem;">
  <section class="glass-panel" style="grid-column: span 1;">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('activity') ?><span>Service Status</span></h3>
        <p class="text-xs text-muted">Core operational parameters check.</p>
      </div>
    </div>
    <div class="snapshot-list" style="margin-bottom:1.5rem;">
      <?php foreach ($healthRows as $row): ?>
        <div>
          <span><?= e($row['label']) ?></span>
          <strong><?= e($row['value']) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('shield') ?><span>IP Brute Force Report</span></h3>
        <p class="text-xs text-muted">IPs with 3+ failed logins.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>IP Address</th>
            <th>Failed Count</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bruteForceAttempts)): ?>
            <tr><td colspan="2" class="text-muted text-xs">No active threats detected.</td></tr>
          <?php else: ?>
            <?php foreach ($bruteForceAttempts as $bf): ?>
              <tr>
                <td class="font-mono text-xs" style="color:#ef4444;"><?= e($bf['ip_address']) ?></td>
                <td><strong><?= number_format($bf['attempts_count']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="glass-panel" style="grid-column: span 2;">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('notification') ?><span>Fraud Risk Alerts</span></h3>
        <p class="text-xs text-muted">Suspicious booking and payment velocity triggers.</p>
      </div>
    </div>

    <?php if (empty($fraudAlerts)): ?>
      <p class="text-muted text-sm">No fraud alerts detected.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Risk Score</th>
              <th>Alert</th>
              <th>Details</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fraudAlerts as $fa): ?>
              <?php $riskColor = $fa['risk_score'] >= 80 ? '#ef4444' : ($fa['risk_score'] >= 50 ? '#f59e0b' : '#10b981'); ?>
              <tr>
                <td><strong style="color:<?= $riskColor ?>;font-size:1.1rem;"><?= (int) $fa['risk_score'] ?>%</strong></td>
                <td>
                  <strong><?= e(ucfirst(str_replace('_', ' ', $fa['alert_type']))) ?></strong>
                  <div class="text-xs text-muted">User: <?= e($fa['user_name'] ?? 'Guest') ?></div>
                </td>
                <td class="text-xs text-muted">
                  Booking: <?= e($fa['booking_code'] ?? 'None') ?>
                  <div style="margin-top:0.15rem;"><?= e($fa['details'] ?? '') ?></div>
                </td>
                <td>
                  <span class="badge badge-<?= $fa['status'] === 'open' ? 'pending' : ($fa['status'] === 'dismissed' ? 'approved' : 'rejected') ?>">
                    <?= e(ucfirst($fa['status'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($fa['status'] === 'open'): ?>
                    <form method="POST" style="margin:0; display:flex; gap:0.25rem;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="resolve_fraud" value="1">
                      <input type="hidden" name="fraud_id" value="<?= e($fa['id']) ?>">
                      <button type="submit" name="new_status" value="dismissed" class="btn btn-sm" style="background:rgba(16,185,129,0.15);color:#10b981;padding:0.2rem 0.4rem;">Dismiss</button>
                      <button type="submit" name="new_status" value="escalated" class="btn btn-sm" style="background:rgba(239,68,68,0.15);color:#ef4444;padding:0.2rem 0.4rem;">Escalate</button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted text-xs">Closed</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="glass-panel" style="padding:1.5rem;">
  <div class="section-head">
    <div>
      <h3>Login Anomaly Alerts</h3>
      <p class="text-xs text-muted">New device registration or geographic login changes.</p>
    </div>
  </div>
  <?php if (empty($loginAnomalies)): ?>
    <p class="text-muted text-sm">No login anomalies detected.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Type</th>
            <th>Metadata</th>
            <th>IP Address</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($loginAnomalies as $la): ?>
            <tr>
              <td class="text-xs text-muted"><?= e($la['created_at']) ?></td>
              <td><strong><?= e($la['user_name'] ?? 'System') ?></strong></td>
              <td><span class="badge badge-pending"><?= e(ucfirst(str_replace('_', ' ', $la['alert_type']))) ?></span></td>
              <td class="text-xs text-muted"><?= e($la['user_agent'] ?? '') ?></td>
              <td class="text-xs font-mono"><?= e($la['ip_address'] ?? '') ?></td>
              <td>
                <?php if (empty($la['is_read'])): ?>
                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="mark_anomaly_read" value="1">
                    <input type="hidden" name="anomaly_id" value="<?= e($la['id']) ?>">
                    <button type="submit" class="btn btn-xs btn-primary">Mark read</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted text-xs">Read</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

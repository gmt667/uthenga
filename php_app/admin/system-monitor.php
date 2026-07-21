<?php
/**
 * Uthenga - Admin System Monitor
 */
$pageTitle = 'System Monitor';
$activeNav = 'admin-system-monitor';

require_once __DIR__ . '/includes/admin_header.php';

// 1. Get Database size
$dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'uthenga_db');
$dbStats = dbQueryOne("
    SELECT 
        SUM(data_length + index_length) / 1024 / 1024 AS size_mb,
        COUNT(table_name) AS tables_count
    FROM information_schema.tables 
    WHERE table_schema = ?
", [$dbName]) ?: ['size_mb' => 0, 'tables_count' => 0];

// 2. Fetch Cache status
$cacheDir = __DIR__ . '/../cache';
$cacheFilesCount = 0;
$cacheSize = 0;
if (is_dir($cacheDir)) {
    $files = scandir($cacheDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $cacheDir . '/' . $file;
            if (is_file($filePath)) {
                $cacheFilesCount++;
                $cacheSize += filesize($filePath);
            }
        }
    }
}

// 3. System Load / PHP Details
$phpVersion = PHP_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$memoryLimit = ini_get('memory_limit');
$postMaxSize = ini_get('post_max_size');
$uploadMaxFilesize = ini_get('upload_max_filesize');

// 4. Session Health
$sessionsCount = uthenga_table_exists('user_sessions') ? dbCount('SELECT COUNT(*) FROM user_sessions') : 0;
$deviceSessionsCount = uthenga_table_exists('device_sessions') ? dbCount('SELECT COUNT(*) FROM device_sessions') : 0;

// 5. Query counts per table to show density
$tables = dbQuery("
    SELECT table_name, table_rows, data_length, index_length 
    FROM information_schema.tables 
    WHERE table_schema = ? 
    ORDER BY (data_length + index_length) DESC LIMIT 15
", [$dbName]);

?>
<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('monitor') ?><span>System Monitor</span></h1>
    <p class="text-muted">Monitor database usage, cache size, PHP metrics, and database table statistics.</p>
  </div>
</div>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('database') ?></div>
    <div>
      <div class="stat-value"><?= number_format((float)$dbStats['size_mb'], 2) ?> MB</div>
      <div class="stat-label">Database Size (<?= number_format($dbStats['tables_count']) ?> tables)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('activity') ?></div>
    <div>
      <div class="stat-value"><?= number_format($cacheFilesCount) ?> files</div>
      <div class="stat-label">Cached Files (<?= number_format($cacheSize / 1024, 2) ?> KB)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-purple"><?= admin_icon_svg('users') ?></div>
    <div>
      <div class="stat-value"><?= number_format($sessionsCount) ?> sessions</div>
      <div class="stat-label">Total Web Sessions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('shield') ?></div>
    <div>
      <div class="stat-value"><?= number_format($deviceSessionsCount) ?> devices</div>
      <div class="stat-label">Registered Devices</div>
    </div>
  </div>
</div>

<div class="grid grid-cols-2 gap-3">
  <!-- PHP Configuration -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('database') ?><span>Server &amp; PHP Metrics</span></h3>
        <p class="text-xs text-muted">Core server metrics and environment config.</p>
      </div>
    </div>
    <div class="snapshot-list">
      <div><span>PHP Version</span><strong><?= e($phpVersion) ?></strong></div>
      <div><span>Web Server</span><strong><?= e($serverSoftware) ?></strong></div>
      <div><span>Memory Limit</span><strong><?= e($memoryLimit) ?></strong></div>
      <div><span>Post Max Size</span><strong><?= e($postMaxSize) ?></strong></div>
      <div><span>Upload Max File Size</span><strong><?= e($uploadMaxFilesize) ?></strong></div>
      <div><span>OPcache Status</span><strong><?= function_exists('opcache_get_status') && opcache_get_status() ? 'Enabled' : 'Disabled' ?></strong></div>
    </div>
  </section>

  <!-- Top Tables by Data Size -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('report') ?><span>Database Table Usage</span></h3>
        <p class="text-xs text-muted">Top tables ranked by storage usage.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Table Name</th>
            <th>Rows</th>
            <th>Data Size</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tables as $t): ?>
            <tr>
              <td><strong><?= e($t['table_name']) ?></strong></td>
              <td><?= number_format($t['table_rows']) ?></td>
              <td><?= number_format(($t['data_length'] + $t['index_length']) / 1024, 2) ?> KB</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

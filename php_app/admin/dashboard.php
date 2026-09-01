<?php
/** Canonical, read-only Admin Overview. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/includes/control_center_data.php';

requireAdminPermission('overview.view');
$pageTitle = 'Admin Overview';
$activeNav = 'admin-dashboard';
$permissions = array_keys(array_filter(
    adminPermissionRegistry(),
    static fn(array $definition, string $permission): bool => adminHasPermission($permission),
    ARRAY_FILTER_USE_BOTH
));
$overview = acc_admin_overview_data($permissions);
$sections = $overview['sections'];

function admin_overview_section(string $key, array $sections): ?array {
    return $sections[$key] ?? null;
}
function admin_overview_status(array $section): string {
    return (string) ($section['status'] ?? 'unavailable');
}
function admin_overview_count(?array $section): ?int {
    if (!$section || admin_overview_status($section) === 'unavailable') return null;
    return isset($section['data']['count']) ? (int) $section['data']['count'] : null;
}
function admin_overview_time(string $value): string {
    if ($value === '') return 'Not recorded';
    try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Africa/Blantyre'))->format('d M Y, H:i'); }
    catch (Throwable) { return 'Not recorded'; }
}
function admin_overview_money(?float $value): string {
    return $value === null ? 'Unavailable' : 'MWK ' . number_format($value, 0);
}

require __DIR__ . '/includes/admin_header.php';
?>
<style>
.admin-overview{max-width:1260px;margin:0 auto;padding:2rem 1rem 3rem}.admin-overview h1{margin:0;font-size:clamp(1.7rem,3vw,2.35rem);letter-spacing:-.03em}.admin-overview__intro{display:flex;justify-content:space-between;gap:1.5rem;align-items:start;margin-bottom:1.75rem}.admin-overview__intro p{max-width:710px;color:var(--clr-text-soft,#64748b);margin:.55rem 0 0;line-height:1.55}.admin-overview__stamp{font-size:.8rem;color:var(--clr-text-soft,#64748b);white-space:nowrap}.admin-overview__grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}.admin-overview__card{grid-column:span 4;border:1px solid var(--clr-border,#dce2e8);background:var(--clr-surface,#fff);border-radius:12px;padding:1.15rem;min-width:0}.admin-overview__card--wide{grid-column:span 8}.admin-overview__card--full{grid-column:1/-1}.admin-overview__card h2{margin:0;font-size:1rem}.admin-overview__card-head{display:flex;gap:1rem;align-items:start;justify-content:space-between;margin-bottom:1rem}.admin-overview__metric{font-size:1.9rem;font-weight:800;letter-spacing:-.03em;margin:.35rem 0}.admin-overview__hint,.admin-overview__empty{font-size:.88rem;color:var(--clr-text-soft,#64748b);line-height:1.5}.admin-overview__status{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.28rem .55rem;background:#f1f5f9;color:#475569;font-size:.75rem;font-weight:700}.admin-overview__status::before{content:'';width:.48rem;height:.48rem;border-radius:50%;background:currentColor}.admin-overview__status--available{background:#ecfdf5;color:#047857}.admin-overview__status--empty{background:#f8fafc;color:#475569}.admin-overview__status--unavailable,.admin-overview__status--degraded{background:#fff7ed;color:#c2410c}.admin-overview__link{font-size:.85rem;font-weight:700;text-decoration:none;color:#0f5d57}.admin-overview__list{list-style:none;padding:0;margin:0}.admin-overview__list li{display:flex;justify-content:space-between;gap:1rem;padding:.7rem 0;border-top:1px solid var(--clr-border,#e5e7eb);font-size:.88rem}.admin-overview__list li:first-child{border-top:0}.admin-overview__list strong{display:block}.admin-overview__list span{color:var(--clr-text-soft,#64748b);text-align:right}.admin-overview__finance{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.admin-overview__finance div{padding:.8rem;border:1px solid var(--clr-border,#e5e7eb);border-radius:9px}.admin-overview__finance span{display:block;color:var(--clr-text-soft,#64748b);font-size:.78rem}.admin-overview__finance strong{display:block;margin-top:.25rem}.admin-overview__health{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.admin-overview__health div{border-left:3px solid #94a3b8;padding:.1rem 0 .1rem .7rem}.admin-overview__health b,.admin-overview__health span{display:block}.admin-overview__health span{font-size:.82rem;color:var(--clr-text-soft,#64748b);margin-top:.2rem}@media(max-width:900px){.admin-overview__card,.admin-overview__card--wide{grid-column:span 6}}@media(max-width:620px){.admin-overview{padding:1.25rem .85rem 2rem}.admin-overview__intro{display:block}.admin-overview__stamp{margin-top:.7rem}.admin-overview__card,.admin-overview__card--wide{grid-column:1/-1}.admin-overview__finance,.admin-overview__health{grid-template-columns:1fr}.admin-overview__list li{display:block}.admin-overview__list span{text-align:left;margin-top:.25rem}}
</style>
<main class="admin-overview" id="main-content">
  <header class="admin-overview__intro">
    <div><h1>Admin Overview</h1><p>Read-only operational queues and platform indicators from authoritative server-side sources. Sections unavailable to your role are omitted.</p></div>
    <div class="admin-overview__stamp">Observed <?= e(admin_overview_time((string) $overview['observed_at'])) ?></div>
  </header>
  <div class="admin-overview__grid">
    <?php foreach ([
      ['vendor_applications','Vendor applications','Review vendors','admin/vendors.php','vendors.view'],
      ['support_cases','Open support cases','Open support','admin/support.php','support.view'],
      ['security_alerts','Security alerts','Review security','admin/security.php','security.view'],
      ['settlements','Settlement reviews','Review settlements','admin/finance/settlements.php','settlements.review'],
    ] as [$key,$label,$action,$href,$permission]): ?>
      <?php if (($section = admin_overview_section($key, $sections)) !== null): ?>
        <section class="admin-overview__card" aria-labelledby="overview-<?= e($key) ?>">
          <div class="admin-overview__card-head"><h2 id="overview-<?= e($key) ?>"><?= e($label) ?></h2><span class="admin-overview__status admin-overview__status--<?= e(admin_overview_status($section)) ?>"><?= e(ucfirst(admin_overview_status($section))) ?></span></div>
          <?php if (($count = admin_overview_count($section)) !== null): ?><div class="admin-overview__metric"><?= number_format($count) ?></div><p class="admin-overview__hint"><?= $count === 0 ? e((string) ($section['error_public'] ?? 'No records require review.')) : 'Requires authorised review.' ?></p>
          <?php else: ?><p class="admin-overview__empty"><?= e((string) ($section['error_public'] ?? 'This operational data is unavailable.')) ?></p><?php endif; ?>
          <a class="admin-overview__link" href="<?= BASE_URL . $href ?>"><?= e($action) ?> →</a>
        </section>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (($finance = admin_overview_section('financial_overview', $sections)) !== null): ?>
      <section class="admin-overview__card admin-overview__card--wide" aria-labelledby="overview-finance">
        <div class="admin-overview__card-head"><div><h2 id="overview-finance">Financial overview</h2><p class="admin-overview__hint">Transaction-ledger states for the current day. Successful payments are not presented as settled revenue.</p></div><span class="admin-overview__status admin-overview__status--<?= e(admin_overview_status($finance)) ?>"><?= e(ucfirst(admin_overview_status($finance))) ?></span></div>
        <?php if (is_array($finance['data'] ?? null)): $money = $finance['data']; ?><div class="admin-overview__finance">
          <div><span>Successful payments</span><strong><?= e(admin_overview_money((float) $money['successful_amount'])) ?></strong></div><div><span>Pending / authorised</span><strong><?= e(admin_overview_money((float) $money['pending_amount'])) ?></strong></div><div><span>Failed payments</span><strong><?= e(admin_overview_money((float) $money['failed_amount'])) ?></strong></div><div><span>Refunded payments</span><strong><?= e(admin_overview_money((float) $money['refunded_amount'])) ?></strong></div>
        </div><p class="admin-overview__hint"><?= e((string) $money['period']) ?> · <?= e((string) $money['currency']) ?> · <?= number_format((int) $money['exception_count']) ?> payment exception(s).</p>
        <?php else: ?><p class="admin-overview__empty"><?= e((string) ($finance['error_public'] ?? 'Financial data is unavailable.')) ?></p><?php endif; ?>
        <a class="admin-overview__link" href="<?= BASE_URL ?>admin/payments.php">Open payment operations →</a>
      </section>
    <?php endif; ?>

    <?php if (adminHasPermission('platform_health.view')): $health = $sections['platform_health']; ?>
      <section class="admin-overview__card" aria-labelledby="overview-health"><div class="admin-overview__card-head"><h2 id="overview-health">Platform health</h2><span class="admin-overview__status admin-overview__status--<?= e(admin_overview_status($health)) ?>"><?= e(ucfirst(admin_overview_status($health))) ?></span></div><div class="admin-overview__health"><?php foreach (($health['data'] ?? []) as $item): ?><div><b><?= e((string) $item['name']) ?></b><span><?= e((string) $item['status']) ?> · <?= e((string) $item['detail']) ?></span></div><?php endforeach; ?></div><a class="admin-overview__link" href="<?= BASE_URL ?>admin/system-monitor.php">Open platform health →</a></section>
    <?php endif; ?>

    <?php if (($support = admin_overview_section('support_cases', $sections)) !== null && is_array($support['data']['records'] ?? null)): ?>
      <section class="admin-overview__card admin-overview__card--wide" aria-labelledby="overview-support-list"><div class="admin-overview__card-head"><h2 id="overview-support-list">Priority support queue</h2><a class="admin-overview__link" href="<?= BASE_URL ?>admin/support.php">Open all cases →</a></div><ul class="admin-overview__list"><?php foreach ($support['data']['records'] as $record): ?><li><div><strong><?= e((string) ($record['ticket_code'] ?? 'Case')) ?></strong><?= e((string) ($record['subject'] ?? 'Support case')) ?></div><span><?= e(ucfirst((string) ($record['priority'] ?? 'normal'))) ?> · <?= e(ucfirst((string) ($record['status'] ?? 'open'))) ?></span></li><?php endforeach; ?></ul><?php if ($support['data']['records'] === []): ?><p class="admin-overview__empty">No open support cases.</p><?php endif; ?></section>
    <?php endif; ?>

    <?php if (($audit = admin_overview_section('recent_audit', $sections)) !== null): ?>
      <section class="admin-overview__card admin-overview__card--full" aria-labelledby="overview-audit"><div class="admin-overview__card-head"><div><h2 id="overview-audit">Recent audit activity</h2><p class="admin-overview__hint">Limited, redacted administrative records.</p></div><a class="admin-overview__link" href="<?= BASE_URL ?>admin/audit-logs.php">Open audit log →</a></div><?php if (is_array($audit['data'] ?? null) && $audit['data'] !== []): ?><ul class="admin-overview__list"><?php foreach ($audit['data'] as $record): ?><li><div><strong><?= e((string) $record['action']) ?></strong><?= e((string) $record['actor']) ?> · <?= e((string) $record['role']) ?></div><span><?= e(admin_overview_time((string) $record['created_at'])) ?></span></li><?php endforeach; ?></ul><?php else: ?><p class="admin-overview__empty"><?= e((string) ($audit['error_public'] ?? 'Audit records are unavailable.')) ?></p><?php endif; ?></section>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>

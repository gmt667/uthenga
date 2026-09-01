<?php
/**
 * Static regression checks for the canonical Admin Overview safety contract.
 * These checks do not touch production data.
 */
$root = dirname(__DIR__);
$dataSource = (string) file_get_contents($root . '/admin/includes/control_center_data.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$api = (string) file_get_contents($root . '/api/tie/admin/control-center.php');
$sidebar = (string) file_get_contents($root . '/admin/includes/admin_sidebar.php');

function admin_overview_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

admin_overview_assert(str_contains($dataSource, "'status' => 'unavailable'"), 'Unavailable data state must be supported.');
admin_overview_assert(str_contains($dataSource, "'observed_at'"), 'Observed timestamps must be present.');
admin_overview_assert(str_contains($dataSource, "'error_public'"), 'Safe public errors must be present.');
admin_overview_assert(!str_contains($dashboard, '24821'), 'Legacy fabricated customer total remains.');
admin_overview_assert(!str_contains($dashboard, '4820000'), 'Legacy fabricated revenue remains.');
admin_overview_assert(!str_contains($dashboard, 'onclick='), 'Overview must not use inline actions.');
admin_overview_assert(str_contains($api, 'acc_admin_overview_data'), 'API must use the canonical overview contract.');
admin_overview_assert(!str_contains($sidebar, 'System Dashboard'), 'Duplicate System Dashboard navigation remains.');
admin_overview_assert(str_contains($sidebar, "'label' => 'Overview'"), 'Canonical Overview navigation is missing.');

echo "Admin Overview contract tests passed.\n";

<?php
/**
 * Source-contract guard for the Accommodation Properties boundary.
 * Database integration tests run only when XAMPP/MariaDB is available.
 */
$root = dirname(__DIR__, 2);
$workspace = file_get_contents($root . '/includes/tie/AccommodationPropertyWorkspace.php');
$migration = file_get_contents($root . '/database/migrations/033_accommodation_property_workspace.sql');
$controlCenter = file_get_contents($root . '/vendor/accommodation-control-center.php');

foreach ([
    [$workspace, 'tie_accommodation_vendor_context'],
    [$workspace, 'ready_for_review'],
    [$workspace, 'profile_version'],
    [$workspace, 'property.profile_saved'],
    [$migration, 'tie_accommodation_property_profiles'],
    [$migration, 'tie_accommodation_property_media'],
    [$controlCenter, 'ai.php#/accommodation?section=properties'],
] as [$source, $needle]) {
    if (!str_contains((string) $source, $needle)) {
        fwrite(STDERR, "Missing Accommodation Properties contract: {$needle}\n");
        exit(1);
    }
}

echo "Accommodation Properties source contracts passed\n";

<?php
/**
 * Uthenga — Map Points API
 * Returns attractions, transport points, hospitals, and ATMs.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// Validate type filter
$allowedTypes = ['attraction', 'transport', 'hospital', 'atm', 'hotel', 'restaurant', 'park'];
$type = trim($_GET['type'] ?? 'all');
if ($type !== 'all' && !in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$points = [];

if (uthenga_table_exists('map_points')) {
    try {
        if ($type === 'all') {
            $points = dbQuery("SELECT * FROM map_points WHERE is_active = 1 ORDER BY is_featured DESC, name ASC") ?: [];
        } else {
            $points = dbQuery("SELECT * FROM map_points WHERE is_active = 1 AND point_type = ? ORDER BY is_featured DESC, name ASC", [$type]) ?: [];
        }
    } catch (Throwable $e) {
        error_log('[map_points API] ' . $e->getMessage());
        $points = [];
    }
}

echo json_encode([
    'success' => true,
    'type'    => $type,
    'count'   => count($points),
    'points'  => $points,
]);
exit;

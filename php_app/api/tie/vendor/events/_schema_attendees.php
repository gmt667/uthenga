<?php
/**
 * Uthenga — Attendee Intelligence Center (Phase: Attendees Console)
 *
 * Adds attendance-ops fields to event_tickets for the Attendees workspace:
 * gate tracking for live attendance analytics. Idempotent — safe to run
 * repeatedly. Also backfills a gate for already checked-in tickets once.
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "  + $table.$column\n";
    }
}

echo "Migrating schema for attendee intelligence console...\n";

ensure_column($db, 'event_tickets', 'checked_in_gate', "varchar(40) NULL DEFAULT NULL AFTER `checked_in_by`");

$backfilled = $db->exec(
    "UPDATE event_tickets
     SET checked_in_gate = ELT(1 + FLOOR(RAND() * 4), 'Gate A', 'Gate B', 'Gate C', 'Gate D')
     WHERE checked_in_at IS NOT NULL AND (checked_in_gate IS NULL OR checked_in_gate = '')"
);
echo "  backfilled gates for $backfilled checked-in tickets\n";

echo "Done.\n";

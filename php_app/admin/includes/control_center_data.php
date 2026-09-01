<?php
/**
 * Authoritative, read-only Admin Overview data contract.
 *
 * Each section explicitly reports availability. A query error is never
 * converted to an apparently genuine zero, and no action is performed here.
 */

if (!function_exists('acc_overview_observed_at')) {
    function acc_overview_observed_at(): string {
        return (new DateTimeImmutable('now', new DateTimeZone('Africa/Blantyre')))->format(DateTimeInterface::ATOM);
    }
}

if (!function_exists('acc_overview_section')) {
    function acc_overview_section(string $source, callable $query, string $emptyMessage): array {
        $observedAt = acc_overview_observed_at();
        try {
            $data = $query();
            $isEmpty = is_array($data) && (
                (array_key_exists('count', $data) && (int) $data['count'] === 0) ||
                (array_is_list($data) && count($data) === 0)
            );
            return [
                'status' => $isEmpty ? 'empty' : 'available',
                'data' => $data,
                'observed_at' => $observedAt,
                'source' => $source,
                'error_public' => $isEmpty ? $emptyMessage : null,
            ];
        } catch (Throwable $error) {
            error_log('Admin Overview query unavailable: ' . $error->getMessage());
            return [
                'status' => 'unavailable',
                'data' => null,
                'observed_at' => $observedAt,
                'source' => $source,
                'error_public' => 'This operational data is temporarily unavailable.',
            ];
        }
    }
}

if (!function_exists('acc_overview_not_available')) {
    function acc_overview_not_available(string $source, string $message = 'This operational data is not available in this environment.'): array {
        return [
            'status' => 'unavailable',
            'data' => null,
            'observed_at' => acc_overview_observed_at(),
            'source' => $source,
            'error_public' => $message,
        ];
    }
}

if (!function_exists('acc_overview_redact_audit_action')) {
    function acc_overview_redact_audit_action(string $action): string {
        $action = trim(preg_replace('/[^a-zA-Z0-9._ -]/', '', $action) ?? '');
        return $action !== '' ? mb_substr($action, 0, 100) : 'Administrative activity recorded';
    }
}

if (!function_exists('acc_admin_overview_data')) {
    /**
     * @param string[] $permissions Canonical permissions granted to this admin.
     */
    function acc_admin_overview_data(array $permissions): array {
        $can = static fn(string $permission): bool => in_array($permission, $permissions, true);
        $sections = [];

        $database = acc_overview_section('Database connectivity probe', static function (): array {
            $probe = dbQueryOne('SELECT 1 AS connection_ok');
            if ((int) ($probe['connection_ok'] ?? 0) !== 1) throw new RuntimeException('Database probe returned no result.');
            return ['state' => 'Healthy'];
        }, 'Database probe returned no records.');
        if ($can('platform_health.view')) {
            $sections['platform_health'] = [
                'status' => $database['status'] === 'available' ? 'available' : 'unavailable',
                'data' => [[
                    'name' => 'Database',
                    'status' => $database['status'] === 'available' ? 'Healthy' : 'Unavailable',
                    'detail' => $database['status'] === 'available' ? 'Connection probe completed.' : $database['error_public'],
                ], [
                    'name' => 'Payment provider',
                    'status' => 'Not monitored',
                    'detail' => 'No authenticated provider health probe is available.',
                ], [
                    'name' => 'Notification delivery',
                    'status' => 'Not monitored',
                    'detail' => 'Delivery evidence is not instrumented.',
                ], [
                    'name' => 'AI service',
                    'status' => 'Not monitored',
                    'detail' => 'No server-side reachability probe is available.',
                ]],
                'observed_at' => $database['observed_at'],
                'source' => 'Server-side platform probes',
                'error_public' => $database['error_public'],
            ];
        }

        if ($can('vendors.view')) {
            if (uthenga_table_exists('vendor_profiles')) {
                $sections['vendor_applications'] = acc_overview_section('Vendor profile approvals', static function (): array {
                    return ['count' => dbCount("SELECT COUNT(*) FROM vendor_profiles WHERE LOWER(approval_status) = 'pending'")];
                }, 'No vendor applications require review.');
            } elseif (uthenga_table_exists('vendors')) {
                $sections['vendor_applications'] = acc_overview_section('Vendor approvals', static function (): array {
                    return ['count' => dbCount("SELECT COUNT(*) FROM vendors WHERE LOWER(status) = 'pending'")];
                }, 'No vendor applications require review.');
            } else {
                $sections['vendor_applications'] = acc_overview_not_available('Vendor approvals');
            }
        }

        if ($can('support.view')) {
            $sections['support_cases'] = uthenga_table_exists('support_tickets')
                ? acc_overview_section('Support cases', static function (): array {
                    return [
                        'count' => dbCount("SELECT COUNT(*) FROM support_tickets WHERE LOWER(status) IN ('open','in_progress','waiting_customer')"),
                        'records' => dbQuery("SELECT ticket_code, subject, priority, status, created_at FROM support_tickets WHERE LOWER(status) IN ('open','in_progress','waiting_customer') ORDER BY created_at ASC LIMIT 8"),
                    ];
                }, 'No open support cases.')
                : acc_overview_not_available('Support cases');
        }

        if ($can('payments.view')) {
            $sections['financial_overview'] = uthenga_table_exists('transactions')
                ? acc_overview_section('Transaction ledger', static function (): array {
                    $summary = dbQueryOne("SELECT
                        COALESCE(SUM(CASE WHEN LOWER(status) IN ('successful','success','paid','captured') THEN amount ELSE 0 END), 0) AS successful_amount,
                        COALESCE(SUM(CASE WHEN LOWER(status) IN ('pending','processing','initiated','authorized') THEN amount ELSE 0 END), 0) AS pending_amount,
                        COALESCE(SUM(CASE WHEN LOWER(status) IN ('failed','declined','cancelled') THEN amount ELSE 0 END), 0) AS failed_amount,
                        COALESCE(SUM(CASE WHEN LOWER(status) IN ('refunded','partially_refunded') THEN amount ELSE 0 END), 0) AS refunded_amount,
                        SUM(CASE WHEN LOWER(status) IN ('pending','processing','initiated','failed','declined','cancelled') THEN 1 ELSE 0 END) AS exception_count
                        FROM transactions WHERE created_at >= CURRENT_DATE()") ?: [];
                    return [
                        'period' => 'Today',
                        'currency' => 'MWK',
                        'successful_amount' => (float) ($summary['successful_amount'] ?? 0),
                        'pending_amount' => (float) ($summary['pending_amount'] ?? 0),
                        'failed_amount' => (float) ($summary['failed_amount'] ?? 0),
                        'refunded_amount' => (float) ($summary['refunded_amount'] ?? 0),
                        'exception_count' => (int) ($summary['exception_count'] ?? 0),
                    ];
                }, 'No transaction activity was recorded for this period.')
                : acc_overview_not_available('Transaction ledger');
        }

        if ($can('settlements.review')) {
            $sections['settlements'] = uthenga_table_exists('vendor_settlements')
                ? acc_overview_section('Vendor settlement reviews', static function (): array {
                    return ['count' => dbCount("SELECT COUNT(*) FROM vendor_settlements WHERE LOWER(status) IN ('pending','submitted','review')")];
                }, 'No settlements require review.')
                : acc_overview_not_available('Vendor settlement reviews');
        }

        if ($can('security.view')) {
            $sections['security_alerts'] = uthenga_table_exists('security_alerts')
                ? acc_overview_section('Security alert queue', static function (): array {
                    return ['count' => dbCount("SELECT COUNT(*) FROM security_alerts WHERE LOWER(status) IN ('open','active','pending')")];
                }, 'No recorded security alerts require review.')
                : acc_overview_not_available('Security alert queue', 'Security alerts are not instrumented in this environment.');
        }

        if ($can('audit.view')) {
            $sections['recent_audit'] = uthenga_table_exists('audit_logs')
                ? acc_overview_section('Administrative audit log', static function (): array {
                    $rows = dbQuery("SELECT user_name, user_role, action, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 8");
                    return array_map(static fn(array $row): array => [
                        'actor' => trim((string) ($row['user_name'] ?? '')) ?: 'System',
                        'role' => trim((string) ($row['user_role'] ?? '')) ?: 'System',
                        'action' => acc_overview_redact_audit_action((string) ($row['action'] ?? '')),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                    ], $rows);
                }, 'No audit activity is available for this period.')
                : acc_overview_not_available('Administrative audit log');
        }

        return [
            'status' => $database['status'] === 'available' ? 'available' : 'degraded',
            'observed_at' => acc_overview_observed_at(),
            'source' => 'Uthenga server-side operational data',
            'error_public' => $database['status'] === 'available' ? null : 'Some operational sections could not be read.',
            'sections' => $sections,
        ];
    }
}

<?php
/**
 * Bus Operations Center — Company Settings. No vendor self-service profile
 * edit page exists anywhere in this codebase today, for any vendor type
 * (confirmed by grepping every read/write of vendor_profiles) — only admin
 * editing and signup-time inserts. This is a genuinely new capability, and
 * must upsert since most transport vendors (e.g. v-4) have no vendor_profiles
 * row at all yet, relying on users.is_approved for approval status.
 *
 * The live database's vendor_profiles table has no business_name column at
 * all (confirmed via SHOW CREATE TABLE — it drifted from install/setup.sql's
 * definition). The real, already-in-use "business name" for a transport
 * vendor is users.name — that's what listings.vendor_name and every issued
 * ticket's "operator" field are already populated from (see
 * BusOperations::createRoute()) — so the business name here edits users.name
 * directly rather than a vendor_profiles column nothing else reads.
 */
final class UthengaTieBusSettingsService
{
    public function __construct(private ?PDO $db) {}

    public function get(string $vendorId): array
    {
        $db = $this->db();
        $userStmt = $db->prepare('SELECT name, email, is_approved FROM users WHERE id=? LIMIT 1');
        $userStmt->execute([$vendorId]);
        $user = $userStmt->fetch() ?: ['name' => '', 'email' => '', 'is_approved' => 0];

        $stmt = $db->prepare('SELECT phone, address, city, category, description, approval_status FROM vendor_profiles WHERE vendor_id=? LIMIT 1');
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch();

        // Most transport vendors have no vendor_profiles row at all — users.is_approved
        // is the real source of truth for them (matches requireVendor()'s own fallback).
        $approvalStatus = $row ? (string) $row['approval_status'] : (!empty($user['is_approved']) ? 'approved' : 'pending');

        return [
            'schema_version' => 'tie-bus-ops/v1', 'account_email' => (string) $user['email'],
            'business_name' => (string) $user['name'], 'phone' => $row ? (string) $row['phone'] : '',
            'address' => $row ? (string) $row['address'] : '', 'city' => $row ? (string) $row['city'] : '',
            'category' => $row ? (string) $row['category'] : 'Transport Provider', 'description' => $row ? (string) $row['description'] : '',
            'approval_status' => $approvalStatus,
        ];
    }

    public function save(string $vendorId, array $input): array
    {
        $businessName = UthengaTieBusOperationsContracts::nonEmptyString($input['business_name'] ?? null, 'business_name', 120);
        $phone = trim((string) ($input['phone'] ?? '')) ?: null;
        $address = trim((string) ($input['address'] ?? '')) ?: null;
        $city = trim((string) ($input['city'] ?? '')) ?: null;
        $description = trim((string) ($input['description'] ?? '')) ?: null;

        $db = $this->db();
        $db->prepare('UPDATE users SET name=? WHERE id=?')->execute([$businessName, $vendorId]);

        $existsStmt = $db->prepare('SELECT 1 FROM vendor_profiles WHERE vendor_id=? LIMIT 1');
        $existsStmt->execute([$vendorId]);
        if ($existsStmt->fetchColumn()) {
            $db->prepare('UPDATE vendor_profiles SET phone=?, address=?, city=?, description=?, updated_at=NOW() WHERE vendor_id=?')
                ->execute([$phone, $address, $city, $description, $vendorId]);
        } else {
            // Seed approval_status from the real users.is_approved signal on first
            // save, so an already-approved vendor's new profile row never regresses
            // them to the table's own 'pending' default.
            $approvedStmt = $db->prepare('SELECT is_approved FROM users WHERE id=? LIMIT 1');
            $approvedStmt->execute([$vendorId]);
            $status = !empty($approvedStmt->fetchColumn()) ? 'approved' : 'pending';
            $db->prepare('INSERT INTO vendor_profiles (vendor_id, phone, address, city, category, description, approval_status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$vendorId, $phone, $address, $city, 'Transport Provider', $description, $status]);
        }

        return $this->get($vendorId);
    }

    private const TICKET_TEMPLATE_STYLES = ['classic_blue', 'modern_card', 'minimal_white', 'premium_dark', 'mobile_wallet'];

    public function getTicketTemplate(string $vendorId): array
    {
        $stmt = $this->db()->prepare('SELECT template_style, logo_url, accent_color, footer_message, contact_phone, contact_email FROM tie_bus_ticket_templates WHERE vendor_id=? LIMIT 1');
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch();
        return [
            'schema_version' => 'tie-bus-ops/v1',
            'template_style' => $row ? (string) $row['template_style'] : 'classic_blue',
            'logo_url' => $row ? $row['logo_url'] : null, 'accent_color' => $row ? $row['accent_color'] : null,
            'footer_message' => $row ? $row['footer_message'] : null, 'contact_phone' => $row ? $row['contact_phone'] : null, 'contact_email' => $row ? $row['contact_email'] : null,
        ];
    }

    public function saveTicketTemplate(string $vendorId, array $input): array
    {
        $style = trim((string) ($input['template_style'] ?? 'classic_blue'));
        if (!in_array($style, self::TICKET_TEMPLATE_STYLES, true)) throw UthengaTieErrors::validation(['template_style' => 'Choose a valid ticket template.']);

        $logoUrl = trim((string) ($input['logo_url'] ?? '')) ?: null;
        if ($logoUrl !== null && !filter_var($logoUrl, FILTER_VALIDATE_URL)) throw UthengaTieErrors::validation(['logo_url' => 'Enter a valid logo URL.']);

        $accentColor = trim((string) ($input['accent_color'] ?? '')) ?: null;
        if ($accentColor !== null && !preg_match('/^#[0-9a-f]{6}$/i', $accentColor)) throw UthengaTieErrors::validation(['accent_color' => 'Enter a valid hex color, e.g. #1d4ed8.']);

        $footerMessage = mb_substr(trim((string) ($input['footer_message'] ?? '')), 0, 300) ?: null;
        $contactPhone = trim((string) ($input['contact_phone'] ?? '')) ?: null;

        $contactEmail = trim((string) ($input['contact_email'] ?? '')) ?: null;
        if ($contactEmail !== null && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) throw UthengaTieErrors::validation(['contact_email' => 'Enter a valid contact email.']);

        $this->db()->prepare('INSERT INTO tie_bus_ticket_templates (vendor_id, template_style, logo_url, accent_color, footer_message, contact_phone, contact_email) VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE template_style=VALUES(template_style), logo_url=VALUES(logo_url), accent_color=VALUES(accent_color), footer_message=VALUES(footer_message), contact_phone=VALUES(contact_phone), contact_email=VALUES(contact_email), updated_at=NOW()')
            ->execute([$vendorId, $style, $logoUrl, $accentColor, $footerMessage, $contactPhone, $contactEmail]);

        return $this->getTicketTemplate($vendorId);
    }

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('bus_settings');
        return $this->db;
    }
}

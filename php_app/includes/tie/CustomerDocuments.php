<?php
/**
 * Trip Planning Assistant — Documents. A personal/trip document wallet, NOT
 * the separate 5-table Events Control Center Documents workspace (that one
 * is vendor-tenant-scoped with a business-document category enum that
 * doesn't fit personal travel documents). Upload/storage/serving mirrors
 * UthengaAccommodationPropertyWorkspace::storeUpload() exactly: real $_FILES
 * validation, true MIME sniffing via finfo (never the client-claimed type),
 * a random on-disk filename with chmod 0600, and a sha256 checksum. The
 * whole storage/ directory is already denied direct web access
 * (storage/.htaccess), and files are only ever served back through
 * documents/file.php after an ownership/visibility check — never a public path.
 */
final class UthengaTieCustomerDocumentsContracts
{
    private const CATEGORIES = ['personal', 'travel', 'reservation', 'financial', 'trip_document', 'other'];

    public static function category($value): string
    {
        $category = strtolower(trim((string) $value));
        return in_array($category, self::CATEGORIES, true) ? $category : 'other';
    }

    public static function label($value): string
    {
        $label = trim((string) $value);
        if ($label === '' || mb_strlen($label) > 120) throw UthengaTieErrors::validation(['label' => 'A document name between 1 and 120 characters is required.']);
        return $label;
    }

    public static function visibility($value): string
    {
        $visibility = strtolower(trim((string) $value));
        return $visibility === 'trip' ? 'trip' : 'personal';
    }

    public static function expiryDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        try { return (new DateTimeImmutable($value))->format('Y-m-d'); }
        catch (Throwable) { throw UthengaTieErrors::validation(['expiry_date' => 'Use a valid date.']); }
    }
}

final class UthengaTieCustomerDocumentsService
{
    private const MAX_UPLOAD = 10 * 1024 * 1024;
    private const ALLOWED_MIME = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    private string $storageDir;

    public function __construct(private ?PDO $db, private UthengaTieTripCollaborationService $collaboration, string $storageDir = '')
    {
        $this->storageDir = $storageDir !== '' ? rtrim($storageDir, '/') : dirname(__DIR__, 2) . '/storage/customer-documents';
    }

    public function list(string $customerId, array $filters = []): array
    {
        $this->db();
        $where = ['customer_id = ?']; $params = [$customerId];
        if (!empty($filters['category'])) { $where[] = 'category = ?'; $params[] = $filters['category']; }
        if (!empty($filters['trip_id'])) { $where[] = 'trip_id = ?'; $params[] = $filters['trip_id']; }
        $stmt = $this->db->prepare('SELECT * FROM customer_documents WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200');
        $stmt->execute($params);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        return ['schema_version' => 'tie-customer-documents/v1', 'documents' => array_map(fn(array $row): array => $this->publicDocument($row, $today), $stmt->fetchAll())];
    }

    public function upload(array $user, array $file, array $input): array
    {
        $this->db();
        $customerId = (string) $user['id'];
        $category = UthengaTieCustomerDocumentsContracts::category($input['category'] ?? null);
        $label = UthengaTieCustomerDocumentsContracts::label($input['label'] ?? null);
        $visibility = UthengaTieCustomerDocumentsContracts::visibility($input['visibility'] ?? null);
        $expiryDate = UthengaTieCustomerDocumentsContracts::expiryDate($input['expiry_date'] ?? null);
        $isSensitive = !empty($input['sensitive']);
        $tripId = trim((string) ($input['trip_id'] ?? ''));
        if ($tripId !== '') {
            if ($this->collaboration->accessFor($tripId, $customerId) === null) throw UthengaTieErrors::validation(['trip_id' => 'Trip not found for this account.']);
        } else {
            $tripId = null; $visibility = 'personal';
        }

        $stored = $this->storeUpload($file);
        $id = $this->uuid();
        $this->db->prepare('INSERT INTO customer_documents (id, customer_id, category, label, trip_id, visibility, original_name, storage_name, mime_type, size_bytes, checksum_sha256, expiry_date, is_sensitive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $customerId, $category, $label, $tripId, $visibility, $stored['original_name'], $stored['storage_name'], $stored['mime_type'], $stored['size_bytes'], $stored['checksum'], $expiryDate, $isSensitive ? 1 : 0]);

        return $this->list($customerId);
    }

    public function delete(string $customerId, string $documentId): array
    {
        $this->db();
        $row = $this->row($documentId);
        if ($row === null || (string) $row['customer_id'] !== $customerId) throw UthengaTieErrors::authorization();
        $path = $this->storagePath($row['storage_name']);
        if (is_file($path)) @unlink($path);
        $this->db->prepare('DELETE FROM customer_documents WHERE id = ?')->execute([$documentId]);
        return $this->list($customerId);
    }

    /** Ownership/visibility check + the info documents/file.php needs to stream bytes. */
    public function fileMeta(string $requesterId, string $documentId): array
    {
        $this->db();
        $row = $this->row($documentId);
        if ($row === null) throw new UthengaTieException('not_found', 'Document not found.', 404);
        $allowed = (string) $row['customer_id'] === $requesterId
            || ((string) $row['visibility'] === 'trip' && $row['trip_id'] !== null && $this->collaboration->accessFor((string) $row['trip_id'], $requesterId) !== null);
        if (!$allowed) throw new UthengaTieException('not_found', 'Document not found.', 404);
        return ['path' => $this->storagePath($row['storage_name']), 'mime_type' => (string) $row['mime_type'], 'original_name' => (string) $row['original_name']];
    }

    private function publicDocument(array $row, DateTimeImmutable $today): array
    {
        $expiry = $row['expiry_date'] !== null ? (string) $row['expiry_date'] : null;
        $status = 'none'; $daysRemaining = null;
        if ($expiry !== null) {
            $expiryDate = new DateTimeImmutable($expiry, new DateTimeZone('UTC'));
            $daysRemaining = (int) $today->diff($expiryDate)->format('%r%a');
            $status = $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring_soon' : 'valid');
        }
        return [
            'id' => (string) $row['id'], 'category' => (string) $row['category'], 'label' => (string) $row['label'],
            'trip_id' => $row['trip_id'] !== null ? (string) $row['trip_id'] : null, 'visibility' => (string) $row['visibility'],
            'original_name' => (string) $row['original_name'], 'mime_type' => (string) $row['mime_type'], 'size_bytes' => (int) $row['size_bytes'],
            'expiry_date' => $expiry, 'expiry_status' => $status, 'days_remaining' => $daysRemaining,
            'sensitive' => (bool) $row['is_sensitive'], 'created_at' => $this->utcIso((string) $row['created_at']),
        ];
    }

    private function row(string $documentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_documents WHERE id = ? LIMIT 1');
        $stmt->execute([$documentId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function storeUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw UthengaTieErrors::validation(['file' => 'Choose a valid uploaded file.']);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_UPLOAD) throw UthengaTieErrors::validation(['file' => 'The file must be smaller than ' . (self::MAX_UPLOAD / 1024 / 1024) . ' MB.']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) throw UthengaTieErrors::validation(['file' => 'Only PDF, JPG, or PNG files are accepted.']);
        if (str_starts_with($mime, 'image/') && @getimagesize((string) $file['tmp_name']) === false) throw UthengaTieErrors::validation(['file' => 'The uploaded image is invalid.']);

        // 0777: PHP CLI (used for migrations/scripts) and the web server
        // (running as its own user, e.g. "daemon") don't share a group in
        // this environment — every sibling storage/ bucket already needs
        // world-writable permissions for uploads to actually succeed
        // through the live server, confirmed by real end-to-end testing.
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0777, true) && !is_dir($this->storageDir)) throw UthengaTieErrors::providerUnavailable('secure_file_storage');
        @chmod($this->storageDir, 0777);
        $name = bin2hex(random_bytes(18)) . '.' . self::ALLOWED_MIME[$mime];
        $path = $this->storageDir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $path)) throw UthengaTieErrors::providerUnavailable('secure_file_storage');
        @chmod($path, 0600);
        return ['storage_name' => $name, 'original_name' => mb_substr(basename((string) ($file['name'] ?? 'upload')), 0, 255), 'mime_type' => $mime, 'size_bytes' => $size, 'checksum' => hash_file('sha256', $path)];
    }

    private function storagePath(string $name): string
    {
        return $this->storageDir . '/' . basename($name);
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('customer_documents');
    }
}

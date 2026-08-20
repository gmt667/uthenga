<?php
/** Event Documents service — a controlled document repository for the Events
 *  Control Center. Documents carry the metadata an enterprise document system
 *  needs (version, status, category, event scope, creator, tags, retention,
 *  legal hold) plus a full audit trail of versions, shares and activity.
 *
 *  Live-data principle: generated reports (attendance, finance, ticket sales,
 *  customer summaries, reviews, event summaries) are rendered from the
 *  operational tables at generation time — facts are never stored twice —
 *  then kept here with a source_label recording which module produced them.
 *  File bytes live under storage/event-documents keyed by a random storage_key
 *  that is never exposed publicly; the API serves previews and downloads
 *  behind authentication. */

final class UthengaDocumentsService
{
    public const SCHEMA = 'tie-documents/v1';

    private const CATEGORIES = ['EVENTS', 'FINANCE', 'TICKETS', 'VENUES', 'MARKETING', 'CUSTOMERS', 'STAFF', 'BUSINESS', 'REPORTS', 'OTHER'];
    private const STATUSES = ['DRAFT', 'PENDING_REVIEW', 'APPROVED', 'FINAL', 'ARCHIVED'];
    private const GEN_TYPES = ['attendance_report', 'financial_report', 'ticket_sales_report', 'customer_report', 'review_report', 'event_summary'];
    private const GEN_FORMATS = ['pdf', 'csv', 'html'];
    private const SHARE_PERMS = ['VIEW', 'COMMENT', 'EDIT'];
    private const TEMPLATE_VARS = ['event_name', 'event_date', 'event_time', 'venue_name', 'event_status', 'revenue', 'orders', 'tickets_sold', 'attendance', 'checkins', 'capacity', 'avg_rating', 'reviews_count', 'customer_name', 'amount', 'ticket_type', 'ticket_number', 'generated_at', 'organizer'];
    private const STORAGE_QUOTA = 10737418240;   // 10 GB per tenant
    private const MAX_UPLOAD = 20971520;         // 20 MB per file
    private const AUTO_SOURCES = [
        'attendance_report' => 'Check-In → Attendance',
        'financial_report' => 'Finance → Event Settlement',
        'ticket_sales_report' => 'Tickets → Sales',
        'customer_report' => 'Customers → Summary',
        'review_report' => 'Reviews → Feedback',
        'event_summary' => 'Analytics → Event Summary',
    ];

    private string $storageDir;

    public function __construct(private PDO $db, string $storageDir = '')
    {
        $this->storageDir = $storageDir !== ''
            ? rtrim($storageDir, '/')
            : dirname(__DIR__, 2) . '/storage/event-documents';
    }

    /* ── shared helpers ─────────────────────────────────────────────── */

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** This vendor's event rows: listing_id + event_id + schedule facts. */
    private function eventRows(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT e.listing_id, e.id AS event_id, e.title, e.status,
                                        e.start_date, e.start_time, e.end_date, e.end_time, e.venue_id
                                 FROM tie_events_events e
                                 WHERE e.vendor_id=? AND e.listing_id IS NOT NULL
                                 ORDER BY e.start_date ASC, e.title ASC');
        $s->execute([$vendorId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Event options for filters and event-scoping (public facade). */
    public function eventsList(string $vendorId): array
    {
        return array_map(fn($e) => [
            'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'],
            'title' => $e['title'], 'start_date' => $e['start_date'], 'status' => $e['status'],
        ], $this->eventRows($vendorId));
    }

    private function eventTitle(?string $eventId, string $vendorId): ?string
    {
        if (!$eventId) return null;
        $s = $this->db->prepare('SELECT title FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        $t = $s->fetchColumn();
        return $t === false ? null : (string) $t;
    }

    private function listingIdFor(?string $eventId, string $vendorId): ?string
    {
        if (!$eventId) return null;
        $s = $this->db->prepare('SELECT listing_id FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        $l = $s->fetchColumn();
        return $l === false ? null : (string) $l;
    }

    private function listingIn(string $vendorId): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $this->eventRows($vendorId));
        return $ids ? implode(',', $ids) : "''";
    }

    private function audit(string $vendorId, string $documentId, string $action, string $actorName, array $details = []): void
    {
        $s = $this->db->prepare('INSERT INTO tie_docs_activity (vendor_id, document_id, action, actor_name, details)
                                 VALUES (?,?,?,?,?)');
        $s->execute([$vendorId, $documentId, $action, $actorName, $details ? json_encode($details) : null]);
    }

    private function storagePath(string $key): string
    {
        return $this->storageDir . '/' . $key;
    }

    private function storeFile(string $contents): array
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw UthengaTieErrors::providerUnavailable('storage');
        }
        $key = $this->uuid();
        if (@file_put_contents($this->storagePath($key), $contents, LOCK_EX) === false) {
            throw UthengaTieErrors::providerUnavailable('storage');
        }
        return ['storage_key' => $key, 'size_bytes' => strlen($contents)];
    }

    private function readFile(string $key): string
    {
        $p = $this->storagePath($key);
        if (!is_file($p) || !is_readable($p)) {
            throw UthengaTieErrors::providerUnavailable('storage');
        }
        return (string) file_get_contents($p);
    }

    private function removeFile(string $key): void
    {
        $p = $this->storagePath($key);
        if (is_file($p)) @unlink($p);
    }

    private function extensionType(string $ext): array
    {
        $map = [
            'pdf' => ['PDF', 'application/pdf'],
            'doc' => ['DOC', 'application/msword'], 'docx' => ['DOCX', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['XLS', 'application/vnd.ms-excel'], 'xlsx' => ['XLSX', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['PPT', 'application/vnd.ms-powerpoint'], 'pptx' => ['PPTX', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'png' => ['PNG', 'image/png'], 'jpg' => ['JPG', 'image/jpeg'], 'jpeg' => ['JPG', 'image/jpeg'],
            'csv' => ['CSV', 'text/csv'], 'txt' => ['TXT', 'text/plain'], 'html' => ['HTM', 'text/html'],
        ];
        $ext = strtolower(ltrim((string) $ext, '.'));
        return $map[$ext] ?? ['DOC', 'application/octet-stream'];
    }

    /** Public enum material for the console. */
    public function enums(): array
    {
        return [
            'categories' => self::CATEGORIES,
            'statuses' => self::STATUSES,
            'gen_types' => self::GEN_TYPES,
            'gen_formats' => self::GEN_FORMATS,
            'share_perms' => self::SHARE_PERMS,
            'template_vars' => self::TEMPLATE_VARS,
            'auto_sources' => self::AUTO_SOURCES,
            'storage_quota' => self::STORAGE_QUOTA,
            'max_upload' => self::MAX_UPLOAD,
        ];
    }

    /* ── dashboard / overview ───────────────────────────────────────── */

    public function overview(string $vendorId): array
    {
        $c = $this->db->prepare('SELECT
            COUNT(*) AS total,
            SUM(status <> "ARCHIVED") AS active,
            SUM(status = "ARCHIVED") AS archived,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recent_30d,
            SUM(created_at >= CURDATE()) AS today,
            COALESCE(SUM(size_bytes),0) AS used_bytes
            FROM tie_docs_documents WHERE vendor_id=?');
        $c->execute([$vendorId]);
        $c = $c->fetch(PDO::FETCH_ASSOC) ?: [];

        $sh = $this->db->prepare('SELECT COUNT(DISTINCT document_id) AS shared
                                  FROM tie_docs_shares WHERE vendor_id=?');
        $sh->execute([$vendorId]);
        $shared = (int) ($sh->fetchColumn() ?: 0);

        $cat = $this->db->prepare('SELECT category, COUNT(*) AS n FROM tie_docs_documents
                                   WHERE vendor_id=? AND status <> "ARCHIVED"
                                   GROUP BY category ORDER BY n DESC');
        $cat->execute([$vendorId]);
        $categories = $cat->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $folders = array_map(fn($e) => [
            'event_id' => $e['event_id'], 'title' => $e['title'], 'status' => $e['status'],
            'start_date' => $e['start_date'], 'listing_id' => $e['listing_id'],
        ], $this->eventRows($vendorId));
        if ($folders) {
            $eIn = implode(',', array_map(fn($e) => $this->db->quote($e['event_id']), $folders));
            $dc = $this->db->prepare("SELECT event_id, COUNT(*) AS n, SUM(size_bytes) AS bytes
                                      FROM tie_docs_documents
                                      WHERE vendor_id=? AND event_id IN ($eIn) AND status <> 'ARCHIVED'
                                      GROUP BY event_id");
            $dc->execute([$vendorId]);
            $counts = [];
            foreach ($dc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) $counts[$r['event_id']] = $r;
            foreach ($folders as &$f) {
                $f['documents'] = (int) ($counts[$f['event_id']]['n'] ?? 0);
                $f['bytes'] = (int) ($counts[$f['event_id']]['bytes'] ?? 0);
            }
            unset($f);
        }

        $used = (int) ($c['used_bytes'] ?? 0);
        return [
            'counts' => [
                'total' => (int) ($c['total'] ?? 0),
                'active' => (int) ($c['active'] ?? 0),
                'archived' => (int) ($c['archived'] ?? 0),
                'recent_30d' => (int) ($c['recent_30d'] ?? 0),
                'today' => (int) ($c['today'] ?? 0),
                'shared' => $shared,
            ],
            'storage' => [
                'used_bytes' => $used,
                'quota_bytes' => self::STORAGE_QUOTA,
                'pct' => $used > 0 ? round($used * 100 / self::STORAGE_QUOTA, 1) : 0,
                'label' => $this->formatBytes($used) . ' of 10 GB',
            ],
            'categories' => $categories,
            'event_folders' => $folders,
            'recent' => $this->documents($vendorId, ['limit' => 6])['items'],
            'activity' => $this->activityFeed($vendorId, 10),
        ];
    }

    /* ── listing ────────────────────────────────────────────────────── */

    /** Filtered, sorted document list. f: view, q, category, event_id,
     *  doc_type, status, creator, tag, sort, limit. */
    public function documents(string $vendorId, array $f = []): array
    {
        $where = ['d.vendor_id = ?'];
        $p = [$vendorId];
        $view = $f['view'] ?? 'all';
        if ($view === 'mine' && !empty($f['actor_id'])) {
            $where[] = 'd.created_by_id = ?';
            $p[] = $f['actor_id'];
        } elseif ($view === 'shared') {
            $where[] = 'EXISTS (SELECT 1 FROM tie_docs_shares sh WHERE sh.document_id = d.id)';
        } elseif ($view === 'reports') {
            $where[] = 'd.source_kind = "GENERATED"';
        } elseif ($view === 'archived') {
            $where[] = 'd.status = "ARCHIVED"';
        }
        if (!empty($f['q'])) {
            $q = '%' . $f['q'] . '%';
            $qb = '';
            $ev = $this->db->prepare('SELECT id FROM tie_events_events WHERE vendor_id=? AND title LIKE ? LIMIT 25');
            $ev->execute([$vendorId, $q]);
            $matchIds = $ev->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($matchIds) {
                $qb = ' OR d.event_id IN (' . implode(',', array_map(fn($x) => $this->db->quote((string) $x), $matchIds)) . ')';
            }
            $where[] = '(d.name LIKE ? OR d.doc_type LIKE ? OR d.category LIKE ? OR
                         d.source_label LIKE ? OR d.created_by LIKE ? OR d.tags LIKE ?' . $qb . ')';
            array_push($p, $q, $q, $q, $q, $q, $q);
        }
        if (!empty($f['category'])) { $where[] = 'd.category = ?'; $p[] = $f['category']; }
        if (!empty($f['event_id'])) { $where[] = 'd.event_id = ?'; $p[] = $f['event_id']; }
        if (!empty($f['doc_type'])) { $where[] = 'd.doc_type = ?'; $p[] = strtoupper($f['doc_type']); }
        if (!empty($f['status'])) { $where[] = 'd.status = ?'; $p[] = $f['status']; }
        if (!empty($f['creator'])) { $where[] = 'd.created_by = ?'; $p[] = $f['creator']; }
        if (!empty($f['tag'])) { $where[] = 'd.tags LIKE ?'; $p[] = '%"' . $f['tag'] . '"%'; }

        $order = match ($f['sort'] ?? 'updated') {
            'created' => 'd.created_at DESC',
            'name' => 'd.name ASC',
            'size' => 'd.size_bytes DESC',
            default => 'd.updated_at DESC',
        };
        $limit = min((int) ($f['limit'] ?? 50), 200);

        $s = $this->db->prepare('SELECT d.id, d.name, d.doc_type, d.category, d.event_id, d.listing_id,
                                        d.size_bytes, d.mime, d.status, d.version, d.source_kind,
                                        d.source_label, d.source_ref, d.tags, d.locked_by, d.locked_at,
                                        d.legal_hold, d.retention_months, d.created_by, d.created_by_id,
                                        d.last_viewed_at, d.created_at, d.updated_at,
                                        (SELECT COUNT(*) FROM tie_docs_shares sh WHERE sh.document_id = d.id) AS shared_with
                                 FROM tie_docs_documents d
                                 WHERE ' . implode(' AND ', $where) . '
                                 ORDER BY ' . $order . ' LIMIT ' . $limit);
        $s->execute($p);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(fn($r) => [
            'id' => $r['id'],
            'name' => $r['name'],
            'doc_type' => $r['doc_type'],
            'category' => $r['category'],
            'event_id' => $r['event_id'],
            'event_title' => $this->eventTitle($r['event_id'], $vendorId),
            'size_bytes' => (int) $r['size_bytes'],
            'size_label' => $this->formatBytes((int) $r['size_bytes']),
            'mime' => $r['mime'],
            'status' => $r['status'],
            'version' => (int) $r['version'],
            'source_kind' => $r['source_kind'],
            'source_label' => $r['source_label'],
            'tags' => $r['tags'] ? (json_decode($r['tags'], true) ?: []) : [],
            'locked' => $r['locked_by'] !== null,
            'locked_by' => $r['locked_by'],
            'legal_hold' => (int) $r['legal_hold'] === 1,
            'shared_with' => (int) $r['shared_with'],
            'created_by' => $r['created_by'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
            'last_viewed_at' => $r['last_viewed_at'],
        ], $rows);

        return ['items' => $items, 'total' => count($items)];
    }

    public function filterOptions(string $vendorId): array
    {
        $s = $this->db->prepare("SELECT
            (SELECT COUNT(DISTINCT doc_type) FROM tie_docs_documents WHERE vendor_id=?) AS types,
            (SELECT COUNT(DISTINCT created_by) FROM tie_docs_documents WHERE vendor_id=?) AS creators,
            (SELECT COUNT(DISTINCT category) FROM tie_docs_documents WHERE vendor_id=?) AS cats");
        $s->execute([$vendorId, $vendorId, $vendorId]);
        $s = $s->fetch(PDO::FETCH_ASSOC) ?: [];

        $docTypes = $this->db->prepare('SELECT DISTINCT doc_type FROM tie_docs_documents WHERE vendor_id=? ORDER BY doc_type');
        $docTypes->execute([$vendorId]);
        $creators = $this->db->prepare('SELECT DISTINCT created_by FROM tie_docs_documents WHERE vendor_id=? ORDER BY created_by');
        $creators->execute([$vendorId]);

        return [
            'categories' => self::CATEGORIES,
            'statuses' => self::STATUSES,
            'doc_types' => $docTypes->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'creators' => $creators->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'available' => $s,
        ];
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB', 'MB', 'GB'];
        $i = -1;
        while ($bytes >= 1024 && $i < 2) { $bytes /= 1024; $i++; }
        return round($bytes, $i < 1 ? 0 : 1) . ' ' . $units[$i];
    }

    /* ── detail / file access ───────────────────────────────────────── */

    public function detail(string $vendorId, string $documentId): array
    {
        $s = $this->db->prepare('SELECT * FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$documentId, $vendorId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);

        $vv = $this->db->prepare('SELECT version, name, size_bytes, note, created_by, created_at
                                  FROM tie_docs_versions WHERE document_id=? ORDER BY version DESC');
        $vv->execute([$documentId]);
        $versions = array_map(fn($r) => [
            'version' => (int) $r['version'], 'name' => $r['name'],
            'size_bytes' => (int) $r['size_bytes'], 'size_label' => $this->formatBytes((int) $r['size_bytes']),
            'note' => $r['note'], 'created_by' => $r['created_by'], 'created_at' => $r['created_at'],
        ], $vv->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $t = $this->db->prepare('SELECT id, sharee_name, permission, created_by, created_at
                                 FROM tie_docs_shares WHERE document_id=? ORDER BY created_at DESC');
        $t->execute([$documentId]);
        $shares = $t->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $a = $this->db->prepare("SELECT document_id, action, actor_name, details, created_at
                                 FROM tie_docs_activity WHERE document_id=?
                                 ORDER BY id DESC LIMIT 50");
        $a->execute([$documentId]);
        $activity = array_map(fn($r) => [
            'action' => $r['action'], 'actor' => $r['actor_name'],
            'details' => $r['details'] ? (json_decode($r['details'], true) ?: []) : [],
            'at' => $r['created_at'],
        ], $a->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return [
            'document' => [
                'id' => $d['id'], 'name' => $d['name'], 'doc_type' => $d['doc_type'],
                'category' => $d['category'], 'event_id' => $d['event_id'],
                'event_title' => $this->eventTitle($d['event_id'], $vendorId),
                'size_bytes' => (int) $d['size_bytes'], 'size_label' => $this->formatBytes((int) $d['size_bytes']),
                'mime' => $d['mime'], 'status' => $d['status'], 'version' => (int) $d['version'],
                'source_kind' => $d['source_kind'], 'source_label' => $d['source_label'],
                'source_ref' => $d['source_ref'], 'source_link' => $this->sourceModule($d['source_label']),
                'tags' => $d['tags'] ? (json_decode($d['tags'], true) ?: []) : [],
                'locked_by' => $d['locked_by'], 'locked_at' => $d['locked_at'],
                'legal_hold' => (int) $d['legal_hold'] === 1,
                'retention_months' => $d['retention_months'],
                'created_by' => $d['created_by'], 'created_at' => $d['created_at'],
                'updated_at' => $d['updated_at'], 'last_viewed_at' => $d['last_viewed_at'],
            ],
            'versions' => $versions,
            'shares' => $shares,
            'activity' => $activity,
            'constants' => [
                'categories' => self::CATEGORIES, 'statuses' => self::STATUSES,
                'share_perms' => self::SHARE_PERMS,
            ],
        ];
    }

    /** Which control-center module produced this document (for deep links). */
    private function sourceModule(?string $label): ?string
    {
        if (!$label) return null;
        foreach (['Finance' => 'finance', 'Attendance' => 'check-in', 'Tickets' => 'tickets',
                  'Customers' => 'customers', 'Reviews' => 'reviews',
                  'Analytics' => 'analytics', 'Marketing' => 'check-in'] as $hay => $mod) {
            if (str_contains($label, $hay)) return $mod;
        }
        return 'documents';
    }

    /** Serve file bytes behind auth. $kind: preview|download. */
    public function file(string $vendorId, string $documentId, string $kind = 'preview', ?string $actorId = null): array
    {
        $s = $this->db->prepare('SELECT id, name, mime, doc_type, size_bytes, storage_key
                                 FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$documentId, $vendorId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);

        $u = $this->db->prepare('UPDATE tie_docs_documents SET last_viewed_at=NOW() WHERE id=?');
        $u->execute([$documentId]);
        $this->audit($vendorId, $documentId, $kind === 'download' ? 'downloaded' : 'viewed',
            $actorId ?: 'Organizer');

        return [
            'name' => $d['name'], 'mime' => $d['mime'], 'doc_type' => $d['doc_type'],
            'size_bytes' => (int) $d['size_bytes'],
            'contents' => base64_encode($this->readFile($d['storage_key'])),
        ];
    }

    /* ── create / upload ────────────────────────────────────────────── */

    /** Create a document from a blank page or a saved template. */
    public function create(array $user, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $category = strtoupper((string) ($input['category'] ?? 'OTHER'));
        if ($name === '') throw UthengaTieErrors::validation(['name' => 'A document name is required.']);
        if (mb_strlen($name) > 220) throw UthengaTieErrors::validation(['name' => 'Name must be at most 220 characters.']);
        if (!in_array($category, self::CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Invalid category.']);

        $vendorId = (string) $user['id'];
        $actorId = (string) $user['id'];
        $actorName = (string) ($user['name'] ?? 'Organizer');
        $eventId = $input['event_id'] ?? null;
        $status = in_array($input['status'] ?? null, self::STATUSES, true) ? $input['status'] : 'DRAFT';
        $tags = $this->normalizeTags($input['tags'] ?? []);
        $templateId = $input['template_id'] ?? null;
        $retention = isset($input['retention_months']) && (int) $input['retention_months'] > 0
            ? min((int) $input['retention_months'], 240) : null;

        if ($eventId !== null) {
            $check = $this->eventTitle($eventId, $vendorId);
            if (!$check) throw UthengaTieErrors::validation(['event_id' => 'Event not found for this account.']);
        }

        $vars = $this->renderTemplateVars($vendorId, $eventId, $actorName);
        $docType = 'PDF';
        $body = '';
        if ($templateId) {
            $t = $this->db->prepare('SELECT id, title, body, doc_type FROM tie_docs_templates
                                     WHERE id=? AND vendor_id=? AND is_active=1');
            $t->execute([$templateId, $vendorId]);
            $template = $t->fetch(PDO::FETCH_ASSOC);
            if (!$template) throw UthengaTieErrors::validation(['template_id' => 'Template not found.']);
            $docType = $template['doc_type'] ?: 'PDF';
            $body = $this->applyVars($template['body'], $vars);
            $this->db->prepare('UPDATE tie_docs_templates SET usage_count=usage_count+1 WHERE id=?')
                ->execute([$templateId]);
        } else {
            $docType = strtoupper((string) ($input['doc_type'] ?? 'PDF'));
            if (!preg_match('/^[A-Z0-9]{1,12}$/', $docType)) throw UthengaTieErrors::validation(['doc_type' => 'Invalid document type.']);
            $body = "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . "</title><style>body{font-family:Georgia,serif;margin:2.6cm;color:#1c2b36;line-height:1.6}"
                . "h1{font-size:24px;border-bottom:3px solid #0f6fd8;padding-bottom:8px;color:#0d2b4a}"
                . ".sk{display:block;margin-top:10px;color:#5a6b7a;font-size:13px}</style></head><body>"
                . "<h1>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</h1>"
                . "<span class=\"sk\">" . htmlspecialchars($vars['event_name'] ?: $vendorId, ENT_QUOTES, 'UTF-8')
                . " · generated " . $vars['generated_at'] . "</span><p style=\"margin-top:24px\"></p></body></html>";
        }

        if (mb_strlen($body) > 1000000) throw UthengaTieErrors::validation(['name' => 'Document body is too large.']);

        $stored = $this->storeFile($body);
        $id = $this->uuid();
        $src = $templateId ? 'TEMPLATE' : 'UPLOAD';
        $this->db->prepare('INSERT INTO tie_docs_documents
            (id, vendor_id, name, doc_type, category, event_id, listing_id, size_bytes, mime,
             storage_key, content_hash, status, version, source_kind, source_label, source_ref,
             template_id, tags, retention_months, created_by, created_by_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $vendorId, $name, $docType, $category, $eventId,
                $this->listingIdFor($eventId, $vendorId), $stored['size_bytes'], 'text/html',
                $stored['storage_key'], hash('sha256', $body), $status, 1,
                $src, $templateId ? $this->db->query('SELECT title FROM tie_docs_templates WHERE id=' . $this->db->quote($templateId))->fetchColumn() : null,
                $templateId, $templateId, json_encode($tags), $retention, $actorName, $actorId]);
        $this->db->prepare('INSERT INTO tie_docs_versions (document_id, version, name, size_bytes, storage_key, note, created_by)
                            VALUES (?,1,?,?,?,?,?)')
            ->execute([$id, $name, $stored['size_bytes'], $stored['storage_key'],
                $templateId ? 'Created from template' : 'Initial version', $actorName]);
        $this->audit($vendorId, $id, 'created', $actorName, ['category' => $category, 'doc_type' => $docType, 'source' => $src]);

        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    /** Upload one or more files. Accepts multipart files (tmp_name) or base64. */
    public function upload(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $actorId = (string) $user['id'];
        $actorName = (string) ($user['name'] ?? 'Organizer');
        $category = strtoupper((string) ($input['category'] ?? 'OTHER'));
        if (!in_array($category, self::CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Invalid category.']);
        $eventId = $input['event_id'] ?? null;
        if ($eventId !== null && !$this->eventTitle($eventId, $vendorId)) {
            throw UthengaTieErrors::validation(['event_id' => 'Event not found for this account.']);
        }
        $status = in_array($input['status'] ?? null, self::STATUSES, true) ? $input['status'] : 'FINAL';
        $tags = $this->normalizeTags($input['tags'] ?? []);

        $files = [];
        if (!empty($input['files']) && is_array($input['files'])) {
            foreach ($input['files'] as $fi) {
                if (isset($fi['content_base64'])) {
                    $files[] = ['name' => $fi['name'] ?? 'document.bin', 'contents' => base64_decode($fi['content_base64']) ?: ''];
                } elseif (!empty($fi['tmp_name']) && is_file($fi['tmp_name'])) {
                    $files[] = ['name' => $fi['name'] ?? basename($fi['tmp_name']), 'contents' => (string) file_get_contents($fi['tmp_name'])];
                }
            }
        }
        if (!empty($input['file']) && is_array($input['file'])) {
            $fi = $input['file'];
            if (!empty($fi['content_base64'])) {
                $files[] = ['name' => $fi['name'] ?? 'document.bin', 'contents' => base64_decode($fi['content_base64']) ?: ''];
            } elseif (!empty($fi['tmp_name']) && is_file($fi['tmp_name'])) {
                $files[] = ['name' => $fi['name'] ?? basename($fi['tmp_name']), 'contents' => (string) file_get_contents($fi['tmp_name'])];
            }
        }
        if (!$files && !empty($input['content_base64'])) {
            $files[] = [
                'name' => $input['name'] ?? 'document.bin',
                'contents' => base64_decode($input['content_base64']) ?: '',
            ];
        }
        if (!$files) throw UthengaTieErrors::validation(['file' => 'No file content was received.']);

        $created = [];
        foreach ($files as $f) {
            $filename = trim((string) ($f['name'] ?? ''));
            $filename = $filename === '' ? 'document.bin' : preg_replace('/[\\/:*?"<>|]/', '_', $filename);
            if (mb_strlen($filename) > 220) $filename = mb_substr($filename, 0, 220);
            $contents = (string) $f['contents'];
            if ($contents === '') throw UthengaTieErrors::validation(['file' => 'File "' . $filename . '" is empty.']);
            if (strlen($contents) > self::MAX_UPLOAD) {
                throw UthengaTieErrors::validation(['file' => 'File "' . $filename . '" exceeds the 20 MB limit.']);
            }
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            [$docType, $mime] = $this->extensionType($ext);
            $stored = $this->storeFile($contents);
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO tie_docs_documents
                (id, vendor_id, name, doc_type, category, event_id, listing_id, size_bytes, mime,
                 storage_key, content_hash, status, version, source_kind, source_label, tags, created_by, created_by_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id, $vendorId, $filename, $docType, $category, $eventId,
                    $this->listingIdFor($eventId, $vendorId), $stored['size_bytes'], $mime,
                    $stored['storage_key'], hash('sha256', $contents), $status, 1,
                    'UPLOAD', 'Upload → Workspace', json_encode($tags), $actorName, $actorId]);
            $this->db->prepare('INSERT INTO tie_docs_versions (document_id, version, name, size_bytes, storage_key, note, created_by)
                                VALUES (?,1,?,?,?,?,?)')
                ->execute([$id, $filename, $stored['size_bytes'], $stored['storage_key'], 'Initial upload', $actorName]);
            $this->audit($vendorId, $id, 'uploaded', $actorName, ['category' => $category, 'size' => $stored['size_bytes']]);
            $created[] = ['id' => $id, 'name' => $filename];
        }

        return ['created' => $created];
    }

    /* ── mutations ──────────────────────────────────────────────────── */

    public function rename(array $user, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 220) throw UthengaTieErrors::validation(['name' => 'Name must be 1–220 characters.']);
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $s = $this->db->prepare('UPDATE tie_docs_documents SET name=? WHERE id=? AND vendor_id=?');
        $s->execute([$name, $id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        $this->audit($vendorId, $id, 'renamed', (string) ($user['name'] ?? 'Organizer'), ['to' => $name]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function move(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $category = strtoupper((string) ($input['category'] ?? ''));
        if (!in_array($category, self::CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Invalid category.']);
        $eventId = $input['event_id'] ?? null;
        if ($eventId !== null && !$this->eventTitle($eventId, $vendorId)) {
            throw UthengaTieErrors::validation(['event_id' => 'Event not found for this account.']);
        }
        $s = $this->db->prepare('UPDATE tie_docs_documents SET category=?, event_id=?, listing_id=?
                                 WHERE id=? AND vendor_id=?');
        $s->execute([$category, $eventId, $this->listingIdFor($eventId, $vendorId), $id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        $this->audit($vendorId, $id, 'moved', (string) ($user['name'] ?? 'Organizer'), ['category' => $category, 'event_id' => $eventId]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function setStatus(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $status = strtoupper((string) ($input['status'] ?? ''));
        if (!in_array($status, self::STATUSES, true)) throw UthengaTieErrors::validation(['status' => 'Invalid status.']);
        $s = $this->db->prepare('SELECT status FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        $from = $s->fetchColumn();
        if ($from === false) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        if ($from === $status) return ['document' => $this->detail($vendorId, $id)['document']];
        $this->db->prepare('UPDATE tie_docs_documents SET status=? WHERE id=?')->execute([$status, $id]);
        $this->audit($vendorId, $id, 'status_changed', (string) ($user['name'] ?? 'Organizer'), ['from' => $from, 'to' => $status]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function archive(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $s = $this->db->prepare('UPDATE tie_docs_documents SET status="ARCHIVED" WHERE id=? AND vendor_id=? AND status<>"ARCHIVED"');
        $s->execute([$id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found or already archived.']);
        $this->audit($vendorId, $id, 'archived', (string) ($user['name'] ?? 'Organizer'));
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function unarchive(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $to = $this->previousStatus($vendorId, $id) ?: 'FINAL';
        $s = $this->db->prepare('UPDATE tie_docs_documents SET status=? WHERE id=? AND vendor_id=? AND status="ARCHIVED"');
        $s->execute([$to, $id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found or not archived.']);
        $this->audit($vendorId, $id, 'unarchived', (string) ($user['name'] ?? 'Organizer'), ['to' => $to]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    /** Status the document held before it was archived (from the audit trail). */
    private function previousStatus(string $vendorId, string $documentId): ?string
    {
        $s = $this->db->prepare('SELECT details FROM tie_docs_activity
                                 WHERE document_id=? AND action="status_changed"
                                   AND JSON_EXTRACT(details, "$.to") = "ARCHIVED"
                                 ORDER BY id DESC LIMIT 1');
        $s->execute([$documentId]);
        $details = $s->fetchColumn();
        if (!$details) return null;
        $d = json_decode((string) $details, true);
        return $d['from'] ?? null;
    }

    public function delete(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $s = $this->db->prepare('SELECT storage_key, legal_hold FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        if ((int) $d['legal_hold'] === 1) {
            throw UthengaTieErrors::validation(['legal_hold' => 'This document is under a legal hold and cannot be deleted.']);
        }
        $vs = $this->db->prepare('SELECT storage_key FROM tie_docs_versions WHERE document_id=?');
        $vs->execute([$id]);
        $keys = $vs->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $this->audit($vendorId, $id, 'deleted', (string) ($user['name'] ?? 'Organizer'));
        $this->db->prepare('DELETE FROM tie_docs_documents WHERE id=?')->execute([$id]);
        foreach ($keys as $k) $this->removeFile((string) $k);
        return ['deleted' => true];
    }

    public function lock(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $enabled = (bool) ($input['lock'] ?? true);
        $actor = (string) ($user['name'] ?? 'Organizer');
        if ($enabled) {
            $s = $this->db->prepare('UPDATE tie_docs_documents SET locked_by=?, locked_at=NOW()
                                     WHERE id=? AND vendor_id=? AND locked_by IS NULL');
            $s->execute([$actor, $id, $vendorId]);
            if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found or already locked.']);
            $this->audit($vendorId, $id, 'locked', $actor);
        } else {
            $s = $this->db->prepare('UPDATE tie_docs_documents SET locked_by=NULL, locked_at=NULL
                                     WHERE id=? AND vendor_id=?');
            $s->execute([$id, $vendorId]);
            if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
            $this->audit($vendorId, $id, 'unlocked', $actor);
        }
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function updateTags(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $tags = $this->normalizeTags($input['tags'] ?? []);
        $s = $this->db->prepare('UPDATE tie_docs_documents SET tags=? WHERE id=? AND vendor_id=?');
        $s->execute([json_encode($tags), $id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        $this->audit($vendorId, $id, 'tags_updated', (string) ($user['name'] ?? 'Organizer'), ['tags' => $tags]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    private function normalizeTags(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
            if (!is_array($raw)) $raw = preg_split('/[,\s]+/', trim($raw));
        }
        $raw = is_array($raw) ? $raw : [];
        $out = [];
        foreach ($raw as $t) {
            $t = trim((string) $t);
            if ($t === '' || mb_strlen($t) > 60) continue;
            $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
            if (!in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= 20) break;
        }
        return $out;
    }

    /* ── versions ───────────────────────────────────────────────────── */

    public function versionUpload(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $actorName = (string) ($user['name'] ?? 'Organizer');
        $actorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $s = $this->db->prepare('SELECT * FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        if ($d['locked_by'] !== null) {
            throw UthengaTieErrors::validation(['locked' => 'This document is locked by ' . $d['locked_by'] . ' and cannot be updated.']);
        }
        $contents = '';
        if (!empty($input['content_base64'])) {
            $contents = base64_decode((string) $input['content_base64']) ?: '';
        } elseif (!empty($input['file']) && is_array($input['file']) && !empty($input['file']['tmp_name']) && is_file($input['file']['tmp_name'])) {
            $contents = (string) file_get_contents($input['file']['tmp_name']);
        }
        if ($contents === '') throw UthengaTieErrors::validation(['file' => 'No file content was received.']);
        if (strlen($contents) > self::MAX_UPLOAD) throw UthengaTieErrors::validation(['file' => 'File exceeds the 20 MB limit.']);

        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 220) throw UthengaTieErrors::validation(['note' => 'Note must be at most 220 characters.']);
        $stored = $this->storeFile($contents);
        $next = (int) $d['version'] + 1;
        $this->db->prepare('INSERT INTO tie_docs_versions (document_id, version, name, size_bytes, storage_key, note, created_by)
                            VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $next, $d['name'], $stored['size_bytes'], $stored['storage_key'], $note ?: 'Version ' . $next, $actorName]);
        $this->db->prepare('UPDATE tie_docs_documents SET version=?, size_bytes=?, storage_key=?, content_hash=?
                            WHERE id=?')
            ->execute([$next, $stored['size_bytes'], $stored['storage_key'], hash('sha256', $contents), $id]);
        $this->audit($vendorId, $id, 'version_created', $actorName, ['version' => $next, 'note' => $note]);
        return ['document' => $this->detail($vendorId, $id)['document'], 'version' => $next];
    }

    /** Roll a document back to an earlier version (kept as a new version). */
    public function versionRestore(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $actorName = (string) ($user['name'] ?? 'Organizer');
        $id = (string) ($input['document_id'] ?? '');
        $target = (int) ($input['version'] ?? 0);
        if ($target < 1) throw UthengaTieErrors::validation(['version' => 'Invalid version number.']);

        $s = $this->db->prepare('SELECT * FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);
        if ($d['locked_by'] !== null) {
            throw UthengaTieErrors::validation(['locked' => 'This document is locked by ' . $d['locked_by'] . ' and cannot be updated.']);
        }
        if ((int) $d['version'] === $target) {
            throw UthengaTieErrors::validation(['version' => 'This is already the current version.']);
        }
        $v = $this->db->prepare('SELECT version, name, size_bytes, storage_key FROM tie_docs_versions
                                 WHERE document_id=? AND version=?');
        $v->execute([$id, $target]);
        $old = $v->fetch(PDO::FETCH_ASSOC);
        if (!$old) throw UthengaTieErrors::validation(['version' => 'Version not found.']);

        $contents = $this->readFile((string) $old['storage_key']);
        $stored = $this->storeFile($contents);
        $next = (int) $d['version'] + 1;
        $this->db->prepare('INSERT INTO tie_docs_versions (document_id, version, name, size_bytes, storage_key, note, created_by)
                            VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $next, $d['name'], $stored['size_bytes'], $stored['storage_key'],
                'Restored from version ' . $target, $actorName]);
        $this->db->prepare('UPDATE tie_docs_documents SET version=?, size_bytes=?, storage_key=?, content_hash=?
                            WHERE id=?')
            ->execute([$next, $stored['size_bytes'], $stored['storage_key'], hash('sha256', $contents), $id]);
        $this->audit($vendorId, $id, 'version_restored', $actorName, ['from' => $target, 'to' => $next]);
        return ['document' => $this->detail($vendorId, $id)['document'], 'version' => $next];
    }

    /* ── sharing ────────────────────────────────────────────────────── */

    public function share(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $sharee = trim((string) ($input['sharee_name'] ?? ''));
        $permission = strtoupper((string) ($input['permission'] ?? 'VIEW'));
        if ($sharee === '' || mb_strlen($sharee) > 120) throw UthengaTieErrors::validation(['sharee_name' => 'Sharee name must be 1–120 characters.']);
        if (!in_array($permission, self::SHARE_PERMS, true)) throw UthengaTieErrors::validation(['permission' => 'Invalid permission.']);

        $s = $this->db->prepare('SELECT id FROM tie_docs_documents WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        if (!$s->fetchColumn()) throw UthengaTieErrors::validation(['document_id' => 'Document not found.']);

        $sid = $this->uuid();
        $s = $this->db->prepare('INSERT INTO tie_docs_shares (id, vendor_id, document_id, sharee_name, permission, created_by)
                                 VALUES (?,?,?,?,?,?)
                                 ON DUPLICATE KEY UPDATE permission=VALUES(permission)');
        $s->execute([$sid, $vendorId, $id, $sharee, $permission, (string) ($user['name'] ?? 'Organizer')]);
        $this->audit($vendorId, $id, 'shared', (string) ($user['name'] ?? 'Organizer'), ['with' => $sharee, 'permission' => $permission]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    public function unshare(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['document_id'] ?? '');
        $sharee = trim((string) ($input['sharee_name'] ?? ''));
        if ($sharee === '') throw UthengaTieErrors::validation(['sharee_name' => 'Sharee name is required.']);
        $s = $this->db->prepare('DELETE FROM tie_docs_shares WHERE document_id=? AND sharee_name=?');
        $s->execute([$id, $sharee]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['sharee_name' => 'No such share exists.']);
        $this->audit($vendorId, $id, 'unshared', (string) ($user['name'] ?? 'Organizer'), ['with' => $sharee]);
        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    /* ── templates ──────────────────────────────────────────────────── */

    public function templates(string $vendorId, bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE vendor_id=? AND is_active=1' : 'WHERE vendor_id=?';
        $s = $this->db->prepare('SELECT id, title, category, doc_type, description, body, is_active, usage_count, created_at, updated_at
                                 FROM tie_docs_templates ' . $where . ' ORDER BY usage_count DESC, title ASC');
        $s->execute([$vendorId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($t) => [
            'id' => $t['id'], 'title' => $t['title'], 'category' => $t['category'],
            'doc_type' => $t['doc_type'], 'description' => $t['description'],
            'is_active' => (int) $t['is_active'] === 1, 'usage_count' => (int) $t['usage_count'],
            'variables' => $this->extractVars($t['body']),
            'created_at' => $t['created_at'], 'updated_at' => $t['updated_at'],
        ], $rows);
    }

    public function saveTemplate(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($title === '' || mb_strlen($title) > 140) throw UthengaTieErrors::validation(['title' => 'Title must be 1–140 characters.']);
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'Template body is required.']);
        if (mb_strlen($body) > 60000) throw UthengaTieErrors::validation(['body' => 'Template is too long (max 60 KB).']);
        $category = strtoupper((string) ($input['category'] ?? 'EVENTS'));
        if (!in_array($category, self::CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Invalid category.']);
        $docType = strtoupper((string) ($input['doc_type'] ?? 'PDF'));
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $docType)) throw UthengaTieErrors::validation(['doc_type' => 'Invalid document type.']);
        $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 240);
        $isActive = (bool) ($input['is_active'] ?? true);

        $vars = $this->extractVars($body);
        foreach ($vars as $v) {
            if (!in_array($v, self::TEMPLATE_VARS, true)) {
                throw UthengaTieErrors::validation(['body' => 'Variable {{' . $v . '}} is not allowed. Allowed: ' . implode(', ', self::TEMPLATE_VARS)]);
            }
        }

        if (!empty($input['template_id'])) {
            $s = $this->db->prepare('UPDATE tie_docs_templates SET title=?, body=?, category=?, doc_type=?, description=?, is_active=?
                                     WHERE id=? AND vendor_id=?');
            $s->execute([$title, $body, $category, $docType, $description, (int) $isActive, $input['template_id'], $vendorId]);
            if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['template_id' => 'Template not found.']);
            $id = (string) $input['template_id'];
        } else {
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO tie_docs_templates (id, vendor_id, title, category, doc_type, description, body, is_active)
                                VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$id, $vendorId, $title, $category, $docType, $description, $body, (int) $isActive]);
        }
        return ['template' => $this->templatesById($vendorId, $id)];
    }

    public function deleteTemplate(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $id = (string) ($input['template_id'] ?? '');
        $s = $this->db->prepare('DELETE FROM tie_docs_templates WHERE id=? AND vendor_id=?');
        $s->execute([$id, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['template_id' => 'Template not found.']);
        return ['deleted' => true];
    }

    private function templatesById(string $vendorId, string $id): ?array
    {
        foreach ($this->templates($vendorId, false) as $t) {
            if ($t['id'] === $id) return $t;
        }
        return null;
    }

    private function extractVars(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $m);
        return array_values(array_unique($m[1] ?? []));
    }

    private function applyVars(string $body, array $vars): string
    {
        $search = []; $replace = [];
        foreach ($vars as $k => $v) {
            $search[] = '{{' . $k . '}}';
            $replace[] = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        }
        return str_replace($search, $replace, $body);
    }

    private function renderTemplateVars(string $vendorId, ?string $eventId, string $organizer): array
    {
        $vars = [
            'event_name' => '', 'event_date' => '', 'event_time' => '', 'venue_name' => '',
            'event_status' => '', 'revenue' => '—', 'orders' => '—', 'tickets_sold' => '—',
            'attendance' => '—', 'checkins' => '—', 'capacity' => '—', 'avg_rating' => '—',
            'reviews_count' => '—', 'customer_name' => $organizer, 'amount' => '—',
            'ticket_type' => '—', 'ticket_number' => '—', 'organizer' => $organizer,
            'generated_at' => date('Y-m-d H:i'),
        ];
        $listingIn = $this->listingIn($vendorId);

        if ($eventId) {
            $s = $this->db->prepare('SELECT e.title, e.status, e.start_date, e.start_time, e.venue_id
                                     FROM tie_events_events e WHERE e.id=? AND e.vendor_id=?');
            $s->execute([$eventId, $vendorId]);
            $e = $s->fetch(PDO::FETCH_ASSOC);
            if ($e) {
                $vars['event_name'] = $e['title'];
                $vars['event_date'] = $e['start_date'];
                $vars['event_time'] = $e['start_time'] ? substr($e['start_time'], 0, 5) : '';
                $vars['event_status'] = $e['status'];
                if ($e['venue_id']) {
                    $v = $this->db->prepare('SELECT name FROM tie_venues WHERE id=?');
                    $v->execute([$e['venue_id']]);
                    $vars['venue_name'] = (string) ($v->fetchColumn() ?: '');
                }
                $cap = $this->db->prepare('SELECT capacity FROM tie_venue_spaces WHERE venue_id=? AND capacity>0 ORDER BY capacity DESC LIMIT 1');
                $cap->execute([$e['venue_id']]);
                $vars['capacity'] = $cap->fetchColumn() !== false ? (string) $cap->fetchColumn() : '—';
                $cap->closeCursor();
            }
            if ($listingIn !== "''") {
                $m = $this->db->prepare('SELECT COALESCE(SUM(grand_total),0), COALESCE(SUM(quantity),0)
                                         FROM bookings WHERE listing_id=? AND deleted_at IS NULL AND booking_status="confirmed"');
                $m->execute([$this->listingIdFor($eventId, $vendorId)]);
                [$rev, $qty] = $m->fetch(PDO::FETCH_NUM);
                $vars['revenue'] = number_format((float) $rev, 2);
                $vars['orders'] = (string) (int) $qty;
                $m2 = $this->db->prepare('SELECT COUNT(*) FROM event_tickets WHERE listing_id=?');
                $m2->execute([$this->listingIdFor($eventId, $vendorId)]);
                $vars['tickets_sold'] = (string) (int) $m2->fetchColumn();
                $m3 = $this->db->prepare('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL');
                $m3->execute([$this->listingIdFor($eventId, $vendorId)]);
                $vars['checkins'] = (string) (int) $m3->fetchColumn();
                $vars['attendance'] = $vars['checkins'];
            }
        } else {
            if ($listingIn !== "''") {
                $b = $this->db->prepare('SELECT COALESCE(SUM(grand_total),0) FROM bookings
                                         WHERE listing_id IN (' . $listingIn . ') AND deleted_at IS NULL');
                $b->execute();
                $vars['revenue'] = number_format((float) $b->fetchColumn(), 2);
                $t = $this->db->prepare('SELECT COUNT(*) FROM event_tickets WHERE listing_id IN (' . $listingIn . ')');
                $t->execute();
                $vars['tickets_sold'] = (string) (int) $t->fetchColumn();
            }
            $r = $this->db->prepare('SELECT COUNT(*), AVG(rating) FROM reviews WHERE listing_id IN (' . $listingIn . ')');
            $r->execute();
            [$cnt, $avg] = $r->fetch(PDO::FETCH_NUM);
            if ((int) $cnt > 0) {
                $vars['reviews_count'] = (string) (int) $cnt;
                $vars['avg_rating'] = number_format((float) $avg, 1);
            }
        }
        return $vars;
    }

    /* ── generated reports (live data) ──────────────────────────────── */

    public function generate(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $actorId = (string) $user['id'];
        $actorName = (string) ($user['name'] ?? 'Organizer');
        $genType = (string) ($input['gen_type'] ?? '');
        if (!in_array($genType, self::GEN_TYPES, true)) throw UthengaTieErrors::validation(['gen_type' => 'Unknown report type.']);
        $format = (string) ($input['format'] ?? 'pdf');
        if (!in_array($format, self::GEN_FORMATS, true)) throw UthengaTieErrors::validation(['format' => 'Invalid format.']);
        $eventId = $input['event_id'] ?? null;
        $eventRequired = in_array($genType, ['attendance_report', 'ticket_sales_report', 'event_summary'], true);
        if ($eventRequired && (!$eventId || !$this->eventTitle((string) $eventId, $vendorId))) {
            throw UthengaTieErrors::validation(['event_id' => 'A valid event is required for this report.']);
        }

        $report = match ($genType) {
            'attendance_report' => $this->attendanceReport($vendorId, (string) $eventId),
            'financial_report' => $this->financialReport($vendorId, $eventId),
            'ticket_sales_report' => $this->ticketSalesReport($vendorId, (string) $eventId),
            'customer_report' => $this->customerReport($vendorId, $eventId),
            'review_report' => $this->reviewReport($vendorId, $eventId),
            'event_summary' => $this->eventSummaryReport($vendorId, (string) $eventId),
        };

        if ($format === 'csv') {
            $contents = $report['csv'];
            $ext = '.csv';
            $docType = 'CSV';
            $mime = 'text/csv';
        } else {
            $contents = $report['html'];
            $ext = '.html';
            $docType = 'PDF';
            $mime = 'text/html';
        }

        $name = $report['name'] . ($format === 'pdf' ? '.pdf' : '.' . $format);
        $stored = $this->storeFile($contents);
        $id = $this->uuid();
        $this->db->prepare('INSERT INTO tie_docs_documents
            (id, vendor_id, name, doc_type, category, event_id, listing_id, size_bytes, mime,
             storage_key, content_hash, status, version, source_kind, source_label, source_ref,
             tags, created_by, created_by_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $vendorId, $name, $docType, 'REPORTS', $eventId,
                $this->listingIdFor($eventId, $vendorId), $stored['size_bytes'], $mime,
                $stored['storage_key'], hash('sha256', $contents), 'FINAL', 1, 'GENERATED',
                self::AUTO_SOURCES[$genType], $eventId, json_encode(['report']), $actorName, $actorId]);
        $this->db->prepare('INSERT INTO tie_docs_versions (document_id, version, name, size_bytes, storage_key, note, created_by)
                            VALUES (?,1,?,?,?,?,?)')
            ->execute([$id, $name, $stored['size_bytes'], $stored['storage_key'], 'Generated from live operating data', $actorName]);
        $this->audit($vendorId, $id, 'generated', $actorName, ['gen_type' => $genType, 'format' => $format, 'event_id' => $eventId]);

        return ['document' => $this->detail($vendorId, $id)['document']];
    }

    private function eventFacts(string $vendorId, string $eventId): array
    {
        $s = $this->db->prepare('SELECT e.title, e.status, e.start_date, e.start_time, e.end_date, e.venue_id, e.listing_id
                                 FROM tie_events_events e WHERE e.id=? AND e.vendor_id=? AND e.listing_id IS NOT NULL');
        $s->execute([$eventId, $vendorId]);
        $e = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$e) return [];
        $e['venue_name'] = '';
        if (!empty($e['venue_id'])) {
            $v = $this->db->prepare('SELECT name FROM tie_venues WHERE id=?');
            $v->execute([$e['venue_id']]);
            $e['venue_name'] = (string) ($v->fetchColumn() ?: '');
        }
        return $e;
    }

    private function shell(string $title, string $subtitle, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</title><style>body{font-family:Georgia,serif;margin:2.2cm 2.6cm;color:#1c2b36;line-height:1.55;font-size:14px}'
            . 'h1{font-size:25px;margin:0 0 4px;color:#0d2b4a}h2{font-size:16px;color:#0d2b4a;border-bottom:2px solid #0f6fd8;padding-bottom:5px;margin-top:26px}'
            . '.sub{color:#5a6b7a;font-size:12.5px;margin-bottom:22px}.grid{display:flex;flex-wrap:wrap;gap:12px}'
            . '.kpi{border:1px solid #dde6ee;border-radius:8px;padding:12px 16px;min-width:150px}'
            . '.kpi b{display:block;font-size:20px;color:#0f6fd8}.kpi span{font-size:11.5px;color:#5a6b7a;text-transform:uppercase;letter-spacing:.6px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border-bottom:1px solid #e4ebf1;padding:7px 9px;text-align:left;font-size:13px}'
            . 'th{background:#f2f6fa;color:#0d2b4a;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px}'
            . '.foot{margin-top:34px;color:#8a97a3;font-size:11px;border-top:1px solid #e4ebf1;padding-top:10px}</style></head><body>'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div class="sub">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</div>'
            . $body
            . '<div class="foot">Generated by the Events Control Center · ' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')
            . ' · ' . date('Y-m-d H:i') . '</div></body></html>';
    }

    private function attendanceReport(string $vendorId, string $eventId): array
    {
        $e = $this->eventFacts($vendorId, $eventId);
        $s = $this->db->prepare('SELECT status, COUNT(*) AS n FROM event_tickets
                                 WHERE listing_id=? GROUP BY status ORDER BY n DESC');
        $s->execute([$e['listing_id']]);
        $byStatus = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total = array_sum(array_map(fn($r) => (int) $r['n'], $byStatus));
        $checkedIn = 0; $checkedOut = 0;
        foreach ($byStatus as $r) {
            if ($r['status'] === 'CHECKED_IN') $checkedIn = (int) $r['n'];
            if ($r['status'] === 'CHECKED_OUT') $checkedOut = (int) $r['n'];
        }
        $rows = '';
        foreach ($byStatus as $r) {
            $pct = $total > 0 ? round((int) $r['n'] * 100 / $total, 1) : 0;
            $rows .= '<tr><td>' . htmlspecialchars($r['status']) . '</td><td>' . (int) $r['n'] . '</td><td>' . $pct . '%</td></tr>';
        }
        $csv = "Status,Count\n" . implode('', array_map(fn($r) => $r['status'] . ',' . $r['n'] . "\n", $byStatus));
        $body = '<div class="grid">'
            . '<div class="kpi"><b>' . $total . '</b><span>Tickets issued</span></div>'
            . '<div class="kpi"><b>' . $checkedIn . '</b><span>Checked in</span></div>'
            . '<div class="kpi"><b>' . $checkedOut . '</b><span>Checked out</span></div>'
            . '</div><h2>By ticket status</h2><table><tr><th>Status</th><th>Count</th><th>Share</th></tr>' . $rows . '</table>';
        return [
            'name' => 'Attendance Report — ' . $e['title'],
            'html' => $this->shell('Attendance Report', $e['title'] . ' · ' . $e['start_date'] . ' · ' . $e['venue_name'], $body),
            'csv' => "Attendance Report: " . $e['title'] . "\n" . $csv,
        ];
    }

    private function financialReport(string $vendorId, ?string $eventId): array
    {
        $listingIn = $eventId ? $this->db->quote((string) $this->listingIdFor($eventId, $vendorId)) : $this->listingIn($vendorId);
        $q = "SELECT COUNT(*) AS orders, COALESCE(SUM(quantity),0) AS tickets,
                     COALESCE(SUM(total_price),0) AS gross, COALESCE(SUM(discount_amount),0) AS discounts,
                     COALESCE(SUM(commission_amount),0) AS commissions, COALESCE(SUM(tax_amount),0) AS tax,
                     COALESCE(SUM(grand_total),0) AS net,
                     SUM(payment_status='Paid') AS paid_count
              FROM bookings WHERE deleted_at IS NULL AND listing_id IN ($listingIn)";
        $b = $this->db->query($q)->fetch(PDO::FETCH_ASSOC) ?: [];
        $label = $eventId ? $this->eventTitle($eventId, $vendorId) : 'All events';
        $f = $this->db->prepare('SELECT doc_type, COUNT(*) AS n FROM tie_finance_documents
                                 WHERE vendor_id=? AND (? IS NULL OR event_id=?) GROUP BY doc_type');
        $f->execute([$vendorId, $eventId, $eventId]);
        $fin = $f->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $finRows = '';
        foreach ($fin as $r) $finRows .= '<tr><td>' . htmlspecialchars($r['doc_type']) . '</td><td>' . (int) $r['n'] . '</td></tr>';
        $body = '<div class="grid">'
            . '<div class="kpi"><b>' . number_format((float) $b['net'], 2) . '</b><span>Net revenue (MK)</span></div>'
            . '<div class="kpi"><b>' . (int) $b['orders'] . '</b><span>Orders</span></div>'
            . '<div class="kpi"><b>' . (int) $b['tickets'] . '</b><span>Tickets sold</span></div>'
            . '<div class="kpi"><b>' . (int) $b['paid_count'] . '</b><span>Paid bookings</span></div>'
            . '</div>'
            . '<h2>Ledger breakdown (MK)</h2><table><tr><th>Measure</th><th>Amount</th></tr>'
            . '<tr><td>Gross (total_price)</td><td>' . number_format((float) $b['gross'], 2) . '</td></tr>'
            . '<tr><td>Discounts</td><td>' . number_format((float) $b['discounts'], 2) . '</td></tr>'
            . '<tr><td>Commissions</td><td>' . number_format((float) $b['commissions'], 2) . '</td></tr>'
            . '<tr><td>Tax</td><td>' . number_format((float) $b['tax'], 2) . '</td></tr>'
            . '<tr><td><b>Grand total</b></td><td><b>' . number_format((float) $b['net'], 2) . '</b></td></tr></table>'
            . '<h2>Finance documents on record</h2><table><tr><th>Type</th><th>Count</th></tr>' . ($finRows ?: '<tr><td colspan="2">None</td></tr>') . '</table>';
        $csv = "Orders,Tickets,Gross(MK),Discounts(MK),Commissions(MK),Tax(MK),Net(MK),Paid\n"
            . implode(',', [$b['orders'], $b['tickets'], $b['gross'], $b['discounts'], $b['commissions'], $b['tax'], $b['net'], $b['paid_count']]) . "\n";
        return ['name' => 'Financial Report — ' . $label,
            'html' => $this->shell('Financial Report', $label . ' · bookings settlement', $body),
            'csv' => $csv];
    }

    private function ticketSalesReport(string $vendorId, string $eventId): array
    {
        $e = $this->eventFacts($vendorId, $eventId);
        $s = $this->db->prepare('SELECT tt.name AS type, tt.price, COUNT(t.id) AS n,
                                        SUM(tt.price) AS revenue
                                 FROM event_tickets t
                                 JOIN ticket_types tt ON tt.id = t.ticket_type_id
                                 WHERE t.listing_id=? GROUP BY tt.id, tt.name, tt.price ORDER BY n DESC');
        $s->execute([$e['listing_id']]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total = array_sum(array_map(fn($r) => (int) $r['n'], $rows));
        $revenue = array_sum(array_map(fn($r) => (float) $r['revenue'], $rows));
        $t = $this->db->prepare('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL');
        $t->execute([$e['listing_id']]);
        $checkedIn = (int) $t->fetchColumn();

        $table = '';
        foreach ($rows as $r) {
            $table .= '<tr><td>' . htmlspecialchars($r['type']) . '</td><td>' . number_format((float) $r['price'], 2)
                . '</td><td>' . (int) $r['n'] . '</td><td>' . number_format((float) $r['revenue'], 2) . '</td></tr>';
        }
        $body = '<div class="grid">'
            . '<div class="kpi"><b>' . $total . '</b><span>Tickets sold</span></div>'
            . '<div class="kpi"><b>' . number_format($revenue, 2) . '</b><span>Revenue (MK)</span></div>'
            . '<div class="kpi"><b>' . $checkedIn . '</b><span>Checked in</span></div>'
            . '</div><h2>By ticket type</h2><table><tr><th>Type</th><th>Price (MK)</th><th>Sold</th><th>Revenue (MK)</th></tr>'
            . ($table ?: '<tr><td colspan="4">No ticket sales yet</td></tr>') . '</table>';
        $csv = "Type,Price,Sold,Revenue\n" . implode('', array_map(fn($r) => str_replace(',', '', $r['type']) . ',' . $r['price'] . ',' . $r['n'] . ',' . $r['revenue'] . "\n", $rows));
        return ['name' => 'Ticket Sales Report — ' . $e['title'],
            'html' => $this->shell('Ticket Sales Report', $e['title'] . ' · ' . $e['start_date'], $body),
            'csv' => $csv];
    }

    private function customerReport(string $vendorId, ?string $eventId): array
    {
        $listingIn = $eventId ? $this->db->quote((string) $this->listingIdFor($eventId, $vendorId)) : $this->listingIn($vendorId);
        $s = $this->db->prepare("SELECT customer_name, customer_email,
                                        COUNT(*) AS orders, COALESCE(SUM(quantity),0) AS tickets,
                                        COALESCE(SUM(grand_total),0) AS spent, MAX(booking_date) AS last_order
                                 FROM bookings WHERE deleted_at IS NULL AND listing_id IN ($listingIn)
                                 GROUP BY customer_id, customer_name, customer_email
                                 ORDER BY spent DESC LIMIT 50");
        $s->execute();
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $table = '';
        foreach ($rows as $r) {
            $table .= '<tr><td>' . htmlspecialchars($r['customer_name']) . '</td><td>' . htmlspecialchars($r['customer_email'])
                . '</td><td>' . (int) $r['orders'] . '</td><td>' . (int) $r['tickets'] . '</td><td>'
                . number_format((float) $r['spent'], 2) . '</td></tr>';
        }
        $label = $eventId ? $this->eventTitle($eventId, $vendorId) : 'All events';
        $body = '<h2>Customer summary (top ' . count($rows) . ')</h2><table><tr><th>Customer</th><th>Email</th><th>Orders</th><th>Tickets</th><th>Spent (MK)</th></tr>'
            . ($table ?: '<tr><td colspan="5">No customer bookings yet</td></tr>') . '</table>';
        $csv = "Name,Email,Orders,Tickets,Spent\n" . implode('', array_map(fn($r) => str_replace(',', ';', $r['customer_name']) . ',' . $r['customer_email'] . ',' . $r['orders'] . ',' . $r['tickets'] . ',' . $r['spent'] . "\n", $rows));
        return ['name' => 'Customer Report — ' . $label,
            'html' => $this->shell('Customer Report', $label, $body),
            'csv' => $csv];
    }

    private function reviewReport(string $vendorId, ?string $eventId): array
    {
        $listingIn = $eventId ? $this->db->quote((string) $this->listingIdFor($eventId, $vendorId)) : $this->listingIn($vendorId);
        $s = $this->db->prepare("SELECT COUNT(*) AS n, ROUND(AVG(rating),1) AS avg, rating
                                 FROM reviews WHERE listing_id IN ($listingIn) GROUP BY rating WITH ROLLUP");
        $s->execute();
        $dist = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total = 0; $avg = 0;
        foreach ($dist as $r) {
            if ($r['rating'] === null) { $total = (int) $r['n']; $avg = (float) $r['avg']; }
        }
        $bars = '';
        for ($rating = 5; $rating >= 1; $rating--) {
            $n = 0;
            foreach ($dist as $r) if ((int) $r['rating'] === $rating) $n = (int) $r['n'];
            $pct = $total > 0 ? round($n * 100 / $total) : 0;
            $bars .= '<tr><td>' . $rating . '★</td><td>' . $n . '</td><td>' . $pct . '%</td></tr>';
        }
        $rec = $this->db->prepare('SELECT user_name, rating, comment, review_date FROM reviews
                                   WHERE listing_id IN (' . $listingIn . ') AND comment IS NOT NULL AND comment<>""
                                   ORDER BY review_date DESC LIMIT 10');
        $rec->execute();
        $recent = '';
        foreach ($rec->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $recent .= '<tr><td>' . htmlspecialchars($r['user_name']) . '</td><td>' . (int) $r['rating'] . '★</td><td>'
                . htmlspecialchars(mb_substr($r['comment'], 0, 120)) . '</td></tr>';
        }
        $label = $eventId ? $this->eventTitle($eventId, $vendorId) : 'All events';
        $body = '<div class="grid">'
            . '<div class="kpi"><b>' . $total . '</b><span>Reviews</span></div>'
            . '<div class="kpi"><b>' . ($total ? $avg . ' / 5' : '—') . '</b><span>Average rating</span></div>'
            . '</div><h2>Rating distribution</h2><table><tr><th>Stars</th><th>Count</th><th>Share</th></tr>' . $bars . '</table>'
            . '<h2>Recent comments</h2><table><tr><th>Customer</th><th>Rating</th><th>Comment</th></tr>'
            . ($recent ?: '<tr><td colspan="3">No comments yet</td></tr>') . '</table>';
        $csv = "Stars,Count\n" . implode('', array_map(fn($r) => (string) $r['rating'] . ',' . $r['n'] . "\n", $dist));
        return ['name' => 'Reviews Report — ' . $label,
            'html' => $this->shell('Reviews Report', $label, $body),
            'csv' => $csv];
    }

    private function eventSummaryReport(string $vendorId, string $eventId): array
    {
        $e = $this->eventFacts($vendorId, $eventId);
        $listingIn = $this->db->quote((string) $e['listing_id']);

        $b = $this->db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(quantity),0) AS tickets,
                                        COALESCE(SUM(grand_total),0) AS revenue
                                 FROM bookings WHERE deleted_at IS NULL AND listing_id IN ($listingIn)");
        $b->execute();
        $b = $b->fetch(PDO::FETCH_ASSOC) ?: [];
        $t = $this->db->prepare('SELECT COUNT(*) FROM event_tickets WHERE listing_id IN (' . $listingIn . ') AND checked_in_at IS NOT NULL');
        $t->execute();
        $attendance = (int) $t->fetchColumn();
        $r = $this->db->prepare('SELECT COUNT(*), ROUND(AVG(rating),1) FROM reviews WHERE listing_id IN (' . $listingIn . ')');
        $r->execute();
        [$revCount, $revAvg] = $r->fetch(PDO::FETCH_NUM);

        $body = '<div class="grid">'
            . '<div class="kpi"><b>' . number_format((float) ($b['revenue'] ?? 0), 2) . '</b><span>Revenue (MK)</span></div>'
            . '<div class="kpi"><b>' . (int) ($b['tickets'] ?? 0) . '</b><span>Tickets sold</span></div>'
            . '<div class="kpi"><b>' . (int) ($b['orders'] ?? 0) . '</b><span>Orders</span></div>'
            . '<div class="kpi"><b>' . $attendance . '</b><span>Attendance</span></div>'
            . '<div class="kpi"><b>' . ((int) $revCount ? $revAvg . ' / 5' : '—') . '</b><span>Avg rating</span></div>'
            . '</div><h2>Event facts</h2><table>'
            . '<tr><td>Event</td><td>' . htmlspecialchars($e['title']) . '</td></tr>'
            . '<tr><td>Status</td><td>' . htmlspecialchars($e['status']) . '</td></tr>'
            . '<tr><td>Date</td><td>' . htmlspecialchars($e['start_date']) . ' ' . ($e['start_time'] ? htmlspecialchars(substr($e['start_time'], 0, 5)) : '') . '</td></tr>'
            . '<tr><td>Venue</td><td>' . htmlspecialchars($e['venue_name']) . '</td></tr>'
            . '<tr><td>Reviews</td><td>' . (int) $revCount . '</td></tr></table>';
        $csv = "Event,Status,Revenue(MK),Tickets,Orders,Attendance,Reviews,AvgRating\n"
            . str_replace(',', ';', $e['title']) . ',' . $e['status'] . ',' . ($b['revenue'] ?? 0) . ',' . ($b['tickets'] ?? 0) . ','
            . ($b['orders'] ?? 0) . ',' . $attendance . ',' . (int) $revCount . ',' . ($revAvg ?: '') . "\n";
        return ['name' => 'Event Summary — ' . $e['title'],
            'html' => $this->shell('Event Summary', $e['title'] . ' · ' . $e['start_date'], $body),
            'csv' => $csv];
    }

    /* ── activity feed ──────────────────────────────────────────────── */

    public function activityFeed(string $vendorId, int $limit = 20): array
    {
        $limit = min(max($limit, 1), 50);
        $s = $this->db->prepare('SELECT a.action, a.actor_name, a.details, a.created_at,
                                        d.id AS document_id, d.name AS document_name
                                 FROM tie_docs_activity a
                                 JOIN tie_docs_documents d ON d.id = a.document_id
                                 WHERE a.vendor_id=?
                                 ORDER BY a.id DESC LIMIT ' . $limit);
        $s->execute([$vendorId]);
        return array_map(fn($r) => [
            'action' => $r['action'], 'actor' => $r['actor_name'],
            'details' => $r['details'] ? (json_decode($r['details'], true) ?: []) : [],
            'at' => $r['created_at'],
            'document_id' => $r['document_id'], 'document_name' => $r['document_name'],
        ], $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
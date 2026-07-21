<?php
/**
 * Uthenga Marketplace — Catalog / Fetch Functions
 * Queries the unified `listings` table with JSON `meta` column.
 * All feed functions normalise rows into a common format consumed by the UI.
 */

// ── Type helpers ──────────────────────────────────────────────────────────────

if (!function_exists('marketplace_placeholder_image')) {
    function marketplace_placeholder_image($type) {
        switch ($type) {
            case 'property':
            case 'accommodation':
                return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&fit=crop&q=80';
            case 'tour':
                return 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=900&fit=crop&q=80';
            case 'transport':
                return 'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=900&fit=crop&q=80';
            case 'mbanda':
                return 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=900&fit=crop&q=80';
            case 'event':
            default:
                return 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=900&fit=crop&q=80';
        }
    }
}

if (!function_exists('marketplace_type_label')) {
    function marketplace_type_label($type) {
        switch ($type) {
            case 'event':        return 'Event';
            case 'property':
            case 'accommodation':return 'Stay';
            case 'tour':         return 'Tour';
            case 'transport':    return 'Transport';
            case 'mbanda':       return 'Mbanda';
            default:             return ucfirst((string) $type);
        }
    }
}

if (!function_exists('marketplace_type_badge_class')) {
    function marketplace_type_badge_class($type) {
        switch ($type) {
            case 'event':         return 'badge-event';
            case 'property':
            case 'accommodation': return 'badge-accommodation';
            case 'tour':          return 'badge-tour';
            case 'transport':     return 'badge-transport';
            case 'mbanda':        return 'badge-marketplace';
            default:              return '';
        }
    }
}

if (!function_exists('marketplace_detail_url')) {
    function marketplace_detail_url(array $item) {
        $type = $item['type'] ?? '';
        $id   = $item['id']   ?? '';
        return 'event-details.php?type=' . rawurlencode((string)$type) . '&id=' . rawurlencode((string)$id);
    }
}

if (!function_exists('marketplace_price_label')) {
    function marketplace_price_label(array $item) {
        $price = isset($item['price_amount']) ? (float)$item['price_amount'] : 0.0;
        $type  = $item['type'] ?? '';

        if ($type === 'event') {
            return $price > 0 ? 'From ' . formatMWK($price) : 'Free Event';
        }
        if ($type === 'property' || $type === 'accommodation') {
            return $price > 0 ? 'From ' . formatMWK($price) . '/night' : 'Contact for price';
        }
        if ($type === 'tour') {
            return $price > 0 ? formatMWK($price) . '/person' : 'Contact for price';
        }
        if ($type === 'transport') {
            return $price > 0 ? formatMWK($price) . '/seat' : 'Contact for price';
        }
        if ($type === 'mbanda') {
            return $price > 0 ? formatMWK($price) : 'Contact vendor';
        }
        return '';
    }
}

if (!function_exists('marketplace_normalize_item')) {
    function marketplace_normalize_item(array $row) {
        // Map listing_type → type (used in URLs / badges)
        if (empty($row['type']) && !empty($row['listing_type'])) {
            $row['type'] = $row['listing_type'] === 'accommodation' ? 'property' : $row['listing_type'];
        }
        $type = $row['type'] ?? '';
        $row['image']      = !empty($row['image']) ? $row['image'] : marketplace_placeholder_image($type);
        $row['price_label']= marketplace_price_label($row);
        $row['type_label'] = marketplace_type_label($type);
        $row['badge_class']= marketplace_type_badge_class($type);
        $row['detail_url'] = marketplace_detail_url($row);
        return $row;
    }
}

/**
 * Extract a first price from a listings.meta JSON column.
 * Supports event ticket prices, accommodation room prices, tour/transport fares.
 */
if (!function_exists('marketplace_price_from_meta')) {
    function marketplace_price_from_meta(array $row): float {
        $meta = json_decode($row['meta'] ?? '{}', true) ?? [];
        $type = $row['listing_type'] ?? $row['type'] ?? '';

        if ($type === 'event') {
            // Try standardTicketPrice first, then vipTicketPrice, then pricePerPerson
            foreach (['standardTicketPrice','vipTicketPrice','pricePerPerson','price'] as $k) {
                if (!empty($meta[$k])) return (float)$meta[$k];
            }
        }
        if ($type === 'accommodation') {
            // Rooms array → min pricePerNight
            if (!empty($meta['rooms']) && is_array($meta['rooms'])) {
                $prices = array_column($meta['rooms'], 'pricePerNight');
                if ($prices) return (float)min($prices);
            }
            foreach (['pricePerNight','basePrice','price'] as $k) {
                if (!empty($meta[$k])) return (float)$meta[$k];
            }
        }
        if ($type === 'tour') {
            foreach (['pricePerPerson','basePrice','price'] as $k) {
                if (!empty($meta[$k])) return (float)$meta[$k];
            }
        }
        if ($type === 'transport') {
            foreach (['pricePerSeat','baseFare','price'] as $k) {
                if (!empty($meta[$k])) return (float)$meta[$k];
            }
        }
        return 0.0;
    }
}

// ── Cache helpers ─────────────────────────────────────────────────────────────

/** Threshold above which an event earns the "🔥 Trending" badge */
if (!defined('TRENDING_SCORE_THRESHOLD')) {
    define('TRENDING_SCORE_THRESHOLD', 10);
}

/** Cache TTL for ranked event list in seconds (15 minutes) */
if (!defined('EVENT_RANK_CACHE_TTL')) {
    define('EVENT_RANK_CACHE_TTL', 900);
}

if (!function_exists('uthenga_cache_dir')) {
    function uthenga_cache_dir() {
        $dir = __DIR__ . '/../cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('uthenga_cache_get')) {
    function uthenga_cache_get($key, $ttl = 900) {
        $file = uthenga_cache_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.cache';
        if (!is_file($file)) return null;
        if ((time() - filemtime($file)) > $ttl) { @unlink($file); return null; }
        $raw = @file_get_contents($file);
        return ($raw === false) ? null : unserialize($raw);
    }
}

if (!function_exists('uthenga_cache_set')) {
    function uthenga_cache_set($key, $value) {
        $file = uthenga_cache_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.cache';
        @file_put_contents($file, serialize($value), LOCK_EX);
    }
}

if (!function_exists('uthenga_cache_invalidate')) {
    function uthenga_cache_invalidate($key = null) {
        $dir = uthenga_cache_dir();
        if ($key !== null) {
            $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.cache';
            @unlink($file);
            return;
        }
        foreach (glob($dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }
}

// ── Base listings query (from unified `listings` table) ───────────────────────

/**
 * Fetch listings from the unified listings table.
 * Normalises each row, including extracting the first price from JSON meta.
 */
if (!function_exists('_marketplace_fetch_listings')) {
    function _marketplace_fetch_listings(string $typeFilter = '', string $search = '', int $limit = 0, bool $featuredOnly = false): array {
        $cacheKey = 'listings_' . md5($typeFilter . '|' . $search . '|' . $limit . '|' . (int) $featuredOnly);
        $cached = uthenga_cache_get($cacheKey, 300);
        if ($cached !== null) {
            return $cached;
        }

        $params = [];
        $where  = ["l.is_active = 1"];

        if ($typeFilter !== '') {
            // Map 'property' → 'accommodation' (DB stores 'accommodation')
            $dbType = ($typeFilter === 'property') ? 'accommodation' : $typeFilter;
            $where[] = "l.listing_type = ?";
            $params[] = $dbType;
        }

        if ($featuredOnly) {
            $where[]  = "l.featured = 1";
        }

        if ($search !== '') {
            $where[]  = "(l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = array_merge($params, [$like, $like, $like]);
        }

        $whereClause = implode(' AND ', $where);
        $limitClause = ($limit > 0) ? " LIMIT {$limit}" : '';

        $sql = "
            SELECT
                l.id,
                l.listing_type,
                l.listing_type AS type,
                l.vendor_id,
                l.vendor_name,
                l.title,
                l.description,
                l.location,
                l.image,
                l.gallery,
                l.rating,
                l.featured,
                l.is_active,
                l.meta,
                l.created_at,
                l.updated_at
            FROM listings l
            WHERE {$whereClause}
            ORDER BY l.featured DESC, l.created_at DESC
            {$limitClause}
        ";

        $rows = dbQuery($sql, $params);
        $items = array_map(function (array $row) {
            // Unify type aliases
            if ($row['listing_type'] === 'accommodation') {
                $row['type'] = 'property';
            }
            $row['price_amount'] = marketplace_price_from_meta($row);
            return marketplace_normalize_item($row);
        }, $rows);

        uthenga_cache_set($cacheKey, $items);
        return $items;
    }
}

// ── AI-Powered Event Ranking ──────────────────────────────────────────────────

/**
 * Track an event interaction metric.
 * Gracefully degrades if event_analytics table does not exist.
 */
if (!function_exists('marketplace_track_event_metric')) {
    function marketplace_track_event_metric($eventId, $metric = 'view') {
        $allowed = [
            'view'     => ['col' => 'view_count',     'weight' => 1],
            'booking'  => ['col' => 'booking_count',  'weight' => 5],
            'wishlist' => ['col' => 'wishlist_count', 'weight' => 3],
            'click'    => ['col' => 'click_count',    'weight' => 2],
        ];
        if (!isset($allowed[$metric])) return;
        $col    = $allowed[$metric]['col'];
        $weight = $allowed[$metric]['weight'];
        try {
            if (uthenga_table_exists('event_analytics')) {
                dbExecute("
                    INSERT INTO event_analytics (event_id, {$col}, popularity_score)
                    VALUES (?, 1, {$weight})
                    ON DUPLICATE KEY UPDATE
                        {$col}           = {$col} + 1,
                        popularity_score = (view_count * 1) + (booking_count * 5) + (wishlist_count * 3) + (click_count * 2)
                ", [$eventId]);
            } elseif ($metric === 'view' && uthenga_table_exists('event_views')) {
                dbExecute(
                    "INSERT INTO event_views (event_id, user_id, session_id, source, viewed_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $eventId,
                        $_SESSION['user_id'] ?? null,
                        $_SESSION['session_id'] ?? null,
                        'listing-page'
                    ]
                );
            }
            if ($metric !== 'view') {
                uthenga_cache_invalidate();
            }
        } catch (Exception $e) {
            error_log('[Uthenga ranking] track_event_metric failed: ' . $e->getMessage());
        }
    }
}

// ── Public fetch functions ────────────────────────────────────────────────────

if (!function_exists('marketplace_fetch_events')) {
    function marketplace_fetch_events($search = '', $limit = 0, $featuredOnly = false) {
        return _marketplace_fetch_listings('event', (string)$search, (int)$limit, (bool)$featuredOnly);
    }
}

/**
 * Fetch events ranked by AI popularity score.
 * Falls back to date ordering when no analytics data exists.
 */
if (!function_exists('marketplace_fetch_ranked_events')) {
    function marketplace_fetch_ranked_events($search = '', $limit = 0, $useCache = true) {
        $cacheKey = 'ranked_events_' . md5($search . '_' . (int)$limit);

        if ($useCache && $search === '') {
            $cached = uthenga_cache_get($cacheKey, EVENT_RANK_CACHE_TTL);
            if ($cached !== null) return $cached;
        }

        $params = [];
        $where  = ["l.is_active = 1", "l.listing_type = 'event'"];

        if ($search !== '') {
            $where[]  = "(l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = [$like, $like, $like];
        }

        $limitClause = ((int)$limit > 0) ? " LIMIT " . (int)$limit : '';

        $hasAnalytics = uthenga_table_exists('event_analytics');
        $wishlistTable = uthenga_first_existing_table(['favorites', 'wishlist']);

        if ($hasAnalytics) {
            $sql = "
                SELECT
                    l.id,
                    l.listing_type,
                    'event' AS type,
                    l.vendor_id,
                    l.vendor_name,
                    l.title,
                    l.description,
                    l.location,
                    l.image,
                    l.gallery,
                    l.rating,
                    l.featured,
                    l.is_active,
                    l.meta,
                    l.created_at,
                    l.updated_at,
                    COALESCE(ea.view_count, 0)        AS view_count,
                    COALESCE(b.booking_count, 0)      AS booking_count,
                    COALESCE(w.wishlist_count, 0)     AS wishlist_count,
                    COALESCE(ea.click_count, 0)       AS click_count,
                    (
                        (COALESCE(ea.view_count, 0) * 1) +
                        (COALESCE(b.booking_count, 0) * 5) +
                        (COALESCE(w.wishlist_count, 0) * 3) +
                        (COALESCE(ea.click_count, 0) * 2)
                    ) AS popularity_score
                FROM listings l
                LEFT JOIN event_analytics ea ON ea.event_id = l.id
                LEFT JOIN (
                    SELECT listing_id, COUNT(*) AS booking_count
                    FROM bookings
                    WHERE listing_type = 'event'
                    GROUP BY listing_id
                ) b ON b.listing_id = l.id
                " . ($wishlistTable === 'favorites'
                    ? "LEFT JOIN (
                        SELECT reference_id AS listing_id, COUNT(*) AS wishlist_count
                        FROM favorites
                        WHERE favorite_type = 'event'
                        GROUP BY reference_id
                    ) w ON w.listing_id = l.id"
                    : "LEFT JOIN (
                        SELECT listing_id, COUNT(*) AS wishlist_count
                        FROM wishlist
                        GROUP BY listing_id
                    ) w ON w.listing_id = l.id") . "
                WHERE l.is_active = 1 AND l.listing_type = 'event'
            ";
        } else {
            $sql = "
                SELECT
                    l.id,
                    l.listing_type,
                    'event' AS type,
                    l.vendor_id,
                    l.vendor_name,
                    l.title,
                    l.description,
                    l.location,
                    l.image,
                    l.gallery,
                    l.rating,
                    l.featured,
                    l.is_active,
                    l.meta,
                    l.created_at,
                    l.updated_at,
                    COALESCE(ev.view_count, 0)       AS view_count,
                    COALESCE(b.booking_count, 0)     AS booking_count,
                    COALESCE(w.wishlist_count, 0)    AS wishlist_count,
                    0                                AS click_count,
                    (
                        (COALESCE(ev.view_count, 0) * 1) +
                        (COALESCE(b.booking_count, 0) * 5) +
                        (COALESCE(w.wishlist_count, 0) * 3)
                    ) AS popularity_score
                FROM listings l
                LEFT JOIN (
                    SELECT event_id, COUNT(*) AS view_count
                    FROM event_views
                    GROUP BY event_id
                ) ev ON ev.event_id = l.id
                LEFT JOIN (
                    SELECT listing_id, COUNT(*) AS booking_count
                    FROM bookings
                    WHERE listing_type = 'event'
                    GROUP BY listing_id
                ) b ON b.listing_id = l.id
                " . ($wishlistTable === 'favorites'
                    ? "LEFT JOIN (
                        SELECT reference_id AS listing_id, COUNT(*) AS wishlist_count
                        FROM favorites
                        WHERE favorite_type = 'event'
                        GROUP BY reference_id
                    ) w ON w.listing_id = l.id"
                    : "LEFT JOIN (
                        SELECT listing_id, COUNT(*) AS wishlist_count
                        FROM wishlist
                        GROUP BY listing_id
                    ) w ON w.listing_id = l.id") . "
                WHERE l.is_active = 1 AND l.listing_type = 'event'
            ";
        }

        if ($search !== '') {
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
        }

        $sql .= " ORDER BY l.featured DESC, popularity_score DESC, l.created_at DESC" . $limitClause;

        try {
            $rows = dbQuery($sql, $params);
        } catch (Exception $ex) {
            error_log('[Uthenga ranking] Ranked query failed, falling back: ' . $ex->getMessage());
            return marketplace_fetch_events($search, $limit, false);
        }

        // Determine if we have any real analytics data
        $hasData = false;
        foreach ($rows as $row) {
            if ((float)($row['popularity_score'] ?? 0) > 0) { $hasData = true; break; }
        }

        $results = [];
        foreach ($rows as $row) {
            $row['price_amount']     = marketplace_price_from_meta($row);
            $normalised              = marketplace_normalize_item($row);
            $normalised['popularity_score'] = (float)($row['popularity_score'] ?? 0);
            $normalised['is_trending']      = $hasData && $normalised['popularity_score'] >= TRENDING_SCORE_THRESHOLD;
            $results[] = $normalised;
        }

        if ($useCache && $search === '') {
            uthenga_cache_set($cacheKey, $results);
        }

        return $results;
    }
}

if (!function_exists('marketplace_fetch_properties')) {
    function marketplace_fetch_properties($search = '', $limit = 0, $featuredOnly = false) {
        return _marketplace_fetch_listings('accommodation', (string)$search, (int)$limit, (bool)$featuredOnly);
    }
}

if (!function_exists('marketplace_fetch_tours')) {
    function marketplace_fetch_tours($search = '', $limit = 0, $featuredOnly = false) {
        return _marketplace_fetch_listings('tour', (string)$search, (int)$limit, (bool)$featuredOnly);
    }
}

if (!function_exists('marketplace_fetch_transport_routes')) {
    function marketplace_fetch_transport_routes($search = '', $limit = 0, $featuredOnly = false) {
        return _marketplace_fetch_listings('transport', (string)$search, (int)$limit, (bool)$featuredOnly);
    }
}

if (!function_exists('marketplace_fetch_mbanda')) {
    function marketplace_fetch_mbanda($search = '', $limit = 0, $featuredOnly = false) {
        return _marketplace_fetch_listings('mbanda', (string)$search, (int)$limit, (bool)$featuredOnly);
    }
}

if (!function_exists('marketplace_fetch_home_feed')) {
    function marketplace_fetch_home_feed($search = '', $limit = 12) {
        $cacheKey = 'home_feed_' . md5($search . '_' . (int)$limit);

        if ($search === '') {
            $cached = uthenga_cache_get($cacheKey, 600);
            if ($cached !== null) return $cached;
        }

        // Fetch all types and merge
        $items = _marketplace_fetch_listings('', (string)$search, 0, false);

        // Sort by newest first
        usort($items, function ($a, $b) {
            $aTime = strtotime($a['created_at'] ?? '') ?: 0;
            $bTime = strtotime($b['created_at'] ?? '') ?: 0;
            return $bTime <=> $aTime;
        });

        if ((int)$limit > 0) {
            $items = array_slice($items, 0, (int)$limit);
        }

        if ($search === '') {
            uthenga_cache_set($cacheKey, $items);
        }

        return $items;
    }
}

/**
 * Fetch a single listing by type+id from the listings table.
 */
if (!function_exists('marketplace_fetch_item')) {
    function marketplace_fetch_item($type, $id) {
        $type = (string)$type;
        $id   = (string)$id;

        $dbType = ($type === 'property') ? 'accommodation' : $type;

        $row = dbQueryOne("
            SELECT l.*, l.listing_type AS type
            FROM listings l
            WHERE l.id = ? AND l.is_active = 1
            LIMIT 1
        ", [$id]);

        if (!$row) return null;

        if ($row['listing_type'] === 'accommodation') $row['type'] = 'property';
        $row['price_amount'] = marketplace_price_from_meta($row);
        return marketplace_normalize_item($row);
    }
}

/**
 * Resolve a full listing entity by type+id (used by event-details.php and request_api.php).
 * Returns a row that includes the full `meta` JSON and all listing columns.
 */
if (!function_exists('marketplace_resolve_entity')) {
    function marketplace_resolve_entity($type, $id) {
        $id = trim((string)$id);
        if ($id === '') return null;

        $type   = strtolower(trim((string)$type));
        $dbType = ($type === 'property' || $type === 'accommodation') ? 'accommodation' : $type;

        // Try by exact type first
        $row = dbQueryOne("
            SELECT l.*
            FROM listings l
            WHERE l.id = ? AND l.is_active = 1
            LIMIT 1
        ", [$id]);

        if (!$row) return null;

        // Add normalised fields
        $row['type']         = ($row['listing_type'] === 'accommodation') ? 'property' : $row['listing_type'];
        $row['listing_type'] = $row['listing_type'];
        $row['price_amount'] = marketplace_price_from_meta($row);
        $row['summary']      = $row['description'];
        $row['venue_name']   = $row['location'];

        // Decode meta for easy field access
        $meta = json_decode($row['meta'] ?? '{}', true) ?? [];
        $row  = array_merge($meta, $row);  // meta fields available as direct keys (row wins on conflict)

        return $row;
    }
}

/**
 * Fetch wishlist/favorites for a user from the wishlist table.
 */
if (!function_exists('marketplace_fetch_favorites')) {
    function marketplace_fetch_favorites($userId) {
        $favoritesTable = uthenga_first_existing_table(['favorites', 'wishlist']);
        if ($favoritesTable === '') {
            return [];
        }

        if ($favoritesTable === 'favorites') {
            $items = dbQuery("
                SELECT
                    f.reference_id AS id,
                    f.favorite_type AS type,
                    f.created_at AS saved_at
                FROM favorites f
                WHERE f.user_id = ?
                ORDER BY f.created_at DESC
            ", [$userId]);
        } else {
            $items = dbQuery("
                SELECT
                    w.listing_id AS id,
                    l.listing_type AS type,
                    w.created_at AS saved_at
                FROM wishlist w
                JOIN listings l ON l.id = w.listing_id
                WHERE w.user_id = ?
                ORDER BY w.created_at DESC
            ", [$userId]);
        }

        return array_values(array_filter(array_map(function ($row) {
            if ($row['type'] === 'accommodation') $row['type'] = 'property';
            $listing = marketplace_fetch_item($row['type'], $row['id']);
            if ($listing) {
                $listing['saved_at'] = $row['saved_at'];
                return $listing;
            }
            return null;
        }, $items)));
    }
}

// ── Advertisement helper ──────────────────────────────────────────────────────

if (!function_exists('getActiveAds')) {
    function getActiveAds($position = 'banner', $limit = 6): array {
        try {
            $now = date('Y-m-d H:i:s');
            $sql = "
                SELECT *
                FROM advertisements
                WHERE is_active = 1
                  AND (start_date IS NULL OR start_date <= ?)
                  AND (end_date IS NULL OR end_date >= ?)
            ";
            $params = [$now, $now];

            if ($position !== '') {
                $sql    .= " AND position = ?";
                $params[] = $position;
            }

            $sql .= " ORDER BY sort_order ASC, created_at DESC";
            if ((int)$limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }
            return dbQuery($sql, $params);
        } catch (Exception $e) {
            return [];
        }
    }
}

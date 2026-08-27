<?php
/** Provider interfaces are intentionally provider-neutral. */

interface UthengaTieRoutingProvider
{
    public function geocode(string $query): UthengaTieLocationContext;
    public function reverseGeocode(UthengaTieLocationContext $location): UthengaTieLocationContext;
    public function directions(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): UthengaTieRoute;
    public function distance(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): array;
}

interface UthengaTieWeatherProvider
{
    public function current(UthengaTieLocationContext $location): array;
}

interface UthengaTieGeocodingProvider
{
    /** Returns only normalized geographic labels; coordinates remain valid if this fails. */
    public function reverse(float $latitude, float $longitude): array;
    public function name(): string;
}

/** Server validates foreground observations; it never accesses device APIs itself. */
interface UthengaTieGeolocationProvider
{
    public function name(): string;
    public function acquisitionModel(): string;
}

/** Adapter seam for the existing deterministic marketplace radius-search path. */
interface UthengaTieGeographicSearchProvider
{
    public function search(UthengaTieCatalogueQuery $criteria): array;
    public function name(): string;
}

interface UthengaTieLlmProvider
{
    public function generate(array $request): array;
    public function generateStructured(array $request, array $schema): array;
    public function healthCheck(): array;
}

/**
 * Explicit unavailable providers prevent an accidental direct SDK call while
 * provider selection is still a product and privacy decision.
 */
final class UthengaTieUnavailableRoutingProvider implements UthengaTieRoutingProvider
{
    public function geocode(string $query): UthengaTieLocationContext { throw UthengaTieErrors::providerUnavailable('routing'); }
    public function reverseGeocode(UthengaTieLocationContext $location): UthengaTieLocationContext { throw UthengaTieErrors::providerUnavailable('routing'); }
    public function directions(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): UthengaTieRoute { throw UthengaTieErrors::providerUnavailable('routing'); }
    public function distance(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): array { throw UthengaTieErrors::providerUnavailable('routing'); }
}

/**
 * GraphHopper is used only from PHP.  The browser receives a normalised route
 * and never receives the provider key or an upstream provider response.
 *
 * Geocoding deliberately remains on the existing, separately-configured
 * Location provider boundary.  Routing must not quietly become an authority
 * for a marketplace address.
 */
final class UthengaTieGraphHopperRoutingProvider implements UthengaTieRoutingProvider
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim(trim($baseUrl ?: 'https://graphhopper.com/api/1'), '/');
    }

    public function geocode(string $query): UthengaTieLocationContext { throw UthengaTieErrors::providerUnavailable('graphhopper_geocoding_not_configured'); }
    public function reverseGeocode(UthengaTieLocationContext $location): UthengaTieLocationContext { throw UthengaTieErrors::providerUnavailable('graphhopper_geocoding_not_configured'); }

    public function directions(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): UthengaTieRoute
    {
        if ($this->apiKey === '') throw UthengaTieErrors::providerUnavailable('graphhopper');
        $from = $origin->toArray(); $to = $destination->toArray();
        // GraphHopper requires repeated `point` query parameters. PHP's
        // http_build_query would instead send point[0]/point[1], which the
        // provider correctly rejects as having no route points.
        $query = http_build_query([
            'profile' => UthengaTieConfig::string('TIE_ROUTING_PROFILE', 'car'),
            'locale' => 'en',
            'instructions' => 'false',
            'points_encoded' => 'false',
            'calc_points' => 'true',
            'key' => $this->apiKey,
        ], '', '&', PHP_QUERY_RFC3986)
            . '&point=' . rawurlencode($from['latitude'] . ',' . $from['longitude'])
            . '&point=' . rawurlencode($to['latitude'] . ',' . $to['longitude']);
        $decoded = $this->get($this->baseUrl . '/route?' . $query);
        $path = is_array($decoded['paths'][0] ?? null) ? $decoded['paths'][0] : null;
        $coordinates = $path['points']['coordinates'] ?? null;
        if ($path === null || !is_array($coordinates) || $coordinates === []) throw UthengaTieErrors::providerUnavailable('graphhopper_malformed_route');

        $normalised = [];
        foreach ($coordinates as $coordinate) {
            if (!is_array($coordinate) || count($coordinate) < 2 || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) continue;
            // GeoJSON's [longitude, latitude] ordering is retained deliberately.
            $normalised[] = [round((float) $coordinate[0], 6), round((float) $coordinate[1], 6)];
        }
        if (count($normalised) < 2) throw UthengaTieErrors::providerUnavailable('graphhopper_malformed_geometry');
        return new UthengaTieRoute([
            'schema_version' => 'tie-route/v1',
            'provider' => 'graphhopper',
            'profile' => UthengaTieConfig::string('TIE_ROUTING_PROFILE', 'car'),
            'distance_m' => max(0, (int) round((float) ($path['distance'] ?? 0))),
            'duration_seconds' => max(0, (int) round(((float) ($path['time'] ?? 0)) / 1000)),
            'geometry' => ['type' => 'LineString', 'coordinates' => $normalised],
        ]);
    }

    public function distance(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): array
    {
        $route = $this->directions($origin, $destination)->toArray();
        return ['distance_m' => $route['distance_m'], 'duration_seconds' => $route['duration_seconds'], 'provider' => 'graphhopper'];
    }

    private function get(string $url): array
    {
        if (!function_exists('curl_init')) throw UthengaTieErrors::providerUnavailable('graphhopper_curl');
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_ROUTING_TIMEOUT_SECONDS', 8)),
            CURLOPT_CONNECTTIMEOUT => max(1, UthengaTieConfig::integer('TIE_ROUTING_CONNECT_TIMEOUT_SECONDS', 3)),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: Uthenga-TIE/1.0 (routing)'],
        ]);
        $body = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw UthengaTieErrors::providerUnavailable('graphhopper');
        return $decoded;
    }
}

/** The composition root is the only place a concrete routing provider is selected. */
final class UthengaTieRoutingProviderFactory
{
    public static function configured(): UthengaTieRoutingProvider
    {
        if (!UthengaTieFeatureFlags::enabled('routing')) return new UthengaTieUnavailableRoutingProvider();
        return match (strtolower(UthengaTieConfig::string('TIE_ROUTING_PROVIDER', 'unconfigured'))) {
            'graphhopper' => new UthengaTieGraphHopperRoutingProvider(
                UthengaTieConfig::string('TIE_GRAPHHOPPER_API_KEY'),
                UthengaTieConfig::string('TIE_GRAPHHOPPER_BASE_URL', 'https://graphhopper.com/api/1')
            ),
            default => new UthengaTieUnavailableRoutingProvider(),
        };
    }
}

final class UthengaTieUnavailableWeatherProvider implements UthengaTieWeatherProvider
{
    public function current(UthengaTieLocationContext $location): array { throw UthengaTieErrors::providerUnavailable('weather'); }
}

final class UthengaTieUnavailableGeocodingProvider implements UthengaTieGeocodingProvider
{
    private string $provider;
    public function __construct(string $provider = 'unconfigured') { $this->provider = $provider; }
    public function reverse(float $latitude, float $longitude): array { throw UthengaTieErrors::providerUnavailable('geocoding'); }
    public function name(): string { return $this->provider; }
}

final class UthengaTieForegroundClientGeolocationProvider implements UthengaTieGeolocationProvider
{
    public function name(): string { return 'foreground_client_observation'; }
    public function acquisitionModel(): string { return 'client_acquires_once_server_validates'; }
}

/** Keeps MariaDB retrieval behind a provider-neutral geographic-search seam. */
final class UthengaTieMariaDbGeographicSearchProvider implements UthengaTieGeographicSearchProvider
{
    private UthengaTieQueryModule $query;
    public function __construct(UthengaTieQueryModule $query) { $this->query = $query; }
    public function search(UthengaTieCatalogueQuery $criteria): array { return $this->query->search($criteria); }
    public function name(): string { return 'mariadb_verified_coordinate_search'; }
}

/** Public architecture descriptor; provider SDK details never leak to consumers. */
final class UthengaTieLocationProviderArchitecture
{
    public const VERSION = 'location-provider-architecture/v1';
    public static function describe(UthengaTieGeolocationProvider $geolocation, UthengaTieGeographicSearchProvider $search): array
    {
        return [
            'schema_version' => self::VERSION,
            'geolocation' => ['provider' => $geolocation->name(), 'acquisition_model' => $geolocation->acquisitionModel(), 'tracking' => 'not_supported'],
            'geocoding' => ['contract' => UthengaTieGeocodingProvider::class, 'selection' => 'configuration_factory', 'failure_mode' => 'partial_geographic_context'],
            'geographic_search' => ['provider' => $search->name(), 'authority' => 'phase_3_query_engine_and_phase_4_availability_engine', 'failure_mode' => 'nearby_unavailable_normal_catalogue_available'],
        ];
    }
}

/** Optional, explicitly enabled Nominatim adapter. It is never selected by default. */
final class UthengaTieNominatimGeocodingProvider implements UthengaTieGeocodingProvider
{
    public function reverse(float $latitude, float $longitude): array
    {
        $endpoint = UthengaTieConfig::string('TIE_NOMINATIM_REVERSE_URL', 'https://nominatim.openstreetmap.org/reverse');
        $url = $endpoint . '?' . http_build_query(['format' => 'jsonv2', 'lat' => $latitude, 'lon' => $longitude, 'addressdetails' => 1], '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => UthengaTieConfig::integer('TIE_GEOCODER_TIMEOUT_SECONDS', 5), 'header' => "Accept: application/json\r\nUser-Agent: Uthenga-TIE/1.0 (location context)\r\n"]]);
        $raw = @file_get_contents($url, false, $context);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) throw UthengaTieErrors::providerUnavailable('nominatim');
        $address = is_array($decoded['address'] ?? null) ? $decoded['address'] : [];
        return [
            'country' => $address['country'] ?? null,
            'region' => $address['state'] ?? $address['region'] ?? null,
            'district' => $address['county'] ?? $address['state_district'] ?? null,
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            'area' => $address['suburb'] ?? $address['neighbourhood'] ?? $address['locality'] ?? null,
            'address' => $decoded['display_name'] ?? null,
            'provider' => $this->name(), 'status' => 'resolved',
        ];
    }
    public function name(): string { return 'nominatim'; }
}

/** Configuration-only provider selection; adapters stay isolated from consumers. */
final class UthengaTieGeocodingProviderFactory
{
    public static function configured(): array
    {
        if (!UthengaTieConfig::boolean('TIE_GEOCODER_ENABLED')) return [new UthengaTieUnavailableGeocodingProvider()];
        $names = array_merge([UthengaTieConfig::string('TIE_GEOCODER_PROVIDER')], UthengaTieConfig::csv('TIE_GEOCODER_FALLBACK_PROVIDERS'));
        $providers = [];
        foreach (array_values(array_unique(array_filter($names))) as $name) $providers[] = self::make($name);
        return $providers ?: [new UthengaTieUnavailableGeocodingProvider()];
    }

    private static function make(string $name): UthengaTieGeocodingProvider
    {
        return match (strtolower(trim($name))) {
            'nominatim' => new UthengaTieNominatimGeocodingProvider(),
            default => new UthengaTieUnavailableGeocodingProvider(strtolower(trim($name)) ?: 'unconfigured'),
        };
    }
}

/** Sole reverse-geocoding boundary: retries/fallbacks/cache never reach consumers. */
final class UthengaTieReverseGeocodingService
{
    private array $providers;
    private static array $rate = [];
    public function __construct(array $providers) { $this->providers = array_values(array_filter($providers, static fn($provider): bool => $provider instanceof UthengaTieGeocodingProvider)); }

    public function resolve(float $latitude, float $longitude): array
    {
        $started = microtime(true); $requestId = UthengaTieObservability::requestId(); $fallbacks = 0;
        foreach ($this->providers as $provider) {
            $name = $provider->name();
            if ($name === 'unconfigured') return $this->complete(UthengaTieGeographicContext::unavailable($name, 'not_configured'), $name, $requestId, $started, $fallbacks);
            if (!$this->withinRateLimit($name)) { UthengaTieMetrics::record('geocoder_rate_limited', 1, $requestId, ['module' => 'location', 'provider' => $name, 'status' => 'rate_limited']); return $this->complete(UthengaTieGeographicContext::unavailable($name, 'rate_limited'), $name, $requestId, $started, $fallbacks); }
            $cached = UthengaTieGeographicContextCache::get($latitude, $longitude, $name);
            if ($cached !== null) { UthengaTieMetrics::record('geocoder_cache_hits', 1, $requestId, ['module' => 'location', 'provider' => $name, 'status' => 'hit']); return $this->complete(UthengaTieGeographicContext::resolved($cached, $name, 'hit'), $name, $requestId, $started, $fallbacks); }
            UthengaTieMetrics::record('geocoder_cache_misses', 1, $requestId, ['module' => 'location', 'provider' => $name, 'status' => 'miss']);
            $attempts = max(1, UthengaTieConfig::integer('TIE_GEOCODER_RETRY_ATTEMPTS', 0) + 1);
            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                try {
                    $raw = $provider->reverse($latitude, $longitude);
                    $context = UthengaTieGeographicContext::resolved($raw, $name, 'miss');
                    UthengaTieGeographicContextCache::put($latitude, $longitude, $name, $raw);
                    return $this->complete($context, $name, $requestId, $started, $fallbacks);
                } catch (Throwable $error) {
                    // The next configured provider is tried after transient failure.
                }
            }
            UthengaTieMetrics::record('provider_failures', 1, $requestId, ['module' => 'location', 'provider' => $name, 'status' => 'provider_unavailable']);
            $fallbacks++;
        }
        $last = $this->providers === [] ? 'unconfigured' : end($this->providers)->name();
        return $this->complete(UthengaTieGeographicContext::unavailable($last, $last === 'unconfigured' ? 'not_configured' : 'provider_unavailable'), $last, $requestId, $started, $fallbacks);
    }

    private function complete(array $context, string $provider, string $requestId, float $started, int $fallbacks): array
    {
        $status = (string) ($context['status'] ?? 'unknown'); $duration = round((microtime(true) - $started) * 1000, 2);
        UthengaTieMetrics::record('latency_ms', $duration, $requestId, ['module' => 'location', 'provider' => $provider, 'status' => $status]);
        if ($fallbacks > 0) UthengaTieMetrics::record('geocoder_fallbacks', $fallbacks, $requestId, ['module' => 'location', 'provider' => $provider, 'status' => $status]);
        UthengaTieObservability::log('location.geographic_context_resolved', $requestId, ['module' => 'location', 'provider' => $provider, 'status' => $status, 'duration_ms' => $duration, 'cache' => $context['provenance']['cache'] ?? 'not_cached']);
        return $context;
    }

    private function withinRateLimit(string $provider): bool
    {
        $limit = UthengaTieConfig::integer('TIE_GEOCODER_RATE_LIMIT_PER_MINUTE', 10);
        if ($limit <= 0) return true;
        $now = time(); $entry = self::$rate[$provider] ?? ['started_at' => $now, 'count' => 0];
        if ($now - $entry['started_at'] >= 60) $entry = ['started_at' => $now, 'count' => 0];
        $entry['count']++; self::$rate[$provider] = $entry;
        return $entry['count'] <= $limit;
    }
}

final class UthengaTieUnavailableLlmProvider implements UthengaTieLlmProvider
{
    private string $provider;
    public function __construct(string $provider = 'unconfigured') { $this->provider = $provider; }
    public function generate(array $request): array { throw UthengaTieErrors::providerUnavailable('llm'); }
    public function generateStructured(array $request, array $schema): array { throw UthengaTieErrors::providerUnavailable('llm'); }
    public function healthCheck(): array { return ['available' => false, 'provider' => $this->provider]; }
}

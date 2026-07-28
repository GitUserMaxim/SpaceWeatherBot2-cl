<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api\Noaa;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\CachedHttpClient;

final class NoaaSwpcClient implements NoaaClientInterface
{
    private const string BASE_JSON = 'https://services.swpc.noaa.gov/json';

    public function __construct(
        private readonly CachedHttpClient $http,
    ) {
    }

    public function getPlanetaryKIndex(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/planetary_k_index_1m.json', 60);
    }

    public function getSolarWindPlasma(): array
    {
        // Replaces the deprecated /products/solar-wind/plasma-2-hour.json (removed by
        // NOAA on 2026-04-30, see Service Change Notice 26-21). This is a plain list of
        // objects now, not the old header-row tabular format.
        return $this->fetchJson(self::BASE_JSON . '/rtsw/rtsw_wind_1m.json', 60);
    }

    public function getSolarWindMag(): array
    {
        // Replaces the deprecated /products/solar-wind/mag-2-hour.json, same notice as above.
        return $this->fetchJson(self::BASE_JSON . '/rtsw/rtsw_mag_1m.json', 60);
    }

    public function getEditedEvents(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/edited_events.json', 300);
    }

    public function getSolarRegions(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/solar_regions.json', 600);
    }

    public function getSolarProbabilities(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/solar_probabilities.json', 3600);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchJson(string $url, int $ttl): array
    {
        $payload = $this->http->get($url, $ttl);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new ApiException(sprintf('Invalid JSON response from NOAA: %s', $url));
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }
}

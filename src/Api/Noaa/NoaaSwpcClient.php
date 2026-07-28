<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api\Noaa;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\CachedHttpClient;

final class NoaaSwpcClient implements NoaaClientInterface
{
    private const string BASE_JSON = 'https://services.swpc.noaa.gov/json';
    private const string BASE_PRODUCTS = 'https://services.swpc.noaa.gov/products/solar-wind';

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
        return $this->fetchTabularJson(self::BASE_PRODUCTS . '/plasma-2-hour.json', 120);
    }

    public function getSolarWindMag(): array
    {
        return $this->fetchTabularJson(self::BASE_PRODUCTS . '/mag-2-hour.json', 120);
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
        //$payload = $this->http->get($url, $ttl);
        //$decoded = json_decode($payload, true);

        $payload = $this->http->get($url, $ttl);

    
        fwrite(STDERR, '[DEBUG] Fetching URL: ' . $url . PHP_EOL);
        fwrite(STDERR, '[DEBUG] Payload length: ' . strlen($payload) . PHP_EOL);

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new ApiException(sprintf('Invalid JSON response from NOAA: %s', $url));
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    private function fetchTabularJson(string $url, int $ttl): array
    {
        $payload = $this->http->get($url, $ttl);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || $decoded === []) {
            throw new ApiException(sprintf('Invalid tabular JSON response from NOAA: %s', $url));
        }

        /** @var list<array<int|string, mixed>> $decoded */
        return $decoded;
    }
}

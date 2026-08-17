<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Location;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use SpaceWeatherBot\Lang\Locale;

/**
 * Reverse geocoding via Nominatim (OpenStreetMap) - free, no API key, but
 * subject to Nominatim's usage policy: max ~1 request/second, and a real
 * identifying User-Agent (set on the Guzzle client passed in here, not
 * added separately - Nominatim rejects generic/empty User-Agents).
 * https://operations.osmfoundation.org/policies/nominatim/
 *
 * This is a "nice to have" on top of saving a location, not a required step,
 * so it deliberately makes ONE fast, non-retried request with a short
 * timeout - a slow or unreachable Nominatim should never make "send my
 * location" feel like it hung.
 */
final class NominatimGeocoder implements GeocoderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reverseGeocode(float $lat, float $lon, Locale $locale): ?string
    {
        try {
            $response = $this->client->get('https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lon,
                    'accept-language' => $locale->value,
                    'zoom' => 14,
                ],
                'timeout' => 4,
                'connect_timeout' => 2,
                'http_errors' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Reverse geocoding failed', ['message' => $exception->getMessage()]);

            return null;
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (! is_array($decoded)) {
            return null;
        }

        $address = is_array($decoded['address'] ?? null) ? $decoded['address'] : [];

        $locality = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null;
        $district = $address['suburb'] ?? $address['city_district'] ?? $address['borough'] ?? null;

        if (is_string($locality) && is_string($district)) {
            return $locality . ', ' . $district;
        }

        if (is_string($locality)) {
            return $locality;
        }

        return isset($decoded['display_name']) && is_string($decoded['display_name'])
            ? $decoded['display_name']
            : null;
    }
}

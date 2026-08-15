<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\HttpClientInterface;
use SpaceWeatherBot\Dto\WeatherReading;

final class OpenMeteoProvider implements WeatherProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
    ) {
    }

    public function name(): string
    {
        return 'Open-Meteo';
    }

    public function fetch(float $lat, float $lon): WeatherReading
    {
        $url = sprintf(
            'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s'
                . '&current=temperature_2m,relative_humidity_2m,wind_speed_10m,surface_pressure'
                . '&wind_speed_unit=ms&timezone=UTC',
            $lat,
            $lon,
        );

        $payload = $this->http->get($url);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! is_array($decoded['current'] ?? null)) {
            throw new ApiException('Invalid response from Open-Meteo.');
        }

        $current = $decoded['current'];
        $observedAt = isset($current['time']) && is_string($current['time'])
            ? new \DateTimeImmutable($current['time'], new \DateTimeZone('UTC'))
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new WeatherReading(
            sourceName: $this->name(),
            temperatureCelsius: isset($current['temperature_2m']) ? (float) $current['temperature_2m'] : null,
            humidityPercent: isset($current['relative_humidity_2m']) ? (int) round((float) $current['relative_humidity_2m']) : null,
            windSpeedMs: isset($current['wind_speed_10m']) ? (float) $current['wind_speed_10m'] : null,
            pressureHpa: isset($current['surface_pressure']) ? (float) $current['surface_pressure'] : null,
            observedAt: $observedAt,
        );
    }
}

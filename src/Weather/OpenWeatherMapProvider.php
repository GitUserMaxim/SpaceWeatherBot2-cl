<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\HttpClientInterface;
use SpaceWeatherBot\Dto\WeatherReading;

final class OpenWeatherMapProvider implements WeatherProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiKey,
    ) {
    }

    public function name(): string
    {
        return 'OpenWeatherMap';
    }

    public function fetch(float $lat, float $lon): WeatherReading
    {
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=metric',
            $lat,
            $lon,
            urlencode($this->apiKey),
        );

        $payload = $this->http->get($url);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! is_array($decoded['main'] ?? null)) {
            throw new ApiException('Invalid response from OpenWeatherMap.');
        }

        $main = $decoded['main'];
        $wind = is_array($decoded['wind'] ?? null) ? $decoded['wind'] : [];
        $observedAt = isset($decoded['dt']) && is_numeric($decoded['dt'])
            ? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimestamp((int) $decoded['dt'])
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new WeatherReading(
            sourceName: $this->name(),
            temperatureCelsius: isset($main['temp']) ? (float) $main['temp'] : null,
            humidityPercent: isset($main['humidity']) ? (int) $main['humidity'] : null,
            windSpeedMs: isset($wind['speed']) ? (float) $wind['speed'] : null,
            pressureHpa: isset($main['pressure']) ? (float) $main['pressure'] : null,
            observedAt: $observedAt,
        );
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\HttpClientInterface;
use SpaceWeatherBot\Dto\WeatherForecastDay;
use SpaceWeatherBot\Dto\WeatherReading;
use SpaceWeatherBot\Utils\Pressure;

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
            pressureMmHg: isset($main['pressure']) ? Pressure::hpaToMmHg((float) $main['pressure']) : null,
            observedAt: $observedAt,
        );
    }

    /**
     * The free tier only offers the 3-hourly / 5-day forecast endpoint, not a
     * true daily one, so this aggregates the 3-hourly slots into per-day
     * buckets (min/max temperature, mean of the rest) ourselves.
     *
     * @return list<WeatherForecastDay>
     *
     * @throws ApiException
     */
    public function fetchForecast(float $lat, float $lon, int $days): array
    {
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/forecast?lat=%s&lon=%s&appid=%s&units=metric',
            $lat,
            $lon,
            urlencode($this->apiKey),
        );

        $payload = $this->http->get($url);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! is_array($decoded['list'] ?? null)) {
            throw new ApiException('Invalid forecast response from OpenWeatherMap.');
        }

        /** @var array<string, array{temp: list<float>, humidity: list<float>, wind: list<float>, pressure: list<float>}> $byDate */
        $byDate = [];

        foreach ($decoded['list'] as $entry) {
            if (! is_array($entry) || ! isset($entry['dt']) || ! is_numeric($entry['dt'])) {
                continue;
            }

            $date = gmdate('Y-m-d', (int) $entry['dt']);
            $byDate[$date] ??= ['temp' => [], 'humidity' => [], 'wind' => [], 'pressure' => []];

            $main = is_array($entry['main'] ?? null) ? $entry['main'] : [];
            $wind = is_array($entry['wind'] ?? null) ? $entry['wind'] : [];

            if (isset($main['temp'])) {
                $byDate[$date]['temp'][] = (float) $main['temp'];
            }

            if (isset($main['humidity'])) {
                $byDate[$date]['humidity'][] = (float) $main['humidity'];
            }

            if (isset($wind['speed'])) {
                $byDate[$date]['wind'][] = (float) $wind['speed'];
            }

            if (isset($main['pressure'])) {
                $byDate[$date]['pressure'][] = (float) $main['pressure'];
            }
        }

        ksort($byDate);

        $result = [];

        foreach (array_slice($byDate, 0, $days, true) as $date => $values) {
            $result[] = new WeatherForecastDay(
                sourceName: $this->name(),
                date: $date,
                tempMinCelsius: $values['temp'] === [] ? null : min($values['temp']),
                tempMaxCelsius: $values['temp'] === [] ? null : max($values['temp']),
                humidityPercent: $values['humidity'] === [] ? null : (int) round(array_sum($values['humidity']) / count($values['humidity'])),
                windSpeedMs: $values['wind'] === [] ? null : array_sum($values['wind']) / count($values['wind']),
                pressureMmHg: $values['pressure'] === [] ? null : Pressure::hpaToMmHg(array_sum($values['pressure']) / count($values['pressure'])),
            );
        }

        return $result;
    }
}

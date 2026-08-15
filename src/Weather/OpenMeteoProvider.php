<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\HttpClientInterface;
use SpaceWeatherBot\Dto\WeatherForecastDay;
use SpaceWeatherBot\Dto\WeatherReading;
use SpaceWeatherBot\Utils\Pressure;

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
            pressureMmHg: isset($current['surface_pressure']) ? Pressure::hpaToMmHg((float) $current['surface_pressure']) : null,
            observedAt: $observedAt,
        );
    }

    /**
     * Open-Meteo's "daily" block has no humidity or pressure fields at all,
     * so the forecast is built by pulling hourly data and aggregating it
     * into per-day buckets ourselves (min/max temperature, mean of the rest).
     *
     * @return list<WeatherForecastDay>
     *
     * @throws ApiException
     */
    public function fetchForecast(float $lat, float $lon, int $days): array
    {
        $url = sprintf(
            'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s'
                . '&hourly=temperature_2m,relative_humidity_2m,wind_speed_10m,surface_pressure'
                . '&wind_speed_unit=ms&timezone=UTC&forecast_days=%d',
            $lat,
            $lon,
            max(1, $days),
        );

        $payload = $this->http->get($url);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! is_array($decoded['hourly'] ?? null)) {
            throw new ApiException('Invalid forecast response from Open-Meteo.');
        }

        $hourly = $decoded['hourly'];
        $times = is_array($hourly['time'] ?? null) ? $hourly['time'] : [];

        /** @var array<string, array{temp: list<float>, humidity: list<float>, wind: list<float>, pressure: list<float>}> $byDate */
        $byDate = [];

        foreach ($times as $index => $time) {
            if (! is_string($time) || strlen($time) < 10) {
                continue;
            }

            $date = substr($time, 0, 10);
            $byDate[$date] ??= ['temp' => [], 'humidity' => [], 'wind' => [], 'pressure' => []];

            if (isset($hourly['temperature_2m'][$index])) {
                $byDate[$date]['temp'][] = (float) $hourly['temperature_2m'][$index];
            }

            if (isset($hourly['relative_humidity_2m'][$index])) {
                $byDate[$date]['humidity'][] = (float) $hourly['relative_humidity_2m'][$index];
            }

            if (isset($hourly['wind_speed_10m'][$index])) {
                $byDate[$date]['wind'][] = (float) $hourly['wind_speed_10m'][$index];
            }

            if (isset($hourly['surface_pressure'][$index])) {
                $byDate[$date]['pressure'][] = (float) $hourly['surface_pressure'][$index];
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

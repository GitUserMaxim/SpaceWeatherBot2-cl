<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Weather;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SpaceWeatherBot\Tests\Support\FakeHttpClient;
use SpaceWeatherBot\Utils\Pressure;
use SpaceWeatherBot\Weather\OpenMeteoProvider;
use SpaceWeatherBot\Weather\OpenWeatherMapProvider;
use SpaceWeatherBot\Weather\WeatherService;

final class WeatherServiceTest extends TestCase
{
    public function testOpenMeteoProviderParsesAResponse(): void
    {
        $http = (new FakeHttpClient())->willReturn('api.open-meteo.com', json_encode([
            'current' => [
                'time' => '2026-08-13T15:00',
                'temperature_2m' => 18.4,
                'relative_humidity_2m' => 62,
                'wind_speed_10m' => 3.7,
                'surface_pressure' => 1009.2,
            ],
        ], JSON_THROW_ON_ERROR));

        $reading = (new OpenMeteoProvider($http))->fetch(55.8197, 37.6117);

        self::assertSame('Open-Meteo', $reading->sourceName);
        self::assertSame(18.4, $reading->temperatureCelsius);
        self::assertSame(62, $reading->humidityPercent);
        self::assertSame(3.7, $reading->windSpeedMs);
        self::assertEqualsWithDelta(Pressure::hpaToMmHg(1009.2), $reading->pressureMmHg, 0.0001);
        self::assertSame('2026-08-13T15:00:00', $reading->observedAt->format('Y-m-d\TH:i:s'));
    }

    public function testOpenWeatherMapProviderParsesAResponse(): void
    {
        $http = (new FakeHttpClient())->willReturn('api.openweathermap.org', json_encode([
            'main' => ['temp' => 19.1, 'humidity' => 58, 'pressure' => 1010],
            'wind' => ['speed' => 2.9],
            'dt' => 1691938800,
        ], JSON_THROW_ON_ERROR));

        $reading = (new OpenWeatherMapProvider($http, 'fake-key'))->fetch(55.8197, 37.6117);

        self::assertSame('OpenWeatherMap', $reading->sourceName);
        self::assertSame(19.1, $reading->temperatureCelsius);
        self::assertSame(58, $reading->humidityPercent);
        self::assertSame(2.9, $reading->windSpeedMs);
        self::assertEqualsWithDelta(Pressure::hpaToMmHg(1010.0), $reading->pressureMmHg, 0.0001);
        self::assertSame(1691938800, $reading->observedAt->getTimestamp());
    }

    public function testWeatherServiceSkipsAFailingProviderAndKeepsTheRest(): void
    {
        $working = (new FakeHttpClient())->willReturn('api.open-meteo.com', json_encode([
            'current' => ['time' => '2026-08-13T15:00', 'temperature_2m' => 20.0, 'relative_humidity_2m' => 50, 'wind_speed_10m' => 4.0, 'surface_pressure' => 1005.0],
        ], JSON_THROW_ON_ERROR));
        $broken = (new FakeHttpClient())->willFail('api.openweathermap.org', 'rate limited');

        $service = new WeatherService([
            new OpenMeteoProvider($working),
            new OpenWeatherMapProvider($broken, 'fake-key'),
        ], new NullLogger());

        $readings = $service->getComparison(55.8197, 37.6117);

        self::assertCount(1, $readings);
        self::assertSame('Open-Meteo', $readings[0]->sourceName);
    }

    public function testWeatherServiceReturnsEmptyWhenEveryProviderFails(): void
    {
        $broken = (new FakeHttpClient())->willFail('api.open-meteo.com');

        $service = new WeatherService([new OpenMeteoProvider($broken)], new NullLogger());

        self::assertSame([], $service->getComparison(55.8197, 37.6117));
    }

    public function testOpenMeteoProviderAggregatesHourlyDataIntoDailyBuckets(): void
    {
        $http = (new FakeHttpClient())->willReturn('api.open-meteo.com', json_encode([
            'hourly' => [
                'time' => ['2026-08-14T00:00', '2026-08-14T12:00', '2026-08-15T00:00', '2026-08-15T12:00'],
                'temperature_2m' => [15.0, 20.0, 16.0, 21.0],
                'relative_humidity_2m' => [70, 50, 65, 55],
                'wind_speed_10m' => [2.0, 4.0, 3.0, 5.0],
                'surface_pressure' => [1010.0, 1008.0, 1012.0, 1006.0],
            ],
        ], JSON_THROW_ON_ERROR));

        $days = (new OpenMeteoProvider($http))->fetchForecast(55.8197, 37.6117, 2);

        self::assertCount(2, $days);

        self::assertSame('2026-08-14', $days[0]->date);
        self::assertSame(15.0, $days[0]->tempMinCelsius);
        self::assertSame(20.0, $days[0]->tempMaxCelsius);
        self::assertSame(60, $days[0]->humidityPercent);
        self::assertSame(3.0, $days[0]->windSpeedMs);
        self::assertEqualsWithDelta(Pressure::hpaToMmHg(1009.0), $days[0]->pressureMmHg, 0.0001);

        self::assertSame('2026-08-15', $days[1]->date);
        self::assertSame(16.0, $days[1]->tempMinCelsius);
        self::assertSame(21.0, $days[1]->tempMaxCelsius);
    }

    public function testOpenWeatherMapProviderAggregates3HourlySlotsIntoDailyBuckets(): void
    {
        $slot = static fn (string $isoUtc): int => (new \DateTimeImmutable($isoUtc, new \DateTimeZone('UTC')))->getTimestamp();

        $http = (new FakeHttpClient())->willReturn('api.openweathermap.org', json_encode([
            'list' => [
                ['dt' => $slot('2026-08-14T03:00:00'), 'main' => ['temp' => 15.0, 'humidity' => 70, 'pressure' => 1010.0], 'wind' => ['speed' => 2.0]],
                ['dt' => $slot('2026-08-14T15:00:00'), 'main' => ['temp' => 20.0, 'humidity' => 50, 'pressure' => 1008.0], 'wind' => ['speed' => 4.0]],
                ['dt' => $slot('2026-08-15T03:00:00'), 'main' => ['temp' => 16.0, 'humidity' => 65, 'pressure' => 1012.0], 'wind' => ['speed' => 3.0]],
                ['dt' => $slot('2026-08-15T15:00:00'), 'main' => ['temp' => 21.0, 'humidity' => 55, 'pressure' => 1006.0], 'wind' => ['speed' => 5.0]],
            ],
        ], JSON_THROW_ON_ERROR));

        $days = (new OpenWeatherMapProvider($http, 'fake-key'))->fetchForecast(55.8197, 37.6117, 2);

        self::assertCount(2, $days);
        self::assertSame('2026-08-14', $days[0]->date);
        self::assertSame(15.0, $days[0]->tempMinCelsius);
        self::assertSame(20.0, $days[0]->tempMaxCelsius);
        self::assertSame(60, $days[0]->humidityPercent);
        self::assertSame(3.0, $days[0]->windSpeedMs);
        self::assertEqualsWithDelta(Pressure::hpaToMmHg(1009.0), $days[0]->pressureMmHg, 0.0001);
    }

    public function testGetForecastComparisonSkipsAFailingProvider(): void
    {
        $working = (new FakeHttpClient())->willReturn('api.open-meteo.com', json_encode([
            'hourly' => [
                'time' => ['2026-08-14T00:00'],
                'temperature_2m' => [15.0],
                'relative_humidity_2m' => [70],
                'wind_speed_10m' => [2.0],
                'surface_pressure' => [1010.0],
            ],
        ], JSON_THROW_ON_ERROR));
        $broken = (new FakeHttpClient())->willFail('api.openweathermap.org', 'rate limited');

        $service = new WeatherService([
            new OpenMeteoProvider($working),
            new OpenWeatherMapProvider($broken, 'fake-key'),
        ], new NullLogger());

        $forecasts = $service->getForecastComparison(55.8197, 37.6117, 1);

        self::assertArrayHasKey('Open-Meteo', $forecasts);
        self::assertArrayNotHasKey('OpenWeatherMap', $forecasts);
        self::assertCount(1, $forecasts['Open-Meteo']);
    }
}

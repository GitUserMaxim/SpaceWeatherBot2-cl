<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Weather;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SpaceWeatherBot\Tests\Support\FakeHttpClient;
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
        self::assertSame(1009.2, $reading->pressureHpa);
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
        self::assertSame(1010.0, $reading->pressureHpa);
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
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Dto\WeatherForecastDay;
use SpaceWeatherBot\Dto\WeatherReading;

interface WeatherProviderInterface
{
    /**
     * Short, human-readable name shown next to this provider's reading
     * (e.g. "Open-Meteo", "OpenWeatherMap").
     */
    public function name(): string;

    /**
     * @throws ApiException
     */
    public function fetch(float $lat, float $lon): WeatherReading;

    /**
     * @return list<WeatherForecastDay> one entry per calendar day, oldest first
     *
     * @throws ApiException
     */
    public function fetchForecast(float $lat, float $lon, int $days): array;
}

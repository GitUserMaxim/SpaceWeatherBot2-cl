<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class WeatherForecastDay
{
    public function __construct(
        public string $sourceName,
        public string $date,
        public ?float $tempMinCelsius,
        public ?float $tempMaxCelsius,
        public ?int $humidityPercent,
        public ?float $windSpeedMs,
        public ?float $pressureMmHg,
    ) {
    }
}

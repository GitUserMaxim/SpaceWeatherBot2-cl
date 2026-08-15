<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class WeatherReading
{
    public function __construct(
        public string $sourceName,
        public ?float $temperatureCelsius,
        public ?int $humidityPercent,
        public ?float $windSpeedMs,
        public ?float $pressureHpa,
        public \DateTimeImmutable $observedAt,
    ) {
    }
}

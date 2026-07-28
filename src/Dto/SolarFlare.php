<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class SolarFlare
{
    public function __construct(
        public string $class,
        public \DateTimeImmutable $peakTime,
        public ?string $region,
    ) {
    }
}

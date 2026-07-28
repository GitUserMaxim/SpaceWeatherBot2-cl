<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class SpaceWeatherConditions
{
    public function __construct(
        public float $kpIndex,
        public string $stormLevel,
        public string $stormLabel,
        public ?float $solarWindSpeed,
        public ?float $imfBz,
        public ?SolarFlare $latestFlare,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

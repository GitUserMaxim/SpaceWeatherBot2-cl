<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class SunReport
{
    /**
     * @param list<string> $activeRegions
     * @param list<string> $coronalHoles
     */
    public function __construct(
        public float $kpIndex,
        public string $stormLevel,
        public string $stormLabel,
        public ?SolarFlare $latestFlare,
        public ?float $solarWindSpeed,
        public ?float $imfBz,
        public ?float $imfBt,
        public array $activeRegions,
        public array $coronalHoles,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

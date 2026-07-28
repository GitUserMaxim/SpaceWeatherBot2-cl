<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class DailySummary
{
    public function __construct(
        public float $kpIndex,
        public ?float $solarWindSpeed,
        public ?SolarFlare $latestFlare,
        public int $stormProbability,
        public string $summaryKey,
        public \DateTimeImmutable $generatedAt,
    ) {
    }
}

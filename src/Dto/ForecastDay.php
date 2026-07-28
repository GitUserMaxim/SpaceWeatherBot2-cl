<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Dto;

final readonly class ForecastDay
{
    public function __construct(
        public string $label,
        public string $date,
        public ?float $kpExpected,
        public int $stormProbability,
        public int $mClassProbability,
        public int $xClassProbability,
        public string $summaryKey,
    ) {
    }
}

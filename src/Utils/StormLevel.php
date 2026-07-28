<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Utils;

final class StormLevel
{
    /**
     * @return array{level: string, label: string}
     */
    public static function fromKp(float $kp): array
    {
        return match (true) {
            $kp >= 9 => ['level' => 'G5', 'label' => 'Extreme'],
            $kp >= 8 => ['level' => 'G4', 'label' => 'Severe'],
            $kp >= 7 => ['level' => 'G3', 'label' => 'Strong'],
            $kp >= 6 => ['level' => 'G2', 'label' => 'Moderate'],
            $kp >= 5 => ['level' => 'G1', 'label' => 'Minor'],
            default => ['level' => 'G0', 'label' => 'Quiet'],
        };
    }
}

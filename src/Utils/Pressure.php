<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Utils;

final class Pressure
{
    private const float HPA_TO_MMHG = 0.750062;

    public static function hpaToMmHg(float $hpa): float
    {
        return $hpa * self::HPA_TO_MMHG;
    }
}

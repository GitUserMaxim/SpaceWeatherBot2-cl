<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Utils;

final class TimeFormatter
{
    public static function format(\DateTimeImmutable $dateTime, string $format): string
    {
        return $dateTime->format($format);
    }

    public static function formatForUser(
        \DateTimeImmutable $dateTime,
        bool $use24Hour,
        \DateTimeZone $timezone,
    ): string {
        $localized = $dateTime->setTimezone($timezone);
        $pattern = $use24Hour ? 'Y-m-d H:i' : 'Y-m-d g:i A';

        return $localized->format($pattern);
    }
}

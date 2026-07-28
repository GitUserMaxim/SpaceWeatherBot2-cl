<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Lang;

enum Locale: string
{
    case En = 'en';
    case Ru = 'ru';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'ru', 'russian' => self::Ru,
            default => self::En,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::En, self::Ru];
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Utils;

final class Html
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function bold(string $value): string
    {
        return '<b>' . self::escape($value) . '</b>';
    }

    public static function line(string $label, string $value): string
    {
        return self::bold($label) . ': ' . self::escape($value);
    }
}

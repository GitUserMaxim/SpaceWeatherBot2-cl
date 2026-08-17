<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Location;

use SpaceWeatherBot\Lang\Locale;

interface GeocoderInterface
{
    /**
     * Turns coordinates into a short human-readable place name (e.g.
     * "Moscow, Ostankinsky District"). Returns null if it can't - callers
     * should fall back to something else (raw coordinates, a default name),
     * never block on this.
     */
    public function reverseGeocode(float $lat, float $lon, Locale $locale): ?string;
}

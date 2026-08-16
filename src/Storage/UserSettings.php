<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Storage;

use SpaceWeatherBot\Lang\Locale;

final readonly class UserSettings
{
    public function __construct(
        public int $chatId,
        public Locale $locale,
        public ?float $locationLat = null,
        public ?float $locationLon = null,
        public ?string $locationName = null,
    ) {
    }

    public function withLocale(Locale $locale): self
    {
        return new self($this->chatId, $locale, $this->locationLat, $this->locationLon, $this->locationName);
    }

    public function withLocation(float $lat, float $lon, string $name): self
    {
        return new self($this->chatId, $this->locale, $lat, $lon, $name);
    }
}

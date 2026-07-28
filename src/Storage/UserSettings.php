<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Storage;

use SpaceWeatherBot\Lang\Locale;

final readonly class UserSettings
{
    public function __construct(
        public int $chatId,
        public Locale $locale,
        public bool $use24HourTime,
    ) {
    }

    public function withLocale(Locale $locale): self
    {
        return new self($this->chatId, $locale, $this->use24HourTime);
    }

    public function withUse24HourTime(bool $use24HourTime): self
    {
        return new self($this->chatId, $this->locale, $use24HourTime);
    }
}

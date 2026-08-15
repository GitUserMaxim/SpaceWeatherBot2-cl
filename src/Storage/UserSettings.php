<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Storage;

use SpaceWeatherBot\Lang\Locale;

final readonly class UserSettings
{
    public function __construct(
        public int $chatId,
        public Locale $locale,
    ) {
    }

    public function withLocale(Locale $locale): self
    {
        return new self($this->chatId, $locale);
    }
}

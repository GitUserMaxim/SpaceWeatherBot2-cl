<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Storage;

interface UserSettingsRepositoryInterface
{
    public function find(int $chatId): ?UserSettings;

    public function save(UserSettings $settings): void;
}

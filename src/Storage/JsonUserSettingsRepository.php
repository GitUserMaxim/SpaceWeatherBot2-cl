<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Storage;

use SpaceWeatherBot\Config\AppConfig;
use SpaceWeatherBot\Lang\Locale;

final class JsonUserSettingsRepository implements UserSettingsRepositoryInterface
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly Locale $defaultLocale,
    ) {
    }

    public function find(int $chatId): ?UserSettings
    {
        $path = $this->pathFor($chatId);

        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return null;
        }

        return new UserSettings(
            chatId: $chatId,
            locale: Locale::fromString((string) ($data['locale'] ?? $this->defaultLocale->value)),
            locationLat: isset($data['location_lat']) ? (float) $data['location_lat'] : null,
            locationLon: isset($data['location_lon']) ? (float) $data['location_lon'] : null,
            locationName: isset($data['location_name']) ? (string) $data['location_name'] : null,
        );
    }

    public function save(UserSettings $settings): void
    {
        $path = $this->pathFor($settings->chatId);
        $payload = json_encode([
            'locale' => $settings->locale->value,
            'location_lat' => $settings->locationLat,
            'location_lon' => $settings->locationLon,
            'location_name' => $settings->locationName,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException(sprintf('Unable to save user settings for chat %d', $settings->chatId));
        }
    }

    private function pathFor(int $chatId): string
    {
        return sprintf('%s/%d.json', rtrim($this->config->usersDir(), '/'), $chatId);
    }
}

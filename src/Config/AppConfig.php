<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Config;

final class AppConfig
{
    public function __construct(
        public readonly string $telegramBotToken,
        public readonly ?string $telegramWebhookSecret,
        public readonly string $appEnv,
        public readonly string $logLevel,
        public readonly string $githubRepo,
        public readonly string $developerName,
        public readonly string $defaultLocale,
        public readonly string $projectRoot,
        public readonly float $defaultWeatherLat,
        public readonly float $defaultWeatherLon,
        public readonly string $defaultWeatherLocationName,
        public readonly ?string $openWeatherMapApiKey,
        public readonly string $version = '1.0.0',
    ) {
    }

    public static function fromEnvironment(string $projectRoot): self
    {
        return new self(
            telegramBotToken: self::requireEnv('TELEGRAM_BOT_TOKEN'),
            telegramWebhookSecret: self::optionalEnv('TELEGRAM_WEBHOOK_SECRET'),
            appEnv: self::optionalEnv('APP_ENV') ?? 'prod',
            logLevel: self::optionalEnv('LOG_LEVEL') ?? 'info',
            githubRepo: self::optionalEnv('GITHUB_REPO') ?? 'https://github.com/yourusername/SpaceWeatherBot',
            developerName: self::optionalEnv('DEVELOPER_NAME') ?? 'Maxim',
            defaultLocale: self::optionalEnv('DEFAULT_LOCALE') ?? 'en',
            projectRoot: $projectRoot,
            // Default: Ostankino, the practical geographic center of Moscow's
            // North-Eastern Administrative Okrug (SVAO). Override via .env for
            // a different city/district.
            defaultWeatherLat: (float) (self::optionalEnv('WEATHER_LAT') ?? '55.8197'),
            defaultWeatherLon: (float) (self::optionalEnv('WEATHER_LON') ?? '37.6117'),
            defaultWeatherLocationName: self::optionalEnv('WEATHER_LOCATION_NAME') ?? 'Moscow, NE (Ostankino)',
            openWeatherMapApiKey: self::optionalEnv('OPENWEATHERMAP_API_KEY'),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'prod';
    }

    public function cacheDir(): string
    {
        return $this->projectRoot . '/storage/cache';
    }

    public function logsDir(): string
    {
        return $this->projectRoot . '/storage/logs';
    }

    public function usersDir(): string
    {
        return $this->projectRoot . '/storage/users';
    }

    public function langDir(): string
    {
        return $this->projectRoot . '/lang';
    }

    private static function requireEnv(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (! is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Required environment variable "%s" is not set.', $key));
        }

        return $value;
    }

    private static function optionalEnv(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}

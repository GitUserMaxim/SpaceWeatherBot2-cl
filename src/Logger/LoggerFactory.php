<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SpaceWeatherBot\Config\AppConfig;

final class LoggerFactory
{
    public static function create(AppConfig $config): LoggerInterface
    {
        $logger = new Logger('spaceweatherbot');
        $logger->pushHandler(new StreamHandler(
            $config->logsDir() . '/app.log',
            self::mapLevel($config->logLevel),
        ));

        return $logger;
    }

    private static function mapLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'warning', 'warn' => Level::Warning,
            'error' => Level::Error,
            default => Level::Info,
        };
    }
}

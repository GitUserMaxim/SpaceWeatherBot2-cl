#!/usr/bin/env php
<?php

declare(strict_types=1);

/** @var array{config: SpaceWeatherBot\Config\AppConfig, logger: Psr\Log\LoggerInterface, telegram: SpaceWeatherBot\Telegram\TelegramClient, updateHandler: SpaceWeatherBot\Telegram\UpdateHandler} $container */
$container = require __DIR__ . '/../bootstrap.php';

$logger = $container['logger'];
$telegram = $container['telegram'];
$updateHandler = $container['updateHandler'];

// Long polling and webhooks are mutually exclusive on Telegram's side.
$telegram->deleteWebhook();

$offset = 0;
$running = true;

if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use (&$running): void {
        $running = false;
    });
    pcntl_signal(SIGTERM, function () use (&$running): void {
        $running = false;
    });
}

fwrite(STDOUT, "SpaceWeatherBot: long polling started. Press Ctrl+C to stop.\n");

while ($running) {
    $updates = $telegram->getUpdates($offset, 30);

    foreach ($updates as $update) {
        $updateId = (int) ($update['update_id'] ?? 0);
        $offset = max($offset, $updateId + 1);

        try {
            $updateHandler->handle($update);
        } catch (\Throwable $exception) {
            $logger->error('Unhandled exception while processing update', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

fwrite(STDOUT, "SpaceWeatherBot: stopped.\n");

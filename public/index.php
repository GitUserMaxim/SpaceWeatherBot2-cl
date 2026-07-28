<?php

declare(strict_types=1);

/** @var array{config: SpaceWeatherBot\Config\AppConfig, logger: Psr\Log\LoggerInterface, telegram: SpaceWeatherBot\Telegram\TelegramClient, updateHandler: SpaceWeatherBot\Telegram\UpdateHandler} $container */
$container = require __DIR__ . '/../bootstrap.php';

$config = $container['config'];
$logger = $container['logger'];
$updateHandler = $container['updateHandler'];

if ($config->telegramWebhookSecret !== null) {
    $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

    if (! hash_equals($config->telegramWebhookSecret, $receivedSecret)) {
        http_response_code(403);
        exit;
    }
}

$rawBody = file_get_contents('php://input');
$update = json_decode($rawBody !== false ? $rawBody : '', true);

if (! is_array($update)) {
    http_response_code(400);
    exit;
}

try {
    $updateHandler->handle($update);
} catch (\Throwable $exception) {
    $logger->error('Unhandled exception while processing update', [
        'message' => $exception->getMessage(),
    ]);
}

http_response_code(200);

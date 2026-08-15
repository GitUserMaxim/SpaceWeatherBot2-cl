<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Telegram;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

final class TelegramClient
{
    private const string API_BASE = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly Client $client,
        private readonly string $token,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed>|null $replyKeyboard
     */
    public function sendMessage(
        int $chatId,
        string $text,
        ?array $replyKeyboard = null,
        string $parseMode = 'HTML',
    ): void {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        if ($replyKeyboard !== null) {
            $payload['reply_markup'] = json_encode($replyKeyboard, JSON_THROW_ON_ERROR);
        }

        $this->call('sendMessage', $payload);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(string $method, array $params, int $requestTimeout = 15): array
    {
        try {
            $response = $this->client->post(self::API_BASE . $this->token . '/' . $method, [
                'form_params' => $params,
                'timeout' => $requestTimeout,
            ]);

            $decoded = json_decode((string) $response->getBody(), true);

            if (! is_array($decoded)) {
                $this->logger->error('Telegram API returned a non-JSON response', ['method' => $method]);

                return [];
            }

            if (($decoded['ok'] ?? false) !== true) {
                $this->logger->error('Telegram API returned an error', [
                    'method' => $method,
                    'response' => $decoded,
                ]);
            }

            return $decoded;
        } catch (\Throwable $exception) {
            $this->logger->error('Telegram API call failed', [
                'method' => $method,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}

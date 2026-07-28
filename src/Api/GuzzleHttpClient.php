<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

final class GuzzleHttpClient implements HttpClientInterface
{
    private const int MAX_RETRIES = 3;

    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $url): string
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; ++$attempt) {
            try {
                $response = $this->client->get($url, [
                    'http_errors' => true,
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ]);

                return (string) $response->getBody();
            } catch (GuzzleException $exception) {
                $lastException = $exception;
                $this->logger->warning('HTTP request failed', [
                    'url' => $url,
                    'attempt' => $attempt,
                    'message' => $exception->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    usleep(200_000 * $attempt);
                }
            }
        }

        throw new ApiException(
            sprintf('Failed to fetch URL after %d attempts: %s', self::MAX_RETRIES, $url),
            0,
            $lastException,
        );
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly FilesystemAdapter $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $url, int $ttlSeconds = 120): string
    {
        $cacheKey = 'http_' . hash('sha256', $url);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($url, $ttlSeconds): string {
                $item->expiresAfter($ttlSeconds);

                return $this->inner->get($url);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Cache fetch failed, falling back to direct request', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return $this->inner->get($url);
        }
    }
}

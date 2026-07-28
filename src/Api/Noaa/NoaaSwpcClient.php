<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api\Noaa;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\CachedHttpClient;

final class NoaaSwpcClient implements NoaaClientInterface
{
    private const string BASE_JSON = 'https://services.swpc.noaa.gov/json';
    private const string BASE_PRODUCTS = 'https://services.swpc.noaa.gov/products/solar-wind';

    public function __construct(
        private readonly CachedHttpClient $http,
    ) {
    }

    public function getPlanetaryKIndex(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/planetary_k_index_1m.json', 60);
    }

    public function getSolarWindPlasma(): array
    {
        return $this->fetchTabularJson(self::BASE_PRODUCTS . '/plasma-2-hour.json', 120);
    }

    public function getSolarWindMag(): array
    {
        return $this->fetchTabularJson(self::BASE_PRODUCTS . '/mag-2-hour.json', 120);
    }

    public function getEditedEvents(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/edited_events.json', 300);
    }

    public function getSolarRegions(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/solar_regions.json', 600);
    }

    public function getSolarProbabilities(): array
    {
        return $this->fetchJson(self::BASE_JSON . '/solar_probabilities.json', 3600);
    }

    /**
     * @return list<array<string, mixed>>
     */
    // private function fetchJson(string $url, int $ttl): array
    // {
    //     //$payload = $this->http->get($url, $ttl);
    //     //$decoded = json_decode($payload, true);

    //     $payload = $this->http->get($url, $ttl);

    
    //     fwrite(STDERR, '[DEBUG] Fetching URL: ' . $url . PHP_EOL);
    //     fwrite(STDERR, '[DEBUG] Payload length: ' . strlen($payload) . PHP_EOL);

    //     $decoded = json_decode($payload, true);

    //     if (! is_array($decoded)) {
    //         throw new ApiException(sprintf('Invalid JSON response from NOAA: %s', $url));
    //     }

    //     /** @var list<array<string, mixed>> $decoded */
    //     return $decoded;
    // }

    // /**
    //  * @return list<array<int|string, mixed>>
    //  */

    private function fetchJson(string $url, int $ttl): array
{
    // 1. Делаем запрос
    $payload = $this->http->get($url, $ttl);

    // 2. Логируем для Render (через STDERR!)
    fwrite(STDERR, '[DEBUG] Fetching URL: ' . $url . PHP_EOL);
    fwrite(STDERR, '[DEBUG] Payload length: ' . strlen($payload) . PHP_EOL);

    // Если payload пустой - сразу логируем, это частая проблема кэша
    if ($payload === '') {
        fwrite(STDERR, '[WARNING] Payload is EMPTY! Check CachedHttpClient cache.' . PHP_EOL);
    } else {
        // Выводим первые 100 символов, чтобы видеть, JSON ли там вообще, или HTML ошибка
        $preview = substr($payload, 0, 100);
        // Экранируем спецсимволы, чтобы лог не сломался
        $safePreview = addcslashes($preview, "\n\r\t");
        fwrite(STDERR, '[DEBUG] First 100 chars: ' . $safePreview . PHP_EOL);
    }

    // 3. Декодируем
    $decoded = json_decode($payload, true);

    // 4. ГЛАВНОЕ: Проверяем, была ли ошибка парсинга, ДО проверки типа
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = json_last_error_msg();
        $errorCode = json_last_error();
        
        // Пишем детальную ошибку в логи Render
        fwrite(STDERR, '[ERROR] JSON Decode failed: ' . $errorMsg . ' (Code: ' . $errorCode . ')' . PHP_EOL);
        
        throw new ApiException(sprintf(
            'JSON Decode Error: %s (Code: %d) for URL: %s',
            $errorMsg,
            $errorCode,
            $url
        ));
    }

    // 5. Проверяем тип данных
    if (! is_array($decoded)) {
        fwrite(STDERR, '[ERROR] Decoded result is not an array. Type: ' . gettype($decoded) . PHP_EOL);
        throw new ApiException(sprintf('Invalid JSON response from NOAA: %s', $url));
    }

    /** @var list<array<string, mixed>> $decoded */
    return $decoded;
}

    private function fetchTabularJson(string $url, int $ttl): array
    {
        $payload = $this->http->get($url, $ttl);
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || $decoded === []) {
            throw new ApiException(sprintf('Invalid tabular JSON response from NOAA: %s', $url));
        }

        /** @var list<array<int|string, mixed>> $decoded */
        return $decoded;
    }
}

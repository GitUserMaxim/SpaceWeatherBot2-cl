<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Weather;

use Psr\Log\LoggerInterface;
use SpaceWeatherBot\Dto\WeatherReading;

final class WeatherService
{
    /**
     * @param list<WeatherProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetches from every configured provider and returns whichever succeeded.
     * A single provider failing (rate limit, timeout, bad response) doesn't
     * take down the others - the whole point of showing several sources is
     * to keep working when one of them doesn't.
     *
     * @return list<WeatherReading>
     */
    public function getComparison(float $lat, float $lon): array
    {
        $readings = [];

        foreach ($this->providers as $provider) {
            try {
                $readings[] = $provider->fetch($lat, $lon);
            } catch (\Throwable $exception) {
                $this->logger->warning('Weather provider failed', [
                    'provider' => $provider->name(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $readings;
    }
}

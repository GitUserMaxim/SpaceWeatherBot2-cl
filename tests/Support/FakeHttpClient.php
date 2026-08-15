<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Support;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\HttpClientInterface;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, string> */
    private array $responses = [];

    /** @var array<string, string> */
    private array $failures = [];

    public function willReturn(string $urlContains, string $body): self
    {
        $this->responses[$urlContains] = $body;

        return $this;
    }

    public function willFail(string $urlContains, string $message = 'Simulated failure'): self
    {
        $this->failures[$urlContains] = $message;

        return $this;
    }

    public function get(string $url): string
    {
        foreach ($this->failures as $needle => $message) {
            if (str_contains($url, $needle)) {
                throw new ApiException($message);
            }
        }

        foreach ($this->responses as $needle => $body) {
            if (str_contains($url, $needle)) {
                return $body;
            }
        }

        throw new ApiException('FakeHttpClient: no response configured for ' . $url);
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api;

interface HttpClientInterface
{
    /**
     * @throws ApiException
     */
    public function get(string $url): string;
}

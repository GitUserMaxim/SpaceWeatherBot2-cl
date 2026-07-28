<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Api\Noaa;

use SpaceWeatherBot\Api\ApiException;

interface NoaaClientInterface
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    public function getPlanetaryKIndex(): array;

    /**
     * @return list<array<int|string, mixed>>
     *
     * @throws ApiException
     */
    public function getSolarWindPlasma(): array;

    /**
     * @return list<array<int|string, mixed>>
     *
     * @throws ApiException
     */
    public function getSolarWindMag(): array;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    public function getEditedEvents(): array;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    public function getSolarRegions(): array;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    public function getSolarProbabilities(): array;
}

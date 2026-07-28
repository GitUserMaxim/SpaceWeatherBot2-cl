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
     * RTSW plasma feed (replaces the deprecated tabular plasma-2-hour.json).
     * Each entry has "time_tag", "proton_speed", "proton_density",
     * "proton_temperature", plus "source"/"active" metadata.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    public function getSolarWindPlasma(): array;

    /**
     * RTSW magnetometer feed (replaces the deprecated tabular mag-2-hour.json).
     * Each entry has "time_tag", "bx_gsm", "by_gsm", "bz_gsm", "bt",
     * "phi_gsm", "theta_gsm", plus "source"/"active" metadata.
     *
     * @return list<array<string, mixed>>
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

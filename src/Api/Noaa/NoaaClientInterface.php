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

    /**
     * Raw text of NOAA's official 3-Day Geomagnetic Forecast bulletin
     * (https://services.swpc.noaa.gov/text/3-day-geomag-forecast.txt).
     * Plain text, not JSON - contains observed/predicted Ap, per-day storm
     * category probabilities, and a table of 3-hourly Kp forecasts for the
     * next 3 days. Lives under /text/, unlike the WAF-protected /products/
     * path, so it's actually reachable from a server.
     *
     * @throws ApiException
     */
    public function getGeomagForecastText(): string;
}

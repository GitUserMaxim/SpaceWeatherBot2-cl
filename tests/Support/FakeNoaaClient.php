<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Support;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\Noaa\NoaaClientInterface;

/**
 * In-memory stand-in for NoaaClientInterface. Every fixture defaults to an
 * empty list so a test only has to set up the endpoints it actually cares about.
 */
final class FakeNoaaClient implements NoaaClientInterface
{
    /** @var list<array<string, mixed>> */
    private array $planetaryKIndex = [];

    /** @var list<array<string, mixed>> */
    private array $solarWindPlasma = [];

    /** @var list<array<string, mixed>> */
    private array $solarWindMag = [];

    /** @var list<array<string, mixed>> */
    private array $editedEvents = [];

    /** @var list<array<string, mixed>> */
    private array $solarRegions = [];

    /** @var list<array<string, mixed>> */
    private array $solarProbabilities = [];

    private ?string $geomagForecastText = null;

    private ?string $failWith = null;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withPlanetaryKIndex(array $rows): self
    {
        $this->planetaryKIndex = $rows;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withSolarWindPlasma(array $rows): self
    {
        $this->solarWindPlasma = $rows;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withSolarWindMag(array $rows): self
    {
        $this->solarWindMag = $rows;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withEditedEvents(array $rows): self
    {
        $this->editedEvents = $rows;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withSolarRegions(array $rows): self
    {
        $this->solarRegions = $rows;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withSolarProbabilities(array $rows): self
    {
        $this->solarProbabilities = $rows;

        return $this;
    }

    public function withGeomagForecastText(string $text): self
    {
        $this->geomagForecastText = $text;

        return $this;
    }

    public function failingWith(string $message): self
    {
        $this->failWith = $message;

        return $this;
    }

    public function getPlanetaryKIndex(): array
    {
        $this->maybeFail();

        return $this->planetaryKIndex;
    }

    public function getSolarWindPlasma(): array
    {
        $this->maybeFail();

        return $this->solarWindPlasma;
    }

    public function getSolarWindMag(): array
    {
        $this->maybeFail();

        return $this->solarWindMag;
    }

    public function getEditedEvents(): array
    {
        $this->maybeFail();

        return $this->editedEvents;
    }

    public function getSolarRegions(): array
    {
        $this->maybeFail();

        return $this->solarRegions;
    }

    public function getSolarProbabilities(): array
    {
        $this->maybeFail();

        return $this->solarProbabilities;
    }

    public function getGeomagForecastText(): string
    {
        $this->maybeFail();

        return $this->geomagForecastText ?? $this->defaultGeomagForecastText();
    }

    /**
     * A realistic bulletin covering today/+1/+2 (UTC), so tests that don't
     * care about this specific feed still get a parseable default instead
     * of having to fabricate one every time.
     */
    private function defaultGeomagForecastText(): string
    {
        $utc = new \DateTimeZone('UTC');
        $day0 = new \DateTimeImmutable('now', $utc);
        $day1 = $day0->modify('+1 day');
        $day2 = $day0->modify('+2 days');

        $label = static fn (\DateTimeImmutable $d): string => $d->format('M j');

        return <<<TXT
        :Product: Geomagnetic Forecast
        :Issued: {$day0->format('Y M d')} 2205 UTC
        # Prepared by the U.S. Dept. of Commerce, NOAA, Space Weather Prediction Center
        #
        NOAA Ap Index Forecast
        Observed Ap {$day0->format('d M')} 008
        Estimated Ap {$day0->format('d M')} 008
        Predicted Ap {$day1->format('d M')}-{$day2->format('d M')} 018-015

        NOAA Geomagnetic Activity Probabilities {$label($day0)}-{$label($day2)}
        Active                40/40/25
        Minor storm           30/25/10
        Moderate storm        05/05/01
        Strong-Extreme storm  01/01/01

        NOAA Kp index forecast {$label($day0)} - {$label($day2)}
                     {$label($day0)}    {$label($day1)}    {$label($day2)}
        00-03UT        3.67      3.67      2.33
        03-06UT        3.33      3.33      2.00
        06-09UT        3.00      3.00      2.67
        09-12UT        2.67      3.00      2.33
        12-15UT        3.00      2.33      2.33
        15-18UT        3.33      2.67      2.33
        18-21UT        3.33      3.00      2.33
        21-00UT        4.00      3.00      2.67
        TXT;
    }

    private function maybeFail(): void
    {
        if ($this->failWith !== null) {
            throw new ApiException($this->failWith);
        }
    }
}

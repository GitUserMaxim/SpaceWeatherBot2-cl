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

    private function maybeFail(): void
    {
        if ($this->failWith !== null) {
            throw new ApiException($this->failWith);
        }
    }
}

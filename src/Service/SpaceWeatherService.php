<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Service;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\Noaa\NoaaClientInterface;
use SpaceWeatherBot\Dto\DailySummary;
use SpaceWeatherBot\Dto\ForecastDay;
use SpaceWeatherBot\Dto\SolarFlare;
use SpaceWeatherBot\Dto\SpaceWeatherConditions;
use SpaceWeatherBot\Dto\SunReport;
use SpaceWeatherBot\Utils\StormLevel;

/**
 * Turns raw NOAA SWPC responses into the DTOs the bot renders.
 *
 * NOTE ON DATA SOURCES: NOAA's public JSON feeds are not versioned and their
 * exact field names have drifted before (e.g. "kp_index" vs "estimated_kp").
 * This class reads several candidate keys defensively so a minor schema
 * change degrades to null/0 instead of throwing. If Maxim notices fields
 * consistently coming back empty, that's the first place to check against
 * a live response (services.swpc.noaa.gov/json/...).
 */
final class SpaceWeatherService
{
    public function __construct(
        private readonly NoaaClientInterface $noaa,
    ) {
    }

    /**
     * @throws ApiException
     */
    public function getCurrentConditions(): SpaceWeatherConditions
    {
        $kp = $this->latestKp();
        $wind = $this->latestSolarWind();
        $flare = $this->latestFlare();
        $storm = StormLevel::fromKp($kp['value']);

        return new SpaceWeatherConditions(
            kpIndex: $kp['value'],
            stormLevel: $storm['level'],
            stormLabel: $storm['label'],
            solarWindSpeed: $wind['speed'],
            imfBz: $wind['bz'],
            latestFlare: $flare,
            updatedAt: $kp['updatedAt'],
        );
    }

    /**
     * @throws ApiException
     */
    public function getSunReport(): SunReport
    {
        $wind = $this->latestSolarWind();
        $flare = $this->latestFlare();

        return new SunReport(
            latestFlare: $flare,
            solarWindSpeed: $wind['speed'],
            imfBz: $wind['bz'],
            imfBt: $wind['bt'],
            activeRegions: $this->activeRegions(),
            // NOAA's currently wired endpoints don't expose coronal hole data;
            // left empty until a dedicated NOAA source is added to NoaaClientInterface.
            coronalHoles: [],
            updatedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /**
     * @return list<ForecastDay>
     *
     * @throws ApiException
     */
    public function getForecast(): array
    {
        $probabilities = $this->forecastProbabilities();
        $currentKp = $this->latestKp()['value'];
        $days = [
            ['label' => 'forecast.today', 'offset' => 0],
            ['label' => 'forecast.tomorrow', 'offset' => 1],
            ['label' => 'forecast.day_plus_2', 'offset' => 2],
            ['label' => 'forecast.day_plus_3', 'offset' => 3],
        ];

        $result = [];

        foreach ($days as $index => $day) {
            $probability = $probabilities[$index] ?? ['c' => 0, 'm' => 0, 'x' => 0];
            // Storm probability isn't published by the solar_probabilities feed;
            // approximated here from the current Kp level as a placeholder.
            $stormProbability = $this->stormProbabilityFromKp($currentKp);

            $result[] = new ForecastDay(
                label: $day['label'],
                date: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->modify(sprintf('+%d days', $day['offset']))
                    ->format('Y-m-d'),
                kpExpected: null,
                stormProbability: $stormProbability,
                mClassProbability: $probability['m'],
                xClassProbability: $probability['x'],
                summaryKey: $this->summaryKeyFor($stormProbability),
            );
        }

        return $result;
    }

    /**
     * @throws ApiException
     */
    public function getDailySummary(): DailySummary
    {
        $kp = $this->latestKp();
        $wind = $this->latestSolarWind();
        $flare = $this->latestFlare();
        $stormProbability = $this->stormProbabilityFromKp($kp['value']);

        return new DailySummary(
            kpIndex: $kp['value'],
            solarWindSpeed: $wind['speed'],
            latestFlare: $flare,
            stormProbability: $stormProbability,
            summaryKey: $this->summaryKeyFor($stormProbability, forDaily: true),
            generatedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /**
     * @return array{value: float, updatedAt: \DateTimeImmutable}
     */
    private function latestKp(): array
    {
        $rows = $this->noaa->getPlanetaryKIndex();
        $last = $rows === [] ? [] : $rows[array_key_last($rows)];

        $value = $this->toFloat($last['kp_index'] ?? $last['estimated_kp'] ?? $last['kp'] ?? null) ?? 0.0;
        $updatedAt = $this->parseTimeTag($last['time_tag'] ?? null);

        return ['value' => $value, 'updatedAt' => $updatedAt];
    }

    /**
     * @return array{speed: ?float, bz: ?float, bt: ?float}
     */
    private function latestSolarWind(): array
    {
        $plasma = $this->lastTabularRow($this->noaa->getSolarWindPlasma());
        $mag = $this->lastTabularRow($this->noaa->getSolarWindMag());

        return [
            'speed' => $this->toFloat($plasma['speed'] ?? null),
            'bz' => $this->toFloat($mag['bz_gsm'] ?? $mag['bz'] ?? null),
            'bt' => $this->toFloat($mag['bt'] ?? null),
        ];
    }

    private function latestFlare(): ?SolarFlare
    {
        $events = $this->noaa->getEditedEvents();
        $candidate = null;
        $candidateTime = null;

        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? $event['event'] ?? '');
            $particulars = (string) ($event['particulars'] ?? '');

            if ($type !== 'XRA' || $particulars === '' || ! preg_match('/^[A-Z]/', $particulars)) {
                continue;
            }

            $time = $this->parseTimeTag($event['max_datetime'] ?? $event['begin_datetime'] ?? null);

            if ($candidateTime === null || $time > $candidateTime) {
                $candidate = new SolarFlare(
                    class: $particulars,
                    peakTime: $time,
                    region: isset($event['reg']) ? (string) $event['reg'] : (isset($event['region']) ? (string) $event['region'] : null),
                );
                $candidateTime = $time;
            }
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function activeRegions(): array
    {
        $regions = $this->noaa->getSolarRegions();
        $numbers = [];

        foreach ($regions as $region) {
            $number = $region['region'] ?? $region['Region'] ?? null;

            if ($number !== null) {
                $numbers[(string) $number] = true;
            }
        }

        $list = array_keys($numbers);
        sort($list);

        return array_slice($list, -8);
    }

    /**
     * @return list<array{c: int, m: int, x: int}>
     */
    private function forecastProbabilities(): array
    {
        $rows = $this->noaa->getSolarProbabilities();
        $row = $rows[0] ?? [];

        $result = [];

        for ($day = 1; $day <= 3; ++$day) {
            $result[] = [
                'c' => $this->toInt($this->firstPresent($row, ["c_class_{$day}_day", "C_Class_{$day}_day"])) ?? 0,
                'm' => $this->toInt($this->firstPresent($row, ["m_class_{$day}_day", "M_Class_{$day}_day"])) ?? 0,
                'x' => $this->toInt($this->firstPresent($row, ["x_class_{$day}_day", "X_Class_{$day}_day"])) ?? 0,
            ];
        }

        // NOAA's "1_day" figure is the forecast FOR tomorrow (issued today), so it lines
        // up with our "tomorrow" slot. There's no NOAA figure for "today" in this feed,
        // so day 1's numbers are reused as the closest available stand-in.
        return array_merge([$result[0]], $result);
    }

    private function stormProbabilityFromKp(float $kp): int
    {
        return match (true) {
            $kp >= 7 => 80,
            $kp >= 5 => 50,
            $kp >= 4 => 25,
            default => 5,
        };
    }

    private function summaryKeyFor(int $stormProbability, bool $forDaily = false): string
    {
        if ($forDaily) {
            return match (true) {
                $stormProbability >= 50 => 'daily.summary_storm',
                $stormProbability >= 25 => 'daily.summary_moderate',
                default => 'daily.summary_quiet',
            };
        }

        // stormProbabilityFromKp() only ever returns 5, 25, 50 or 80, so a
        // "10-24" band here would be dead code — keeping the tiers aligned
        // with the actual reachable values.
        return match (true) {
            $stormProbability >= 50 => 'forecast.summary_storm',
            $stormProbability >= 25 => 'forecast.summary_elevated',
            default => 'forecast.summary_quiet',
        };
    }

    /**
     * @param list<array<int|string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function lastTabularRow(array $rows): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $header = $rows[0];
        $last = $rows[array_key_last($rows)];

        if (! is_array($header) || ! is_array($last)) {
            return [];
        }

        /** @var array<string, mixed> $combined */
        $combined = @array_combine(array_map('strval', $header), $last) ?: [];

        return $combined;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function firstPresent(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return $row[$key];
            }
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        $float = $this->toFloat($value);

        return $float === null ? null : (int) round($float);
    }

    private function parseTimeTag(?string $value): \DateTimeImmutable
    {
        if ($value === null) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }
}

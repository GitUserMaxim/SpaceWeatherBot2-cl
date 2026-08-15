<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Service;

use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Api\Noaa\NoaaClientInterface;
use SpaceWeatherBot\Dto\ForecastDay;
use SpaceWeatherBot\Dto\SolarFlare;
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
    public function getSunReport(): SunReport
    {
        $kp = $this->latestKp();
        $wind = $this->latestSolarWind();
        $flare = $this->latestFlare();
        $storm = StormLevel::fromKp($kp['value']);

        return new SunReport(
            kpIndex: $kp['value'],
            stormLevel: $storm['level'],
            stormLabel: $storm['label'],
            latestFlare: $flare,
            solarWindSpeed: $wind['speed'],
            imfBz: $wind['bz'],
            imfBt: $wind['bt'],
            activeRegions: $this->activeRegions(),
            // NOAA's currently wired endpoints don't expose coronal hole data;
            // left empty until a dedicated NOAA source is added to NoaaClientInterface.
            coronalHoles: [],
            updatedAt: $kp['updatedAt'],
        );
    }

    /**
     * @return list<ForecastDay>
     *
     * @throws ApiException
     */
    public function getForecast(): array
    {
        $flareProbabilities = $this->forecastProbabilities();
        $bulletin = $this->parseGeomagBulletin($this->noaa->getGeomagForecastText());
        $currentKp = $this->latestKp()['value'];

        $days = [
            ['label' => 'forecast.today', 'offset' => 0],
            ['label' => 'forecast.tomorrow', 'offset' => 1],
            ['label' => 'forecast.day_plus_2', 'offset' => 2],
            ['label' => 'forecast.day_plus_3', 'offset' => 3],
        ];

        $result = [];

        foreach ($days as $index => $day) {
            $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify(sprintf('+%d days', $day['offset']))
                ->format('Y-m-d');

            $flare = $flareProbabilities[$index] ?? ['c' => 0, 'm' => 0, 'x' => 0];
            $entry = $this->findBulletinEntry($bulletin, $date, $currentKp);

            $result[] = new ForecastDay(
                label: $day['label'],
                date: $date,
                kpExpected: $entry['kpExpected'],
                stormProbability: $entry['stormProbability'],
                mClassProbability: $flare['m'],
                xClassProbability: $flare['x'],
                summaryKey: $this->summaryKeyFor($entry['stormProbability']),
            );
        }

        return $result;
    }

    /**
     * @throws ApiException
     */
    public function getCurrentKp(): float
    {
        return $this->latestKp()['value'];
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
        $plasma = $this->latestActiveRow($this->noaa->getSolarWindPlasma());
        $mag = $this->latestActiveRow($this->noaa->getSolarWindMag());

        return [
            'speed' => $this->toFloat($plasma['proton_speed'] ?? $plasma['speed'] ?? null),
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

        $list = array_map('strval', array_keys($numbers));
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

        // NOAA's "date" field on the latest row IS today, so "1_day" is today's
        // figure, "2_day" is tomorrow's, "3_day" is +2 days'. NOAA doesn't forecast
        // a 4th day at all, so "+3 days" reuses the 3-day figure as the closest
        // available stand-in.
        return array_merge($result, [$result[2]]);
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

    /**
     * Parses NOAA's plain-text 3-Day Geomagnetic Forecast bulletin into a
     * date-keyed map. Real example of the bulletin structure this expects:
     *
     *   NOAA Geomagnetic Activity Probabilities 14 Aug-16 Aug
     *   Active                40/40/25
     *   Minor storm           30/25/10
     *   Moderate storm        05/05/01
     *   Strong-Extreme storm  01/01/01
     *
     *   NOAA Kp index forecast 14 Aug - 16 Aug
     *                Aug 14    Aug 15    Aug 16
     *   00-03UT        3.67      3.67      2.33
     *   ...
     *
     * "Storm probability" here is Minor + Moderate + Strong-Extreme summed
     * (i.e. the chance of Kp reaching 5 or higher at all that day), and
     * "Kp expected" is the highest of that day's eight 3-hourly values.
     *
     * @return array<string, array{stormProbability: int, kpExpected: float}>
     */
    private function parseGeomagBulletin(string $text): array
    {
        $issuedYear = preg_match('/:Issued:\s*(\d{4})/', $text, $issuedMatch)
            ? (int) $issuedMatch[1]
            : (int) (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');

        // The three day-column labels repeat directly above the Kp table
        // (e.g. "Aug 14    Aug 15    Aug 16") - the most reliable place to
        // read exactly which three calendar dates this bulletin covers.
        if (! preg_match('/NOAA Kp index forecast[^\n]*\n\s*((?:[A-Za-z]{3}\s+\d{1,2}\s*){3})/', $text, $headerMatch)) {
            return [];
        }

        preg_match_all('/([A-Za-z]{3})\s+(\d{1,2})/', $headerMatch[1], $dateMatches, PREG_SET_ORDER);

        if (count($dateMatches) !== 3) {
            return [];
        }

        $dates = [];
        $previousMonth = null;

        foreach ($dateMatches as $match) {
            $year = $issuedYear;
            $month = (int) (\DateTimeImmutable::createFromFormat('M', $match[1])?->format('n') ?? 0);

            // Handles a bulletin issued in December forecasting into January.
            if ($previousMonth !== null && $month < $previousMonth) {
                ++$year;
            }

            $previousMonth = $month;
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, (int) $match[2]);
        }

        // Sum the three storm-severity tiers into one "any storm" probability
        // per day-column. "Active" (sub-storm level activity) isn't a storm,
        // so it's intentionally excluded.
        $stormTotals = [0, 0, 0];

        foreach (['Minor storm', 'Moderate storm', 'Strong-Extreme storm'] as $tier) {
            if (preg_match('/^' . preg_quote($tier, '/') . '\s+(\d+)\/(\d+)\/(\d+)/m', $text, $tierMatch)) {
                $stormTotals[0] += (int) $tierMatch[1];
                $stormTotals[1] += (int) $tierMatch[2];
                $stormTotals[2] += (int) $tierMatch[3];
            }
        }

        // Eight 3-hourly rows, one column per day - a day's expected Kp is
        // the highest of its eight values (the peak, which is what actually
        // matters for storm risk).
        $kpMax = [0.0, 0.0, 0.0];

        if (preg_match_all('/^\d{2}-\d{2}UT\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/m', $text, $kpRows, PREG_SET_ORDER)) {
            foreach ($kpRows as $row) {
                for ($column = 0; $column < 3; ++$column) {
                    $kpMax[$column] = max($kpMax[$column], (float) $row[$column + 1]);
                }
            }
        }

        $result = [];

        foreach ($dates as $index => $date) {
            $result[$date] = [
                'stormProbability' => min(100, $stormTotals[$index]),
                'kpExpected' => $kpMax[$index],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array{stormProbability: int, kpExpected: float}> $bulletin
     *
     * @return array{stormProbability: int, kpExpected: ?float}
     */
    private function findBulletinEntry(array $bulletin, string $date, float $fallbackKp): array
    {
        if (isset($bulletin[$date])) {
            return $bulletin[$date];
        }

        if ($bulletin === []) {
            // Bulletin didn't parse at all (unexpected format change) - fall
            // back to the old rough Kp-based estimate rather than showing nothing.
            return ['stormProbability' => $this->stormProbabilityFromKp($fallbackKp), 'kpExpected' => null];
        }

        // The wanted date is outside NOAA's 3-day window (e.g. "today" queried
        // before a fresh bulletin has been issued, or the 4th UI day NOAA never
        // covers at all) - reuse the chronologically nearest day NOAA did forecast.
        $closest = null;
        $closestDiff = null;

        foreach ($bulletin as $bulletinDate => $entry) {
            $diff = abs(strtotime($bulletinDate) - strtotime($date));

            if ($closestDiff === null || $diff < $closestDiff) {
                $closest = $entry;
                $closestDiff = $diff;
            }
        }

        return $closest ?? ['stormProbability' => $this->stormProbabilityFromKp($fallbackKp), 'kpExpected' => null];
    }

    private function summaryKeyFor(int $stormProbability): string
    {
        return match (true) {
            $stormProbability >= 50 => 'forecast.summary_storm',
            $stormProbability >= 25 => 'forecast.summary_elevated',
            $stormProbability >= 10 => 'forecast.summary_moderate',
            default => 'forecast.summary_quiet',
        };
    }

    /**
     * RTSW feeds can contain rows from more than one satellite; "active" marks
     * which one SWPC currently trusts. Prefer the latest active row, falling
     * back to the latest row overall if no row is flagged active.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function latestActiveRow(array $rows): array
    {
        $best = [];
        $bestTime = null;
        $fallbackBest = [];
        $fallbackTime = null;

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['time_tag'])) {
                continue;
            }

            $time = $this->parseTimeTag((string) $row['time_tag']);

            if ($fallbackTime === null || $time > $fallbackTime) {
                $fallbackBest = $row;
                $fallbackTime = $time;
            }

            if (($row['active'] ?? false) === true && ($bestTime === null || $time > $bestTime)) {
                $best = $row;
                $bestTime = $time;
            }
        }

        return $bestTime !== null ? $best : $fallbackBest;
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

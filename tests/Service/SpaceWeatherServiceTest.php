<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Service;

use PHPUnit\Framework\TestCase;
use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Service\SpaceWeatherService;
use SpaceWeatherBot\Tests\Support\FakeNoaaClient;

final class SpaceWeatherServiceTest extends TestCase
{
    public function testCurrentConditionsUsesLatestRowsAndParsesFields(): void
    {
        $noaa = (new FakeNoaaClient())
            ->withPlanetaryKIndex([
                ['time_tag' => '2026-07-27T09:00:00', 'kp_index' => 2.0],
                ['time_tag' => '2026-07-27T12:00:00', 'kp_index' => 5.67],
            ])
            ->withSolarWindPlasma([
                ['time_tag' => '2026-07-27T11:55:00.000Z', 'proton_speed' => 300.0, 'proton_density' => 4.5, 'proton_temperature' => 50000.0, 'source' => 'DSCOVR', 'active' => false],
                ['time_tag' => '2026-07-27T11:58:00.000Z', 'proton_speed' => 412.3, 'proton_density' => 4.7, 'proton_temperature' => 52000.0, 'source' => 'DSCOVR', 'active' => true],
            ])
            ->withSolarWindMag([
                ['time_tag' => '2026-07-27T11:58:00.000Z', 'bx_gsm' => 1.1, 'by_gsm' => 2.2, 'bz_gsm' => -6.7, 'phi_gsm' => 10.0, 'theta_gsm' => 20.0, 'bt' => 7.8, 'source' => 'DSCOVR', 'active' => true],
            ])
            ->withEditedEvents([
                ['type' => 'XRA', 'particulars' => 'C3.4', 'max_datetime' => '2026-07-27T09:00:00', 'reg' => '3700'],
                ['type' => 'XRA', 'particulars' => 'M2.1', 'max_datetime' => '2026-07-27T11:40:00', 'reg' => '3701'],
                ['type' => 'FLA', 'particulars' => '', 'max_datetime' => '2026-07-27T11:50:00'],
            ]);

        $service = new SpaceWeatherService($noaa);
        $conditions = $service->getCurrentConditions();

        self::assertSame(5.67, $conditions->kpIndex);
        self::assertSame('G1', $conditions->stormLevel);
        self::assertSame('Minor', $conditions->stormLabel);
        self::assertSame(412.3, $conditions->solarWindSpeed);
        self::assertSame(-6.7, $conditions->imfBz);
        self::assertNotNull($conditions->latestFlare);
        self::assertSame('M2.1', $conditions->latestFlare->class);
        self::assertSame('3701', $conditions->latestFlare->region);
        self::assertSame('2026-07-27T12:00:00+00:00', $conditions->updatedAt->format('Y-m-d\TH:i:sP'));
    }

    public function testCurrentConditionsFallsBackToZeroKpWhenFeedIsEmpty(): void
    {
        $service = new SpaceWeatherService(new FakeNoaaClient());

        $conditions = $service->getCurrentConditions();

        self::assertSame(0.0, $conditions->kpIndex);
        self::assertSame('G0', $conditions->stormLevel);
        self::assertNull($conditions->solarWindSpeed);
        self::assertNull($conditions->latestFlare);
    }

    public function testCurrentConditionsPropagatesApiException(): void
    {
        $noaa = (new FakeNoaaClient())->failingWith('NOAA is down');
        $service = new SpaceWeatherService($noaa);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('NOAA is down');

        $service->getCurrentConditions();
    }

    public function testSolarWindPrefersActiveSourceOverNewerInactiveReading(): void
    {
        $noaa = (new FakeNoaaClient())
            ->withSolarWindPlasma([
                // Newer timestamp, but this satellite is no longer the one SWPC trusts.
                ['time_tag' => '2026-07-27T12:05:00.000Z', 'proton_speed' => 999.9, 'source' => 'ACE', 'active' => false],
                // Older timestamp, but flagged active — this is the one that should win.
                ['time_tag' => '2026-07-27T12:00:00.000Z', 'proton_speed' => 450.0, 'source' => 'DSCOVR', 'active' => true],
            ])
            ->withSolarWindMag([
                ['time_tag' => '2026-07-27T12:05:00.000Z', 'bz_gsm' => -20.0, 'source' => 'ACE', 'active' => false],
                ['time_tag' => '2026-07-27T12:00:00.000Z', 'bz_gsm' => -3.0, 'source' => 'DSCOVR', 'active' => true],
            ]);

        $conditions = (new SpaceWeatherService($noaa))->getCurrentConditions();

        self::assertSame(450.0, $conditions->solarWindSpeed);
        self::assertSame(-3.0, $conditions->imfBz);
    }

    public function testSolarWindFallsBackToLatestOverallWhenNoRowIsFlaggedActive(): void
    {
        $noaa = (new FakeNoaaClient())
            ->withSolarWindPlasma([
                ['time_tag' => '2026-07-27T12:00:00.000Z', 'proton_speed' => 400.0],
                ['time_tag' => '2026-07-27T12:05:00.000Z', 'proton_speed' => 420.0],
            ]);

        $conditions = (new SpaceWeatherService($noaa))->getCurrentConditions();

        self::assertSame(420.0, $conditions->solarWindSpeed);
    }

    public function testSunReportDeduplicatesSortsAndCapsActiveRegionsAtEight(): void
    {
        $regions = [];

        foreach ([3695, 3690, 3699, 3691, 3695, 3692, 3693, 3694, 3696, 3697] as $number) {
            $regions[] = ['region' => (string) $number];
        }

        $noaa = (new FakeNoaaClient())->withSolarRegions($regions);
        $service = new SpaceWeatherService($noaa);

        $sun = $service->getSunReport();

        // 9 unique regions in (3695 is a duplicate), capped to the 8 highest, ascending.
        self::assertSame(
            ['3691', '3692', '3693', '3694', '3695', '3696', '3697', '3699'],
            $sun->activeRegions,
        );
        self::assertSame([], $sun->coronalHoles);
    }

    public function testForecastMapsNoaaDayOffsetsOntoTodayTomorrowPlus2Plus3(): void
    {
        $noaa = (new FakeNoaaClient())
            ->withPlanetaryKIndex([['time_tag' => '2026-07-27T12:00:00', 'kp_index' => 6.0]])
            ->withSolarProbabilities([[
                'c_class_1_day' => '25', 'm_class_1_day' => '05', 'x_class_1_day' => '01',
                'C_Class_2_day' => '30', 'M_Class_2_day' => '10', 'X_Class_2_day' => '02',
                'c_class_3_day' => '35', 'm_class_3_day' => '15', 'x_class_3_day' => '03',
            ]]);

        $service = new SpaceWeatherService($noaa);
        $days = $service->getForecast();

        self::assertCount(4, $days);
        self::assertSame(
            ['forecast.today', 'forecast.tomorrow', 'forecast.day_plus_2', 'forecast.day_plus_3'],
            array_map(static fn ($day) => $day->label, $days),
        );

        // NOAA's own "date" field on this row IS today, so Day-1 figures land on
        // "today", Day-2 on "tomorrow", Day-3 on "+2 days". NOAA has no Day-4 figure
        // at all, so "+3 days" reuses the Day-3 numbers as the closest stand-in.
        self::assertSame(5, $days[0]->mClassProbability);
        self::assertSame(1, $days[0]->xClassProbability);

        self::assertSame(10, $days[1]->mClassProbability);
        self::assertSame(2, $days[1]->xClassProbability);

        self::assertSame(15, $days[2]->mClassProbability);
        self::assertSame(3, $days[2]->xClassProbability);

        self::assertSame(15, $days[3]->mClassProbability);
        self::assertSame(3, $days[3]->xClassProbability);

        // kpExpected comes from the bulletin's per-day 3-hourly max. The default
        // fixture bulletin covers today/+1/+2 UTC; "+3 days" isn't covered by
        // NOAA at all, so it reuses the +2 day's figures as the closest stand-in.
        self::assertSame(4.00, $days[0]->kpExpected);
        self::assertSame(3.67, $days[1]->kpExpected);
        self::assertSame(2.67, $days[2]->kpExpected);
        self::assertSame(2.67, $days[3]->kpExpected);

        // Dates are consecutive, starting today, one per day.
        $expectedStart = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($days as $offset => $day) {
            self::assertSame(
                $expectedStart->modify("+{$offset} days")->format('Y-m-d'),
                $day->date,
            );
        }
    }

    public function testForecastReadsRealBulletinFormatPrecisely(): void
    {
        // A hand-built bulletin using the exact structure NOAA publishes at
        // services.swpc.noaa.gov/text/3-day-geomag-forecast.txt, anchored to
        // today/+1/+2 so date-matching lines up regardless of when this runs.
        $utc = new \DateTimeZone('UTC');
        $day0 = new \DateTimeImmutable('now', $utc);
        $day1 = $day0->modify('+1 day');
        $day2 = $day0->modify('+2 days');
        $label = static fn (\DateTimeImmutable $d): string => $d->format('M j');

        $bulletin = <<<TXT
        :Product: Geomagnetic Forecast
        :Issued: {$day0->format('Y M d')} 2205 UTC
        #
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

        $noaa = (new FakeNoaaClient())
            ->withPlanetaryKIndex([['time_tag' => '2026-07-27T12:00:00', 'kp_index' => 1.0]])
            ->withGeomagForecastText($bulletin);

        $days = (new SpaceWeatherService($noaa))->getForecast();

        // Minor + Moderate + Strong-Extreme summed per day column.
        self::assertSame(36, $days[0]->stormProbability);
        self::assertSame(31, $days[1]->stormProbability);
        self::assertSame(12, $days[2]->stormProbability);
        // "+3 days" isn't in the bulletin at all - nearest available (+2) is reused.
        self::assertSame(12, $days[3]->stormProbability);

        self::assertSame(4.00, $days[0]->kpExpected);
        self::assertSame(3.67, $days[1]->kpExpected);
        self::assertSame(2.67, $days[2]->kpExpected);
    }

    public function testForecastFallsBackToKpBasedEstimateWhenBulletinDoesNotParse(): void
    {
        $cases = [
            // kp, expected fallback stormProbability, expected summaryKey
            [2.0, 5, 'forecast.summary_quiet'],
            [4.5, 25, 'forecast.summary_elevated'],
            [6.0, 50, 'forecast.summary_storm'],
            [8.0, 80, 'forecast.summary_storm'],
        ];

        foreach ($cases as [$kp, $expectedProbability, $expectedKey]) {
            $noaa = (new FakeNoaaClient())
                ->withPlanetaryKIndex([['time_tag' => '2026-07-27T12:00:00', 'kp_index' => $kp]])
                ->withSolarProbabilities([[]])
                // Not a real bulletin - forces the parser to return [] and the
                // Kp-based fallback to kick in for every day.
                ->withGeomagForecastText('this is not a NOAA bulletin');

            $days = (new SpaceWeatherService($noaa))->getForecast();

            foreach ($days as $day) {
                self::assertSame($expectedProbability, $day->stormProbability, "kp={$kp}");
                self::assertSame($expectedKey, $day->summaryKey, "kp={$kp}");
                self::assertNull($day->kpExpected, "kp={$kp}");
            }
        }
    }

    public function testDailySummaryUsesTodaysBulletinEntryWhenAvailable(): void
    {
        $noaa = (new FakeNoaaClient())
            ->withPlanetaryKIndex([['time_tag' => '2026-07-27T12:00:00', 'kp_index' => 1.0]]);
        // No explicit bulletin set - uses FakeNoaaClient's default fixture,
        // whose "today" column sums to 30+5+1 = 36% storm probability.

        $summary = (new SpaceWeatherService($noaa))->getDailySummary();

        self::assertSame(1.0, $summary->kpIndex);
        self::assertSame(36, $summary->stormProbability);
        self::assertSame('daily.summary_moderate', $summary->summaryKey);
    }

    public function testDailySummaryFallsBackToKpBasedEstimateWhenBulletinDoesNotParse(): void
    {
        $cases = [
            [2.0, 5, 'daily.summary_quiet'],
            [4.5, 25, 'daily.summary_moderate'],
            [6.0, 50, 'daily.summary_storm'],
        ];

        foreach ($cases as [$kp, $expectedProbability, $expectedKey]) {
            $noaa = (new FakeNoaaClient())
                ->withPlanetaryKIndex([['time_tag' => '2026-07-27T12:00:00', 'kp_index' => $kp]])
                ->withGeomagForecastText('this is not a NOAA bulletin');

            $summary = (new SpaceWeatherService($noaa))->getDailySummary();

            self::assertSame($kp, $summary->kpIndex);
            self::assertSame($expectedProbability, $summary->stormProbability, "kp={$kp}");
            self::assertSame($expectedKey, $summary->summaryKey, "kp={$kp}");
        }
    }
}

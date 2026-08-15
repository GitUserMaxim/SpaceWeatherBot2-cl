<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Telegram;

use Psr\Log\LoggerInterface;
use SpaceWeatherBot\Api\ApiException;
use SpaceWeatherBot\Config\AppConfig;
use SpaceWeatherBot\Lang\Locale;
use SpaceWeatherBot\Lang\MenuAction;
use SpaceWeatherBot\Lang\Translator;
use SpaceWeatherBot\Service\SpaceWeatherService;
use SpaceWeatherBot\Storage\UserSettings;
use SpaceWeatherBot\Storage\UserSettingsRepositoryInterface;
use SpaceWeatherBot\Utils\Html;
use SpaceWeatherBot\Utils\TimeFormatter;
use SpaceWeatherBot\Weather\WeatherService;

final class UpdateHandler
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly Translator $translator,
        private readonly UserSettingsRepositoryInterface $settingsRepository,
        private readonly SpaceWeatherService $weatherService,
        private readonly WeatherService $groundWeatherService,
        private readonly AppConfig $config,
        private readonly Locale $defaultLocale,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            // Ignore edited messages, callback queries, channel posts, etc.
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);

        if ($chatId === 0) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $settings = $this->settingsRepository->find($chatId) ?? new UserSettings($chatId, $this->defaultLocale, true);
        $locale = $settings->locale;

        if ($text === '/start' || $text === '/menu') {
            $this->settingsRepository->save($settings);
            $this->reply(
                $chatId,
                Html::bold($this->translator->get($locale, 'app.name')) . "\n\n" . $this->translator->get($locale, 'app.welcome'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $action = $this->translator->resolveMenuAction($locale, $text);

        if ($action === null) {
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.welcome'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        match ($action) {
            MenuAction::CurrentConditions => $this->sendCurrentConditions($chatId, $settings),
            MenuAction::Forecast => $this->sendForecast($chatId, $locale),
            MenuAction::Sun => $this->sendSun($chatId, $locale, $settings),
            MenuAction::DailySummary => $this->sendDailySummary($chatId, $locale),
            MenuAction::Weather => $this->sendWeather($chatId, $locale, $settings),
            MenuAction::Glossary => $this->reply(
                $chatId,
                $this->translator->get($locale, 'glossary.choose'),
                KeyboardFactory::glossaryMenu($this->translator, $locale),
            ),
            MenuAction::Language => $this->reply(
                $chatId,
                $this->translator->get($locale, 'language.choose'),
                KeyboardFactory::languageMenu($this->translator, $locale),
            ),
            MenuAction::Settings => $this->sendSettings($chatId, $locale, $settings),
            MenuAction::About => $this->sendAbout($chatId, $locale),
            MenuAction::Back => $this->reply(
                $chatId,
                Html::bold($this->translator->get($locale, 'app.name')),
                KeyboardFactory::mainMenu($this->translator, $locale),
            ),
            MenuAction::Explain => $this->sendExplain($chatId, $locale),
            MenuAction::SetLanguageEn => $this->changeLocale($chatId, $settings, Locale::En),
            MenuAction::SetLanguageRu => $this->changeLocale($chatId, $settings, Locale::Ru),
            MenuAction::SetTime24h => $this->changeTimeFormat($chatId, $locale, $settings, true),
            MenuAction::SetTime12h => $this->changeTimeFormat($chatId, $locale, $settings, false),
            MenuAction::GlossaryKp,
            MenuAction::GlossarySolarWind,
            MenuAction::GlossaryImfBz,
            MenuAction::GlossaryCme,
            MenuAction::GlossarySolarFlare,
            MenuAction::GlossaryGeomagneticStorm,
            MenuAction::GlossaryGStorms,
            MenuAction::GlossaryMClassFlare,
            MenuAction::GlossaryXClassFlare => $this->sendGlossaryArticle($chatId, $locale, $action),
        };
    }

    private function sendCurrentConditions(int $chatId, UserSettings $settings): void
    {
        $locale = $settings->locale;

        try {
            $conditions = $this->weatherService->getCurrentConditions();
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load current conditions', ['message' => $exception->getMessage()]);
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.data_unavailable'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $tz = new \DateTimeZone('UTC');
        $lines = [
            Html::bold($this->translator->get($locale, 'conditions.title')),
            '',
            Html::line($this->translator->get($locale, 'conditions.kp'), number_format($conditions->kpIndex, 2)),
            Html::line(
                $this->translator->get($locale, 'conditions.storm_level'),
                $conditions->stormLevel . ' — ' . $this->translator->get($locale, 'storm_labels.' . $conditions->stormLevel),
            ),
            Html::line(
                $this->translator->get($locale, 'conditions.solar_wind'),
                $conditions->solarWindSpeed !== null
                    ? number_format($conditions->solarWindSpeed, 0) . ' km/s'
                    : $this->translator->get($locale, 'conditions.na'),
            ),
            Html::line(
                $this->translator->get($locale, 'conditions.imf_bz'),
                $conditions->imfBz !== null
                    ? number_format($conditions->imfBz, 1) . ' nT'
                    : $this->translator->get($locale, 'conditions.na'),
            ),
            Html::line(
                $this->translator->get($locale, 'conditions.latest_flare'),
                $conditions->latestFlare !== null
                    ? $conditions->latestFlare->class . ' (' . TimeFormatter::formatForUser($conditions->latestFlare->peakTime, $settings->use24HourTime, $tz) . ' UTC)'
                    : $this->translator->get($locale, 'conditions.none'),
            ),
            Html::line(
                $this->translator->get($locale, 'conditions.updated'),
                TimeFormatter::formatForUser($conditions->updatedAt, $settings->use24HourTime, $tz) . ' UTC',
            ),
        ];

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::currentConditionsMenu($this->translator, $locale));
    }

    private function sendForecast(int $chatId, Locale $locale): void
    {
        try {
            $days = $this->weatherService->getForecast();
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load forecast', ['message' => $exception->getMessage()]);
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.data_unavailable'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $lines = [Html::bold($this->translator->get($locale, 'forecast.title')), ''];

        foreach ($days as $day) {
            $lines[] = Html::bold($this->translator->get($locale, $day->label));

            if ($day->kpExpected !== null) {
                $lines[] = Html::line($this->translator->get($locale, 'forecast.kp'), number_format($day->kpExpected, 2));
            }

            $lines[] = Html::line($this->translator->get($locale, 'forecast.storm_prob'), $day->stormProbability . '%');
            $lines[] = Html::line($this->translator->get($locale, 'forecast.m_prob'), $day->mClassProbability . '%');
            $lines[] = Html::line($this->translator->get($locale, 'forecast.x_prob'), $day->xClassProbability . '%');
            $lines[] = $this->translator->get($locale, $day->summaryKey);
            $lines[] = '';
        }

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::backOnly($this->translator, $locale));
    }

    private function sendSun(int $chatId, Locale $locale, UserSettings $settings): void
    {
        try {
            $sun = $this->weatherService->getSunReport();
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load sun report', ['message' => $exception->getMessage()]);
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.data_unavailable'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $tz = new \DateTimeZone('UTC');
        $lines = [
            Html::bold($this->translator->get($locale, 'sun.title')),
            '',
            Html::line(
                $this->translator->get($locale, 'sun.latest_flare'),
                $sun->latestFlare !== null
                    ? $sun->latestFlare->class . ' (' . TimeFormatter::formatForUser($sun->latestFlare->peakTime, $settings->use24HourTime, $tz) . ' UTC)'
                    : $this->translator->get($locale, 'sun.none'),
            ),
            Html::line(
                $this->translator->get($locale, 'sun.solar_wind'),
                $sun->solarWindSpeed !== null ? number_format($sun->solarWindSpeed, 0) . ' km/s' : $this->translator->get($locale, 'sun.unavailable'),
            ),
            Html::line(
                $this->translator->get($locale, 'sun.imf_bz'),
                $sun->imfBz !== null ? number_format($sun->imfBz, 1) . ' nT' : $this->translator->get($locale, 'sun.unavailable'),
            ),
            Html::line(
                $this->translator->get($locale, 'sun.imf_bt'),
                $sun->imfBt !== null ? number_format($sun->imfBt, 1) . ' nT' : $this->translator->get($locale, 'sun.unavailable'),
            ),
            Html::line(
                $this->translator->get($locale, 'sun.active_regions'),
                $sun->activeRegions !== [] ? implode(', ', $sun->activeRegions) : $this->translator->get($locale, 'sun.none'),
            ),
            Html::line(
                $this->translator->get($locale, 'sun.coronal_holes'),
                $sun->coronalHoles !== [] ? implode(', ', $sun->coronalHoles) : $this->translator->get($locale, 'sun.none'),
            ),
        ];

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::backOnly($this->translator, $locale));
    }

    private function sendDailySummary(int $chatId, Locale $locale): void
    {
        try {
            $summary = $this->weatherService->getDailySummary();
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load daily summary', ['message' => $exception->getMessage()]);
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.data_unavailable'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $lines = [
            Html::bold($this->translator->get($locale, 'daily.title')),
            '',
            Html::line($this->translator->get($locale, 'daily.kp'), number_format($summary->kpIndex, 2)),
            Html::line(
                $this->translator->get($locale, 'daily.solar_wind'),
                $summary->solarWindSpeed !== null ? number_format($summary->solarWindSpeed, 0) . ' km/s' : $this->translator->get($locale, 'conditions.na'),
            ),
            Html::line(
                $this->translator->get($locale, 'daily.latest_flare'),
                $summary->latestFlare?->class ?? $this->translator->get($locale, 'conditions.none'),
            ),
            Html::line($this->translator->get($locale, 'daily.storm_probability'), $summary->stormProbability . '%'),
            '',
            Html::bold($this->translator->get($locale, 'daily.summary')) . ': ' . $this->translator->get($locale, $summary->summaryKey),
        ];

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::backOnly($this->translator, $locale));
    }

    private function sendWeather(int $chatId, Locale $locale, UserSettings $settings): void
    {
        $readings = $this->groundWeatherService->getComparison(
            $this->config->defaultWeatherLat,
            $this->config->defaultWeatherLon,
        );

        if ($readings === []) {
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'weather.no_sources'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $tz = new \DateTimeZone('UTC');
        $lines = [
            Html::bold($this->translator->get($locale, 'weather.title')),
            Html::line($this->translator->get($locale, 'weather.location'), $this->config->defaultWeatherLocationName),
            '',
        ];

        foreach ($readings as $reading) {
            $lines[] = Html::bold($reading->sourceName);
            $lines[] = Html::line(
                $this->translator->get($locale, 'weather.temperature'),
                $reading->temperatureCelsius !== null ? number_format($reading->temperatureCelsius, 1) . ' °C' : $this->translator->get($locale, 'conditions.na'),
            );
            $lines[] = Html::line(
                $this->translator->get($locale, 'weather.humidity'),
                $reading->humidityPercent !== null ? $reading->humidityPercent . '%' : $this->translator->get($locale, 'conditions.na'),
            );
            $lines[] = Html::line(
                $this->translator->get($locale, 'weather.wind'),
                $reading->windSpeedMs !== null ? number_format($reading->windSpeedMs, 1) . ' m/s' : $this->translator->get($locale, 'conditions.na'),
            );
            $lines[] = Html::line(
                $this->translator->get($locale, 'weather.pressure'),
                $reading->pressureHpa !== null ? number_format($reading->pressureHpa, 0) . ' hPa' : $this->translator->get($locale, 'conditions.na'),
            );
            $lines[] = Html::line(
                $this->translator->get($locale, 'weather.observed'),
                TimeFormatter::formatForUser($reading->observedAt, $settings->use24HourTime, $tz) . ' UTC',
            );
            $lines[] = '';
        }

        try {
            $lines[] = Html::line($this->translator->get($locale, 'weather.kp_index'), number_format($this->weatherService->getCurrentKp(), 2));
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load Kp for weather screen', ['message' => $exception->getMessage()]);
        }

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::mainMenu($this->translator, $locale));
    }

    private function sendSettings(int $chatId, Locale $locale, UserSettings $settings): void
    {
        $currentFormat = $settings->use24HourTime
            ? $this->translator->get($locale, 'settings.time_24h')
            : $this->translator->get($locale, 'settings.time_12h');

        $lines = [
            Html::bold($this->translator->get($locale, 'settings.title')),
            '',
            Html::line($this->translator->get($locale, 'settings.time_format'), $currentFormat),
        ];

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::settingsMenu($this->translator, $locale));
    }

    private function sendAbout(int $chatId, Locale $locale): void
    {
        $lines = [
            Html::bold($this->translator->get($locale, 'about.title')),
            '',
            Html::line($this->translator->get($locale, 'about.version'), $this->config->version),
            Html::line($this->translator->get($locale, 'about.php'), PHP_VERSION),
            Html::line($this->translator->get($locale, 'about.github'), $this->config->githubRepo),
            Html::line($this->translator->get($locale, 'about.developer'), $this->config->developerName),
            '',
            Html::bold($this->translator->get($locale, 'about.sources')),
            Html::escape($this->translator->get($locale, 'about.sources_list')),
        ];

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::backOnly($this->translator, $locale));
    }

    private function sendExplain(int $chatId, Locale $locale): void
    {
        try {
            $conditions = $this->weatherService->getCurrentConditions();
        } catch (ApiException $exception) {
            $this->logger->warning('Failed to load conditions for explain screen', ['message' => $exception->getMessage()]);
            $this->reply(
                $chatId,
                $this->translator->get($locale, 'app.data_unavailable'),
                KeyboardFactory::mainMenu($this->translator, $locale),
            );

            return;
        }

        $kp = $conditions->kpIndex;
        $keys = match (true) {
            $kp >= 7 => ['storm', 'storm_satellite', 'storm_gps', 'storm_radio', 'storm_power', 'storm_sensitive'],
            $kp >= 5 => ['high', 'satellite', 'gps_impact', 'radio_impact', 'power_grid', 'sensitive'],
            $kp >= 4 => ['moderate', 'aurora_possible', 'gps_may_vary'],
            default => ['low', 'no_effects', 'gps_ok', 'radio_ok'],
        };

        $lines = [Html::bold($this->translator->get($locale, 'explain.title')), ''];

        foreach ($keys as $key) {
            $lines[] = $this->translator->get($locale, 'explain.' . $key);
        }

        $this->reply($chatId, implode("\n", $lines), KeyboardFactory::backOnly($this->translator, $locale));
    }

    private function sendGlossaryArticle(int $chatId, Locale $locale, MenuAction $action): void
    {
        $articleKey = $action->glossaryArticleKey();

        if ($articleKey === null) {
            return;
        }

        $text = Html::bold($this->translator->get($locale, $action->translationKey()))
            . "\n\n"
            . Html::escape($this->translator->get($locale, $articleKey));

        $this->reply($chatId, $text, KeyboardFactory::glossaryMenu($this->translator, $locale));
    }

    private function changeLocale(int $chatId, UserSettings $settings, Locale $newLocale): void
    {
        $updated = $settings->withLocale($newLocale);
        $this->settingsRepository->save($updated);

        $this->reply(
            $chatId,
            $this->translator->get($newLocale, 'language.changed'),
            KeyboardFactory::mainMenu($this->translator, $newLocale),
        );
    }

    private function changeTimeFormat(int $chatId, Locale $locale, UserSettings $settings, bool $use24h): void
    {
        $updated = $settings->withUse24HourTime($use24h);
        $this->settingsRepository->save($updated);

        $this->reply(
            $chatId,
            $this->translator->get($locale, 'settings.time_changed'),
            KeyboardFactory::mainMenu($this->translator, $locale),
        );
    }

    /**
     * @param array<string, mixed> $keyboard
     */
    private function reply(int $chatId, string $text, array $keyboard): void
    {
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }
}

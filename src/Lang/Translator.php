<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Lang;

final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $catalogues = [];

    public function __construct(
        private readonly string $langDir,
    ) {
    }

    public function get(Locale $locale, string $key, array $replace = []): string
    {
        $value = $this->resolve($locale, $key);

        if (! is_string($value)) {
            return $key;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function allMenuLabels(Locale $locale): array
    {
        return [
            $this->get($locale, 'menu.current_conditions'),
            $this->get($locale, 'menu.forecast'),
            $this->get($locale, 'menu.sun'),
            $this->get($locale, 'menu.glossary'),
            $this->get($locale, 'menu.daily_summary'),
            $this->get($locale, 'menu.language'),
            $this->get($locale, 'menu.settings'),
            $this->get($locale, 'menu.about'),
        ];
    }

    public function resolveMenuAction(Locale $locale, string $text): ?MenuAction
    {
        foreach (MenuAction::cases() as $action) {
            if ($text === $this->get($locale, $action->translationKey())) {
                return $action;
            }
        }

        if ($text === $this->get($locale, 'app.back')) {
            return MenuAction::Back;
        }

        if ($text === $this->get($locale, 'menu.explain')) {
            return MenuAction::Explain;
        }

        return $this->resolveGlossaryAction($locale, $text)
            ?? $this->resolveLanguageAction($locale, $text)
            ?? $this->resolveSettingsAction($locale, $text);
    }

    private function resolveGlossaryAction(Locale $locale, string $text): ?MenuAction
    {
        $map = [
            'glossary.kp_index' => MenuAction::GlossaryKp,
            'glossary.solar_wind' => MenuAction::GlossarySolarWind,
            'glossary.imf_bz' => MenuAction::GlossaryImfBz,
            'glossary.cme' => MenuAction::GlossaryCme,
            'glossary.solar_flare' => MenuAction::GlossarySolarFlare,
            'glossary.geomagnetic_storm' => MenuAction::GlossaryGeomagneticStorm,
            'glossary.g_storms' => MenuAction::GlossaryGStorms,
            'glossary.m_class_flare' => MenuAction::GlossaryMClassFlare,
            'glossary.x_class_flare' => MenuAction::GlossaryXClassFlare,
        ];

        foreach ($map as $key => $action) {
            if ($text === $this->get($locale, $key)) {
                return $action;
            }
        }

        return null;
    }

    private function resolveLanguageAction(Locale $locale, string $text): ?MenuAction
    {
        if ($text === $this->get($locale, 'language.en')) {
            return MenuAction::SetLanguageEn;
        }

        if ($text === $this->get($locale, 'language.ru')) {
            return MenuAction::SetLanguageRu;
        }

        return null;
    }

    private function resolveSettingsAction(Locale $locale, string $text): ?MenuAction
    {
        if ($text === $this->get($locale, 'settings.time_24h')) {
            return MenuAction::SetTime24h;
        }

        if ($text === $this->get($locale, 'settings.time_12h')) {
            return MenuAction::SetTime12h;
        }

        return null;
    }

    private function resolve(Locale $locale, string $key): mixed
    {
        $catalogue = $this->loadCatalogue($locale);
        $segments = explode('.', $key);
        $value = $catalogue;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $key;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCatalogue(Locale $locale): array
    {
        $code = $locale->value;

        if (isset($this->catalogues[$code])) {
            return $this->catalogues[$code];
        }

        $path = rtrim($this->langDir, '/') . '/' . $code . '.php';

        if (! is_file($path)) {
            throw new \RuntimeException(sprintf('Language file not found: %s', $path));
        }

        /** @var array<string, mixed> $catalogue */
        $catalogue = require $path;
        $this->catalogues[$code] = $catalogue;

        return $catalogue;
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Lang;

enum MenuAction
{
    case Sun;
    case Forecast;
    case Weather;
    case Glossary;
    case Settings;
    case About;
    case Back;
    case Explain;

    case GlossaryKp;
    case GlossarySolarWind;
    case GlossaryImfBz;
    case GlossaryCme;
    case GlossarySolarFlare;
    case GlossaryGeomagneticStorm;
    case GlossaryGStorms;
    case GlossaryMClassFlare;
    case GlossaryXClassFlare;

    case SetLanguageEn;
    case SetLanguageRu;

    /**
     * Translation key whose rendered value is the button label for this action.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::Sun => 'menu.sun',
            self::Forecast => 'menu.forecast',
            self::Weather => 'menu.weather',
            self::Glossary => 'menu.glossary',
            self::Settings => 'menu.settings',
            self::About => 'menu.about',
            self::Back => 'app.back',
            self::Explain => 'menu.explain',
            self::GlossaryKp => 'glossary.kp_index',
            self::GlossarySolarWind => 'glossary.solar_wind',
            self::GlossaryImfBz => 'glossary.imf_bz',
            self::GlossaryCme => 'glossary.cme',
            self::GlossarySolarFlare => 'glossary.solar_flare',
            self::GlossaryGeomagneticStorm => 'glossary.geomagnetic_storm',
            self::GlossaryGStorms => 'glossary.g_storms',
            self::GlossaryMClassFlare => 'glossary.m_class_flare',
            self::GlossaryXClassFlare => 'glossary.x_class_flare',
            self::SetLanguageEn => 'language.en',
            self::SetLanguageRu => 'language.ru',
        };
    }

    /**
     * Translation key of the glossary article body, for glossary term actions only.
     */
    public function glossaryArticleKey(): ?string
    {
        return match ($this) {
            self::GlossaryKp => 'glossary.articles.kp_index',
            self::GlossarySolarWind => 'glossary.articles.solar_wind',
            self::GlossaryImfBz => 'glossary.articles.imf_bz',
            self::GlossaryCme => 'glossary.articles.cme',
            self::GlossarySolarFlare => 'glossary.articles.solar_flare',
            self::GlossaryGeomagneticStorm => 'glossary.articles.geomagnetic_storm',
            self::GlossaryGStorms => 'glossary.articles.g_storms',
            self::GlossaryMClassFlare => 'glossary.articles.m_class_flare',
            self::GlossaryXClassFlare => 'glossary.articles.x_class_flare',
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function glossaryTerms(): array
    {
        return [
            self::GlossaryKp,
            self::GlossarySolarWind,
            self::GlossaryImfBz,
            self::GlossaryCme,
            self::GlossarySolarFlare,
            self::GlossaryGeomagneticStorm,
            self::GlossaryGStorms,
            self::GlossaryMClassFlare,
            self::GlossaryXClassFlare,
        ];
    }
}

<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Telegram;

use SpaceWeatherBot\Lang\Locale;
use SpaceWeatherBot\Lang\MenuAction;
use SpaceWeatherBot\Lang\Translator;

/**
 * Builds Telegram ReplyKeyboardMarkup arrays. Keyboards are plain button-text
 * driven, matching how Translator::resolveMenuAction() matches incoming text -
 * except the location button in settingsMenu(), which is a native Telegram
 * "share location" button (request_location) and arrives as a location
 * message, not text.
 */
final class KeyboardFactory
{
    /**
     * @return array<string, mixed>
     */
    public static function mainMenu(Translator $t, Locale $locale): array
    {
        return self::keyboard([
            [$t->get($locale, MenuAction::Weather->translationKey()), $t->get($locale, MenuAction::Sun->translationKey())],
            [$t->get($locale, MenuAction::Forecast->translationKey()), $t->get($locale, MenuAction::Glossary->translationKey())],
            [$t->get($locale, MenuAction::Settings->translationKey()), $t->get($locale, MenuAction::About->translationKey())],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sunMenu(Translator $t, Locale $locale): array
    {
        return self::keyboard([
            [$t->get($locale, MenuAction::Explain->translationKey())],
            [$t->get($locale, MenuAction::Back->translationKey())],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function glossaryMenu(Translator $t, Locale $locale): array
    {
        $rows = [];
        $row = [];

        foreach (MenuAction::glossaryTerms() as $term) {
            $row[] = $t->get($locale, $term->translationKey());

            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $rows[] = $row;
        }

        $rows[] = [$t->get($locale, MenuAction::Back->translationKey())];

        return self::keyboard($rows);
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsMenu(Translator $t, Locale $locale): array
    {
        return self::keyboard([
            [$t->get($locale, MenuAction::SetLanguageEn->translationKey()), $t->get($locale, MenuAction::SetLanguageRu->translationKey())],
            [['text' => $t->get($locale, 'settings.send_location'), 'request_location' => true]],
            [$t->get($locale, MenuAction::Back->translationKey())],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function backOnly(Translator $t, Locale $locale): array
    {
        return self::keyboard([
            [$t->get($locale, MenuAction::Back->translationKey())],
        ]);
    }

    /**
     * @param list<list<string|array<string, mixed>>> $rows
     *
     * @return array<string, mixed>
     */
    private static function keyboard(array $rows): array
    {
        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }
}

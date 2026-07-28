# Space Weather Bot

Telegram-бот, показывающий космическую погоду по данным NOAA SWPC: текущий Kp-индекс,
уровень геомагнитной бури, солнечный ветер, вспышки, прогноз на 3 дня и глоссарий терминов.
Интерфейс на английском и русском.

## Установка

```bash
composer install
cp .env.example .env
```

Открой `.env` и укажи токен бота, полученный у [@BotFather](https://t.me/BotFather):

```
TELEGRAM_BOT_TOKEN=123456:AA...
```

## Запуск локально (long polling)

Самый простой способ для разработки — не нужен публичный HTTPS-адрес:

```bash
composer start
# или
php bin/poll.php
```

Бот сам снимет вебхук (если он был установлен) и начнёт опрашивать Telegram.
Останавливается через Ctrl+C.

## Запуск в проде (webhook)

1. Разместить `public/` за HTTPS (nginx/Apache + php-fpm, либо любой PaaS).
2. Один раз вызвать Telegram API, чтобы указать адрес вебхука:

```bash
curl -F "url=https://your-domain.example.com/index.php" \
     -F "secret_token=$TELEGRAM_WEBHOOK_SECRET" \
     "https://api.telegram.org/bot<TOKEN>/setWebhook"
```

`TELEGRAM_WEBHOOK_SECRET` в `.env` — опционален, но если задан, `public/index.php`
проверяет заголовок `X-Telegram-Bot-Api-Secret-Token` на каждый запрос.

## Команды бота

- `/start` или `/menu` — показать главное меню.
- Дальше — обычная навигация кнопками (reply-клавиатура), без inline-кнопок.

## Известные ограничения

- **Прогноз (`Forecast`)**: NOAA не публикует отдельный прогноз по Kp-индексу через
  уже подключённые эндпойнты, поэтому `kpExpected` всегда `null`, а «вероятность бури»
  оценивается грубо по текущему Kp, а не по официальному прогнозу NOAA. Если нужна точность —
  стоит добавить в `NoaaClientInterface` эндпойнт
  `https://services.swpc.noaa.gov/products/noaa-planetary-k-index-forecast.json`.
- **Coronal holes** в разделе «Sun» всегда пустой список — среди уже подключённых
  NOAA-эндпойнтов нет источника данных по корональным дырам.
- Форматы JSON-фидов NOAA не версионируются и меняются без предупреждения. Разбор в
  `SpaceWeatherService` сделан защитно (несколько вариантов имён полей), но если цифры
  выглядят подозрительно — первым делом сверить реальный ответ NOAA с тем, что ожидает код.
- Код не запускался и не тестировался на реальном PHP (в среде, где он был написан,
  нет интерпретатора PHP) — синтаксис проверен статическим парсером, но перед продом
  стоит прогнать `composer test` / `composer analyse` и вручную потыкать бота.

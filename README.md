# Space Weather Bot

Telegram-бот, показывающий космическую погоду по данным NOAA SWPC: текущий Kp-индекс,
уровень геомагнитной бури, солнечный ветер, вспышки, прогноз на 3 дня и глоссарий терминов.
Интерфейс на английском и русском.

## Установка

```bash
composer install
cp .env.example .env
```


`TELEGRAM_WEBHOOK_SECRET` в `.env` — опционален, но если задан, `public/index.php`
проверяет заголовок `X-Telegram-Bot-Api-Secret-Token` на каждый запрос.

## Команды бота

- `/start` или `/menu` — показать главное меню.
# Секреты проекта — sushiwithlove-ru

> Реальные значения — в `.env` (локально + для `deploy.py`) и в `api/config.php` (на сервере, не в git).
> Этот файл — только карта: имя → где живёт → на что влияет → как ротировать.

---

## Telegram

### `BOT_TOKEN`
- **Где хранится:** `.env` (локально) + `api/config.php` → `define('BOT_TOKEN', ...)` (на сервере)
- **На что влияет:**
  - `api/tg/bot.php` — приём webhook от Telegram
  - `api/auth/tg.php` — авторизация пользователей через Telegram Login / Mini App
  - `tg-app/*` — Telegram Mini App (кнопки, WebApp API)
  - `setWebhook`, `setChatMenuButton`, `answerCallbackQuery` и все вызовы Bot API
- **Где получить новый:** Telegram → `@BotFather` → `/mybots` → выбрать `@sushi45_bot` → API Token → Revoke. Бот тот же, chat_id и подписчики не теряются.
- **Последняя ротация:** не менялся с первой настройки

---

## VK

### `VK_TOKEN`
- **Где хранится:** `.env` + `api/config.php` → `define('VK_TOKEN', ...)`
- **На что влияет:**
  - `api/vk/callback.php` — повар-бот (раскладки по артикулу через `messages.send` + `photos.getMessagesUploadServer`)
  - `api/vk_notify.php` — уведомления кухне о новых заказах (`messages.send` в `VK_PEER_IDS`)
  - ⚠️ Если заменить — БОБА ИНТЕГРАЦИИ ОТВАЛЯТСЯ одновременно
- **Где получить новый:** ВК → Сообщество «Суши с Любовью» (id=237666301) → Управление → Работа с API → Ключи доступа → Создать ключ с правами **messages, photos, manage**
- **Последняя ротация:** 2026-04-20 (предыдущий протух — `error 38 "Unknown application"`)

### `VK_CONFIRMATION`
- **Где хранится:** `api/config.php` → `define('VK_CONFIRMATION', '813d4204')`
- **На что влияет:** `api/vk/callback.php` — подтверждение Callback API. Если изменить — ВК перестанет слать апдейты в `message_new`
- **Где получить новый:** ВК → Сообщество → Управление → Работа с API → Callback API → строка подтверждения. Привязано к Callback-серверу, не к токену
- **Последняя ротация:** не менялся

### `VK_PEER_IDS`
- Это не секрет, а настройка, но важно знать: `api/config.php` → `define('VK_PEER_IDS', '3150260,290986594')` — получатели уведомлений кухни (Михаил Богачёв, Милана Романова)

---

## База данных MySQL (Beget)

### `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- **Где хранится:** `.env` + `api/config.php` → `define('DB_*', ...)`
- **Текущие значения (не секретны):** `DB_HOST=localhost`, `DB_USER=angelros_swl`, `DB_NAME=angelros_swl`
- **На что влияет:** ВСЯ серверная логика — `api/auth/*`, `api/orders/*`, `api/menu/*`, `api/admin/*`, `api/staff/*`, `api/reviews/*`, `api/points/*`
- **Где сменить пароль:** Beget → Панель управления → MySQL → БД `angelros_swl` → «Изменить пароль»
- **Последняя ротация:** не менялся

---

## Админка

### `ADMIN_PASS`
- **Где хранится:** `api/config.php` → `define('ADMIN_PASS', ...)`
- **На что влияет:** `api/admin/` — вход в админ-панель (одноразовый ввод, сессия потом). Сотрудники заходят через `api/staff/` по своим аккаунтам, ADMIN_PASS — только для владельца
- **Где сменить:** открыть `api/config.php` на сервере, поменять значение. Ничего кроме входа не пострадает
- **Последняя ротация:** не менялся

---

## SSH / SFTP (Beget)

### `BEGET_SSH_HOST`, `BEGET_SSH_USER`, `BEGET_SSH_PASS`
- **Где хранится:** `.env` (только локально — сервер сам себе SSH не делает)
- **Текущие значения:** `BEGET_SSH_HOST=angelros.beget.tech`, `BEGET_SSH_USER=angelros`
- **На что влияет:** `deploy.py` — автоматическая загрузка файлов на Beget через SFTP
- **Где сменить пароль:** Beget → Панель управления → Доступы / SSH → сменить пароль
- **Последняя ротация:** не менялся

---

## Google Sheets

### `GS_SHEET_ID`
- Не секрет, а идентификатор публичной таблицы: `api/config.php` → `define('GS_SHEET_ID', '10vZ9_4tPf23o3E3ETdIqHxQmgDc4_hm0Jtrpu4i_PnA')`
- **На что влияет:** `api/vk/callback.php` — таблица с ТТК (артикул, название, фото, вес, рецепт, активность) для повар-бота
- **Доступ:** анонимный через `export?format=csv`. Если сменить лист — обновить ID в `config.php`

---

## Как обновлять этот файл
- При добавлении нового секрета (в `.env` или `config.php`) — **сразу** добавить сюда секцию
- При ротации — обновить поле «Последняя ротация» + причину
- Перед заменой любого токена — прочитать секцию «На что влияет», чтобы понять blast radius

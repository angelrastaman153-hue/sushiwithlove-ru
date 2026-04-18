# Telegram Mini App — Sushi with Love
## Документация для разработчика

---

## Структура файлов

```
tg-app/
├── index.html          ← Точка входа. HTML-разметка всех экранов и bottom sheet
├── css/
│   └── styles.css      ← Все стили. CSS-переменные для темы Telegram
└── js/
    ├── data.js         ← ДАННЫЕ: меню, цены, промокоды, зоны доставки, CONFIG
    ├── cart.js         ← КОРЗИНА: добавление/удаление, подсчёт, CloudStorage
    └── app.js          ← ЛОГИКА: навигация, рендер, формы, отправка заказа
```

---

## Что за что отвечает

### index.html
- Разметка 5 экранов (`#screen-loading`, `#screen-menu`, `#screen-address`, `#screen-details`, `#screen-success`)
- Разметка 2 bottom sheet (`#sheet-product`, `#sheet-cart`)
- Подключение Telegram WebApp SDK (первым в `<head>`)
- Вспомогательные inline-скрипты (геолокация, выбор оплаты, поделиться)

### css/styles.css
- CSS-переменные синхронизированы с темой Telegram через `tg.themeParams`
- Главный акцентный цвет: `--accent: #2AABEE`
- Секции помечены комментариями `/* ← МОЖНО МЕНЯТЬ */`
- Тёмная тема: `[data-theme="dark"]` — переключается из JS

### js/data.js — конфиг + загрузчик меню
- `CONFIG` — телефон, адрес самовывоза, пороги доставки, `menuApiUrl`
- `DELIVERY_ZONES` — зоны и стоимость доставки
- `PROMO_CODES` — промокоды (добавить новый = 1 строка)
- `CATEGORIES`, `MENU_ITEMS` — **заполняются динамически** из `/api/menu/public.php` (тот же источник, что у сайта)
- `loadMenuFromApi(cb)` — загружает меню: мгновенно из localStorage-кэша (5 мин), потом свежие данные из сети
- **Меню НЕ редактируется здесь.** Цены, фото, стопы, состав, варианты — только через админку (БД). Что изменилось на сайте — автоматически обновится и в боте.

### js/cart.js
- Синглтон `Cart` с методами: `add`, `remove`, `setQty`, `qty`, `totalCount`, `clear`, `getItems`, `getTotals`, `applyPromo`
- Хранилище: `tg.CloudStorage` (синхронизация между устройствами) + `localStorage` как fallback
- Ключ: `'swl_cart'`

### js/app.js
- `init()` — запуск при загрузке страницы
- `navigateTo(screenId)` — переход между экранами с анимацией
- `renderMenu()` — генерация карточек товаров
- `openProductSheet(id)` — bottom sheet с товаром
- `openCartSheet()` — bottom sheet корзины
- `handleAddressNext()` — валидация адреса и переход на детали
- `handleSubmitOrder()` — отправка заказа (ЗАГЛУШКА — нужен реальный API)
- `submitOrder(data)` — **СЮДА ВСТАВИТЬ реальный fetch на бэкенд**

---

## Навигация между экранами

```
[loading] ──► [menu]
                │
       тап на карточку ──► bottom sheet: товар (overlay)
                │
    MainButton / иконка корзины ──► bottom sheet: корзина (overlay)
                │
         кнопка «Оформить» ──► [address]
                │
         MainButton «Далее» ──► [details]
                │
     MainButton «Оформить заказ» ──► [success]
                │
            кнопка «Отлично» ──► tg.close()
```

**BackButton Telegram** работает на всех экранах кроме `loading` и `success`.

---

## Как добавить / изменить товар

Через админку сайта (таблица `menu_items` в БД). Mini App и сайт берут меню из одного и того же эндпоинта `/api/menu/public.php`, поэтому изменения появляются одновременно в обоих местах. Локальный кэш — 5 минут.

Поля, которые app.js получает для каждой позиции (после адаптации в `_adaptMenu`):
- `id` — DB id (стабильный ключ для корзины)
- `fpArticle` — артикул FrontPad (для order.php)
- `category` — строковый DB id категории
- `name`, `desc`, `weight`, `price`, `img`
- `stop` — `true` если is_stop=1 (карточка показывает «Стоп», кнопка заблокирована)
- Варианты (`group_key` + `variant_label`) раскладываются в отдельные карточки с лейблом в имени

---

## Как добавить промокод

В `js/data.js` добавь строку в `PROMO_CODES`:

```js
'НОВЫЙКОД': { type: 'percent', value: 20, desc: 'Скидка 20%' },
// или
'СКИДКА200': { type: 'fixed',   value: 200, desc: 'Скидка 200 ₽' },
```

---

## Как подключить реальный бэкенд (Frontpad)

В `js/app.js` найди функцию `submitOrder(orderData)` и замени симуляцию:

```js
async function submitOrder(orderData) {
  const res = await fetch('/api/order', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(orderData),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return await res.json();
}
```

**ВАЖНО:** Secret key Frontpad нельзя хранить в браузере — только на сервере (Vercel Function, Node.js proxy и т.п.).

---

## Как подключить DaData (подсказки адресов)

1. Получи ключ на https://dadata.ru/
2. В `js/data.js` замени:
```js
dadataKey: 'ВСТАВЬ_КЛЮЧ_DADATA',
```

---

## Как обновить телефон и адрес самовывоза

В `js/data.js` в объекте `CONFIG`:

```js
const CONFIG = {
  phone:        '+7 (352) 222-22-22',    // ← сюда
  pickupAddress:'г. Курган, ул. ...',    // ← и сюда
  ...
};
```

---

## Как запустить локально

```bash
# Нужен HTTPS — Mini App не работает без SSL
# Вариант 1: ngrok
npx serve . && ngrok http 3000

# Вариант 2: Vercel (уже задеплоен)
# Просто открой https://sushiwithlove-ru.vercel.app/tg-app/

# Вариант 3: VS Code Live Server с сертификатом
```

Затем укажи URL в @BotFather → Edit Bot → Edit Menu Button → URL.

---

## Известные ограничения (v1.0)

- `tg.requestLocation()` возвращает координаты, но без reverse geocoding они не превращаются в адрес автоматически — нужен Яндекс Геокодер или DaData
- `tg.requestContact()` работает только если пользователь установил номер телефона в Telegram
- Отправка заказа — заглушка, нужен бэкенд-прокси для Frontpad
- Онлайн-оплата — не реализована в v1 (нужен Telegram Payments + эквайринг)

---

## Чеклист перед публикацией

- [ ] Обновить телефон и адрес в `CONFIG` (data.js)
- [ ] Заменить `submitOrder()` на реальный API
- [ ] Добавить ключ DaData (опционально)
- [ ] Проверить пути к фотографиям (все фото должны быть рядом с index.html или по корректному пути)
- [ ] Указать URL в @BotFather для меню бота
- [ ] Проверить на iOS + Android
- [ ] Убедиться что URL на HTTPS

---

*Создано 2026-04-13 на основе brief.md § 13 и research.md § 8*

/**
 * data.js — Конфиг доставки, промокоды, загрузка меню из БД
 * Sushi with Love — Telegram Mini App
 *
 * МЕНЮ БЕРЁТСЯ ИЗ ТОЙ ЖЕ БД, ЧТО И САЙТ (/api/menu/public.php).
 * Править меню (цены, фото, состав, стопы) — только через админку.
 * Изменилось на сайте — автоматически изменилось и в Mini App.
 *
 * Здесь остаются СТАТИЧНЫЕ настройки: контакты, зоны доставки, промокоды.
 */

// ─── Настройки доставки ───────────────────────────────────────────────────────
const CONFIG = {
  shopName:     'Суши с Любовью',
  phone:        '+7 (352) 266-20-70',
  phone2:       '+7 (922) 578-20-70',
  pickupAddress:'г. Курган, ул. Гоголя, 7',
  pickupTime:   '~30 минут',
  workHours:    '10:00–22:00',
  freeDeliveryFrom: 900,
  minOrderDelivery: 600,
  minOrderPickup:   300,
  defaultDeliveryTime: 45,
  frontpadPoint: 746,
  dadataKey: 'ВСТАВЬ_КЛЮЧ_DADATA',

  // Путь до API меню на сервере (относительно корня сайта)
  menuApiUrl:   '/api/menu/public.php',
  // Кэш меню в localStorage (тот же ключ что и на сайте — чтобы не тянуть дважды)
  menuCacheKey: 'swl_menu_v3',
  menuCacheTtl: 5 * 60 * 1000, // 5 минут
};

// ─── Зоны доставки ────────────────────────────────────────────────────────────
const DELIVERY_ZONES = [
  { name: 'Центр',         cost: 0,   time: '30–40 мин', minOrder: 600  },
  { name: 'Заречный',      cost: 100, time: '50–60 мин', minOrder: 800  },
  { name: 'Рябково',       cost: 100, time: '50–60 мин', minOrder: 800  },
  { name: 'За кольцевой',  cost: 150, time: '60–75 мин', minOrder: 1000 },
];
const DEFAULT_DELIVERY = { cost: 100, time: '45–60 мин', minOrder: 600 };

// ─── Промокоды ────────────────────────────────────────────────────────────────
const PROMO_CODES = {
  'СВ10':  { type: 'percent', value: 10, desc: 'Скидка 10% при самовывозе' },
  '1LOVE': { type: 'percent', value: 10, desc: 'Скидка на первый заказ — проверяется оператором' },
  'BDAY1': { type: 'percent', value: 10, desc: 'Скидка в день рождения (до)' },
  'BDAY2': { type: 'percent', value: 10, desc: 'Скидка в день рождения (после)' },
};

// ─── Динамическое меню (заполняется из API) ───────────────────────────────────
// До вызова loadMenuFromApi() оба массива пусты.
let CATEGORIES = [];
let MENU_ITEMS = [];

// Эмодзи для категорий — подбирается по slug/имени. Для неизвестных — 🍽️.
function _categoryEmoji(cat) {
  const key = ((cat.slug || '') + ' ' + (cat.name || '')).toLowerCase();
  if (/хит/.test(key))                           return '🔥';
  if (/сет/.test(key))                           return '🍱';
  if (/ролл|маки|филадельф/.test(key))           return '🍣';
  if (/горяч|запеч|темпур/.test(key))            return '♨️';
  if (/пицц/.test(key))                          return '🍕';
  if (/суши|нигир|гункан/.test(key))             return '🍥';
  if (/напит|кол|сок|лимон|вод|чай/.test(key))   return '🥤';
  if (/десерт|моти|торт/.test(key))              return '🍰';
  if (/соус|добавк|допо|имбир|васаб/.test(key))  return '🥢';
  return '🍽️';
}

// Нормализует image_url из БД: относительный путь → абсолютный от корня сайта
function _normalizeImage(url) {
  if (!url) return null;
  const s = String(url).trim();
  if (!s) return null;
  if (/^(https?:)?\/\//i.test(s)) return s;   // https://… или //…
  if (s.startsWith('/')) return s;            // /photos/…
  return '/' + s;                             // photos/… → /photos/…
}

// Формат веса: "280 г" или пусто
function _formatWeight(grams) {
  const n = parseInt(grams);
  return n > 0 ? n + ' г' : '';
}

/**
 * Преобразует ответ /api/menu/public.php в формат, ожидаемый app.js.
 *
 * Ключевые решения:
 *  • category (строка) = строковое представление DB-id категории (для совместимости)
 *  • id товара       = menu_items.id из БД (стабильный ключ для корзины)
 *  • fpArticle       = fp_article_id (артикул FrontPad для order.php)
 *  • Товары с group_key (варианты) рендерятся как отдельные карточки — в имя
 *    дописывается variant_label, чтобы пользователь видел вариант в карточке/корзине.
 *  • is_stop=1 превращается в поле stop=true — app.js блокирует кнопку
 */
function _adaptMenu(apiData) {
  const catsRaw = (apiData.categories || []).slice()
    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
  const itemsRaw = (apiData.items || []).slice()
    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

  const cats = catsRaw.map(c => ({
    id:   String(c.id),
    name: _categoryEmoji(c) + ' ' + c.name,
    _raw: c,
  }));

  // Оставляем только категории, в которых реально есть активные позиции
  const catIdsWithItems = new Set(itemsRaw.map(i => String(i.category_id)));
  const catsFiltered = cats.filter(c => catIdsWithItems.has(c.id));

  const items = itemsRaw.map(it => {
    const price = parseFloat(it.price) || 0;
    const stop  = parseInt(it.is_stop) === 1;

    // Имя: если это вариант в группе — добавляем лейбл варианта
    let name = it.name || '';
    if (it.group_key && it.variant_label) {
      // Не дублируем, если лейбл уже есть в имени
      if (!name.toLowerCase().includes(String(it.variant_label).toLowerCase())) {
        name = name + ' · ' + it.variant_label;
      }
    }

    return {
      id:        it.id,                              // DB id — стабильный
      fpArticle: parseInt(it.fp_article_id) || 0,
      category:  String(it.category_id),
      name,
      desc:      it.description || '',
      weight:    _formatWeight(it.weight_grams) || (it.variant_label || ''),
      price,
      badge:     null,                               // в БД бейджа нет
      img:       _normalizeImage(it.image_url),
      stop,
      upsell:    [],                                 // заполним ниже
    };
  });

  // Авто-upsell: первые 3 доступных товара из категорий «Добавки/Напитки/Соусы»
  const upsellPool = items
    .filter(x => !x.stop && x.fpArticle)
    .filter(x => {
      const cat = catsRaw.find(c => String(c.id) === x.category);
      const key = ((cat?.slug || '') + ' ' + (cat?.name || '')).toLowerCase();
      return /напит|соус|добавк|допо/.test(key);
    })
    .slice(0, 3)
    .map(x => x.id);

  items.forEach(x => {
    if (!x.stop) x.upsell = upsellPool.filter(id => id !== x.id).slice(0, 3);
  });

  return { categories: catsFiltered, items };
}

/**
 * Загрузка меню из того же API, что использует сайт.
 * Сначала мгновенно даёт закэшированную версию (если свежая), потом обновляет.
 *
 * @param {Function} onUpdate — вызывается с {fromCache: bool}, когда данные готовы
 * @returns {Promise<void>} — резолвится после первого рендера (из кэша или сети)
 */
async function loadMenuFromApi(onUpdate) {
  let renderedFromCache = false;

  // 1) Попытка из кэша — мгновенный рендер
  try {
    const cached = JSON.parse(localStorage.getItem(CONFIG.menuCacheKey) || 'null');
    if (cached && cached.ts && (Date.now() - cached.ts) < CONFIG.menuCacheTtl
        && cached.data && cached.data.ok) {
      const adapted = _adaptMenu(cached.data);
      CATEGORIES = adapted.categories;
      MENU_ITEMS = adapted.items;
      renderedFromCache = true;
      if (onUpdate) onUpdate({ fromCache: true });
    }
  } catch (e) {}

  // 2) Фоновое обновление из сети
  const fetchPromise = fetch(CONFIG.menuApiUrl)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) throw new Error('Menu API error');
      try { localStorage.setItem(CONFIG.menuCacheKey, JSON.stringify({ ts: Date.now(), data })); } catch (e) {}
      const adapted = _adaptMenu(data);
      CATEGORIES = adapted.categories;
      MENU_ITEMS = adapted.items;
      if (onUpdate) onUpdate({ fromCache: false });
    })
    .catch(err => {
      console.error('[Mini App] Menu load error:', err);
      if (!renderedFromCache) throw err;  // если даже кэша не было — фейлим init
    });

  // Если кэш был — возвращаемся сразу, сеть догонит в фоне
  if (renderedFromCache) return;
  return fetchPromise;
}

/**
 * data.js — Данные меню, настройки доставки, промокоды
 * Sushi with Love — Telegram Mini App
 *
 * ЧТОБЫ ОБНОВИТЬ МЕНЮ: добавь/измени объекты в MENU_ITEMS
 * ЧТОБЫ ОБНОВИТЬ ЦЕНЫ: меняй поле price у нужного товара
 * ЧТОБЫ ДОБАВИТЬ ПРОМОКОД: добавь в PROMO_CODES
 */

// ─── Настройки доставки ───────────────────────────────────────────────────────
const CONFIG = {
  shopName:     'Sushi with Love',
  phone:        '+7 (352) 222-22-22',       // ← ЗАМЕНИТЬ на реальный номер
  pickupAddress:'г. Курган, ул. Гоголя, 101', // ← ЗАМЕНИТЬ на реальный адрес
  pickupTime:   '~30 минут',
  workHours:    '10:00 – 22:00',
  freeDeliveryFrom: 900,   // ₽ — порог бесплатной доставки
  minOrderDelivery: 600,   // ₽ — минимальная сумма для доставки
  minOrderPickup:   300,   // ₽ — минимальная сумма для самовывоза
  defaultDeliveryTime: 45, // минут
  // DaData API (подсказки адресов) — вставь свой ключ:
  dadataKey: 'ВСТАВЬ_КЛЮЧ_DADATA',
};

// ─── Зоны доставки ────────────────────────────────────────────────────────────
const DELIVERY_ZONES = [
  { name: 'Центр',         cost: 0,   time: '30–40 мин', minOrder: 600  },
  { name: 'Заречный',      cost: 100, time: '50–60 мин', minOrder: 800  },
  { name: 'Рябково',       cost: 100, time: '50–60 мин', minOrder: 800  },
  { name: 'За кольцевой',  cost: 150, time: '60–75 мин', minOrder: 1000 },
];
// Если адрес не распознан — стандартная доставка:
const DEFAULT_DELIVERY = { cost: 100, time: '45–60 мин', minOrder: 600 };

// ─── Промокоды ────────────────────────────────────────────────────────────────
// type: 'percent' — скидка в процентах, 'fixed' — фиксированная сумма
const PROMO_CODES = {
  'СВ10':  { type: 'percent', value: 10, desc: 'Скидка 10%' },
  '1LOVE': { type: 'percent', value: 15, desc: 'Скидка 15% на первый заказ' },
  'BDAY':  { type: 'fixed',   value: 200, desc: 'Скидка 200 ₽ в день рождения' },
};

// ─── Категории ────────────────────────────────────────────────────────────────
const CATEGORIES = [
  { id: 'hits',    name: '🔥 Хиты'    },
  { id: 'sets',    name: '🍱 Сеты'    },
  { id: 'rolls',   name: '🍣 Роллы'   },
  { id: 'hot',     name: '♨️ Горячие' },
  { id: 'drinks',  name: '🥤 Напитки' },
  { id: 'extras',  name: '🥢 Добавки' },
];

// ─── Меню ─────────────────────────────────────────────────────────────────────
// badge: 'hit' | 'new' | 'sale' | null
// upsell: массив id товаров для блока "Часто берут вместе"
const MENU_ITEMS = [

  // ── Хиты ──────────────────────────────────────────────────────────────────
  {
    id: 1, category: 'hits',
    name: 'Сет ДОБРО',
    desc: 'Роллы с лососем, тунцом, авокадо, огурцом и сливочным сыром',
    weight: '640 г · 32 шт', price: 890,
    badge: 'hit',
    img: '../photos/sets/set-dobro.jpg',
    upsell: [101, 102, 201],
  },
  {
    id: 2, category: 'hits',
    name: '2 сета по цене 1',
    desc: 'Бархатный + Темпурный — выгоднее вдвоём',
    weight: '800 г · 40 шт', price: 1290,
    badge: 'sale',
    img: '../photos/sets/set-2po1.jpg',
    upsell: [201, 202, 101],
  },
  {
    id: 3, category: 'hits',
    name: 'Сет ВЫГОДНЫЙ',
    desc: 'Классические роллы с лососем, крем-сыром и авокадо',
    weight: '560 г · 28 шт', price: 750,
    badge: 'hit',
    img: '../photos/sets/set-vygodnyy.jpg',
    upsell: [101, 201, 301],
  },
  {
    id: 4, category: 'hits',
    name: 'Сет ПЛЯЖНЫЙ',
    desc: 'Лёгкие роллы с огурцом, авокадо и рисом',
    weight: '520 г · 26 шт', price: 690,
    badge: null,
    img: '../photos/sets/set-plyazhnyy.jpg',
    upsell: [201, 202, 301],
  },
  {
    id: 5, category: 'hits',
    name: 'Сет КОМБО',
    desc: 'Микс из роллов и суши — лосось, угорь, тунец, авокадо',
    weight: '680 г · 34 шт', price: 990,
    badge: 'hit',
    img: '../photos/sets/set-kombo.jpg',
    upsell: [101, 102, 201],
  },
  {
    id: 6, category: 'hits',
    name: 'Сет ВОСТОРГ',
    desc: 'Праздничный набор с лососем, икрой тобико и крем-сыром',
    weight: '720 г · 36 шт', price: 1190,
    badge: null,
    img: '../photos/sets/set-vostorg.jpg',
    upsell: [202, 101, 302],
  },
  {
    id: 7, category: 'hits',
    name: 'Сет ЗАПЕЧЁННЫЙ',
    desc: 'Запечённые роллы с лососем, майонезом и сыром',
    weight: '600 г · 30 шт', price: 890,
    badge: null,
    img: '../photos/sets/set-zapechennyy.jpg',
    upsell: [101, 201, 301],
  },
  {
    id: 8, category: 'hits',
    name: 'Сет ТЕМПУРНЫЙ',
    desc: 'Роллы в хрустящем темпурном кляре с лососем и огурцом',
    weight: '580 г · 29 шт', price: 790,
    badge: null,
    img: '../photos/sets/set-tempurnyy.jpg',
    upsell: [101, 102, 301],
  },

  // ── Сеты ──────────────────────────────────────────────────────────────────
  {
    id: 11, category: 'sets',
    name: 'Сет ПОПУЛЯРНЫЙ',
    desc: 'Роллы с лососем и огурцом — хит у постоянных гостей',
    weight: '480 г · 24 шт', price: 590,
    badge: null,
    img: '../photos/sets/set-populyarnyy.jpg',
    upsell: [201, 101, 301],
  },
  {
    id: 12, category: 'sets',
    name: 'Сет АМУР',
    desc: 'Романтический набор: лосось, авокадо, мягкий сыр',
    weight: '520 г · 26 шт', price: 690,
    badge: null,
    img: '../photos/sets/set-amur.jpg',
    upsell: [202, 101, 302],
  },
  {
    id: 13, category: 'sets',
    name: 'Сет БОЛЬШАЯ КОМПАНИЯ',
    desc: 'Большой набор для компании 4–6 человек: роллы, суши, гункан',
    weight: '1480 г · 74 шт', price: 1690,
    badge: null,
    img: '../photos/sets/set-bolshaya-kompaniya.jpg',
    upsell: [201, 202, 302],
  },
  {
    id: 14, category: 'sets',
    name: 'Сет КОРПОРАТИВНЫЙ',
    desc: 'Офисный заказ на 6–8 человек: ассорти роллов и суши',
    weight: '1960 г · 98 шт', price: 1990,
    badge: null,
    img: '../photos/sets/set-korporativnyy.jpg',
    upsell: [201, 202, 302],
  },
  {
    id: 15, category: 'sets',
    name: 'Сет ПО КАЙФУ',
    desc: 'Вечерний набор: лосось, угорь, авокадо, огурец',
    weight: '640 г · 32 шт', price: 890,
    badge: null,
    img: '../photos/sets/set-po-kayfu.jpg',
    upsell: [201, 301, 101],
  },
  {
    id: 16, category: 'sets',
    name: 'Сет ТОПЧИК',
    desc: 'Новинка: необычные сочетания вкусов для гурманов',
    weight: '600 г · 30 шт', price: 990,
    badge: 'new',
    img: '../photos/sets/set-topchik.jpg',
    upsell: [202, 302, 101],
  },

  // ── Роллы ─────────────────────────────────────────────────────────────────
  {
    id: 21, category: 'rolls',
    name: 'Филадельфия классическая',
    desc: 'Лосось, огурец, сливочный сыр, рис, нори',
    weight: '280 г · 8 шт', price: 490,
    badge: 'hit',
    img: '../photos/rolls/roll-filadelfiya.jpg',
    upsell: [101, 201, 301],
  },
  {
    id: 22, category: 'rolls',
    name: 'Лайт Филадельфия',
    desc: 'Лёгкая версия: лосось, огурец, сыр — без нори снаружи',
    weight: '260 г · 8 шт', price: 390,
    badge: null,
    img: '../photos/rolls/roll-layt-filadelfiya.jpg',
    upsell: [101, 201, 301],
  },
  {
    id: 23, category: 'rolls',
    name: 'Чикен Каппа',
    desc: 'Курица, огурец, сливочный сыр, соус терияки',
    weight: '260 г · 8 шт', price: 350,
    badge: null,
    img: null, // фото пока нет — будет отображён градиент
    upsell: [101, 201, 301],
  },

  // ── Горячие ───────────────────────────────────────────────────────────────
  {
    id: 31, category: 'hot',
    name: 'Зап. с лососем',
    desc: 'Запечённый ролл с лососем, майонезом и сыром',
    weight: '280 г · 8 шт', price: 490,
    badge: 'hit',
    img: '../photos/rolls/roll-zap-losos.jpg',
    upsell: [101, 102, 201],
  },
  {
    id: 32, category: 'hot',
    name: 'Зап. ЭБИ Томаго',
    desc: 'Запечённый ролл с тигровой креветкой, икрой тобико',
    weight: '280 г · 8 шт', price: 450,
    badge: null,
    img: '../photos/rolls/roll-ebi-tomago.jpg',
    upsell: [101, 201, 302],
  },
  {
    id: 33, category: 'hot',
    name: 'Гор. с креветками',
    desc: 'Горячий ролл с тигровыми креветками и сливочным соусом',
    weight: '300 г · 8 шт', price: 470,
    badge: null,
    img: '../photos/rolls/roll-gor-krevetki.jpg',
    upsell: [101, 201, 302],
  },
  {
    id: 34, category: 'hot',
    name: 'Зап. ЭТНА',
    desc: 'Острый запечённый ролл с лососем и соусом спайси',
    weight: '280 г · 8 шт', price: 480,
    badge: 'new',
    img: '../photos/rolls/roll-etna.jpg',
    upsell: [101, 102, 201],
  },

  // ── Напитки ───────────────────────────────────────────────────────────────
  {
    id: 201, category: 'drinks',
    name: 'Кола 0.5 л',
    desc: 'Coca-Cola, охлаждённая',
    weight: '500 мл', price: 99,
    badge: null,
    img: '../photos/extras/kola.jpg',
    upsell: [],
  },
  {
    id: 202, category: 'drinks',
    name: 'Сок 1 л',
    desc: 'Добрый — яблоко, мультифрукт или апельсин-манго',
    weight: '1000 мл', price: 199,
    badge: null,
    img: '../photos/extras/sok.jpg',
    upsell: [],
  },
  {
    id: 203, category: 'drinks',
    name: 'Лимонад AquAlania',
    desc: 'Слива, груша, смородина или барбарис — на выбор',
    weight: '500 мл', price: 199,
    badge: 'new',
    img: '../photos/extras/lemonad.jpg',
    upsell: [],
  },

  // ── Добавки ───────────────────────────────────────────────────────────────
  {
    id: 101, category: 'extras',
    name: 'Соевый соус',
    desc: 'Классический соевый соус от шефа, 100 мл',
    weight: '100 мл', price: 60,
    badge: null,
    img: '../photos/extras/soevyy.jpg',
    upsell: [],
  },
  {
    id: 102, category: 'extras',
    name: 'Имбирь',
    desc: 'Маринованный имбирь',
    weight: '30 г', price: 50,
    badge: null,
    img: '../photos/extras/imbir.jpg',
    upsell: [],
  },
  {
    id: 103, category: 'extras',
    name: 'Васаби',
    desc: 'Паста васаби',
    weight: '15 г', price: 50,
    badge: null,
    img: '../photos/extras/vasabi.jpg',
    upsell: [],
  },
  {
    id: 301, category: 'extras',
    name: 'Картофель фри',
    desc: 'Хрустящий картофель фри, порция',
    weight: '150 г', price: 150,
    badge: null,
    img: '../photos/extras/fri.jpg',
    upsell: [201],
  },
  {
    id: 302, category: 'extras',
    name: 'Комбо фри + наггетсы',
    desc: 'Картофель фри и куриные наггетсы',
    weight: '300 г', price: 250,
    badge: null,
    img: '../photos/extras/kombo-fri.jpg',
    upsell: [201],
  },
  {
    id: 303, category: 'extras',
    name: 'Тигровые креветки',
    desc: 'Тигровые креветки в соусе, 5 шт',
    weight: '150 г', price: 350,
    badge: null,
    img: '../photos/extras/krevetki.jpg',
    upsell: [101, 201],
  },
  {
    id: 304, category: 'extras',
    name: 'Моти ассорти',
    desc: 'Японские рисовые пирожные — клубника, манго, шоколад',
    weight: '150 г · 3 шт', price: 150,
    badge: null,
    img: '../photos/extras/moti.jpg',
    upsell: [202],
  },
];

// ─── Экспорт ──────────────────────────────────────────────────────────────────
// Используется в app.js и cart.js

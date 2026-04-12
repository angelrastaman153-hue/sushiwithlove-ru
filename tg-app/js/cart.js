/**
 * cart.js — Управление корзиной
 * Sushi with Love — Telegram Mini App
 *
 * Хранилище: tg.CloudStorage (между устройствами) с fallback на localStorage
 * Ключ хранилища: 'swl_cart'
 */

const Cart = (() => {
  // ── Состояние ──────────────────────────────────────────────────────────────
  let state = {
    items: {},        // { [itemId]: quantity }
    promoCode: null,  // строка или null
    promoDiscount: 0, // сумма скидки в рублях
  };

  // ── Хранилище (CloudStorage + localStorage fallback) ──────────────────────
  const STORAGE_KEY = 'swl_cart';

  async function save() {
    const data = JSON.stringify(state);
    try {
      if (window.Telegram?.WebApp?.CloudStorage) {
        await new Promise((resolve, reject) => {
          window.Telegram.WebApp.CloudStorage.setItem(STORAGE_KEY, data, (err) => {
            if (err) reject(err); else resolve();
          });
        });
      }
    } catch (e) { /* тихо игнорируем */ }
    // Всегда дублируем в localStorage как запасной вариант
    try { localStorage.setItem(STORAGE_KEY, data); } catch (e) {}
  }

  async function load() {
    // Сначала пробуем CloudStorage
    try {
      if (window.Telegram?.WebApp?.CloudStorage) {
        const data = await new Promise((resolve) => {
          window.Telegram.WebApp.CloudStorage.getItem(STORAGE_KEY, (err, val) => {
            resolve(val || null);
          });
        });
        if (data) {
          state = JSON.parse(data);
          return;
        }
      }
    } catch (e) {}
    // Fallback: localStorage
    try {
      const data = localStorage.getItem(STORAGE_KEY);
      if (data) state = JSON.parse(data);
    } catch (e) {}
  }

  // ── Методы корзины ─────────────────────────────────────────────────────────

  /** Добавить товар (или увеличить количество) */
  function add(itemId) {
    const id = String(itemId);
    state.items[id] = (state.items[id] || 0) + 1;
    save();
  }

  /** Убрать одну единицу товара (или удалить если была 1) */
  function remove(itemId) {
    const id = String(itemId);
    if (!state.items[id]) return;
    state.items[id]--;
    if (state.items[id] <= 0) delete state.items[id];
    save();
  }

  /** Установить точное количество (0 = удалить) */
  function setQty(itemId, qty) {
    const id = String(itemId);
    if (qty <= 0) {
      delete state.items[id];
    } else {
      state.items[id] = qty;
    }
    save();
  }

  /** Количество конкретного товара */
  function qty(itemId) {
    return state.items[String(itemId)] || 0;
  }

  /** Общее количество позиций в корзине */
  function totalCount() {
    return Object.values(state.items).reduce((s, q) => s + q, 0);
  }

  /** Очистить корзину */
  function clear() {
    state = { items: {}, promoCode: null, promoDiscount: 0 };
    save();
  }

  /** Список товаров корзины: [{ item, qty }] */
  function getItems() {
    return Object.entries(state.items)
      .filter(([, q]) => q > 0)
      .map(([id, q]) => ({
        item: MENU_ITEMS.find(i => String(i.id) === id),
        qty: q,
      }))
      .filter(({ item }) => Boolean(item));
  }

  /**
   * Подсчёт итогов
   * @param {number} deliveryCost — стоимость доставки (из зоны или DEFAULT_DELIVERY.cost)
   * @returns {{ subtotal, deliveryCost, discount, total, isFreeDelivery, toFreeDelivery }}
   */
  function getTotals(deliveryCost) {
    const subtotal = getItems().reduce((s, { item, qty }) => s + item.price * qty, 0);

    // Бесплатная доставка если сумма >= порога
    const isFreeDelivery = subtotal >= CONFIG.freeDeliveryFrom;
    const actualDelivery = isFreeDelivery ? 0 : (deliveryCost ?? DEFAULT_DELIVERY.cost);
    const toFreeDelivery = isFreeDelivery ? 0 : Math.max(0, CONFIG.freeDeliveryFrom - subtotal);

    // Скидка промокода — только на товары, не на доставку
    const discount = state.promoDiscount || 0;
    const total = Math.max(0, subtotal + actualDelivery - discount);

    return { subtotal, deliveryCost: actualDelivery, discount, total, isFreeDelivery, toFreeDelivery };
  }

  /** Применить промокод. Возвращает { ok, message } */
  function applyPromo(code) {
    const normalized = (code || '').trim().toUpperCase();
    const promo = PROMO_CODES[normalized];
    if (!promo) {
      return { ok: false, message: 'Промокод не найден' };
    }
    state.promoCode = normalized;
    const items = getItems();
    const subtotal = items.reduce((s, { item, qty }) => s + item.price * qty, 0);
    if (promo.type === 'percent') {
      state.promoDiscount = Math.round(subtotal * promo.value / 100);
    } else {
      state.promoDiscount = Math.min(promo.value, subtotal);
    }
    save();
    return { ok: true, message: promo.desc };
  }

  /** Снять промокод */
  function removePromo() {
    state.promoCode = null;
    state.promoDiscount = 0;
    save();
  }

  /** Текущий промокод */
  function getPromo() {
    return state.promoCode;
  }

  // Публичный API
  return { load, save, add, remove, setQty, qty, totalCount, clear, getItems, getTotals, applyPromo, removePromo, getPromo };
})();

/**
 * app.js — Главный модуль приложения
 * Sushi with Love — Telegram Mini App
 *
 * Отвечает за:
 *  - инициализацию Telegram WebApp
 *  - навигацию между экранами
 *  - рендер меню, карточек, корзины
 *  - обработку форм и отправку заказа
 */

// ─── Telegram WebApp ──────────────────────────────────────────────────────────
const tg = window.Telegram?.WebApp;

// ─── Состояние приложения ─────────────────────────────────────────────────────
const App = {
  currentScreen: 'loading',
  screenHistory: [],         // стек для кнопки Назад
  deliveryCost: DEFAULT_DELIVERY.cost,
  deliveryType: 'delivery',  // 'delivery' | 'pickup'
  orderAddress: '',
  orderZone: null,
  scrollspyObserver: null,   // IntersectionObserver для категорий
};

// ─── Обёртки для кнопок Telegram (SDK onClick additive — нужен offClick) ──────
const _btnHandlers = { main: null, back: null };

function setMainBtnHandler(fn) {
  if (!tg) return;
  if (_btnHandlers.main) tg.MainButton.offClick(_btnHandlers.main);
  _btnHandlers.main = fn || null;
  if (fn) tg.MainButton.onClick(fn);
}

function setBackBtnHandler(fn) {
  if (!tg) return;
  if (_btnHandlers.back) tg.BackButton.offClick(_btnHandlers.back);
  _btnHandlers.back = fn || null;
  if (fn) tg.BackButton.onClick(fn);
}

// ─── Инициализация ────────────────────────────────────────────────────────────
async function init() {
  if (tg) {
    tg.ready();
    tg.expand();
    try { tg.disableVerticalSwipes(); } catch(e) {}
    // Применяем цвета темы Telegram
    applyTelegramTheme();
    tg.onEvent('themeChanged', applyTelegramTheme);
  }

  // Параллельно: загружаем корзину и меню из API (как на сайте)
  const menuReady = loadMenuFromApi(({ fromCache }) => {
    // Вызывается дважды: сразу из кэша и после свежего ответа сети.
    // Если пользователь уже на экране меню — просто перерисовываем.
    renderCategories();
    renderMenu();
    // Пересчитываем бейдж/кнопку — состав корзины мог содержать товары,
    // которых больше нет в актуальном меню.
    if (App.currentScreen !== 'loading') {
      updateCartBadge();
      updateMainButton();
    }
  });

  try {
    await Promise.all([ Cart.load(), menuReady ]);
  } catch (err) {
    console.error('[Mini App] Init failed:', err);
    showToast('Не удалось загрузить меню. Проверьте соединение.');
    return;
  }

  // На случай если onUpdate не вызвался (например, только сеть, без кэша)
  if (!document.querySelector('#menu-content .menu-section')) {
    renderCategories();
    renderMenu();
  }

  setTimeout(() => {
    navigateTo('menu');
    updateCartBadge();
    updateMainButton();
  }, 300);
}

function applyTelegramTheme() {
  if (!tg?.themeParams) return;
  const p = tg.themeParams;
  const r = document.documentElement;
  if (p.bg_color)            r.style.setProperty('--tg-bg',          p.bg_color);
  if (p.text_color)          r.style.setProperty('--tg-text',         p.text_color);
  if (p.hint_color)          r.style.setProperty('--tg-hint',         p.hint_color);
  if (p.link_color)          r.style.setProperty('--tg-link',         p.link_color);
  if (p.button_color)        r.style.setProperty('--tg-btn',          p.button_color);
  if (p.button_text_color)   r.style.setProperty('--tg-btn-text',     p.button_text_color);
  if (p.secondary_bg_color)  r.style.setProperty('--tg-secondary-bg', p.secondary_bg_color);
  document.body.dataset.theme = tg.colorScheme || 'light';
}

// ─── Навигация ────────────────────────────────────────────────────────────────
function navigateTo(screenId, pushHistory = true) {
  const current = document.querySelector('.screen.active');
  const next    = document.getElementById('screen-' + screenId);
  if (!next || current === next) return;

  if (pushHistory && App.currentScreen !== 'loading') {
    App.screenHistory.push(App.currentScreen);
  }

  // Анимация: уходящий экран — влево, приходящий — справа
  if (current) {
    current.classList.add('slide-out');
    setTimeout(() => current.classList.remove('active', 'slide-out'), 280);
  }
  next.classList.add('active', 'slide-in');
  setTimeout(() => next.classList.remove('slide-in'), 280);

  App.currentScreen = screenId;

  // BackButton
  if (tg) {
    if (App.screenHistory.length > 0 && screenId !== 'success') {
      tg.BackButton.show();
      setBackBtnHandler(navigateBack);
    } else {
      tg.BackButton.hide();
      setBackBtnHandler(null);
    }
  }

  updateMainButton();
}

function navigateBack() {
  const prev = App.screenHistory.pop();
  if (prev) {
    navigateTo(prev, false);
  }
}

// ─── MainButton ───────────────────────────────────────────────────────────────
function updateMainButton() {
  if (!tg) return;
  const count = Cart.totalCount();
  const { total } = Cart.getTotals(App.deliveryCost);

  if (App.currentScreen === 'menu') {
    if (count > 0) {
      tg.MainButton.setText(`🛒 Корзина · ${count} · ${formatPrice(total)}`);
      tg.MainButton.show();
      setMainBtnHandler(openCartSheet);
    } else {
      tg.MainButton.hide();
      setMainBtnHandler(null);
    }
  } else if (App.currentScreen === 'address') {
    tg.MainButton.setText('Далее →');
    tg.MainButton.show();
    setMainBtnHandler(handleAddressNext);
  } else if (App.currentScreen === 'details') {
    tg.MainButton.setText(`Оформить заказ · ${formatPrice(total)}`);
    tg.MainButton.show();
    setMainBtnHandler(handleSubmitOrder);
  } else if (App.currentScreen === 'success') {
    tg.MainButton.hide();
    setMainBtnHandler(null);
  }
}

// ─── Рендер категорий ─────────────────────────────────────────────────────────
function renderCategories() {
  const nav = document.getElementById('categories-nav');
  nav.innerHTML = CATEGORIES.map(cat =>
    `<button class="cat-tab" data-cat="${cat.id}" onclick="scrollToCategory('${cat.id}')">${cat.name}</button>`
  ).join('');
}

function scrollToCategory(catId) {
  const section = document.getElementById('section-' + catId);
  if (!section) return;
  const menuContent = document.querySelector('.menu-content');
  const tabsHeight = document.querySelector('.categories-nav').offsetHeight +
                     document.querySelector('.app-header').offsetHeight;
  const top = section.offsetTop - tabsHeight - 8;
  menuContent.scrollTo({ top, behavior: 'smooth' });
  setActiveCategory(catId);
}

function setActiveCategory(catId) {
  document.querySelectorAll('.cat-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.cat === catId);
  });
  // Автоскролл активного таба в видимость
  const activeTab = document.querySelector(`.cat-tab[data-cat="${catId}"]`);
  if (activeTab) {
    activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  }
}

// ─── Рендер меню ──────────────────────────────────────────────────────────────
function renderMenu() {
  const container = document.getElementById('menu-content');
  const html = CATEGORIES.map(cat => {
    const items = MENU_ITEMS.filter(i => i.category === cat.id);
    if (!items.length) return '';
    return `
      <section class="menu-section" id="section-${cat.id}" data-cat="${cat.id}">
        <h2 class="section-title">${cat.name}</h2>
        <div class="cards-grid">
          ${items.map(renderCard).join('')}
        </div>
      </section>`;
  }).join('');

  container.innerHTML = html;

  // Scrollspy через IntersectionObserver
  setupScrollspy();
}

function renderCard(item) {
  const q = Cart.qty(item.id);
  const stopBadge = item.stop
    ? `<span class="badge badge-stop" style="background:#e05a5a;color:#fff">Стоп</span>`
    : (item.badge ? `<span class="badge badge-${item.badge}">${badgeText(item.badge)}</span>` : '');
  const imgHtml = item.img
    ? `<img src="${item.img}" alt="${item.name}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
    : '';
  const placeholderHtml = `<div class="card-img-placeholder" style="${item.img ? 'display:none' : ''}">${categoryEmoji(item.category)}</div>`;

  const btnHtml = item.stop
    ? `<button class="card-add-btn" style="opacity:.4;cursor:not-allowed" disabled aria-label="Стоп">✕</button>`
    : (q > 0
      ? `<div class="card-counter">
           <button class="counter-btn minus" onclick="event.stopPropagation();decreaseItem(${item.id})">−</button>
           <span class="counter-qty">${q}</span>
           <button class="counter-btn plus" onclick="event.stopPropagation();increaseItem(${item.id})">+</button>
         </div>`
      : `<button class="card-add-btn" onclick="event.stopPropagation();increaseItem(${item.id})" aria-label="Добавить">+</button>`);

  return `
    <div class="card" data-id="${item.id}" onclick="openProductSheet(${item.id})">
      <div class="card-img">
        ${imgHtml}
        ${placeholderHtml}
        ${stopBadge}
      </div>
      <div class="card-body">
        <div class="card-name">${item.name}</div>
        <div class="card-weight">${item.weight}</div>
        <div class="card-footer">
          <span class="card-price">${formatPrice(item.price)}</span>
          ${btnHtml}
        </div>
      </div>
    </div>`;
}

function badgeText(badge) {
  const map = { hit: 'Хит', new: 'Новинка', sale: 'Акция' };
  return map[badge] || badge;
}

function categoryEmoji(cat) {
  const map = { hits: '🔥', sets: '🍱', rolls: '🍣', hot: '♨️', drinks: '🥤', extras: '🥢' };
  return map[cat] || '🍽️';
}

// ─── Scrollspy ────────────────────────────────────────────────────────────────
function setupScrollspy() {
  if (App.scrollspyObserver) App.scrollspyObserver.disconnect();
  const sections = document.querySelectorAll('.menu-section');
  App.scrollspyObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) setActiveCategory(entry.target.dataset.cat);
    });
  }, { root: document.querySelector('.menu-content'), rootMargin: '-40% 0px -50% 0px' });
  sections.forEach(s => App.scrollspyObserver.observe(s));
}

// ─── Корзина (добавление / удаление) ─────────────────────────────────────────
function increaseItem(id) {
  haptic('light');
  Cart.add(id);
  refreshCard(id);
  updateCartBadge();
  updateMainButton();
  // Анимация бейджа корзины
  const badge = document.getElementById('cart-badge');
  badge.classList.add('bounce');
  setTimeout(() => badge.classList.remove('bounce'), 300);
}

function decreaseItem(id) {
  haptic('light');
  Cart.remove(id);
  refreshCard(id);
  updateCartBadge();
  updateMainButton();
  // Если корзина открыта — обновляем её
  if (document.getElementById('sheet-cart').classList.contains('open')) {
    renderCartSheet();
  }
}

function refreshCard(id) {
  const card = document.querySelector(`.card[data-id="${id}"]`);
  if (!card) return;
  const item = MENU_ITEMS.find(i => i.id === id);
  if (!item) return;
  const q = Cart.qty(id);
  const footer = card.querySelector('.card-footer');
  const oldBtn = footer.querySelector('.card-add-btn, .card-counter');
  const newBtnHtml = item.stop
    ? `<button class="card-add-btn" style="opacity:.4;cursor:not-allowed" disabled aria-label="Стоп">✕</button>`
    : (q > 0
      ? `<div class="card-counter">
           <button class="counter-btn minus" onclick="event.stopPropagation();decreaseItem(${id})">−</button>
           <span class="counter-qty">${q}</span>
           <button class="counter-btn plus" onclick="event.stopPropagation();increaseItem(${id})">+</button>
         </div>`
      : `<button class="card-add-btn" onclick="event.stopPropagation();increaseItem(${id})" aria-label="Добавить">+</button>`);
  if (oldBtn) oldBtn.outerHTML = newBtnHtml;
}

function updateCartBadge() {
  const count = Cart.totalCount();
  const badge = document.getElementById('cart-badge');
  badge.textContent = count;
  badge.style.display = count > 0 ? 'flex' : 'none';
}

// ─── Product Bottom Sheet ─────────────────────────────────────────────────────
function openProductSheet(itemId) {
  const item = MENU_ITEMS.find(i => i.id === itemId);
  if (!item) return;
  haptic('light');

  const q = Cart.qty(item.id);
  const badgeHtml = item.badge ? `<span class="badge badge-${item.badge}">${badgeText(item.badge)}</span>` : '';
  const imgHtml = item.img
    ? `<img src="${item.img}" alt="${item.name}" class="sheet-product-img">`
    : `<div class="sheet-product-placeholder">${categoryEmoji(item.category)}</div>`;

  // Блок "часто берут вместе"
  const upsellItems = (item.upsell || [])
    .map(id => MENU_ITEMS.find(i => i.id === id))
    .filter(Boolean)
    .slice(0, 3);
  const upsellHtml = upsellItems.length ? `
    <div class="upsell-block">
      <div class="upsell-title">Часто берут вместе</div>
      <div class="upsell-list">
        ${upsellItems.map(u => `
          <div class="upsell-item" onclick="increaseItem(${u.id});haptic('light')">
            ${u.img ? `<img src="${u.img}" alt="${u.name}">` : `<div class="upsell-placeholder">${categoryEmoji(u.category)}</div>`}
            <div class="upsell-name">${u.name}</div>
            <div class="upsell-price">${formatPrice(u.price)}</div>
            <button class="upsell-add">+</button>
          </div>`).join('')}
      </div>
    </div>` : '';

  const btnHtml = item.stop
    ? `<button class="sheet-add-btn" style="opacity:.5;cursor:not-allowed" disabled>Нет в наличии</button>`
    : (q > 0
      ? `<div class="sheet-counter-row">
           <button class="counter-btn minus lg" onclick="decreaseItem(${item.id});updateSheetProductBtn(${item.id})">−</button>
           <span class="counter-qty lg" id="sheet-qty-${item.id}">${q}</span>
           <button class="counter-btn plus lg" onclick="increaseItem(${item.id});updateSheetProductBtn(${item.id})">+</button>
         </div>`
      : `<button class="sheet-add-btn" id="sheet-add-${item.id}" onclick="increaseItem(${item.id});updateSheetProductBtn(${item.id})">
           В корзину · ${formatPrice(item.price)}
         </button>`);

  document.getElementById('sheet-product-content').innerHTML = `
    <div class="sheet-img-wrap">
      ${imgHtml}
      ${badgeHtml}
    </div>
    <div class="sheet-product-body">
      <div class="sheet-product-name">${item.name}</div>
      <div class="sheet-product-desc">${item.desc}</div>
      <div class="sheet-product-meta">${item.weight}${item.kcal ? ' · ' + item.kcal + ' ккал' : ''}</div>
      <div class="sheet-product-price">${formatPrice(item.price)}</div>
      <div id="sheet-btn-wrap-${item.id}">${btnHtml}</div>
      ${upsellHtml}
    </div>`;

  openSheet('sheet-product');

  if (tg) {
    tg.BackButton.show();
    setBackBtnHandler(() => closeSheet('sheet-product'));
  }
}

function updateSheetProductBtn(id) {
  const item = MENU_ITEMS.find(i => i.id === id);
  if (!item) return;
  const q = Cart.qty(id);
  const wrap = document.getElementById('sheet-btn-wrap-' + id);
  if (!wrap) return;
  if (q > 0) {
    wrap.innerHTML = `
      <div class="sheet-counter-row">
        <button class="counter-btn minus lg" onclick="decreaseItem(${id});updateSheetProductBtn(${id})">−</button>
        <span class="counter-qty lg" id="sheet-qty-${id}">${q}</span>
        <button class="counter-btn plus lg" onclick="increaseItem(${id});updateSheetProductBtn(${id})">+</button>
      </div>`;
  } else {
    wrap.innerHTML = `
      <button class="sheet-add-btn" id="sheet-add-${id}" onclick="increaseItem(${id});updateSheetProductBtn(${id})">
        В корзину · ${formatPrice(item.price)}
      </button>`;
  }
}

// ─── Cart Bottom Sheet ────────────────────────────────────────────────────────
function openCartSheet() {
  if (Cart.totalCount() === 0) return;
  haptic('light');
  renderCartSheet();
  openSheet('sheet-cart');
  if (tg) {
    tg.MainButton.setText(`Оформить заказ · ${formatPrice(Cart.getTotals(App.deliveryCost).total)}`);
    setMainBtnHandler(handleCheckoutFromCart);
    tg.BackButton.show();
    setBackBtnHandler(() => closeSheet('sheet-cart'));
  }
}

function renderCartSheet() {
  const items = Cart.getItems();
  if (!items.length) {
    closeSheet('sheet-cart');
    updateMainButton();
    return;
  }
  const totals = Cart.getTotals(App.deliveryCost);

  // Прогресс-бар до бесплатной доставки
  const progressHtml = !totals.isFreeDelivery ? `
    <div class="free-delivery-bar">
      <div class="fdb-track"><div class="fdb-fill" style="width:${Math.min(100, totals.subtotal / CONFIG.freeDeliveryFrom * 100)}%"></div></div>
      <div class="fdb-text">До бесплатной доставки: <strong>${formatPrice(totals.toFreeDelivery)}</strong></div>
    </div>` : `
    <div class="free-delivery-bar achieved">
      <div class="fdb-text">🎉 Доставка бесплатная!</div>
    </div>`;

  // Список товаров
  const itemsHtml = items.map(({ item, qty }) => `
    <div class="cart-row" data-id="${item.id}">
      <div class="cart-img-wrap">
        ${item.img ? `<img src="${item.img}" alt="${item.name}" class="cart-img">` : `<div class="cart-img-placeholder">${categoryEmoji(item.category)}</div>`}
      </div>
      <div class="cart-info">
        <div class="cart-name">${item.name}</div>
        <div class="cart-unit-price">${formatPrice(item.price)} / шт</div>
      </div>
      <div class="cart-controls">
        <button class="counter-btn minus sm" onclick="decreaseItem(${item.id})">−</button>
        <span class="counter-qty sm">${qty}</span>
        <button class="counter-btn plus sm" onclick="increaseItem(${item.id})">+</button>
      </div>
      <div class="cart-total">${formatPrice(item.price * qty)}</div>
    </div>`).join('');

  // Промокод
  const promoCode = Cart.getPromo();
  const promoHtml = `
    <div class="promo-block">
      <div class="promo-input-row">
        <input type="text" id="promo-input" class="promo-input" placeholder="Промокод" value="${promoCode || ''}" autocomplete="off" autocorrect="off" autocapitalize="characters">
        <button class="promo-apply-btn" onclick="applyPromoCode()">Применить</button>
      </div>
      <div id="promo-message" class="promo-message ${promoCode ? 'success' : ''}">
        ${promoCode ? `✓ ${PROMO_CODES[promoCode]?.desc || 'Скидка применена'}` : ''}
      </div>
    </div>`;

  // Итог
  const summaryHtml = `
    <div class="cart-summary">
      <div class="summary-row"><span>Товары</span><span>${formatPrice(totals.subtotal)}</span></div>
      <div class="summary-row"><span>Доставка</span><span>${totals.deliveryCost === 0 ? 'Бесплатно' : formatPrice(totals.deliveryCost)}</span></div>
      ${totals.discount > 0 ? `<div class="summary-row discount"><span>Скидка (${promoCode})</span><span>−${formatPrice(totals.discount)}</span></div>` : ''}
      <div class="summary-row total"><span>Итого</span><span>${formatPrice(totals.total)}</span></div>
    </div>
    <button class="checkout-btn" onclick="handleCheckoutFromCart()">
      Оформить заказ · ${formatPrice(totals.total)}
    </button>`;

  document.getElementById('sheet-cart-content').innerHTML =
    progressHtml + itemsHtml + promoHtml + summaryHtml;
}

function applyPromoCode() {
  const input = document.getElementById('promo-input');
  const msg = document.getElementById('promo-message');
  const result = Cart.applyPromo(input.value);
  msg.textContent = (result.ok ? '✓ ' : '✗ ') + result.message;
  msg.className = 'promo-message ' + (result.ok ? 'success' : 'error');
  haptic(result.ok ? 'success' : 'error');
  if (result.ok) renderCartSheet(); // пересчитать итоги
}

function handleCheckoutFromCart() {
  closeSheet('sheet-cart');
  haptic('light');
  navigateTo('address');
}

// ─── Экран: адрес ─────────────────────────────────────────────────────────────
function initAddressScreen() {
  // Предзаполнить если адрес уже был
  if (App.orderAddress) {
    document.getElementById('addr-street').value = App.orderAddress;
  }
  updateDeliveryType(App.deliveryType);
}

function updateDeliveryType(type) {
  App.deliveryType = type;
  const deliveryBlock = document.getElementById('delivery-block');
  const pickupBlock = document.getElementById('pickup-block');
  document.querySelectorAll('.seg-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.type === type);
  });
  deliveryBlock.style.display = type === 'delivery' ? 'block' : 'none';
  pickupBlock.style.display   = type === 'pickup'   ? 'block' : 'none';
}

function handleAddressNext() {
  if (App.deliveryType === 'pickup') {
    navigateTo('details');
    return;
  }
  const street = document.getElementById('addr-street').value.trim();
  const house  = document.getElementById('addr-house').value.trim();
  if (!street || !house) {
    showFieldError('addr-street', 'Введите улицу и дом');
    haptic('error');
    return;
  }
  App.orderAddress = street + ', ' + house;
  const apt = document.getElementById('addr-apt').value.trim();
  if (apt) App.orderAddress += ', кв. ' + apt;
  navigateTo('details');
}

function showFieldError(fieldId, msg) {
  const field = document.getElementById(fieldId);
  field.classList.add('error');
  field.placeholder = msg;
  setTimeout(() => { field.classList.remove('error'); field.placeholder = field.dataset.placeholder || ''; }, 2000);
}

// DaData подсказки адресов
let dadataTimeout;
function handleStreetInput(e) {
  clearTimeout(dadataTimeout);
  const val = e.target.value.trim();
  if (val.length < 3) { closeDadataSuggestions(); return; }
  dadataTimeout = setTimeout(() => fetchDaData(val), 300);
}

async function fetchDaData(query) {
  if (!CONFIG.dadataKey || CONFIG.dadataKey === 'ВСТАВЬ_КЛЮЧ_DADATA') return;
  try {
    const res = await fetch('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Token ' + CONFIG.dadataKey },
      body: JSON.stringify({ query: 'Курган, ' + query, count: 5, locations: [{ city: 'Курган' }] }),
    });
    const data = await res.json();
    showDadataSuggestions(data.suggestions || []);
  } catch (e) {}
}

function showDadataSuggestions(suggestions) {
  let list = document.getElementById('dadata-list');
  if (!list) {
    list = document.createElement('div');
    list.id = 'dadata-list';
    list.className = 'dadata-suggestions';
    document.getElementById('addr-street').parentNode.appendChild(list);
  }
  list.innerHTML = suggestions.map(s =>
    `<div class="dadata-item" onclick="selectDadataItem('${s.value.replace(/'/g,"\\'")}','${(s.data.house||'').replace(/'/g,"\\'")}')">
       ${s.value}
     </div>`
  ).join('');
  list.style.display = suggestions.length ? 'block' : 'none';
}

function selectDadataItem(value, house) {
  // Разбираем на улицу и дом
  const streetPart = value.replace(/^г Курган,?\s*/i, '').trim();
  document.getElementById('addr-street').value = streetPart;
  if (house) document.getElementById('addr-house').value = house;
  closeDadataSuggestions();
  document.getElementById('addr-house').focus();
}

function closeDadataSuggestions() {
  const list = document.getElementById('dadata-list');
  if (list) list.style.display = 'none';
}

// ─── Экран: детали заказа ─────────────────────────────────────────────────────
function initDetailsScreen() {
  // Предзаполняем имя из Telegram
  const user = tg?.initDataUnsafe?.user;
  if (user?.first_name && !document.getElementById('det-name').value) {
    document.getElementById('det-name').value = user.first_name;
  }
  // Показываем сводку заказа
  updateDetailsSummary();
}

function updateDetailsSummary() {
  const totals = Cart.getTotals(App.deliveryCost);
  const address = App.deliveryType === 'pickup' ? CONFIG.pickupAddress : (App.orderAddress || '—');
  const time = App.deliveryType === 'pickup' ? CONFIG.pickupTime : `~${CONFIG.defaultDeliveryTime} мин`;
  document.getElementById('details-summary').innerHTML = `
    <div class="summary-row"><span>Товары</span><span>${formatPrice(totals.subtotal)}</span></div>
    <div class="summary-row"><span>${App.deliveryType === 'pickup' ? 'Самовывоз' : 'Доставка'}</span><span>${totals.deliveryCost === 0 ? 'Бесплатно' : formatPrice(totals.deliveryCost)}</span></div>
    ${totals.discount > 0 ? `<div class="summary-row discount"><span>Скидка</span><span>−${formatPrice(totals.discount)}</span></div>` : ''}
    <div class="summary-row total"><span>Итого</span><span>${formatPrice(totals.total)}</span></div>
    <div class="summary-address"><span>📍 ${address}</span></div>
    <div class="summary-time"><span>⏱ ${time}</span></div>`;
}

// Сохранённый телефон (получен через requestContact или введён вручную)
let _savedPhone = '';

function requestContactPhone() {
  if (!tg) { showPhoneInput(); return; }
  try {
    tg.requestContact((ok, contact) => {
      if (ok && contact?.phone_number) {
        let phone = contact.phone_number;
        if (!phone.startsWith('+')) phone = '+' + phone;
        _savedPhone = phone;
        const btn = document.getElementById('contact-btn');
        if (btn) { btn.textContent = '✓ Номер получен'; btn.disabled = true; }
      } else {
        showPhoneInput();
      }
    });
  } catch(e) {
    showPhoneInput();
  }
}

function showPhoneInput() {
  const btn = document.getElementById('contact-btn');
  const manual = document.getElementById('det-phone-manual');
  if (btn)    btn.style.display = 'none';
  if (manual) { manual.style.display = 'block'; manual.focus(); }
}

function getPhone() {
  if (_savedPhone) return _savedPhone;
  const manual = document.getElementById('det-phone-manual');
  return manual ? manual.value.trim() : '';
}

// ─── Отправка заказа ──────────────────────────────────────────────────────────
async function handleSubmitOrder() {
  const name  = document.getElementById('det-name').value.trim();
  const phone = getPhone();
  const payment = document.querySelector('input[name="payment"]:checked')?.value || 'cash';
  const comment = document.getElementById('det-comment').value.trim();
  const chopsticks = parseInt(document.getElementById('chopsticks-count').textContent) || 0;

  if (!name) { showFieldError('det-name', 'Введите имя'); haptic('error'); return; }
  if (!phone || phone.replace(/\D/g,'').length < 10) {
    // Показываем поле ввода телефона если ещё не показано
    showPhoneInput();
    showFieldError('det-phone-manual', 'Введите телефон');
    haptic('error'); return;
  }

  haptic('light');
  if (tg) { tg.MainButton.showProgress(false); tg.MainButton.disable(); }

  const items = Cart.getItems();
  const totals = Cart.getTotals(App.deliveryCost);

  // Формируем данные для отправки
  const orderData = {
    name, phone, payment, comment, chopsticks,
    deliveryType: App.deliveryType,
    address: App.deliveryType === 'pickup' ? CONFIG.pickupAddress : App.orderAddress,
    promoCode: Cart.getPromo(),
    items: items.map(({ item, qty }) => ({ id: item.id, fpArticle: item.fpArticle, name: item.name, qty, price: item.price })),
    subtotal:  totals.subtotal,
    delivery:  totals.deliveryCost,
    discount:  totals.discount,
    total:     totals.total,
    tgUserId:  tg?.initDataUnsafe?.user?.id || null,
  };

  try {
    const result = await submitOrder(orderData);
    // Успех
    Cart.clear();
    if (tg) { tg.MainButton.hideProgress(); tg.MainButton.hide(); }
    showSuccessScreen(orderData, result.order_id);
  } catch (err) {
    if (tg) { tg.MainButton.hideProgress(); tg.MainButton.enable(); }
    haptic('error');
    showToast('Ошибка отправки заказа. Позвоните нам: ' + CONFIG.phone);
  }
}

/**
 * Отправка заказа:
 *  1. api/orders/save.php  — сохраняем в БД и шлём уведомление в ВК
 *  2. order.php            — отправляем заказ в FrontPad (secret key на сервере)
 *  3. api/orders/link_fp.php — привязываем fp_order_id к записи в БД
 */
async function submitOrder(orderData) {
  // Определяем время суток для пометки предзаказа (UTC+5, Курган)
  const now = new Date();
  const localMinutes = (now.getUTCHours() * 60 + now.getUTCMinutes() + 5 * 60) % (24 * 60);
  const isClosed = localMinutes < 10 * 60 || localMinutes >= 22 * 60;

  let comment = orderData.comment || '';
  if (isClosed) {
    const note = '⏰ ПРЕДЗАКАЗ (Mini App) — принято в нерабочее время, перезвонить после 10:00';
    comment = comment ? note + '. ' + comment : note;
  } else {
    const note = '📱 Заказ из Telegram Mini App';
    comment = comment ? note + '. ' + comment : note;
  }

  // Маппинг способа оплаты: 1 = онлайн, 2 = наличные, 3 = картой курьеру
  const payMap = { online: '1', cash: '2', card: '3' };
  const pay = payMap[orderData.payment] || '2';

  // delivery_type: 'self' для самовывоза — именно так ожидают order.php и vk_format_order
  const deliveryType = orderData.deliveryType === 'pickup' ? 'self' : 'delivery';

  // Разбиваем адрес на улицу и дом
  let street = '', home = '', flat = '';
  if (orderData.deliveryType !== 'pickup' && orderData.address) {
    const parts = orderData.address.split(',');
    street = (parts[0] || '').trim();
    home   = (parts[1] || '').replace(/д\.?\s*/i, '').trim();
    const flatMatch = orderData.address.match(/кв\.?\s*(\d+)/i);
    flat = flatMatch ? flatMatch[1] : '';
  }

  // ── Шаг 1: сохраняем в БД + уведомление в ВК ────────────────────────────────
  const savePayload = {
    name:          orderData.name,
    phone:         orderData.phone,
    delivery_type: deliveryType,
    street,
    home,
    entrance:      '',
    floor:         '',
    flat,
    pay,
    cash_from:     '',
    comment,
    promo_code:    orderData.promoCode || '',
    preorder_time: '',
    order_total:   orderData.total,
    items:         orderData.items.map(i => ({ name: i.name, qty: i.qty, price: i.price })),
    items_total:   orderData.subtotal,
    delivery_cost: orderData.delivery,
    promo_discount: orderData.discount,
    total_paid:    orderData.total,
    points_spent:  0,
  };

  const saveRes = await fetch('../api/orders/save.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(savePayload),
  });
  const saveData = await saveRes.json();
  if (!saveData.ok) throw new Error('Ошибка сохранения заказа');
  const dbOrderId = saveData.order_id;

  // ── Шаг 2: отправляем в FrontPad ─────────────────────────────────────────────
  const fpPayload = {
    name:          orderData.name,
    phone:         orderData.phone,
    delivery_type: deliveryType,
    street,
    home,
    entrance:      '',
    floor:         '',
    flat,
    comment,
    pay,
    point:         CONFIG.frontpadPoint,
    cash_from:     '',
    chopsticks:    orderData.chopsticks || 0,
    preorder_date: '',
    preorder_time: '',
    order_total:   orderData.total,
    promo_code:    orderData.promoCode || '',
    items:         orderData.items.map(i => ({ id: i.fpArticle || i.id, qty: i.qty, price: i.price })),
  };

  const fpRes = await fetch('../order.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(fpPayload),
  });

  const fpText = await fpRes.text();
  let fpResult;
  try { fpResult = JSON.parse(fpText); } catch(e) {
    console.error('order.php вернул не JSON:', fpText);
    throw new Error('Ошибка сервера');
  }

  if (!fpResult.ok) {
    console.error('Ошибка Frontpad:', fpResult);
    throw new Error(fpResult.error || 'Ошибка оформления заказа');
  }

  // ── Шаг 3: привязываем fp_order_id к записи в БД (fire & forget) ─────────────
  const fpOrderId = fpResult.order_id;
  if (fpOrderId) {
    fetch('../api/orders/link_fp.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ order_id: dbOrderId, fp_order_id: fpOrderId }),
    }).catch(() => {});
  }

  return { ok: true, order_id: dbOrderId };
}

// ─── Экран: успех ─────────────────────────────────────────────────────────────
function showSuccessScreen(orderData, orderId) {
  const num = orderId || (Math.floor(Math.random() * 9000) + 1000);
  const now = new Date();
  now.setMinutes(now.getMinutes() + CONFIG.defaultDeliveryTime);
  const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

  document.getElementById('success-order-num').textContent = orderId ? '№' + num : '#' + num;
  document.getElementById('success-time').textContent = `Доставим к ~${timeStr}. Позвоним для подтверждения.`;
  document.getElementById('success-summary').innerHTML = `${orderData.items.length} поз. · ${formatPrice(orderData.total)}`;

  navigateTo('success');
  haptic('success');

  // Запускаем анимацию чекмарка
  setTimeout(() => document.getElementById('check-circle').classList.add('animate'), 100);
}

// ─── Bottom Sheet helpers ─────────────────────────────────────────────────────
function openSheet(sheetId) {
  const sheet = document.getElementById(sheetId);
  const overlay = document.getElementById('overlay');
  sheet.classList.add('open');
  overlay.classList.add('active');
  overlay.onclick = () => closeSheet(sheetId);
  // Свайп вниз для закрытия
  addSwipeToClose(sheet, () => closeSheet(sheetId));
}

function closeSheet(sheetId) {
  const sheet = document.getElementById(sheetId);
  sheet.classList.remove('open');
  document.getElementById('overlay').classList.remove('active');
  if (tg) {
    tg.BackButton.hide();
    // Восстанавливаем BackButton для экрана если нужно
    if (App.screenHistory.length > 0) {
      tg.BackButton.show();
      setBackBtnHandler(navigateBack);
    } else {
      setBackBtnHandler(null);
    }
  }
  updateMainButton();
}

let swipeStartY = 0;
function addSwipeToClose(sheet, callback) {
  const handle = sheet.querySelector('.sheet-handle');
  const el = handle || sheet;

  // Удаляем предыдущие слушатели через сохранённые ссылки
  if (el._swipe) {
    el.removeEventListener('touchstart', el._swipe.start);
    el.removeEventListener('touchmove',  el._swipe.move);
    el.removeEventListener('touchend',   el._swipe.end);
  }

  const startHandler = e => { swipeStartY = (e.touches?.[0] || e).clientY; };
  const moveHandler  = e => {
    const dy = (e.touches?.[0] || e).clientY - swipeStartY;
    if (dy > 0) sheet.style.transform = `translateY(${dy}px)`;
  };
  const endHandler   = e => {
    const dy = (e.changedTouches?.[0] || e).clientY - swipeStartY;
    sheet.style.transform = '';
    if (dy > 80) callback();
  };

  el._swipe = { start: startHandler, move: moveHandler, end: endHandler };
  el.addEventListener('touchstart', el._swipe.start, { passive: true });
  el.addEventListener('touchmove',  el._swipe.move,  { passive: true });
  el.addEventListener('touchend',   el._swipe.end,   { passive: true });
}

// ─── Степпер палочек ──────────────────────────────────────────────────────────
let chopsticksCount = 0;
function changeChopsticks(delta) {
  chopsticksCount = Math.max(0, chopsticksCount + delta);
  document.getElementById('chopsticks-count').textContent = chopsticksCount;
  haptic('light');
}

// ─── Утилиты ──────────────────────────────────────────────────────────────────
function formatPrice(n) {
  return n.toLocaleString('ru-RU') + ' ₽';
}

function haptic(type) {
  if (!tg?.HapticFeedback) return;
  try {
    if (type === 'light' || type === 'medium' || type === 'heavy') {
      tg.HapticFeedback.impactOccurred(type);
    } else if (type === 'success' || type === 'error' || type === 'warning') {
      tg.HapticFeedback.notificationOccurred(type);
    }
  } catch(e) {}
}

function showToast(msg) {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}

// ─── Обработчики событий DOM ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Экран адреса: переключатель доставки/самовывоза
  document.querySelectorAll('.seg-btn').forEach(btn => {
    btn.addEventListener('click', () => updateDeliveryType(btn.dataset.type));
  });

  // Экран адреса: инициализировать при переходе
  document.getElementById('screen-address').addEventListener('animationend', initAddressScreen, { once: false });

  // Экран деталей: инициализировать при переходе
  document.getElementById('screen-details').addEventListener('animationend', initDetailsScreen, { once: false });

  // Убираем автозум на iOS при фокусе на input
  document.querySelectorAll('input, textarea').forEach(el => {
    el.style.fontSize = '16px'; // iOS не зумит при fontSize >= 16px
  });

  // Запуск приложения
  init();
});

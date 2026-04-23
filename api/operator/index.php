<?php
require_once __DIR__ . '/../config.php';

define('OPER_PASS', 'swlOper2026');
session_name('swl_operator');
session_start();

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if ($_POST['pass'] === OPER_PASS) {
        $_SESSION['oper'] = true;
        header('Location: ?'); exit;
    }
    $login_error = 'Неверный пароль';
}

if (empty($_SESSION['oper'])) {
?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Оператор — вход</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#1a1a1a;border:1px solid #333;border-radius:14px;padding:40px;max-width:360px;width:100%}
  h2{margin:0 0 24px;font-size:1.3rem;color:#e8a847}
  input{width:100%;box-sizing:border-box;background:#222;border:1px solid #444;color:#eee;border-radius:8px;padding:12px;font-size:1rem;margin-bottom:16px}
  button{width:100%;background:#e8a847;border:none;color:#000;border-radius:8px;padding:12px;font-size:1rem;font-weight:700;cursor:pointer}
  .err{color:#e05a5a;margin-bottom:12px;font-size:0.9rem}
</style></head><body>
<div class="box">
  <h2>📦 Панель оператора</h2>
  <?php if (!empty($login_error)) echo '<div class="err">'.$login_error.'</div>'; ?>
  <form method="post">
    <input type="password" name="pass" placeholder="Пароль" autofocus>
    <button type="submit">Войти</button>
  </form>
</div>
</body></html><?php
    exit;
}

// === AJAX ===
require_once __DIR__ . '/../db.php';

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'orders_list') {
        $pdo = db();
        $where = array('1=1');
        $params = array();
        if (!empty($_GET['status'])) { $where[] = 'o.status=?'; $params[] = $_GET['status']; }
        if (!empty($_GET['from']))   { $where[] = 'DATE(o.created_at)>=?'; $params[] = $_GET['from']; }
        if (!empty($_GET['to']))     { $where[] = 'DATE(o.created_at)<=?'; $params[] = $_GET['to']; }
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $sql = 'SELECT o.*, u.name as user_name, u.phone as user_phone
                FROM orders o LEFT JOIN users u ON u.id=o.user_id
                WHERE '.implode(' AND ',$where).'
                ORDER BY o.created_at DESC LIMIT '.$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_out(array('ok'=>true,'orders'=>$stmt->fetchAll()));
    }

    if ($action === 'order_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $oid    = intval($data['order_id']);
        $status = $data['status'];
        $allowed = array('new','pending','cooking','delivering','done','cancelled');
        if (!in_array($status, $allowed)) { json_out(array('ok'=>false,'error'=>'Bad status')); }
        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute(array($status, $oid));
        if ($status === 'done') {
            $order = db()->query('SELECT * FROM orders WHERE id='.$oid)->fetch();
            if ($order && !$order['points_earned'] && !$order['points_spent'] && $order['user_id']) {
                $earn_pct = intval(loyalty_config('earn_pct'));
                $earned = intval($order['items_total'] * $earn_pct / 100);
                if ($earned > 0) {
                    db()->prepare('UPDATE orders SET points_earned=? WHERE id=?')->execute(array($earned, $oid));
                    db()->prepare('UPDATE users SET points=points+?, last_order_at=NOW() WHERE id=?')->execute(array($earned, $order['user_id']));
                    db()->prepare('INSERT INTO points_log (user_id, order_id, delta, reason, created_at) VALUES (?,?,?,?,NOW())')->execute(array($order['user_id'], $oid, $earned, 'earn'));
                }
            }
        }
        json_out(array('ok'=>true));
    }

    if ($action === 'order_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data          = json_decode(file_get_contents('php://input'), true);
        $oid           = intval($data['order_id']);
        $address       = isset($data['address'])       ? trim($data['address'])       : null;
        $pay_type      = isset($data['pay_type'])      ? $data['pay_type']            : null;
        $delivery_cost = isset($data['delivery_cost']) ? intval($data['delivery_cost']): 0;
        $total_paid    = isset($data['total_paid'])    ? intval($data['total_paid'])  : 0;
        $comment       = isset($data['comment'])       ? trim($data['comment'])       : null;
        if ($comment === '') $comment = null;

        $items_json = null;
        $items_total = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            $slim = array();
            foreach ($data['items'] as $it) {
                $qty   = max(1, intval($it['qty']));
                $price = max(0, floatval($it['price']));
                $slim[] = array(
                    'id'     => isset($it['id'])   ? (int)$it['id'] : 0,
                    'name'   => isset($it['name']) ? (string)$it['name'] : '',
                    'qty'    => $qty,
                    'price'  => $price,
                    'isGift' => !empty($it['isGift']) ? 1 : 0,
                );
                if (empty($it['isGift'])) $items_total += $qty * $price;
            }
            $items_json = json_encode($slim, JSON_UNESCAPED_UNICODE);
        }

        db()->prepare('
            UPDATE orders SET address=?, pay_type=?, delivery_cost=?, total_paid=?,
                              items_json=?, items_total=?, comment=?
            WHERE id=?
        ')->execute(array($address, $pay_type, $delivery_cost, $total_paid,
                          $items_json, $items_total, $comment, $oid));

        json_out(array('ok'=>true, 'items_total'=>$items_total));
    }

    if ($action === 'counts') {
        $pdo = db();
        $counts = array(
            'new'        => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
            'cooking'    => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='cooking'")->fetchColumn(),
            'delivering' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivering'")->fetchColumn(),
        );
        json_out(array('ok'=>true,'counts'=>$counts));
    }

    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Суши с Любовью — Заказы</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#111;color:#eee;min-height:100vh}
  /* Header */
  .header{background:#161616;border-bottom:1px solid #2a2a2a;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
  .header-title{font-weight:700;color:#e8a847;font-size:1.05rem}
  .header-right{display:flex;align-items:center;gap:14px}
  .refresh-info{font-size:0.78rem;color:#555}
  /* Status tabs */
  .status-tabs{display:flex;gap:8px;padding:16px 20px;overflow-x:auto;background:#161616;border-bottom:1px solid #2a2a2a}
  .status-tab{padding:8px 18px;border-radius:20px;border:1px solid #2a2a2a;background:#1a1a1a;color:#aaa;font-size:0.85rem;cursor:pointer;white-space:nowrap;transition:all 0.15s}
  .status-tab.active{border-color:#e8a847;background:rgba(232,168,71,0.15);color:#e8a847;font-weight:600}
  .status-tab .cnt{display:inline-block;background:#e8a847;color:#000;border-radius:10px;padding:1px 7px;font-size:0.75rem;font-weight:700;margin-left:6px}
  /* Orders */
  .orders{padding:16px 20px;display:flex;flex-direction:column;gap:12px}
  .order-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:16px;transition:border-color 0.2s}
  .order-card.status-new{border-left:3px solid #6ab0ff}
  .order-card.status-cooking{border-left:3px solid #ffaa44}
  .order-card.status-delivering{border-left:3px solid #44cc88}
  .order-card.status-done{border-left:3px solid #444;opacity:0.7}
  .order-card.status-cancelled{border-left:3px solid #cc6666;opacity:0.6}
  .order-card.status-pending{border-left:3px solid #f97316}
  .order-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px;flex-wrap:wrap}
  .order-id{font-weight:700;font-size:1rem;color:#eee}
  .order-id small{font-weight:400;color:#555;font-size:0.78rem;margin-left:6px}
  .order-date{font-size:0.8rem;color:#666}
  .order-meta{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;font-size:0.88rem}
  .order-meta-item{display:flex;flex-direction:column;gap:2px}
  .order-meta-label{font-size:0.72rem;color:#555;text-transform:uppercase;letter-spacing:.04em}
  .order-meta-value{color:#eee;font-weight:500}
  .order-meta-value.accent{color:#e8a847;font-weight:700}
  /* Status buttons */
  .status-btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid #2a2a2a}
  .status-btn{padding:8px 16px;border-radius:8px;border:none;font-size:0.82rem;font-weight:600;cursor:pointer;transition:opacity 0.15s}
  .status-btn:hover{opacity:0.85}
  .status-btn.cooking{background:#3a2a1a;color:#ffaa44;border:1px solid #5a3a1a}
  .status-btn.delivering{background:#1a3a2a;color:#44cc88;border:1px solid #1a5a3a}
  .status-btn.done{background:#1a2a1a;color:#66bb66;border:1px solid #2a4a2a}
  .status-btn.cancelled{background:#2a1a1a;color:#cc6666;border:1px solid #4a2a2a}
  .status-btn.new{background:#1a2a3a;color:#6ab0ff;border:1px solid #1a3a5a}
  .status-btn.active-status{opacity:0.35;cursor:default;pointer-events:none}
  /* Badge */
  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:600}
  .badge-new{background:#2a3a5a;color:#6ab0ff}
  .badge-cooking{background:#3a2a1a;color:#ffaa44}
  .badge-delivering{background:#1a3a2a;color:#44cc88}
  .badge-done{background:#1a2a1a;color:#66bb66}
  .badge-cancelled{background:#2a1a1a;color:#cc6666}
  .badge-pending{background:#2a1a0a;color:#f97316}
  /* Promo */
  .promo-tag{display:inline-block;padding:2px 8px;background:rgba(232,168,71,0.15);border:1px solid rgba(232,168,71,0.3);border-radius:8px;font-size:0.75rem;color:#e8a847}
  /* Empty */
  .empty{text-align:center;padding:60px 20px;color:#444;font-size:0.95rem}
  /* Alert */
  .alert{position:fixed;bottom:20px;right:20px;padding:12px 20px;border-radius:10px;font-size:0.9rem;font-weight:600;z-index:100;display:none}
  .alert-ok{background:#1a3a1a;border:1px solid #2a5a2a;color:#66bb66}
  .alert-err{background:#3a1a1a;border:1px solid #5a2a2a;color:#cc6666}
  /* Date filter */
  .date-filter{padding:12px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid #2a2a2a;background:#161616}
  .date-filter label{font-size:0.8rem;color:#666}
  .date-filter input{background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:6px 10px;font-size:0.85rem}
  .logout-btn{font-size:0.8rem;color:#555;text-decoration:none}
  .logout-btn:hover{color:#e05a5a}
  /* Edit form */
  .edit-panel{display:none;border-top:1px solid #2a2a2a;margin-top:12px;padding-top:12px}
  .edit-panel.open{display:block}
  .edit-row{display:flex;gap:10px;margin-bottom:10px;align-items:flex-end;flex-wrap:wrap}
  .edit-field{display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px}
  .edit-field label{font-size:0.72rem;color:#555;text-transform:uppercase;letter-spacing:.04em}
  .edit-field input,.edit-field select{background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:8px 10px;font-size:0.88rem;width:100%}
  .items-table{width:100%;border-collapse:collapse;margin-bottom:8px;font-size:0.85rem}
  .items-table th{text-align:left;color:#555;font-size:0.72rem;text-transform:uppercase;padding:4px 6px;border-bottom:1px solid #2a2a2a}
  .items-table td{padding:4px 6px;vertical-align:middle}
  .items-table input{background:#222;border:1px solid #333;color:#eee;border-radius:6px;padding:5px 8px;font-size:0.85rem;width:100%}
  .items-table input.inp-qty{width:54px}
  .items-table input.inp-price{width:80px}
  .btn-del-row{background:none;border:none;color:#cc6666;cursor:pointer;font-size:1rem;padding:0 4px}
  .btn-add-row{background:#1a2a1a;border:1px solid #2a4a2a;color:#44cc88;border-radius:7px;padding:5px 12px;font-size:0.82rem;cursor:pointer;margin-bottom:10px}
  .btn-save-edit{background:#e8a847;border:none;color:#000;border-radius:8px;padding:9px 20px;font-weight:700;cursor:pointer;font-size:0.88rem}
  .btn-save-edit:hover{opacity:0.85}
  .items-total-line{font-size:0.82rem;color:#aaa;margin-bottom:8px}
  /* All status buttons */
  .all-statuses{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
  .stt-btn{padding:6px 13px;border-radius:7px;border:1px solid #333;background:#1a1a1a;color:#aaa;font-size:0.8rem;cursor:pointer;font-weight:500;transition:all 0.15s}
  .stt-btn:hover{opacity:0.8}
  .stt-btn.cur{opacity:0.3;cursor:default;pointer-events:none}
  .stt-btn.s-new{color:#6ab0ff;border-color:#1a3a5a}
  .stt-btn.s-cooking{color:#ffaa44;border-color:#5a3a1a}
  .stt-btn.s-delivering{color:#44cc88;border-color:#1a5a3a}
  .stt-btn.s-done{color:#66bb66;border-color:#2a4a2a}
  .stt-btn.s-cancelled{color:#cc6666;border-color:#4a2a2a}
  @media(max-width:600px){.orders{padding:12px}}
</style>
</head>
<body>

<div class="header">
  <div class="header-title">📦 Заказы — Суши с Любовью</div>
  <div class="header-right">
    <span class="refresh-info" id="refreshInfo">Обновление…</span>
    <a class="logout-btn" href="?logout=1">Выйти</a>
  </div>
</div>

<div class="status-tabs">
  <div class="status-tab active" onclick="setFilter('')" id="tab-all">Все <span class="cnt" id="cnt-all"></span></div>
  <div class="status-tab" onclick="setFilter('new')" id="tab-new">🆕 Новые <span class="cnt" id="cnt-new" style="display:none"></span></div>
  <div class="status-tab" onclick="setFilter('pending')" id="tab-pending">⚠️ Без FP <span class="cnt" id="cnt-pending" style="display:none"></span></div>
  <div class="status-tab" onclick="setFilter('cooking')" id="tab-cooking">👨‍🍳 Готовятся <span class="cnt" id="cnt-cooking" style="display:none"></span></div>
  <div class="status-tab" onclick="setFilter('delivering')" id="tab-delivering">🛵 Доставка <span class="cnt" id="cnt-delivering" style="display:none"></span></div>
  <div class="status-tab" onclick="setFilter('done')" id="tab-done">✅ Выполнены</div>
  <div class="status-tab" onclick="setFilter('cancelled')" id="tab-cancelled">❌ Отменены</div>
</div>

<div class="date-filter">
  <label>С:</label>
  <input type="date" id="dateFrom" onchange="loadOrders()">
  <label>По:</label>
  <input type="date" id="dateTo" onchange="loadOrders()">
</div>

<div class="orders" id="ordersList">
  <div class="empty">Загрузка…</div>
</div>

<div class="alert" id="alertBox"></div>

<script>
var currentFilter = '';
var refreshTimer  = null;
var REFRESH_SEC   = 30;
var countdown     = REFRESH_SEC;
var ordersData    = {};  // id -> order object

function setFilter(status) {
  currentFilter = status;
  document.querySelectorAll('.status-tab').forEach(function(t){ t.classList.remove('active'); });
  var id = 'tab-' + (status || 'all');
  var el = document.getElementById(id);
  if (el) el.classList.add('active');
  loadOrders();
}

function loadOrders() {
  var status = currentFilter;
  var from   = document.getElementById('dateFrom').value;
  var to     = document.getElementById('dateTo').value;
  var url    = '?action=orders_list' + (status?'&status='+status:'') + (from?'&from='+from:'') + (to?'&to='+to:'');
  fetch(url).then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    ordersData = {};
    r.orders.forEach(function(o){ ordersData[o.id] = o; });
    renderOrders(r.orders);
    updateCounts(r.orders);
  });
  resetRefresh();
}

function updateCounts(orders) {
  var counts = {all:0, new:0, pending:0, cooking:0, delivering:0};
  orders.forEach(function(o) {
    counts.all++;
    if (o.status === 'pending') counts.pending++;
    else if (o.status === 'new') counts.new++;
    else if (o.status === 'cooking') counts.cooking++;
    else if (o.status === 'delivering') counts.delivering++;
  });
  var cntAll = document.getElementById('cnt-all');
  if (cntAll) { cntAll.textContent = counts.all; cntAll.style.display = counts.all ? '' : 'none'; }
  ['new','pending','cooking','delivering'].forEach(function(s) {
    var el = document.getElementById('cnt-' + s);
    if (el) { el.textContent = counts[s]; el.style.display = counts[s] ? '' : 'none'; }
  });
}

var STATUS_LABELS = {new:'Новый', cooking:'Готовится', delivering:'Доставляется', done:'Выполнен', cancelled:'Отменён', pending:'⚠️ Без FP'};
var ALL_STATUSES  = [
  {s:'new',        label:'🆕 Новый'},
  {s:'cooking',    label:'👨‍🍳 Готовится'},
  {s:'delivering', label:'🛵 Доставляется'},
  {s:'done',       label:'✅ Выполнен'},
  {s:'cancelled',  label:'❌ Отменён'}
];

function renderOrders(orders) {
  if (!orders || !orders.length) {
    document.getElementById('ordersList').innerHTML = '<div class="empty">Нет заказов</div>';
    return;
  }

  var html = '';
  orders.forEach(function(o) {
    var status   = o.status || 'new';
    var badgeCls = 'badge-' + status;
    var cardCls  = 'status-' + status;
    var label    = STATUS_LABELS[status] || status;
    var client   = o.user_name || (o.client_name || '—');
    var phone    = o.user_phone || o.client_phone || '';
    if (phone && phone.length === 11) phone = formatPhone(phone);
    var clientStr = client + (phone ? ' · ' + phone : '');

    var fpBadge = o.fp_order_id
      ? '<small style="color:#555;font-size:0.75rem">FP: ' + o.fp_order_id + '</small>'
      : '<small style="color:#f97316;font-size:0.75rem">⚠️ Нет в FP</small>';

    var promoHtml = o.promo_code
      ? '<span class="promo-tag">🏷 ' + escHtml(o.promo_code) + (o.promo_discount ? ' −' + fmt(o.promo_discount) : '') + '</span> '
      : '';

    // Состав
    var items = null;
    try { items = o.items_json ? JSON.parse(o.items_json) : null; } catch(e){}
    var itemsHtml = '';
    if (items && items.length) {
      itemsHtml = '<div style="margin:10px 0 4px;font-size:0.75rem;color:#555;text-transform:uppercase;letter-spacing:.04em">Состав</div>'
        + '<div style="font-size:0.85rem;margin-bottom:8px">';
      items.forEach(function(it) {
        itemsHtml += '<div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #1f1f1f">'
          + '<span style="color:#ccc">' + (it.isGift ? '🎁 ' : '') + escHtml(it.name) + (it.qty > 1 ? ' × ' + it.qty : '') + '</span>'
          + '<span style="color:#888;white-space:nowrap;margin-left:10px">' + (it.isGift ? 'подарок' : (it.price * it.qty) + ' ₽') + '</span>'
          + '</div>';
      });
      itemsHtml += '</div>';
    }

    // Все кнопки статуса
    var sttBtns = ALL_STATUSES.map(function(b) {
      var isCur = (b.s === status);
      return '<button class="stt-btn s-' + b.s + (isCur ? ' cur' : '') + '" onclick="changeStatus(' + o.id + ',\'' + b.s + '\')">' + b.label + '</button>';
    }).join('');

    // Форма редактирования
    var editItems = items || [];
    var itemRows = editItems.map(function(it, i) {
      return '<tr>'
        + '<td><input type="text" value="' + escHtml(it.name) + '" placeholder="Название" data-field="name" data-i="' + i + '"></td>'
        + '<td><input type="number" class="inp-qty" value="' + it.qty + '" min="1" data-field="qty" data-i="' + i + '"></td>'
        + '<td><input type="number" class="inp-price" value="' + it.price + '" min="0" data-field="price" data-i="' + i + '"></td>'
        + '<td><button class="btn-del-row" onclick="editDelRow(this)">✕</button></td>'
        + '</tr>';
    }).join('');

    var editForm = '<div class="edit-panel" id="edit-' + o.id + '">'
      + '<table class="items-table"><thead><tr><th>Название</th><th>Кол-во</th><th>Цена</th><th></th></tr></thead>'
      + '<tbody id="items-body-' + o.id + '">' + itemRows + '</tbody></table>'
      + '<button class="btn-add-row" onclick="editAddRow(' + o.id + ')">+ Добавить позицию</button>'
      + '<div class="items-total-line">Сумма товаров: <b id="calc-total-' + o.id + '">' + parseInt(o.items_total) + ' ₽</b></div>'
      + '<div class="edit-row">'
      +   '<div class="edit-field"><label>Адрес</label><input type="text" id="ef-address-' + o.id + '" value="' + escHtml(o.address||'') + '"></div>'
      +   '<div class="edit-field"><label>Оплата</label><select id="ef-pay-' + o.id + '">'
      +     '<option value="qr"' + (o.pay_type==='qr'?' selected':'') + '>QR / карта</option>'
      +     '<option value="cash"' + (o.pay_type==='cash'?' selected':'') + '>Наличными</option>'
      +   '</select></div>'
      + '</div>'
      + '<div class="edit-row">'
      +   '<div class="edit-field"><label>Доставка, ₽</label><input type="number" id="ef-delivery-' + o.id + '" value="' + parseInt(o.delivery_cost||0) + '" min="0" oninput="editRecalc(' + o.id + ')"></div>'
      +   '<div class="edit-field"><label>Итого, ₽</label><input type="number" id="ef-total-' + o.id + '" value="' + parseInt(o.total_paid||0) + '" min="0"></div>'
      + '</div>'
      + '<div class="edit-field" style="margin-bottom:10px"><label>Комментарий</label><input type="text" id="ef-comment-' + o.id + '" value="' + escHtml(o.comment||'') + '"></div>'
      + '<button class="btn-save-edit" onclick="saveEdit(' + o.id + ')">💾 Сохранить изменения</button>'
      + '</div>';

    html += '<div class="order-card ' + cardCls + '" id="card-' + o.id + '">'
      + '<div class="order-head">'
      +   '<div><span class="order-id">#' + (o.display_number || o.id) + '</span> ' + fpBadge + '</div>'
      +   '<div style="display:flex;align-items:center;gap:8px">'
      +     '<span class="badge ' + badgeCls + '">' + label + '</span>'
      +     '<span class="order-date">' + fmtDate(o.created_at) + '</span>'
      +   '</div>'
      + '</div>'
      + '<div class="order-meta">'
      +   '<div class="order-meta-item"><div class="order-meta-label">Клиент</div><div class="order-meta-value">' + escHtml(clientStr) + '</div></div>'
      +   '<div class="order-meta-item"><div class="order-meta-label">Товары</div><div class="order-meta-value accent">' + fmt(o.items_total) + '</div></div>'
      + (o.delivery_cost > 0
          ? '<div class="order-meta-item"><div class="order-meta-label">Доставка</div><div class="order-meta-value">' + fmt(o.delivery_cost) + '</div></div>'
          : '<div class="order-meta-item"><div class="order-meta-label">Доставка</div><div class="order-meta-value" style="color:#44cc88">Бесплатно</div></div>'
        )
      +   '<div class="order-meta-item"><div class="order-meta-label">Итого</div><div class="order-meta-value accent">' + fmt(o.total_paid) + '</div></div>'
      + (o.points_spent  > 0 ? '<div class="order-meta-item"><div class="order-meta-label">Списано</div><div class="order-meta-value" style="color:#e05a5a">−' + o.points_spent  + ' б</div></div>' : '')
      + (o.points_earned > 0 ? '<div class="order-meta-item"><div class="order-meta-label">Начислено</div><div class="order-meta-value" style="color:#44cc88">+' + o.points_earned + ' б</div></div>' : '')
      + '</div>'
      + (o.address ? '<div style="font-size:0.82rem;color:#777;margin-bottom:4px">📍 ' + escHtml(o.address) + '</div>' : '')
      + (o.comment ? '<div style="font-size:0.82rem;color:#777;margin-bottom:4px">💬 ' + escHtml(o.comment) + '</div>' : '')
      + (promoHtml ? '<div style="margin-bottom:8px">' + promoHtml + '</div>' : '')
      + itemsHtml
      + '<div class="all-statuses">' + sttBtns + '</div>'
      + '<div style="margin-top:6px">'
      +   '<button onclick="toggleEdit(' + o.id + ')" style="background:none;border:1px solid #333;color:#888;border-radius:7px;padding:5px 13px;font-size:0.8rem;cursor:pointer">✏️ Редактировать</button>'
      + '</div>'
      + editForm
      + '</div>';
  });

  document.getElementById('ordersList').innerHTML = html;
}

function toggleEdit(oid) {
  var panel = document.getElementById('edit-' + oid);
  if (!panel) return;
  panel.classList.toggle('open');
}

function editAddRow(oid) {
  var tbody = document.getElementById('items-body-' + oid);
  if (!tbody) return;
  var i = tbody.rows.length;
  var tr = document.createElement('tr');
  tr.innerHTML = '<td><input type="text" value="" placeholder="Название" data-field="name" data-i="' + i + '"></td>'
    + '<td><input type="number" class="inp-qty" value="1" min="1" data-field="qty" data-i="' + i + '"></td>'
    + '<td><input type="number" class="inp-price" value="0" min="0" data-field="price" data-i="' + i + '"></td>'
    + '<td><button class="btn-del-row" onclick="editDelRow(this)">✕</button></td>';
  tbody.appendChild(tr);
}

function editDelRow(btn) {
  var tr = btn.closest('tr');
  var tbody = tr.parentNode;
  var oid = tbody.id.replace('items-body-', '');
  tr.remove();
  editRecalc(oid);
}

function editRecalc(oid) {
  var tbody = document.getElementById('items-body-' + oid);
  if (!tbody) return;
  var total = 0;
  Array.from(tbody.rows).forEach(function(tr) {
    var qty   = parseFloat(tr.querySelector('[data-field="qty"]').value)   || 0;
    var price = parseFloat(tr.querySelector('[data-field="price"]').value) || 0;
    total += qty * price;
  });
  var delivery = parseFloat(document.getElementById('ef-delivery-' + oid).value) || 0;
  var calcEl = document.getElementById('calc-total-' + oid);
  if (calcEl) calcEl.textContent = Math.round(total) + ' ₽';
  var totalEl = document.getElementById('ef-total-' + oid);
  if (totalEl) totalEl.value = Math.round(total + delivery);
}

function saveEdit(oid) {
  var tbody = document.getElementById('items-body-' + oid);
  var items = [];
  if (tbody) {
    Array.from(tbody.rows).forEach(function(tr) {
      var name  = tr.querySelector('[data-field="name"]').value.trim();
      var qty   = parseInt(tr.querySelector('[data-field="qty"]').value)   || 1;
      var price = parseFloat(tr.querySelector('[data-field="price"]').value) || 0;
      if (name) items.push({name:name, qty:qty, price:price, id:0, isGift:0});
    });
  }
  var payload = {
    order_id:      oid,
    items:         items,
    address:       document.getElementById('ef-address-'  + oid).value.trim(),
    pay_type:      document.getElementById('ef-pay-'      + oid).value,
    delivery_cost: parseInt(document.getElementById('ef-delivery-' + oid).value) || 0,
    total_paid:    parseInt(document.getElementById('ef-total-'    + oid).value) || 0,
    comment:       document.getElementById('ef-comment-'  + oid).value.trim()
  };
  fetch('?action=order_edit', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) {
      showAlert('✅ Заказ сохранён', false);
      loadOrders();
    } else {
      showAlert('Ошибка: ' + (r.error || '?'), true);
    }
  });
}

function changeStatus(oid, status) {
  fetch('?action=order_status', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({order_id: oid, status: status})
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) {
      showAlert(status === 'done' ? '✅ Выполнен — баллы начислены' : 'Статус обновлён', false);
      loadOrders();
    } else {
      showAlert('Ошибка: ' + (r.error || '?'), true);
    }
  });
}

function resetRefresh() {
  if (refreshTimer) clearInterval(refreshTimer);
  countdown = REFRESH_SEC;
  updateRefreshInfo();
  refreshTimer = setInterval(function() {
    countdown--;
    updateRefreshInfo();
    if (countdown <= 0) loadOrders();
  }, 1000);
}

function updateRefreshInfo() {
  var el = document.getElementById('refreshInfo');
  if (el) el.textContent = countdown > 0 ? 'Обновление через ' + countdown + ' с' : 'Обновляем…';
}

function showAlert(msg, isErr) {
  var box = document.getElementById('alertBox');
  box.className = 'alert ' + (isErr ? 'alert-err' : 'alert-ok');
  box.textContent = msg;
  box.style.display = 'block';
  setTimeout(function(){ box.style.display = 'none'; }, 3000);
}

function fmt(n){ return parseInt(n).toLocaleString('ru') + ' ₽'; }
function fmtDate(s){ return s ? s.slice(0,16).replace('T',' ') : '—'; }
function formatPhone(p){ if (p && p.length===11) return '8 ('+p.slice(1,4)+') '+p.slice(4,7)+'-'+p.slice(7,9)+'-'+p.slice(9,11); return p||''; }
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Старт
loadOrders();
</script>
</body>
</html>

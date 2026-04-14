<?php
require_once __DIR__ . '/../config.php';

// Пароль администратора
define('ADMIN_PASS', 'swlAdmin2026');
session_start();

// Выход
if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

// Вход
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if ($_POST['pass'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: ?tab=users'); exit;
    }
    $login_error = 'Неверный пароль';
}

// Проверка сессии
if (empty($_SESSION['admin'])) {
    // Форма входа
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Админ — вход</title>
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
  <h2>🔐 Панель управления</h2>
  <?php if (!empty($login_error)) echo '<div class="err">'.$login_error.'</div>'; ?>
  <form method="post">
    <input type="password" name="pass" placeholder="Пароль" autofocus>
    <button type="submit">Войти</button>
  </form>
</div>
</body></html><?php
    exit;
}

// === AJAX-запросы ===
require_once __DIR__ . '/../db.php';

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Начислить/списать баллы вручную
    if ($action === 'points' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $uid  = intval($data['user_id']);
        $delta = intval($data['delta']);
        $reason = isset($data['reason']) ? trim($data['reason']) : 'admin';
        if (!$uid || !$delta) { json_out(array('ok'=>false,'error'=>'Bad params')); }
        db()->prepare('UPDATE users SET points = points + ? WHERE id = ?')->execute(array($delta, $uid));
        db()->prepare('INSERT INTO points_log (user_id, delta, reason, created_at) VALUES (?,?,?,NOW())')->execute(array($uid, $delta, $reason));
        json_out(array('ok'=>true));
    }

    // Изменить статус заказа
    if ($action === 'order_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $oid  = intval($data['order_id']);
        $status = $data['status'];
        $allowed = array('new','cooking','delivering','done','cancelled');
        if (!in_array($status, $allowed)) { json_out(array('ok'=>false,'error'=>'Bad status')); }
        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute(array($status, $oid));
        // При статусе done — начислить баллы (если ещё не начислены)
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

    // Обновить настройки лояльности
    if ($action === 'loyalty_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $allowed_keys = array('earn_pct','spend_max_pct','min_order_spend','welcome_bonus','expire_days');
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed_keys)) {
                db()->prepare('UPDATE loyalty_config SET value=? WHERE key_name=?')->execute(array($v, $k));
            }
        }
        json_out(array('ok'=>true));
    }

    // Данные для дашборда
    if ($action === 'stats') {
        $pdo = db();
        $today = date('Y-m-d');
        $stats = array(
            'users_total'   => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'orders_today'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn(),
            'revenue_today' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled'")->fetchColumn(),
            'orders_month'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn(),
            'revenue_month' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND status!='cancelled'")->fetchColumn(),
        );
        json_out(array('ok'=>true,'stats'=>$stats));
    }

    if ($action === 'users_list') {
        $stmt = db()->query('SELECT id,name,phone,tg_id,points,created_at FROM users ORDER BY created_at DESC LIMIT 200');
        json_out(array('ok'=>true,'users'=>$stmt->fetchAll()));
    }

    if ($action === 'orders_list') {
        $pdo = db();
        $where = array('1=1');
        $params = array();
        if (!empty($_GET['status'])) { $where[] = 'o.status=?'; $params[] = $_GET['status']; }
        if (!empty($_GET['from']))   { $where[] = 'DATE(o.created_at)>=?'; $params[] = $_GET['from']; }
        if (!empty($_GET['to']))     { $where[] = 'DATE(o.created_at)<=?'; $params[] = $_GET['to']; }
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $sql = 'SELECT o.*, u.name as user_name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE '.implode(' AND ',$where).' ORDER BY o.created_at DESC LIMIT '.$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_out(array('ok'=>true,'orders'=>$stmt->fetchAll()));
    }

    if ($action === 'points_log') {
        $stmt = db()->query('SELECT pl.*, u.name as user_name FROM points_log pl LEFT JOIN users u ON u.id=pl.user_id ORDER BY pl.created_at DESC LIMIT 200');
        json_out(array('ok'=>true,'log'=>$stmt->fetchAll()));
    }

    if ($action === 'loyalty_config_get') {
        $stmt = db()->query('SELECT key_name, value, comment FROM loyalty_config ORDER BY key_name');
        json_out(array('ok'=>true,'config'=>$stmt->fetchAll()));
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Суши с Любовью — Панель управления</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#111;color:#eee;min-height:100vh}
  a{color:#e8a847;text-decoration:none}
  /* Layout */
  .layout{display:flex;min-height:100vh}
  .sidebar{width:220px;background:#161616;border-right:1px solid #2a2a2a;padding:20px 0;flex-shrink:0;position:fixed;height:100vh;overflow-y:auto}
  .main{flex:1;margin-left:220px;padding:28px}
  /* Sidebar */
  .sidebar-logo{padding:0 20px 20px;border-bottom:1px solid #2a2a2a;font-weight:700;color:#e8a847;font-size:1rem}
  .nav-link{display:block;padding:12px 20px;color:#aaa;font-size:0.9rem;border-left:3px solid transparent;transition:all 0.15s}
  .nav-link:hover,.nav-link.active{color:#e8a847;border-left-color:#e8a847;background:#1a1a1a}
  .nav-section{padding:14px 20px 6px;font-size:0.72rem;color:#555;text-transform:uppercase;letter-spacing:.08em}
  .logout-link{margin-top:auto;padding:12px 20px;color:#666;font-size:0.85rem;border-top:1px solid #2a2a2a;margin-top:20px;display:block}
  /* Cards */
  .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:28px}
  .card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:20px}
  .card-val{font-size:2rem;font-weight:700;color:#e8a847;margin-bottom:4px}
  .card-label{font-size:0.82rem;color:#666}
  /* Table */
  .section{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:20px;margin-bottom:24px}
  .section h2{font-size:1rem;margin-bottom:16px;color:#eee}
  table{width:100%;border-collapse:collapse;font-size:0.88rem}
  th{text-align:left;color:#666;font-weight:600;padding:8px 10px;border-bottom:1px solid #2a2a2a}
  td{padding:10px;border-bottom:1px solid #1f1f1f;vertical-align:top}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:#1f1f1f}
  /* Badges */
  .badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600}
  .badge-new{background:#2a3a5a;color:#6ab0ff}
  .badge-cooking{background:#3a2a1a;color:#ffaa44}
  .badge-delivering{background:#1a3a2a;color:#44cc88}
  .badge-done{background:#1a2a1a;color:#66bb66}
  .badge-cancelled{background:#2a1a1a;color:#cc6666}
  /* Form */
  .form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px}
  input[type=text],input[type=number],select,textarea{background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:8px 12px;font-size:0.9rem}
  input[type=text]:focus,input[type=number]:focus,select:focus{outline:none;border-color:#e8a847}
  .btn{padding:8px 18px;border-radius:8px;border:none;cursor:pointer;font-size:0.88rem;font-weight:600}
  .btn-primary{background:#e8a847;color:#000}
  .btn-primary:hover{background:#d4913a}
  .btn-sm{padding:4px 12px;font-size:0.8rem}
  .btn-danger{background:#c0392b;color:#fff}
  /* Tabs */
  .tab{display:none}.tab.active{display:block}
  /* Points log */
  .pts-plus{color:#44cc88}.pts-minus{color:#e05a5a}
  /* Config */
  .config-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #2a2a2a}
  .config-row:last-child{border-bottom:none}
  .config-label{flex:1;font-size:0.9rem}
  .config-comment{font-size:0.78rem;color:#555;margin-top:2px}
  .config-input{width:100px}
  /* Search */
  .search-bar{display:flex;gap:10px;margin-bottom:16px}
  .search-bar input{flex:1}
  /* Alerts */
  .alert{padding:10px 16px;border-radius:8px;margin-bottom:12px;font-size:0.88rem}
  .alert-ok{background:#1a3a1a;border:1px solid #2a5a2a;color:#66bb66}
  .alert-err{background:#3a1a1a;border:1px solid #5a2a2a;color:#cc6666}
  @media(max-width:768px){.sidebar{display:none}.main{margin-left:0}}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">🍱 SWL Admin</div>
  <div class="nav-section">Обзор</div>
  <a class="nav-link" href="#" onclick="showTab('dashboard')">📊 Дашборд</a>
  <div class="nav-section">Данные</div>
  <a class="nav-link" href="#" onclick="showTab('users')">👥 Пользователи</a>
  <a class="nav-link" href="#" onclick="showTab('orders')">📦 Заказы</a>
  <a class="nav-link" href="#" onclick="showTab('points')">🎁 Баллы</a>
  <div class="nav-section">Настройки</div>
  <a class="nav-link" href="#" onclick="showTab('loyalty')">⚙️ Лояльность</a>
  <a class="logout-link" href="?logout=1">🚪 Выйти</a>
</aside>

<!-- MAIN -->
<main class="main">
  <div id="alert-box" style="display:none"></div>

  <!-- DASHBOARD -->
  <div class="tab active" id="tab-dashboard">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">📊 Дашборд</h1>
    <div class="cards" id="statsCards">
      <div class="card"><div class="card-val" id="s-users">…</div><div class="card-label">Пользователей</div></div>
      <div class="card"><div class="card-val" id="s-orders-today">…</div><div class="card-label">Заказов сегодня</div></div>
      <div class="card"><div class="card-val" id="s-revenue-today">…</div><div class="card-label">Выручка сегодня</div></div>
      <div class="card"><div class="card-val" id="s-orders-month">…</div><div class="card-label">Заказов в месяце</div></div>
      <div class="card"><div class="card-val" id="s-revenue-month">…</div><div class="card-label">Выручка за месяц</div></div>
    </div>
    <div class="section">
      <h2>Последние заказы</h2>
      <div id="dash-orders"><div style="color:#555">Загрузка…</div></div>
    </div>
  </div>

  <!-- USERS -->
  <div class="tab" id="tab-users">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">👥 Пользователи</h1>
    <div class="search-bar">
      <input type="text" id="userSearch" placeholder="Поиск по имени, телефону, Telegram ID…" oninput="searchUsers()">
    </div>
    <div class="section">
      <table>
        <thead><tr><th>ID</th><th>Имя</th><th>Телефон</th><th>Баллы</th><th>Telegram</th><th>Дата рег.</th><th>Действия</th></tr></thead>
        <tbody id="usersTable"><tr><td colspan="7" style="color:#555">Загрузка…</td></tr></tbody>
      </table>
    </div>
    <!-- Панель управления баллами -->
    <div class="section" id="userPointsPanel" style="display:none">
      <h2>🎁 Начислить / списать баллы</h2>
      <div class="form-row">
        <input type="number" id="ptsDelta" placeholder="Кол-во (+ или -)" style="width:160px">
        <input type="text" id="ptsReason" placeholder="Причина" style="width:200px">
        <button class="btn btn-primary" onclick="applyPoints()">Применить</button>
        <button class="btn" style="background:#333;color:#aaa" onclick="document.getElementById('userPointsPanel').style.display='none'">Отмена</button>
      </div>
      <input type="hidden" id="ptsUserId">
    </div>
  </div>

  <!-- ORDERS -->
  <div class="tab" id="tab-orders">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">📦 Заказы</h1>
    <div class="form-row" style="margin-bottom:16px">
      <select id="orderStatusFilter" onchange="loadOrders()">
        <option value="">Все статусы</option>
        <option value="new">Новые</option>
        <option value="cooking">Готовятся</option>
        <option value="delivering">Доставляются</option>
        <option value="done">Выполнены</option>
        <option value="cancelled">Отменены</option>
      </select>
      <input type="date" id="orderDateFrom" onchange="loadOrders()">
      <input type="date" id="orderDateTo" onchange="loadOrders()">
    </div>
    <div class="section">
      <table>
        <thead><tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Сумма</th><th>Доставка</th><th>Баллы</th><th>Статус</th><th>Действия</th></tr></thead>
        <tbody id="ordersTable"><tr><td colspan="8" style="color:#555">Загрузка…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- POINTS LOG -->
  <div class="tab" id="tab-points">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">🎁 История баллов</h1>
    <div class="section">
      <table>
        <thead><tr><th>Дата</th><th>Пользователь</th><th>Изменение</th><th>Причина</th><th>Заказ</th></tr></thead>
        <tbody id="pointsTable"><tr><td colspan="5" style="color:#555">Загрузка…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- LOYALTY CONFIG -->
  <div class="tab" id="tab-loyalty">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">⚙️ Настройки лояльности</h1>
    <div class="section">
      <div id="loyaltyForm"><div style="color:#555">Загрузка…</div></div>
      <div style="margin-top:20px">
        <button class="btn btn-primary" onclick="saveLoyalty()">💾 Сохранить настройки</button>
      </div>
    </div>
  </div>

</main>
</div>

<script>
var currentTab = 'dashboard';
var selectedUserId = null;

function showTab(name) {
  document.querySelectorAll('.tab').forEach(function(t){ t.classList.remove('active'); });
  document.querySelectorAll('.nav-link').forEach(function(l){ l.classList.remove('active'); });
  var el = document.getElementById('tab-' + name);
  if (el) el.classList.add('active');
  currentTab = name;
  if (name === 'dashboard') { loadStats(); loadDashOrders(); }
  if (name === 'users')     loadUsers();
  if (name === 'orders')    loadOrders();
  if (name === 'points')    loadPoints();
  if (name === 'loyalty')   loadLoyalty();
}

function api(action, method, body, cb) {
  var url = '?action=' + action;
  var opts = { method: method || 'GET' };
  if (body) { opts.headers = {'Content-Type':'application/json'}; opts.body = JSON.stringify(body); }
  fetch(url, opts).then(function(r){ return r.json(); }).then(cb).catch(function(e){ showAlert(e.message, true); });
}

function showAlert(msg, isErr) {
  var box = document.getElementById('alert-box');
  box.className = 'alert ' + (isErr ? 'alert-err' : 'alert-ok');
  box.textContent = msg;
  box.style.display = 'block';
  setTimeout(function(){ box.style.display='none'; }, 3000);
}

function fmt(n){ return parseInt(n).toLocaleString('ru') + ' ₽'; }
function fmtPts(n){ var v=parseInt(n); return (v>0?'+':'')+v+' б.'; }
function fmtDate(s){ return s ? s.slice(0,16).replace('T',' ') : '—'; }
function statusBadge(s){
  var labels={new:'Новый',cooking:'Готовится',delivering:'Доставляется',done:'Выполнен',cancelled:'Отменён'};
  return '<span class="badge badge-'+s+'">'+(labels[s]||s)+'</span>';
}

// === DASHBOARD ===
function loadStats() {
  api('stats','GET',null,function(r){
    if (!r.ok) return;
    document.getElementById('s-users').textContent = r.stats.users_total;
    document.getElementById('s-orders-today').textContent = r.stats.orders_today;
    document.getElementById('s-revenue-today').textContent = fmt(r.stats.revenue_today);
    document.getElementById('s-orders-month').textContent = r.stats.orders_month;
    document.getElementById('s-revenue-month').textContent = fmt(r.stats.revenue_month);
  });
}
function loadDashOrders() {
  fetch('?action=orders_list&limit=10').then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    var html = '<table><thead><tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr></thead><tbody>';
    r.orders.forEach(function(o){
      html += '<tr><td>#'+o.id+'</td><td>'+fmtDate(o.created_at)+'</td><td>'+(o.user_name||'—')+'</td><td>'+fmt(o.total_paid)+'</td><td>'+statusBadge(o.status)+'</td></tr>';
    });
    html += '</tbody></table>';
    document.getElementById('dash-orders').innerHTML = html;
  });
}

// === USERS ===
var allUsers = [];
function loadUsers() {
  fetch('?action=users_list').then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    allUsers = r.users;
    renderUsers(allUsers);
  });
}
function renderUsers(users) {
  var html = '';
  users.forEach(function(u){
    html += '<tr>'
      +'<td>'+u.id+'</td>'
      +'<td>'+(u.name||'—')+'</td>'
      +'<td>'+(u.phone ? formatPhone(u.phone) : '—')+'</td>'
      +'<td style="color:#e8a847;font-weight:600">'+u.points+'</td>'
      +'<td>'+(u.tg_id ? '<a href="tg://user?id='+u.tg_id+'" style="color:#6ab0ff">@'+u.tg_id+'</a>' : '—')+'</td>'
      +'<td>'+fmtDate(u.created_at)+'</td>'
      +'<td><button class="btn btn-sm btn-primary" onclick="openPointsPanel('+u.id+', \''+escHtml(u.name||'')+'\')">± Баллы</button></td>'
      +'</tr>';
  });
  document.getElementById('usersTable').innerHTML = html || '<tr><td colspan="7" style="color:#555">Нет пользователей</td></tr>';
}
function searchUsers() {
  var q = document.getElementById('userSearch').value.toLowerCase();
  if (!q) { renderUsers(allUsers); return; }
  renderUsers(allUsers.filter(function(u){
    return (u.name||'').toLowerCase().includes(q) || (u.phone||'').includes(q) || String(u.tg_id||'').includes(q);
  }));
}
function openPointsPanel(uid, name) {
  selectedUserId = uid;
  document.getElementById('userPointsPanel').style.display = 'block';
  document.getElementById('ptsUserId').value = uid;
  document.getElementById('ptsDelta').value = '';
  document.getElementById('ptsReason').value = '';
  document.getElementById('ptsDelta').focus();
  document.getElementById('userPointsPanel').querySelector('h2').textContent = '🎁 Баллы для: ' + name;
}
function applyPoints() {
  var delta = parseInt(document.getElementById('ptsDelta').value);
  var reason = document.getElementById('ptsReason').value.trim() || 'admin';
  if (!delta) { showAlert('Введите количество баллов', true); return; }
  api('points', 'POST', {user_id: selectedUserId, delta: delta, reason: reason}, function(r){
    if (r.ok) { showAlert('Баллы обновлены'); document.getElementById('userPointsPanel').style.display='none'; loadUsers(); }
    else showAlert(r.error, true);
  });
}
function formatPhone(p) {
  if (p.length===11) return '8 ('+p.slice(1,4)+') '+p.slice(4,7)+'-'+p.slice(7,9)+'-'+p.slice(9,11);
  return p;
}

// === ORDERS ===
function loadOrders() {
  var status = document.getElementById('orderStatusFilter').value;
  var from   = document.getElementById('orderDateFrom').value;
  var to     = document.getElementById('orderDateTo').value;
  var url = '?action=orders_list' + (status?'&status='+status:'') + (from?'&from='+from:'') + (to?'&to='+to:'');
  fetch(url).then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    var html = '';
    r.orders.forEach(function(o){
      html += '<tr>'
        +'<td>#'+o.id+(o.fp_order_id?'<br><small style="color:#555">FP:'+o.fp_order_id+'</small>':'')+'</td>'
        +'<td>'+fmtDate(o.created_at)+'</td>'
        +'<td>'+(o.user_name||'—')+'</td>'
        +'<td>'+fmt(o.total_paid)+'</td>'
        +'<td>'+(o.delivery_cost>0?fmt(o.delivery_cost):'Бесплатно')+'</td>'
        +'<td>'+(o.points_spent>0?'<span class="pts-minus">-'+o.points_spent+'</span>':'')
               +(o.points_earned>0?'<span class="pts-plus">+'+o.points_earned+'</span>':'—')+'</td>'
        +'<td>'+statusBadge(o.status)+'</td>'
        +'<td><select class="btn-sm" onchange="changeOrderStatus('+o.id+',this.value)" style="background:#222;border:1px solid #333;color:#eee;border-radius:6px;padding:4px">'
        +['new','cooking','delivering','done','cancelled'].map(function(s){return '<option value="'+s+'"'+(s===o.status?' selected':'')+'>'+s+'</option>';}).join('')
        +'</select></td>'
        +'</tr>';
    });
    document.getElementById('ordersTable').innerHTML = html || '<tr><td colspan="8" style="color:#555">Нет заказов</td></tr>';
  });
}
function changeOrderStatus(oid, status) {
  api('order_status','POST',{order_id:oid,status:status},function(r){
    if (r.ok) showAlert('Статус обновлён' + (status==='done'?' (баллы начислены если положено)':''));
    else showAlert(r.error,true);
  });
}

// === POINTS LOG ===
function loadPoints() {
  fetch('?action=points_log').then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    var html = '';
    r.log.forEach(function(l){
      html += '<tr>'
        +'<td>'+fmtDate(l.created_at)+'</td>'
        +'<td>'+(l.user_name||'ID '+l.user_id)+'</td>'
        +'<td class="'+(l.delta>0?'pts-plus':'pts-minus')+'">'+fmtPts(l.delta)+'</td>'
        +'<td>'+(l.reason||'—')+'</td>'
        +'<td>'+(l.order_id?'#'+l.order_id:'—')+'</td>'
        +'</tr>';
    });
    document.getElementById('pointsTable').innerHTML = html || '<tr><td colspan="5" style="color:#555">Нет записей</td></tr>';
  });
}

// === LOYALTY CONFIG ===
var loyaltyData = {};
function loadLoyalty() {
  fetch('?action=loyalty_config_get').then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    loyaltyData = r.config;
    var html = '';
    r.config.forEach(function(c){
      html += '<div class="config-row">'
        +'<div class="config-label"><b>'+c.key_name+'</b><div class="config-comment">'+c.comment+'</div></div>'
        +'<input class="config-input" type="number" data-key="'+c.key_name+'" value="'+c.value+'">'
        +'</div>';
    });
    document.getElementById('loyaltyForm').innerHTML = html;
  });
}
function saveLoyalty() {
  var data = {};
  document.querySelectorAll('.config-input').forEach(function(inp){
    data[inp.dataset.key] = inp.value;
  });
  api('loyalty_config','POST',data,function(r){
    if (r.ok) showAlert('Настройки сохранены');
    else showAlert(r.error,true);
  });
}

function escHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }


// Загрузка при старте
showTab('dashboard');
</script>

</body>
</html>

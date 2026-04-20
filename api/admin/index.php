<?php
require_once __DIR__ . '/../config.php';

// Безопасные cookie для сессии (PHP 5.6-совместимо)
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
// Хак для SameSite в PHP 5.6: передаём его через параметр path
session_set_cookie_params(0, '/; SameSite=Strict', '', $https, true);
session_start();

// CSRF-токен
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
}

// Rate limit на вход — 5 попыток / 10 минут / IP
function admin_rl_file($ip) {
    $dir = sys_get_temp_dir() . '/swl_admin_rl';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/' . md5($ip) . '.json';
}
function admin_rl_check($ip) {
    $f = admin_rl_file($ip);
    $now = time();
    $d = (file_exists($f) ? json_decode(file_get_contents($f), true) : null);
    if (!is_array($d)) $d = array('attempts' => array(), 'locked_until' => 0);
    $lock = isset($d['locked_until']) ? $d['locked_until'] : 0;
    if ($now < $lock) return array('ok' => false, 'wait' => $lock - $now);
    return array('ok' => true);
}
function admin_rl_fail($ip) {
    $f = admin_rl_file($ip);
    $now = time();
    $d = (file_exists($f) ? json_decode(file_get_contents($f), true) : null);
    if (!is_array($d)) $d = array('attempts' => array(), 'locked_until' => 0);
    $attempts = isset($d['attempts']) && is_array($d['attempts']) ? $d['attempts'] : array();
    $attempts = array_values(array_filter($attempts, function($t) use ($now) { return $t > $now - 600; }));
    $attempts[] = $now;
    $d['attempts'] = $attempts;
    if (count($attempts) >= 5) { $d['locked_until'] = $now + 600; $d['attempts'] = array(); }
    @file_put_contents($f, json_encode($d));
}
function admin_rl_reset($ip) { @unlink(admin_rl_file($ip)); }

$client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

// Выход
if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

// Вход
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    $rl = admin_rl_check($client_ip);
    if (!$rl['ok']) {
        $login_error = 'Слишком много попыток. Попробуйте через ' . ceil($rl['wait']/60) . ' мин.';
    } elseif (hash_equals(ADMIN_PASS, (string)$_POST['pass'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
        admin_rl_reset($client_ip);
        header('Location: ?tab=users'); exit;
    } else {
        admin_rl_fail($client_ip);
        $login_error = 'Неверный пароль';
    }
}

// Проверка сессии
if (empty($_SESSION['admin'])) {
    // Форма входа
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Админ — вход</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
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

// Проверка CSRF для POST-действий
function admin_csrf_check($data) {
    $token = (is_array($data) && isset($data['_csrf'])) ? $data['_csrf'] : '';
    $sess  = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
    if (!$token || !$sess || !hash_equals($sess, $token)) {
        json_out(array('ok' => false, 'error' => 'CSRF token invalid'));
    }
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Начислить/списать баллы вручную
    if ($action === 'points' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        admin_csrf_check($data);
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
        admin_csrf_check($data);
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

    // Удаление тестового заказа (с каскадом)
    if ($action === 'delete_test_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        admin_csrf_check($data);
        $oid = intval(isset($data['order_id']) ? $data['order_id'] : 0);
        if (!$oid) { json_out(array('ok'=>false,'error'=>'Нет order_id')); }
        $pdo = db();
        $row = $pdo->prepare('SELECT id FROM orders WHERE id=? AND is_test=1');
        $row->execute(array($oid));
        if (!$row->fetch()) { json_out(array('ok'=>false,'error'=>'Заказ не найден или не тестовый')); }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM order_log WHERE order_id=?')->execute(array($oid));
            try { $pdo->prepare('DELETE FROM reviews WHERE order_id=?')->execute(array($oid)); } catch (Exception $e) {}
            $pdo->prepare('DELETE FROM points_log WHERE order_id=?')->execute(array($oid));
            $pdo->prepare('DELETE FROM orders WHERE id=? AND is_test=1')->execute(array($oid));
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            json_out(array('ok'=>false,'error'=>$e->getMessage()));
        }
        json_out(array('ok'=>true));
    }

    // Обновить настройки лояльности
    if ($action === 'loyalty_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        admin_csrf_check($data);
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
            'orders_today'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today' AND is_test=0")->fetchColumn(),
            'revenue_today' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled' AND is_test=0")->fetchColumn(),
            'orders_month'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND is_test=0")->fetchColumn(),
            'revenue_month' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND status!='cancelled' AND is_test=0")->fetchColumn(),
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

    if ($action === 'analytics') {
        $pdo  = db();
        $days = isset($_GET['days']) ? max(1, intval($_GET['days'])) : 7;
        // Воронка за период
        $funnel = $pdo->query("
            SELECT funnel_stage, COUNT(*) as cnt
            FROM analytics_sessions
            WHERE created_at >= NOW() - INTERVAL {$days} DAY
            GROUP BY funnel_stage
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Источники трафика
        $sources = $pdo->query("
            SELECT COALESCE(utm_source,'direct') as src, COUNT(*) as cnt
            FROM analytics_sessions
            WHERE created_at >= NOW() - INTERVAL {$days} DAY
            GROUP BY utm_source ORDER BY cnt DESC LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Устройства
        $devices = $pdo->query("
            SELECT COALESCE(device,'unknown') as device, COUNT(*) as cnt
            FROM analytics_sessions
            WHERE created_at >= NOW() - INTERVAL {$days} DAY
            GROUP BY device
        ")->fetchAll(PDO::FETCH_ASSOC);

        // По дням
        $byDay = $pdo->query("
            SELECT DATE(created_at) as day,
                COUNT(*) as sessions,
                SUM(funnel_stage='ordered') as orders
            FROM analytics_sessions
            WHERE created_at >= NOW() - INTERVAL {$days} DAY
            GROUP BY DATE(created_at) ORDER BY day
        ")->fetchAll(PDO::FETCH_ASSOC);

        json_out(array('ok'=>true,'funnel'=>$funnel,'sources'=>$sources,'devices'=>$devices,'byDay'=>$byDay));
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
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="csrf" content="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8'); ?>">
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
  /* Analytics */
  .funnel-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
  .funnel-bar-wrap{flex:1;background:#222;border-radius:6px;height:22px;overflow:hidden}
  .funnel-bar{height:100%;background:#e8a847;border-radius:6px;transition:width 0.4s}
  .funnel-label{width:110px;font-size:0.85rem;color:#aaa}
  .funnel-val{width:50px;text-align:right;font-weight:700;color:#e8a847}
  .funnel-pct{width:45px;text-align:right;font-size:0.8rem;color:#666}
  .src-table td{padding:6px 12px;border-bottom:1px solid #1a1a1a;font-size:0.88rem}
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
  <a class="nav-link" href="#" onclick="showTab('analytics')">📈 Аналитика</a>
  <div class="nav-section">Смотреть как</div>
  <a class="nav-link" href="open_staff.php?to=owner" target="_blank">🍱 Управляющий</a>
  <a class="nav-link" href="open_staff.php?to=admin" target="_blank">👔 Администратор</a>
  <a class="nav-link" href="open_staff.php?to=operator" target="_blank">📦 Оператор</a>
  <a class="nav-link" href="open_staff.php?to=courier" target="_blank">🛵 Курьер</a>
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
        <thead><tr><th>№ / FP</th><th>Время</th><th>Клиент</th><th>Телефон</th><th>Адрес</th><th>Позиции</th><th>Сумма</th><th>Оплата</th><th>Статус</th><th>Действия</th></tr></thead>
        <tbody id="ordersTable"><tr><td colspan="10" style="color:#555">Загрузка…</td></tr></tbody>
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

  <!-- ANALYTICS -->
  <div class="tab" id="tab-analytics">
    <h1 style="font-size:1.4rem;margin-bottom:20px;color:#e8a847">📈 Аналитика</h1>
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
      <span style="color:#aaa;font-size:0.9rem">Период:</span>
      <select id="analyticsDays" onchange="loadAnalytics()" style="background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:6px 12px">
        <option value="7">7 дней</option>
        <option value="14">14 дней</option>
        <option value="30" selected>30 дней</option>
        <option value="90">90 дней</option>
      </select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <!-- Воронка -->
      <div class="section">
        <h2 style="margin-bottom:16px">🔽 Воронка</h2>
        <div id="analyticsF"><div style="color:#555">Загрузка…</div></div>
      </div>
      <!-- Источники -->
      <div class="section">
        <h2 style="margin-bottom:16px">🌐 Откуда пришли</h2>
        <div id="analyticsS"><div style="color:#555">Загрузка…</div></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <!-- Устройства -->
      <div class="section">
        <h2 style="margin-bottom:16px">📱 Устройства</h2>
        <div id="analyticsD"><div style="color:#555">Загрузка…</div></div>
      </div>
      <!-- По дням -->
      <div class="section">
        <h2 style="margin-bottom:16px">📅 По дням</h2>
        <div id="analyticsDays2" style="font-size:0.85rem"><div style="color:#555">Загрузка…</div></div>
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
  if (name === 'dashboard')  { loadStats(); loadDashOrders(); }
  if (name === 'users')      loadUsers();
  if (name === 'orders')     loadOrders();
  if (name === 'points')     loadPoints();
  if (name === 'loyalty')    loadLoyalty();
  if (name === 'analytics')  loadAnalytics();
}

var CSRF = document.querySelector('meta[name="csrf"]').content;
function api(action, method, body, cb) {
  var url = '?action=' + action;
  var opts = { method: method || 'GET' };
  if (body) {
    body._csrf = CSRF;
    opts.headers = {'Content-Type':'application/json'};
    opts.body = JSON.stringify(body);
  }
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
  fetch('?action=orders_list&limit=50').then(function(r){return r.json();}).then(function(r){
    if (!r.ok) return;
    var html = '<table><thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody>';
    r.orders.forEach(function(o){
      var numLabel = o.is_test == 1
        ? '#000 <span style="background:#f59e0b;color:#000;padding:1px 6px;border-radius:4px;font-size:0.65rem">ТЕСТ</span>'
        : '#' + (o.display_number || o.id);
      var delBtn = o.is_test == 1
        ? '<button onclick="deleteTestOrder('+o.id+')" title="Удалить тестовый заказ" style="background:transparent;border:none;color:#888;font-size:1rem;cursor:pointer;padding:2px 8px">✕</button>'
        : '';
      html += '<tr><td>'+numLabel+'</td><td>'+fmtDate(o.created_at)+'</td><td>'+(o.user_name||o.client_name||'—')+'</td><td>'+fmt(o.total_paid)+'</td><td>'+statusBadge(o.status)+'</td><td>'+delBtn+'</td></tr>';
    });
    html += '</tbody></table>';
    document.getElementById('dash-orders').innerHTML = html;
  });
}

function deleteTestOrder(oid) {
  if (!confirm('Удалить тестовый заказ?')) return;
  api('delete_test_order','POST',{order_id:oid},function(r){
    if (r.ok) { showAlert('🗑 Тестовый заказ удалён'); loadDashOrders(); loadOrders(); loadStats(); }
    else showAlert(r.error||'Ошибка', true);
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
      var testBadge = o.is_test == 1 ? '<span style="background:#f59e0b;color:#000;padding:1px 6px;border-radius:4px;font-size:0.7rem;margin-left:4px">ТЕСТ</span>' : '';
      var fpLine = o.fp_order_id
        ? '<small style="color:#4ade80">FP: '+o.fp_order_id+'</small>'
        : '<small style="color:#f97316">⚠ Нет в FP</small>';
      var phone = o.client_phone ? formatPhone(o.client_phone) : '—';
      var addr = o.delivery_type === 'self' ? '🏃 Самовывоз' : (o.address || '—');
      var itemsCount = 0;
      if (o.items_json) {
        try { var arr = JSON.parse(o.items_json); arr.forEach(function(i){ itemsCount += (i.qty||1); }); } catch(e) {}
      }
      var itemsBtn = o.items_json
        ? '<button class="btn-sm" onclick="showOrderItems('+o.id+')" style="background:#333;border:1px solid #555;color:#eee;border-radius:6px;padding:3px 8px;cursor:pointer">📋 '+itemsCount+' поз.</button>'
        : '<span style="color:#666">—</span>';
      var payIcon = o.pay_type === 'cash' ? '💵 Наличные' : (o.pay_type === 'qr' ? '📱 QR' : '—');
      var numLabel = o.is_test == 1 ? '#000' : '#' + (o.display_number || o.id);
      html += '<tr>'
        +'<td><b>'+numLabel+'</b>'+testBadge+'<br>'+fpLine+'</td>'
        +'<td>'+fmtDate(o.created_at)+'</td>'
        +'<td>'+(o.user_name||o.client_name||'—')+'</td>'
        +'<td>'+phone+'</td>'
        +'<td style="max-width:220px;font-size:0.85rem">'+addr+'</td>'
        +'<td>'+itemsBtn+'</td>'
        +'<td><b>'+fmt(o.total_paid)+'</b>'
          +(o.delivery_cost>0?'<br><small style="color:#888">дост: '+fmt(o.delivery_cost)+'</small>':'')
          +(o.promo_discount>0?'<br><small style="color:#4ade80">−'+fmt(o.promo_discount)+' '+(o.promo_code||'')+'</small>':'')
          +(o.points_spent>0?'<br><small class="pts-minus">−'+o.points_spent+' б.</small>':'')
          +(o.points_earned>0?'<br><small class="pts-plus">+'+o.points_earned+' б.</small>':'')
          +'</td>'
        +'<td style="font-size:0.85rem">'+payIcon+'</td>'
        +'<td>'+statusBadge(o.status)+'</td>'
        +'<td><select class="btn-sm" onchange="changeOrderStatus('+o.id+',this.value)" style="background:#222;border:1px solid #333;color:#eee;border-radius:6px;padding:4px">'
        +['new','cooking','delivering','done','cancelled'].map(function(s){return '<option value="'+s+'"'+(s===o.status?' selected':'')+'>'+({new:'Новый',cooking:'Готовится',delivering:'Доставляется',done:'Выполнен',cancelled:'Отменён'}[s])+'</option>';}).join('')
        +'</select>'
        +(o.is_test == 1 ? ' <button onclick="deleteTestOrder('+o.id+')" title="Удалить тестовый заказ" style="background:transparent;border:1px solid #555;color:#888;border-radius:6px;padding:3px 8px;cursor:pointer;margin-left:4px">✕</button>' : '')
        +'</td>'
        +'</tr>';
    });
    window._ordersCache = r.orders;
    document.getElementById('ordersTable').innerHTML = html || '<tr><td colspan="10" style="color:#555">Нет заказов</td></tr>';
  });
}
function showOrderItems(oid) {
  var orders = window._ordersCache || [];
  var o = null;
  for (var i=0;i<orders.length;i++) if (orders[i].id == oid) { o = orders[i]; break; }
  if (!o) return;
  var items = [];
  try { items = JSON.parse(o.items_json || '[]'); } catch(e) {}
  var html = '<h3 style="margin:0 0 12px 0;color:#e8a847">Заказ #'+o.id+(o.fp_order_id?' (FP: '+o.fp_order_id+')':'')+'</h3>';
  html += '<div style="font-size:0.85rem;color:#888;margin-bottom:12px">'+fmtDate(o.created_at)+' · '+(o.client_name||'—')+' · '+(o.client_phone||'')+'</div>';
  if (o.address) html += '<div style="margin-bottom:8px">📍 '+o.address+'</div>';
  if (o.comment) html += '<div style="margin-bottom:12px;color:#ccc;font-style:italic">💬 '+o.comment+'</div>';
  html += '<table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #333">Позиция</th><th style="text-align:right;padding:6px;border-bottom:1px solid #333">Кол-во</th><th style="text-align:right;padding:6px;border-bottom:1px solid #333">Цена</th><th style="text-align:right;padding:6px;border-bottom:1px solid #333">Сумма</th></tr></thead><tbody>';
  items.forEach(function(it){
    var giftMark = it.isGift ? ' 🎁' : '';
    var sum = it.isGift ? 0 : (it.price*it.qty);
    html += '<tr><td style="padding:6px;border-bottom:1px solid #222">'+it.name+giftMark+'<br><small style="color:#666">арт. '+it.id+'</small></td>'
         +'<td style="text-align:right;padding:6px;border-bottom:1px solid #222">'+it.qty+'</td>'
         +'<td style="text-align:right;padding:6px;border-bottom:1px solid #222">'+(it.isGift?'<span style="color:#4ade80">Подарок</span>':fmt(it.price))+'</td>'
         +'<td style="text-align:right;padding:6px;border-bottom:1px solid #222">'+fmt(sum)+'</td></tr>';
  });
  html += '</tbody></table>';
  html += '<div style="margin-top:12px;text-align:right;font-size:0.9rem">'
       + 'Товары: <b>'+fmt(o.items_total)+'</b><br>'
       + (o.delivery_cost>0?'Доставка: <b>'+fmt(o.delivery_cost)+'</b><br>':'')
       + (o.promo_discount>0?'Скидка '+(o.promo_code||'')+': <b style="color:#4ade80">−'+fmt(o.promo_discount)+'</b><br>':'')
       + (o.points_spent>0?'Баллами: <b style="color:#f97316">−'+o.points_spent+'</b><br>':'')
       + '<span style="font-size:1.1rem">К оплате: <b>'+fmt(o.total_paid)+'</b></span></div>';
  var modal = document.getElementById('orderItemsModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'orderItemsModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
    modal.onclick = function(e){ if (e.target === modal) modal.style.display='none'; };
    document.body.appendChild(modal);
  }
  modal.innerHTML = '<div style="background:#1a1a1a;border:1px solid #333;border-radius:12px;padding:24px;max-width:700px;width:100%;max-height:85vh;overflow-y:auto;position:relative">'
    +'<button onclick="document.getElementById(\'orderItemsModal\').style.display=\'none\'" style="position:absolute;top:12px;right:12px;background:none;border:none;color:#888;font-size:1.4rem;cursor:pointer">✕</button>'
    + html + '</div>';
  modal.style.display = 'flex';
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

function loadAnalytics() {
  var days = document.getElementById('analyticsDays').value || 30;
  fetch('?action=analytics&days=' + days + '&token=' + encodeURIComponent(document.cookie.match(/admin_token=([^;]+)/)?.[1]||''))
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (!d.ok) return;

      // --- Воронка ---
      var stages = [
        {key:'landed',   label:'Зашли на сайт'},
        {key:'browsed',  label:'Листали меню'},
        {key:'cart',     label:'Добавили в корзину'},
        {key:'checkout', label:'Открыли оформление'},
        {key:'ordered',  label:'Оформили заказ'},
      ];
      var top = d.funnel['landed'] || 1;
      var fHtml = stages.map(function(s) {
        var n   = d.funnel[s.key] || 0;
        var pct = Math.round(n / top * 100);
        return '<div class="funnel-row">'
          + '<div class="funnel-label">'+s.label+'</div>'
          + '<div class="funnel-bar-wrap"><div class="funnel-bar" style="width:'+pct+'%"></div></div>'
          + '<div class="funnel-val">'+n+'</div>'
          + '<div class="funnel-pct">'+pct+'%</div>'
          + '</div>';
      }).join('');
      document.getElementById('analyticsF').innerHTML = fHtml;

      // --- Источники ---
      var sHtml = '<table class="src-table" style="width:100%"><tr><th style="text-align:left;color:#666;font-weight:400;padding:6px 12px">Источник</th><th style="text-align:right;color:#666;font-weight:400;padding:6px 12px">Визитов</th></tr>';
      var srcIcons = {vk:'🔵',instagram:'📷',yandex:'🔴',google:'🟢',direct:'🔗',telegram:'✈️','2gis':'🗺️',referral:'🌐'};
      d.sources.forEach(function(s) {
        var icon = srcIcons[s.src] || '🌐';
        sHtml += '<tr><td>'+icon+' '+escHtml(s.src)+'</td><td style="text-align:right;color:#e8a847;font-weight:700">'+s.cnt+'</td></tr>';
      });
      sHtml += '</table>';
      document.getElementById('analyticsS').innerHTML = sHtml;

      // --- Устройства ---
      var dHtml = '<table class="src-table" style="width:100%">';
      d.devices.forEach(function(dv) {
        var icon = dv.device === 'mobile' ? '📱' : '🖥️';
        dHtml += '<tr><td>'+icon+' '+escHtml(dv.device)+'</td><td style="text-align:right;color:#e8a847;font-weight:700">'+dv.cnt+'</td></tr>';
      });
      dHtml += '</table>';
      document.getElementById('analyticsD').innerHTML = dHtml;

      // --- По дням ---
      var bHtml = '<table class="src-table" style="width:100%"><tr>'
        + '<th style="text-align:left;color:#666;font-weight:400;padding:6px 12px">Дата</th>'
        + '<th style="text-align:right;color:#666;font-weight:400;padding:6px 12px">Визиты</th>'
        + '<th style="text-align:right;color:#666;font-weight:400;padding:6px 12px">Заказы</th>'
        + '</tr>';
      d.byDay.forEach(function(row) {
        bHtml += '<tr><td>'+row.day+'</td>'
          + '<td style="text-align:right">'+row.sessions+'</td>'
          + '<td style="text-align:right;color:#e8a847;font-weight:700">'+row.orders+'</td></tr>';
      });
      bHtml += '</table>';
      document.getElementById('analyticsDays2').innerHTML = bHtml;
    });
}

// Загрузка при старте
showTab('dashboard');
</script>

</body>
</html>

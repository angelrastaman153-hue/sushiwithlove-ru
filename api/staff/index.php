<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

session_name('swl_staff');
session_start();

// Выход
if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

// Вход
$login_error = '';
$chosen_role = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swl_login'])) {
    $login = trim(isset($_POST['swl_login']) ? $_POST['swl_login'] : '');
    $pass  = isset($_POST['swl_pass']) ? $_POST['swl_pass'] : '';
    $chosen_role = isset($_POST['swl_role']) ? $_POST['swl_role'] : '';
    $stmt  = db()->prepare('SELECT * FROM staff WHERE login=? AND active=1 LIMIT 1');
    $stmt->execute(array($login));
    $staff = $stmt->fetch();
    if ($staff && password_verify($pass, $staff['password'])) {
        $_SESSION['staff_id']   = $staff['id'];
        $_SESSION['staff_role'] = $staff['role'];
        $_SESSION['staff_name'] = $staff['name'];
        header('Location: ?'); exit;
    }
    $login_error = 'Неверный логин или пароль';
}

// Проверка сессии
if (empty($_SESSION['staff_id'])) {
    $roles = array(
        'owner'    => array('icon' => '🍱', 'label' => 'Управляющий',  'hint' => 'Владелец бизнеса'),
        'admin'    => array('icon' => '👔', 'label' => 'Администратор','hint' => 'Управление бизнесом'),
        'operator' => array('icon' => '📦', 'label' => 'Оператор',     'hint' => 'Работа с заказами'),
        'courier'  => array('icon' => '🛵', 'label' => 'Курьер',       'hint' => 'Доставка'),
    );
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Вход — Суши с Любовью</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#111;color:#eee;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:20px}
  .logo{font-size:1.1rem;font-weight:700;color:#e8a847;margin-bottom:32px;text-align:center}
  .logo span{display:block;font-size:0.82rem;color:#555;font-weight:400;margin-top:4px}
  /* Экран выбора роли */
  .roles{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:420px;width:100%}
  .role-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:22px 16px;text-align:center;cursor:pointer;transition:all 0.15s;user-select:none}
  .role-card:hover{border-color:#e8a847;background:#1f1e1a}
  .role-card:active{transform:scale(.97)}
  .role-icon{font-size:2rem;margin-bottom:10px}
  .role-label{font-size:0.95rem;font-weight:700;color:#eee}
  .role-hint{font-size:0.75rem;color:#555;margin-top:4px}
  /* Экран логина */
  .login-box{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:28px;max-width:360px;width:100%;display:none}
  .login-header{display:flex;align-items:center;gap:12px;margin-bottom:22px}
  .login-icon{font-size:1.8rem}
  .login-title{font-size:1.05rem;font-weight:700;color:#eee}
  .login-sub{font-size:0.8rem;color:#555;margin-top:2px}
  .back-btn{background:none;border:none;color:#555;cursor:pointer;font-size:0.82rem;margin-bottom:18px;padding:0;display:flex;align-items:center;gap:5px}
  .back-btn:hover{color:#e8a847}
  label{display:block;font-size:0.78rem;color:#888;margin-bottom:5px}
  .inp{width:100%;background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:12px;font-size:0.95rem;margin-bottom:14px;outline:none}
  .inp:focus{border-color:#e8a847}
  .submit-btn{width:100%;background:#e8a847;border:none;color:#000;border-radius:8px;padding:12px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:4px}
  .submit-btn:hover{background:#d4913a}
  .err{color:#e05a5a;font-size:0.85rem;background:#2a1a1a;border:1px solid #4a2a2a;padding:10px 14px;border-radius:8px;margin-bottom:14px}
</style></head><body>
<div class="logo">🍱 Суши с Любовью<span>Панель сотрудника</span></div>

<!-- Экран 1: выбор роли -->
<div class="roles" id="screenRoles">
  <?php foreach ($roles as $key => $r): ?>
  <div class="role-card" onclick="selectRole('<?php echo $key; ?>')">
    <div class="role-icon"><?php echo $r['icon']; ?></div>
    <div class="role-label"><?php echo $r['label']; ?></div>
    <div class="role-hint"><?php echo $r['hint']; ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Экран 2: логин/пароль -->
<div class="login-box" id="screenLogin">
  <button class="back-btn" onclick="goBack()">← Назад</button>
  <div class="login-header">
    <div class="login-icon" id="loginIcon"></div>
    <div>
      <div class="login-title" id="loginTitle"></div>
      <div class="login-sub" id="loginSub"></div>
    </div>
  </div>
  <?php if ($login_error): ?>
  <div class="err"><?php echo $login_error; ?></div>
  <?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="swl_role" id="hiddenRole" value="<?php echo htmlspecialchars($chosen_role); ?>">
    <!-- Обманываем автозаполнение браузера -->
    <input type="text" style="display:none" aria-hidden="true">
    <input type="password" style="display:none" aria-hidden="true">
    <label>Логин</label>
    <input class="inp" type="text" name="swl_login" id="loginInp" autocomplete="off" value="">
    <label>Пароль</label>
    <input class="inp" type="password" name="swl_pass" autocomplete="new-password" value="">
    <button class="submit-btn" type="submit">Войти</button>
  </form>
</div>

<script>
var ROLES = {
  owner:    {icon:'🍱', label:'Управляющий',  hint:'Владелец бизнеса'},
  admin:    {icon:'👔', label:'Администратор', hint:'Управление бизнесом'},
  operator: {icon:'📦', label:'Оператор',      hint:'Работа с заказами'},
  courier:  {icon:'🛵', label:'Курьер',        hint:'Доставка'}
};

function selectRole(role) {
  var r = ROLES[role];
  document.getElementById('loginIcon').textContent  = r.icon;
  document.getElementById('loginTitle').textContent = r.label;
  document.getElementById('loginSub').textContent   = r.hint;
  document.getElementById('hiddenRole').value       = role;
  document.getElementById('screenRoles').style.display = 'none';
  document.getElementById('screenLogin').style.display = 'block';
  setTimeout(function(){ document.getElementById('loginInp').focus(); }, 50);
}
function goBack() {
  document.getElementById('screenRoles').style.display = '';
  document.getElementById('screenLogin').style.display = 'none';
  document.getElementById('loginInp').value = '';
}
<?php if ($login_error && $chosen_role): ?>
// Показываем экран логина с ошибкой (после неудачной попытки)
selectRole('<?php echo htmlspecialchars($chosen_role); ?>');
<?php endif; ?>
</script>
</body></html><?php
    exit;
}

$sid   = intval($_SESSION['staff_id']);
$srole = $_SESSION['staff_role'];
$sname = $_SESSION['staff_name'];

// === AJAX ===
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $pdo = db();

    // --- Список заказов ---
    if ($action === 'orders_list') {
        $where  = array('1=1');
        $params = array();
        if ($srole === 'courier') {
            $where[] = "o.status='delivering'";
        } else {
            if (!empty($_GET['status'])) { $where[] = 'o.status=?'; $params[] = $_GET['status']; }
            if (!empty($_GET['from']))   { $where[] = 'DATE(o.created_at)>=?'; $params[] = $_GET['from']; }
            if (!empty($_GET['to']))     { $where[] = 'DATE(o.created_at)<=?'; $params[] = $_GET['to']; }
        }
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $stmt = $pdo->prepare(
            'SELECT o.*, u.name as user_name, u.phone as user_phone
             FROM orders o LEFT JOIN users u ON u.id=o.user_id
             WHERE '.implode(' AND ',$where).'
             ORDER BY o.created_at DESC LIMIT '.$limit
        );
        $stmt->execute($params);
        json_out(array('ok'=>true,'orders'=>$stmt->fetchAll()));
    }

    // --- Смена статуса ---
    if ($action === 'order_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true);
        $oid    = intval($data['order_id']);
        $status = isset($data['status']) ? $data['status'] : '';
        $allowed_all    = array('new','cooking','delivering','done','cancelled');
        $allowed_courier = array('done','cancelled');
        $allowed = ($srole === 'courier') ? $allowed_courier : $allowed_all;
        if (!in_array($status, $allowed)) { json_out(array('ok'=>false,'error'=>'Not allowed')); }

        // Узнаём текущий статус для лога
        $cur = $pdo->query('SELECT status FROM orders WHERE id='.$oid)->fetch();
        $from_status = $cur ? $cur['status'] : '';

        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute(array($status, $oid));

        // Лог действия
        $pdo->prepare('INSERT INTO order_log (order_id, staff_id, staff_name, from_status, to_status, created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute(array($oid, $sid, $sname, $from_status, $status));

        // При выполнении — начислить баллы
        if ($status === 'done') {
            $order = $pdo->query('SELECT * FROM orders WHERE id='.$oid)->fetch();
            if ($order && !$order['points_earned'] && !$order['points_spent'] && $order['user_id']) {
                $earn_pct = intval(loyalty_config('earn_pct'));
                $earned   = intval($order['items_total'] * $earn_pct / 100);
                if ($earned > 0) {
                    $pdo->prepare('UPDATE orders SET points_earned=? WHERE id=?')->execute(array($earned, $oid));
                    $pdo->prepare('UPDATE users SET points=points+?, last_order_at=NOW() WHERE id=?')->execute(array($earned, $order['user_id']));
                    $pdo->prepare('INSERT INTO points_log (user_id, order_id, delta, reason, created_at) VALUES (?,?,?,?,NOW())')->execute(array($order['user_id'], $oid, $earned, 'earn'));
                }
            }
        }
        json_out(array('ok'=>true));
    }

    // --- Список сотрудников (только owner) ---
    if ($action === 'staff_list' && ($srole === 'owner' || $srole === 'admin')) {
        $stmt = $pdo->query('SELECT id,name,login,role,active,created_at FROM staff ORDER BY id');
        json_out(array('ok'=>true,'staff'=>$stmt->fetchAll()));
    }

    // --- Добавить сотрудника (только owner) ---
    if ($action === 'staff_add' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data  = json_decode(file_get_contents('php://input'), true);
        $name  = trim(isset($data['name'])  ? $data['name']  : '');
        $login = trim(isset($data['login']) ? $data['login'] : '');
        $pass  = isset($data['pass'])  ? $data['pass']  : '';
        $role  = isset($data['role'])  ? $data['role']  : 'operator';
        if (!$name || !$login || !$pass) { json_out(array('ok'=>false,'error'=>'Заполните все поля')); }
        // admin может создавать только operator/courier, не owner/admin
        $roles = ($srole === 'owner') ? array('admin','operator','courier') : array('operator','courier');
        if (!in_array($role, $roles)) { json_out(array('ok'=>false,'error'=>'Недостаточно прав для этой роли')); }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $pdo->prepare('INSERT INTO staff (name, login, password, role) VALUES (?,?,?,?)')->execute(array($name, $login, $hash, $role));
            json_out(array('ok'=>true,'id'=>$pdo->lastInsertId()));
        } catch (PDOException $e) {
            json_out(array('ok'=>false,'error'=>'Логин уже занят'));
        }
    }

    // --- Смена пароля сотрудника (только owner) ---
    if ($action === 'staff_pass' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $tid  = intval($data['id']);
        $pass = isset($data['pass']) ? $data['pass'] : '';
        if (!$tid || strlen($pass) < 4) { json_out(array('ok'=>false,'error'=>'Слишком короткий пароль')); }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE staff SET password=? WHERE id=?')->execute(array($hash, $tid));
        json_out(array('ok'=>true));
    }

    // --- Деактивация/активация (только owner, нельзя себя) ---
    if ($action === 'staff_toggle' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $tid  = intval($data['id']);
        if ($tid === $sid) { json_out(array('ok'=>false,'error'=>'Нельзя отключить себя')); }
        // admin не может трогать owner-аккаунты
        if ($srole === 'admin') {
            $target = $pdo->query('SELECT role FROM staff WHERE id='.$tid)->fetch();
            if ($target && in_array($target['role'], array('owner','admin'))) {
                json_out(array('ok'=>false,'error'=>'Недостаточно прав'));
            }
        }
        $cur = $pdo->query('SELECT active FROM staff WHERE id='.$tid)->fetch();
        $new = $cur['active'] ? 0 : 1;
        $pdo->prepare('UPDATE staff SET active=? WHERE id=?')->execute(array($new, $tid));
        json_out(array('ok'=>true,'active'=>$new));
    }

    // --- Лог действий (только owner) ---
    if ($action === 'order_log' && ($srole === 'owner' || $srole === 'admin')) {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
        $stmt = $pdo->query('SELECT * FROM order_log ORDER BY created_at DESC LIMIT '.$limit);
        json_out(array('ok'=>true,'log'=>$stmt->fetchAll()));
    }

    // --- Статистика (только owner) ---
    if ($action === 'stats' && ($srole === 'owner' || $srole === 'admin')) {
        $today = date('Y-m-d');
        $stats = array(
            'orders_today'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn(),
            'revenue_today' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled'")->fetchColumn(),
            'orders_month'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn(),
            'revenue_month' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND status!='cancelled'")->fetchColumn(),
        );
        json_out(array('ok'=>true,'stats'=>$stats));
    }

    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Суши с Любовью — <?php echo htmlspecialchars($sname); ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#111;color:#eee;min-height:100vh}
  /* Header */
  .header{background:#161616;border-bottom:1px solid #2a2a2a;padding:13px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
  .header-left{display:flex;align-items:center;gap:14px}
  .header-title{font-weight:700;color:#e8a847;font-size:1rem}
  .header-user{font-size:0.82rem;color:#666}
  .header-right{display:flex;align-items:center;gap:14px}
  .refresh-info{font-size:0.78rem;color:#444}
  .logout-btn{font-size:0.8rem;color:#555;text-decoration:none}
  .logout-btn:hover{color:#e05a5a}
  /* Tabs */
  .tabs{display:flex;gap:4px;padding:14px 20px;background:#161616;border-bottom:1px solid #2a2a2a;overflow-x:auto}
  .tab-btn{padding:8px 18px;border-radius:20px;border:1px solid #2a2a2a;background:#1a1a1a;color:#888;font-size:0.85rem;cursor:pointer;white-space:nowrap;transition:all 0.15s}
  .tab-btn.active{border-color:#e8a847;background:rgba(232,168,71,0.12);color:#e8a847;font-weight:600}
  .tab-btn .cnt{display:inline-block;background:#e8a847;color:#000;border-radius:10px;padding:1px 7px;font-size:0.72rem;font-weight:700;margin-left:5px}
  /* Status filter */
  .status-tabs{display:flex;gap:6px;padding:12px 20px;overflow-x:auto;border-bottom:1px solid #222;background:#161616}
  .stab{padding:6px 14px;border-radius:16px;border:1px solid #2a2a2a;background:#1a1a1a;color:#888;font-size:0.8rem;cursor:pointer;white-space:nowrap}
  .stab.active{border-color:#e8a847;background:rgba(232,168,71,0.1);color:#e8a847;font-weight:600}
  .stab .cnt{background:#e8a847;color:#000;border-radius:8px;padding:1px 6px;font-size:0.7rem;font-weight:700;margin-left:4px}
  /* Date filter */
  .date-filter{padding:10px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid #222;background:#161616}
  .date-filter label{font-size:0.78rem;color:#555}
  .date-filter input{background:#222;border:1px solid #333;color:#eee;border-radius:7px;padding:5px 10px;font-size:0.82rem}
  /* Pages */
  .page{display:none;padding:16px 20px}
  .page.active{display:block}
  /* Orders */
  .orders-list{display:flex;flex-direction:column;gap:10px;margin-top:14px}
  .order-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:14px}
  .order-card.s-new{border-left:3px solid #6ab0ff}
  .order-card.s-pending{border-left:3px solid #f97316}
  .order-card.s-cooking{border-left:3px solid #ffaa44}
  .order-card.s-delivering{border-left:3px solid #44cc88}
  .order-card.s-done{border-left:3px solid #333;opacity:.65}
  .order-card.s-cancelled{border-left:3px solid #c66;opacity:.55}
  .order-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;flex-wrap:wrap}
  .order-id{font-weight:700;font-size:0.95rem}
  .order-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:0.85rem;margin-bottom:10px}
  .m-label{font-size:0.7rem;color:#555;text-transform:uppercase}
  .m-val{color:#ddd;font-weight:500}
  .m-val.accent{color:#e8a847;font-weight:700}
  .status-btns{display:flex;gap:7px;flex-wrap:wrap;padding-top:10px;border-top:1px solid #252525}
  .s-btn{padding:7px 14px;border-radius:7px;border:none;font-size:0.8rem;font-weight:600;cursor:pointer}
  .s-btn.cooking{background:#3a2a1a;color:#ffaa44;border:1px solid #5a3a1a}
  .s-btn.delivering{background:#1a3a2a;color:#44cc88;border:1px solid #1a5a3a}
  .s-btn.done{background:#1a2a1a;color:#66bb66;border:1px solid #2a4a2a}
  .s-btn.cancelled{background:#2a1a1a;color:#cc6666;border:1px solid #4a2a2a}
  .s-btn.new{background:#1a2a3a;color:#6ab0ff;border:1px solid #1a3a5a}
  /* Badge */
  .badge{display:inline-block;padding:3px 10px;border-radius:16px;font-size:0.72rem;font-weight:600}
  .badge-new{background:#2a3a5a;color:#6ab0ff}
  .badge-pending{background:#2a1a0a;color:#f97316}
  .badge-cooking{background:#3a2a1a;color:#ffaa44}
  .badge-delivering{background:#1a3a2a;color:#44cc88}
  .badge-done{background:#1a2a1a;color:#66bb66}
  .badge-cancelled{background:#2a1a1a;color:#cc6666}
  .badge-owner{background:#3a1a3a;color:#cc88ff}
  .badge-operator{background:#1a2a3a;color:#6ab0ff}
  .badge-courier{background:#1a3a2a;color:#44cc88}
  /* Promo */
  .promo-tag{display:inline-block;padding:2px 8px;background:rgba(232,168,71,0.12);border:1px solid rgba(232,168,71,0.3);border-radius:7px;font-size:0.73rem;color:#e8a847}
  /* Section */
  .section{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:18px;margin-bottom:18px}
  .section h2{font-size:0.95rem;margin-bottom:14px;color:#ccc}
  /* Table */
  table{width:100%;border-collapse:collapse;font-size:0.84rem}
  th{text-align:left;color:#555;font-weight:600;padding:7px 10px;border-bottom:1px solid #2a2a2a}
  td{padding:9px 10px;border-bottom:1px solid #1f1f1f;vertical-align:middle}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:#1f1f1f}
  /* Form */
  .form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px}
  input[type=text],input[type=password],select{background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:8px 12px;font-size:0.88rem}
  input[type=text]:focus,input[type=password]:focus,select:focus{outline:none;border-color:#e8a847}
  .btn{padding:8px 16px;border-radius:7px;border:none;cursor:pointer;font-size:0.84rem;font-weight:600}
  .btn-primary{background:#e8a847;color:#000}
  .btn-sm{padding:5px 12px;font-size:0.78rem}
  .btn-danger{background:#c0392b;color:#fff}
  .btn-ghost{background:#2a2a2a;color:#aaa}
  /* Stats cards */
  .stat-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:18px}
  .stat-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:10px;padding:16px}
  .stat-val{font-size:1.7rem;font-weight:700;color:#e8a847}
  .stat-lbl{font-size:0.78rem;color:#555;margin-top:2px}
  /* Alert */
  .alert{position:fixed;bottom:20px;right:20px;padding:12px 20px;border-radius:10px;font-size:0.88rem;font-weight:600;z-index:100;display:none}
  .alert-ok{background:#1a3a1a;border:1px solid #2a5a2a;color:#66bb66}
  .alert-err{background:#3a1a1a;border:1px solid #5a2a2a;color:#cc6666}
  /* Empty */
  .empty{text-align:center;padding:50px 20px;color:#444;font-size:0.9rem}
  /* Modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:50;align-items:center;justify-content:center}
  .modal-bg.open{display:flex}
  .modal{background:#1a1a1a;border:1px solid #333;border-radius:14px;padding:28px;max-width:380px;width:100%;margin:20px}
  .modal h3{font-size:1rem;margin-bottom:18px;color:#e8a847}
  .modal label{display:block;font-size:0.8rem;color:#888;margin-bottom:5px;margin-top:10px}
  .modal input,.modal select{width:100%}
  .modal-btns{display:flex;gap:10px;margin-top:18px;justify-content:flex-end}
  @media(max-width:600px){.page{padding:12px}.orders-list{gap:8px}}
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <div class="header-title">🍱 Суши с Любовью</div>
    <div class="header-user"><?php echo htmlspecialchars($sname); ?> · <?php
      $roleLabels = array('owner'=>'Управляющий','admin'=>'Администратор','operator'=>'Оператор','courier'=>'Курьер');
      echo isset($roleLabels[$srole]) ? $roleLabels[$srole] : $srole;
    ?></div>
  </div>
  <div class="header-right">
    <?php if ($srole !== 'courier'): ?>
    <span class="refresh-info" id="refreshInfo"></span>
    <?php endif; ?>
    <a class="logout-btn" href="?logout=1">Выйти</a>
  </div>
</div>

<!-- Табы навигации -->
<div class="tabs">
  <div class="tab-btn active" onclick="showPage('orders')" id="ptab-orders">
    📦 Заказы <span class="cnt" id="cnt-active" style="display:none"></span>
  </div>
  <?php if (($srole === 'owner' || $srole === 'admin')): ?>
  <div class="tab-btn" onclick="showPage('staff')" id="ptab-staff">👥 Сотрудники</div>
  <div class="tab-btn" onclick="showPage('log')" id="ptab-log">📋 Активность</div>
  <?php endif; ?>
</div>

<!-- === СТРАНИЦА: ЗАКАЗЫ === -->
<div class="page active" id="page-orders">

  <?php if ($srole !== 'courier'): ?>
  <!-- Фильтр по статусу -->
  <div class="status-tabs">
    <div class="stab active" onclick="setStatusFilter('')" id="stab-all">Все</div>
    <div class="stab" onclick="setStatusFilter('new')" id="stab-new">🆕 Новые</div>
    <div class="stab" onclick="setStatusFilter('pending')" id="stab-pending">⚠️ Без FP</div>
    <div class="stab" onclick="setStatusFilter('cooking')" id="stab-cooking">👨‍🍳 Готовятся</div>
    <div class="stab" onclick="setStatusFilter('delivering')" id="stab-delivering">🛵 Доставка</div>
    <div class="stab" onclick="setStatusFilter('done')" id="stab-done">✅ Выполнены</div>
    <div class="stab" onclick="setStatusFilter('cancelled')" id="stab-cancelled">❌ Отменены</div>
  </div>
  <!-- Фильтр по дате -->
  <div class="date-filter">
    <label>С:</label>
    <input type="date" id="dateFrom" onchange="loadOrders()">
    <label>По:</label>
    <input type="date" id="dateTo" onchange="loadOrders()">
  </div>
  <?php else: ?>
  <div style="padding:14px 20px;font-size:0.85rem;color:#555;border-bottom:1px solid #222;background:#161616">
    🛵 Заказы в доставке — нажмите «Выполнен» после передачи клиенту
  </div>
  <?php endif; ?>

  <div id="ordersList" style="padding:14px 20px">
    <div class="empty">Загрузка…</div>
  </div>
</div>

<?php if (($srole === 'owner' || $srole === 'admin')): ?>
<!-- === СТРАНИЦА: СОТРУДНИКИ === -->
<div class="page" id="page-staff">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-top:4px">
    <div style="font-size:1rem;font-weight:600;color:#ccc">Сотрудники</div>
    <button class="btn btn-primary btn-sm" onclick="openAddStaff()">+ Добавить</button>
  </div>
  <div class="section">
    <table>
      <thead><tr><th>Имя</th><th>Логин</th><th>Роль</th><th>Статус</th><th>Действия</th></tr></thead>
      <tbody id="staffTable"><tr><td colspan="5" style="color:#555">Загрузка…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- === СТРАНИЦА: АКТИВНОСТЬ === -->
<div class="page" id="page-log">
  <div style="font-size:1rem;font-weight:600;color:#ccc;margin-bottom:16px;padding-top:4px">Активность сотрудников</div>
  <div class="section">
    <table>
      <thead><tr><th>Время</th><th>Сотрудник</th><th>Заказ</th><th>Было</th><th>Стало</th></tr></thead>
      <tbody id="logTable"><tr><td colspan="5" style="color:#555">Загрузка…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Модал: добавить сотрудника -->
<div class="modal-bg" id="addStaffModal">
  <div class="modal">
    <h3>Новый сотрудник</h3>
    <label>Имя (полное)</label>
    <input type="text" id="newName" placeholder="Иван Иванов">
    <label>Логин</label>
    <input type="text" id="newLogin" placeholder="ivan">
    <label>Пароль</label>
    <input type="password" id="newPass" placeholder="Минимум 4 символа">
    <label>Роль</label>
    <select id="newRole">
      <?php if ($srole === 'owner'): ?>
      <option value="admin">Администратор</option>
      <?php endif; ?>
      <option value="operator">Оператор</option>
      <option value="courier">Курьер</option>
    </select>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeAddStaff()">Отмена</button>
      <button class="btn btn-primary" onclick="addStaff()">Добавить</button>
    </div>
  </div>
</div>

<!-- Модал: смена пароля -->
<div class="modal-bg" id="passModal">
  <div class="modal">
    <h3>Сменить пароль</h3>
    <input type="hidden" id="passStaffId">
    <label>Новый пароль</label>
    <input type="password" id="newPassChange" placeholder="Минимум 4 символа">
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closePassModal()">Отмена</button>
      <button class="btn btn-primary" onclick="savePass()">Сохранить</button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="alert" id="alertBox"></div>

<script>
var ROLE          = '<?php echo $srole; ?>';
var currentPage   = 'orders';
var statusFilter  = '';
var refreshTimer  = null;
var REFRESH_SEC   = 30;
var countdown     = REFRESH_SEC;

// --- Навигация по страницам ---
function showPage(name) {
  document.querySelectorAll('.page').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
  var p = document.getElementById('page-' + name);
  if (p) p.classList.add('active');
  var b = document.getElementById('ptab-' + name);
  if (b) b.classList.add('active');
  currentPage = name;
  if (name === 'orders') loadOrders();
  if (name === 'staff')  loadStaff();
  if (name === 'log')    loadLog();
}

// --- Фильтр статуса ---
function setStatusFilter(s) {
  statusFilter = s;
  document.querySelectorAll('.stab').forEach(function(t){ t.classList.remove('active'); });
  var id = 'stab-' + (s || 'all');
  var el = document.getElementById(id);
  if (el) el.classList.add('active');
  loadOrders();
}

// === ЗАКАЗЫ ===
function loadOrders() {
  var url = '?action=orders_list';
  if (ROLE !== 'courier') {
    if (statusFilter) url += '&status=' + statusFilter;
    var df = document.getElementById('dateFrom');
    var dt = document.getElementById('dateTo');
    if (df && df.value) url += '&from=' + df.value;
    if (dt && dt.value) url += '&to=' + dt.value;
  }
  fetch(url).then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    renderOrders(r.orders);
    updateStatusCounts(r.orders);
  });
  resetRefresh();
}

function updateStatusCounts(orders) {
  var counts = {new:0, pending:0, cooking:0, delivering:0};
  var active = 0;
  orders.forEach(function(o) {
    if (counts[o.status] !== undefined) { counts[o.status]++; active++; }
  });
  var ca = document.getElementById('cnt-active');
  if (ca) { ca.textContent = active; ca.style.display = active ? '' : 'none'; }
  ['new','pending','cooking','delivering'].forEach(function(s) {
    var el = document.getElementById('stab-' + s);
    if (!el) return;
    var old = el.querySelector('.cnt');
    if (old) old.remove();
    if (counts[s]) {
      var sp = document.createElement('span');
      sp.className = 'cnt'; sp.textContent = counts[s];
      el.appendChild(sp);
    }
  });
}

function renderOrders(orders) {
  if (!orders || !orders.length) {
    document.getElementById('ordersList').innerHTML = '<div class="empty">Нет заказов</div>';
    return;
  }
  var sLabels = {new:'Новый',pending:'⚠️ Без FP',cooking:'Готовится',delivering:'Доставляется',done:'Выполнен',cancelled:'Отменён'};
  var nextMap = {
    new:       [{s:'cooking',    l:'👨‍🍳 Готовится'},{s:'cancelled',l:'❌ Отмена'}],
    pending:   [{s:'cooking',    l:'👨‍🍳 Готовится'},{s:'cancelled',l:'❌ Отмена'}],
    cooking:   [{s:'delivering', l:'🛵 Доставляется'},{s:'done',l:'✅ Выполнен'},{s:'cancelled',l:'❌ Отмена'}],
    delivering:[{s:'done',       l:'✅ Выполнен'},{s:'cancelled',l:'❌ Отмена'}],
    done:[], cancelled:[]
  };
  var courierBtns = {delivering:[{s:'done',l:'✅ Выполнен'}]};

  var html = '<div class="orders-list">';
  orders.forEach(function(o) {
    var st   = o.status || 'new';
    var btns = (ROLE === 'courier' ? (courierBtns[st] || []) : (nextMap[st] || []))
      .map(function(b){ return '<button class="s-btn '+b.s+'" onclick="changeStatus('+o.id+',\''+b.s+'\')">'+b.l+'</button>'; }).join('');

    var client = o.user_name ? esc(o.user_name) : '—';
    var phone  = o.user_phone ? fmt_phone(o.user_phone) : '';
    var fpInfo = o.fp_order_id
      ? '<small style="color:#555;font-size:0.72rem">FP:'+o.fp_order_id+'</small>'
      : '<small style="color:#f97316;font-size:0.72rem">⚠️ Нет в FP</small>';

    html += '<div class="order-card s-'+st+'">'
      + '<div class="order-head">'
      +   '<div><span class="order-id">#'+o.id+'</span> '+fpInfo+'</div>'
      +   '<div style="display:flex;gap:8px;align-items:center">'
      +     '<span class="badge badge-'+st+'">'+(sLabels[st]||st)+'</span>'
      +     '<span style="font-size:0.78rem;color:#555">'+fmtDate(o.created_at)+'</span>'
      +   '</div>'
      + '</div>'
      + '<div class="order-meta">'
      +   '<div><div class="m-label">Клиент</div><div class="m-val">'+client+(phone?' · '+phone:'')+'</div></div>'
      +   '<div><div class="m-label">Сумма</div><div class="m-val accent">'+fmt(o.items_total)+'</div></div>'
      +   '<div><div class="m-label">Доставка</div><div class="m-val">'+(o.delivery_cost>0?fmt(o.delivery_cost):'<span style="color:#44cc88">Бесплатно</span>')+'</div></div>'
      +   '<div><div class="m-label">Итого</div><div class="m-val accent">'+fmt(o.total_paid)+'</div></div>'
      + (o.promo_code ? '<div><div class="m-label">Промокод</div><div class="m-val"><span class="promo-tag">'+esc(o.promo_code)+'</span></div></div>' : '')
      + '</div>'
      + (btns ? '<div class="status-btns">'+btns+'</div>' : '')
      + '</div>';
  });
  html += '</div>';
  document.getElementById('ordersList').innerHTML = html;
}

function changeStatus(oid, status) {
  fetch('?action=order_status', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({order_id:oid, status:status})
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert(status==='done'?'✅ Выполнен':('Статус: '+status), false); loadOrders(); }
    else showAlert(r.error||'Ошибка', true);
  });
}

// === СОТРУДНИКИ (owner) ===
function loadStaff() {
  fetch('?action=staff_list').then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    var rLabels = {owner:'Управляющий', operator:'Оператор', courier:'Курьер'};
    var html = '';
    r.staff.forEach(function(s) {
      var activeBtn = s.active
        ? '<button class="btn btn-sm btn-danger" onclick="toggleStaff('+s.id+')">Откл.</button>'
        : '<button class="btn btn-sm btn-ghost" onclick="toggleStaff('+s.id+')">Вкл.</button>';
      html += '<tr style="'+(s.active?'':'opacity:.5')+'">'
        + '<td><b>'+esc(s.name)+'</b></td>'
        + '<td style="color:#888">'+esc(s.login)+'</td>'
        + '<td><span class="badge badge-'+s.role+'">'+(rLabels[s.role]||s.role)+'</span></td>'
        + '<td>'+(s.active?'<span style="color:#44cc88">Активен</span>':'<span style="color:#666">Откл.</span>')+'</td>'
        + '<td style="display:flex;gap:6px;flex-wrap:wrap">'
        +   '<button class="btn btn-sm btn-ghost" onclick="openPassModal('+s.id+')">Пароль</button>'
        +   activeBtn
        + '</td>'
        + '</tr>';
    });
    document.getElementById('staffTable').innerHTML = html || '<tr><td colspan="5" style="color:#555">Нет сотрудников</td></tr>';
  });
}
function openAddStaff() { document.getElementById('addStaffModal').classList.add('open'); }
function closeAddStaff(){ document.getElementById('addStaffModal').classList.remove('open'); }
function addStaff() {
  var payload = {
    name:  document.getElementById('newName').value.trim(),
    login: document.getElementById('newLogin').value.trim(),
    pass:  document.getElementById('newPass').value,
    role:  document.getElementById('newRole').value
  };
  fetch('?action=staff_add', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
  .then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('Сотрудник добавлен', false); closeAddStaff(); loadStaff(); }
    else showAlert(r.error||'Ошибка', true);
  });
}
function openPassModal(id) {
  document.getElementById('passStaffId').value = id;
  document.getElementById('newPassChange').value = '';
  document.getElementById('passModal').classList.add('open');
}
function closePassModal(){ document.getElementById('passModal').classList.remove('open'); }
function savePass() {
  var id   = parseInt(document.getElementById('passStaffId').value);
  var pass = document.getElementById('newPassChange').value;
  fetch('?action=staff_pass', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,pass:pass})})
  .then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('Пароль обновлён', false); closePassModal(); }
    else showAlert(r.error||'Ошибка', true);
  });
}
function toggleStaff(id) {
  fetch('?action=staff_toggle', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})})
  .then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert(r.active?'Сотрудник активирован':'Сотрудник отключён', false); loadStaff(); }
    else showAlert(r.error||'Ошибка', true);
  });
}

// === ЛОГ АКТИВНОСТИ (owner) ===
function loadLog() {
  fetch('?action=order_log').then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    var sLabels = {new:'Новый',pending:'Без FP',cooking:'Готовится',delivering:'Доставляется',done:'Выполнен',cancelled:'Отменён'};
    var html = '';
    r.log.forEach(function(l) {
      html += '<tr>'
        + '<td style="white-space:nowrap">'+fmtDate(l.created_at)+'</td>'
        + '<td>'+esc(l.staff_name||'—')+'</td>'
        + '<td>#'+l.order_id+'</td>'
        + '<td style="color:#888">'+(sLabels[l.from_status]||l.from_status||'—')+'</td>'
        + '<td><span class="badge badge-'+(l.to_status||'new')+'">'+(sLabels[l.to_status]||l.to_status||'—')+'</span></td>'
        + '</tr>';
    });
    document.getElementById('logTable').innerHTML = html || '<tr><td colspan="5" style="color:#555">Нет записей</td></tr>';
  });
}

// === Авторефреш (не для курьера) ===
function resetRefresh() {
  if (ROLE === 'courier') return;
  if (refreshTimer) clearInterval(refreshTimer);
  countdown = REFRESH_SEC;
  updateRefreshInfo();
  refreshTimer = setInterval(function() {
    countdown--;
    updateRefreshInfo();
    if (countdown <= 0 && currentPage === 'orders') loadOrders();
  }, 1000);
}
function updateRefreshInfo() {
  var el = document.getElementById('refreshInfo');
  if (el) el.textContent = countdown > 0 ? 'Обновление через ' + countdown + ' с' : '…';
}

// === Утилиты ===
function showAlert(msg, isErr) {
  var box = document.getElementById('alertBox');
  box.className = 'alert ' + (isErr ? 'alert-err' : 'alert-ok');
  box.textContent = msg;
  box.style.display = 'block';
  setTimeout(function(){ box.style.display='none'; }, 3000);
}
function fmt(n){ return parseInt(n).toLocaleString('ru')+' ₽'; }
function fmtDate(s){ return s?s.slice(0,16).replace('T',' '):'—'; }
function fmt_phone(p){ if(p&&p.length===11) return '8 ('+p.slice(1,4)+') '+p.slice(4,7)+'-'+p.slice(7,9)+'-'+p.slice(9,11); return p||''; }
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Закрытие модалов по клику вне
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-bg')) {
    e.target.classList.remove('open');
  }
});

// Старт
loadOrders();
</script>
</body>
</html>

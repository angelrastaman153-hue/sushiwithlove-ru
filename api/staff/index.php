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
$role_labels_auth = array('owner'=>'Управляющий','admin'=>'Администратор','operator'=>'Оператор','courier'=>'Курьер');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swl_login'])) {
    $login       = trim(isset($_POST['swl_login']) ? $_POST['swl_login'] : '');
    $pass        = isset($_POST['swl_pass'])  ? $_POST['swl_pass']  : '';
    $chosen_role = isset($_POST['swl_role'])  ? $_POST['swl_role']  : '';
    $stmt = db()->prepare('SELECT * FROM staff WHERE login=? AND active=1 LIMIT 1');
    $stmt->execute(array($login));
    $staff = $stmt->fetch();
    if ($staff && password_verify($pass, $staff['password'])) {
        // Проверяем есть ли у сотрудника выбранная роль
        $staff_roles = array_map('trim', explode(',', $staff['role']));
        if ($chosen_role && !in_array($chosen_role, $staff_roles)) {
            $rname = isset($role_labels_auth[$chosen_role]) ? $role_labels_auth[$chosen_role] : $chosen_role;
            $login_error = 'У вас нет доступа как «' . $rname . '»';
        } else {
            $_SESSION['staff_id']   = $staff['id'];
            $_SESSION['staff_role'] = $chosen_role ? $chosen_role : $staff_roles[0];
            $_SESSION['staff_name'] = $staff['name'];
            header('Location: ?'); exit;
        }
    } else {
        $login_error = 'Неверный логин или пароль';
    }
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
$srole_actual = $_SESSION['staff_role']; // реальная роль сотрудника (для проверки прав)
$srole = $srole_actual;                    // эффективная роль (может быть переопределена)
$sname = $_SESSION['staff_name'];

// "Смотреть как" — владелец может временно переключаться на любую роль
$view_as = '';
if ($srole_actual === 'owner' && isset($_GET['view'])) {
    $v = $_GET['view'];
    if (in_array($v, array('admin', 'operator', 'courier'), true)) {
        $srole = $v;
        $view_as = $v; // для отображения индикатора
    }
}

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

        // При выполнении — начислить баллы + создать токен отзыва
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
            // Создаём токен отзыва (только для не-тестовых заказов)
            if (!$order['is_test']) {
                $phone = $order['client_phone'];
                $name  = $order['client_name'];
                if (!$phone && $order['user_id']) {
                    $u = $pdo->prepare('SELECT phone, name FROM users WHERE id=?');
                    $u->execute(array($order['user_id']));
                    $urow = $u->fetch();
                    if ($urow) { $phone = $urow['phone']; $name = $urow['name']; }
                }
                $existing = $pdo->prepare('SELECT token FROM reviews WHERE order_id=?');
                $existing->execute(array($oid));
                if (!$existing->fetch()) {
                    $token = bin2hex(random_bytes(16));

                    // Вычисляем время отправки: через 1 час, но не позже 22:00 Кургана (UTC+5)
                    $send_utc = time() + 3600;
                    $send_h   = intval(gmdate('H', $send_utc + 18000)); // час в Кургане
                    if ($send_h < 10 || $send_h >= 22) {
                        // Следующее утро 10:00 Кургана = 05:00 UTC
                        $d = gmdate('Y-m-d', time() + 18000 + 86400);
                        $send_utc = strtotime($d . ' 05:00:00');
                    }
                    $scheduled_for = gmdate('Y-m-d H:i:s', $send_utc);

                    $pdo->prepare('INSERT INTO reviews (order_id, token, phone, source, scheduled_for) VALUES (?,?,?,?,?)')
                       ->execute(array($oid, $token, $phone ?: null, 'site', $scheduled_for));

                    $link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' . $token;
                    $client_info = ($name ?: ($phone ?: 'клиент'));
                    $when = ($send_h >= 10 && $send_h < 22)
                        ? 'через ~1 час'
                        : ('завтра в 10:00 — ' . gmdate('d.m', $send_utc + 18000));
                    json_out(array('ok'=>true, 'review_link' => $link,
                        'review_scheduled' => $scheduled_for, 'review_when' => $when));
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
        $pass  = isset($data['pass'])   ? $data['pass']   : '';
        $roles_in = isset($data['roles']) ? $data['roles'] : array();
        if (!$name || !$login || !$pass) { json_out(array('ok'=>false,'error'=>'Заполните все поля')); }
        if (empty($roles_in))            { json_out(array('ok'=>false,'error'=>'Выберите хотя бы одну роль')); }
        // Допустимые роли: admin может назначать только operator/courier
        $allowed = ($srole === 'owner') ? array('admin','operator','courier') : array('operator','courier');
        $clean_roles = array();
        foreach ($roles_in as $r) {
            if (in_array($r, $allowed)) $clean_roles[] = $r;
        }
        if (empty($clean_roles)) { json_out(array('ok'=>false,'error'=>'Недостаточно прав для выбранных ролей')); }
        $role_val = implode(',', $clean_roles);
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $pdo->prepare('INSERT INTO staff (name, login, password, role) VALUES (?,?,?,?)')->execute(array($name, $login, $hash, $role_val));
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
        // admin не может трогать owner/admin-аккаунты
        if ($srole === 'admin') {
            $target = $pdo->query('SELECT role FROM staff WHERE id='.$tid)->fetch();
            if ($target) {
                $troles = array_map('trim', explode(',', $target['role']));
                if (array_intersect($troles, array('owner','admin'))) {
                    json_out(array('ok'=>false,'error'=>'Недостаточно прав'));
                }
            }
        }
        $cur = $pdo->query('SELECT active FROM staff WHERE id='.$tid)->fetch();
        $new = $cur['active'] ? 0 : 1;
        $pdo->prepare('UPDATE staff SET active=? WHERE id=?')->execute(array($new, $tid));
        json_out(array('ok'=>true,'active'=>$new));
    }

    // --- Редактировать сотрудника (имя + роли) ---
    if ($action === 'staff_edit' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data     = json_decode(file_get_contents('php://input'), true);
        $tid      = intval($data['id']);
        $name     = trim(isset($data['name']) ? $data['name'] : '');
        $roles_in = isset($data['roles']) ? $data['roles'] : array();
        if (!$tid)  { json_out(array('ok'=>false,'error'=>'Не указан сотрудник')); }
        if (!$name) { json_out(array('ok'=>false,'error'=>'Введите имя')); }
        if (empty($roles_in)) { json_out(array('ok'=>false,'error'=>'Выберите хотя бы одну роль')); }
        // Нельзя редактировать себя (роли), нельзя admin трогать owner/admin
        if ($srole === 'admin') {
            $target = $pdo->query('SELECT role FROM staff WHERE id='.$tid)->fetch();
            if ($target) {
                $troles = array_map('trim', explode(',', $target['role']));
                if (array_intersect($troles, array('owner','admin'))) {
                    json_out(array('ok'=>false,'error'=>'Недостаточно прав'));
                }
            }
        }
        $allowed = ($srole === 'owner') ? array('admin','operator','courier') : array('operator','courier');
        // owner не может снять с себя owner через этот экшен
        if ($tid === $sid) { $allowed[] = 'owner'; }
        $clean = array();
        foreach ($roles_in as $r) { if (in_array($r, $allowed)) $clean[] = $r; }
        if (empty($clean)) { json_out(array('ok'=>false,'error'=>'Нет допустимых ролей')); }
        $pdo->prepare('UPDATE staff SET name=?, role=? WHERE id=?')->execute(array($name, implode(',', $clean), $tid));
        json_out(array('ok'=>true));
    }

    // --- Ручное создание ссылки отзыва (owner/admin) ---
    if ($action === 'manual_review' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($srole === 'owner' || $srole === 'admin')) {
        try {
            $data  = json_decode(file_get_contents('php://input'), true);
            $phone = isset($data['phone']) ? preg_replace('/\D/','',$data['phone']) : '';
            $name  = isset($data['name'])  ? trim($data['name']) : '';
            if (!$phone) { json_out(array('ok'=>false,'error'=>'Нет телефона')); }
            // PHP 5.6 совместимость
            if (function_exists('random_bytes')) {
                $token = bin2hex(random_bytes(16));
            } else {
                $token = md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true));
            }
            $pdo->prepare('INSERT INTO reviews (order_id, token, phone, source) VALUES (?,?,?,?)')
               ->execute(array(0, $token, $phone, 'manual'));
            $link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' . $token;
            require_once __DIR__ . '/../vk_notify.php';
            vk_send("❤️ ОТЗЫВЫ С ЛЮБОВЬЮ — ручной запрос\nКлиент: " . ($name ?: $phone) . "\nСсылка:\n" . $link);
            json_out(array('ok'=>true, 'token'=>$token, 'link'=>$link));
        } catch (Exception $e) {
            json_out(array('ok'=>false,'error'=>$e->getMessage()));
        }
    }

    // --- Удаление тестового заказа (только owner) ---
    if ($action === 'delete_test_order' && $_SERVER['REQUEST_METHOD'] === 'POST' && $srole === 'owner') {
        $data = json_decode(file_get_contents('php://input'), true);
        $oid  = intval(isset($data['order_id']) ? $data['order_id'] : 0);
        if (!$oid) { json_out(array('ok'=>false,'error'=>'Нет order_id')); }
        // Убеждаемся что это тестовый заказ
        $row = $pdo->prepare('SELECT id FROM orders WHERE id=? AND is_test=1');
        $row->execute(array($oid));
        if (!$row->fetch()) { json_out(array('ok'=>false,'error'=>'Заказ не найден или не тестовый')); }
        $pdo->prepare('DELETE FROM orders WHERE id=? AND is_test=1')->execute(array($oid));
        json_out(array('ok'=>true));
    }

    // --- Удаление любого заказа (только owner) ---
    if ($action === 'delete_order' && $_SERVER['REQUEST_METHOD'] === 'POST' && $srole === 'owner') {
        $data = json_decode(file_get_contents('php://input'), true);
        $oid  = intval(isset($data['order_id']) ? $data['order_id'] : 0);
        if (!$oid) { json_out(array('ok'=>false,'error'=>'Нет order_id')); }
        $pdo->prepare('DELETE FROM reviews WHERE order_id=?')->execute(array($oid));
        $pdo->prepare('DELETE FROM order_log WHERE order_id=?')->execute(array($oid));
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute(array($oid));
        json_out(array('ok'=>true));
    }

    // === МЕНЮ ===

    // --- Список позиций меню ---
    if ($action === 'menu_list' && ($srole === 'owner' || $srole === 'admin')) {
        $cats  = $pdo->query('SELECT * FROM menu_categories ORDER BY sort_order, name')->fetchAll();
        $items = $pdo->query('SELECT * FROM menu_items ORDER BY sort_order, name')->fetchAll();
        json_out(array('ok'=>true, 'categories'=>$cats, 'items'=>$items));
    }

    // --- Переключить активность позиции ---
    if ($action === 'menu_toggle_active' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval(isset($d['id']) ? $d['id'] : 0);
        if (!$id) json_out(array('ok'=>false,'error'=>'no id'));
        $cur = $pdo->prepare('SELECT is_active FROM menu_items WHERE id=?');
        $cur->execute(array($id));
        $row = $cur->fetch();
        if (!$row) json_out(array('ok'=>false,'error'=>'not found'));
        $new = $row['is_active'] ? 0 : 1;
        $pdo->prepare('UPDATE menu_items SET is_active=? WHERE id=?')->execute(array($new, $id));
        json_out(array('ok'=>true,'is_active'=>$new));
    }

    // --- Обновить позицию меню ---
    if ($action === 'menu_update' && ($srole === 'owner' || $srole === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id']);
        if (!$id) json_out(array('ok'=>false,'error'=>'no id'));
        $pdo->prepare('UPDATE menu_items SET fp_article_id=?, description=?, flavor_description=?, image_url=?, price=?, weight_grams=?, pieces_count=?, is_stop=?, updated_at=NOW() WHERE id=?')
           ->execute(array(
               isset($d['fp_article_id']) && $d['fp_article_id'] !== '' ? intval($d['fp_article_id']) : null,
               isset($d['description'])        ? trim($d['description'])        : null,
               isset($d['flavor_description']) ? trim($d['flavor_description']) : null,
               isset($d['image_url'])     ? trim($d['image_url'])     : null,
               isset($d['price'])         ? floatval($d['price'])     : 0,
               isset($d['weight_grams']) && $d['weight_grams'] !== null && $d['weight_grams'] !== '' ? intval($d['weight_grams']) : null,
               isset($d['pieces_count']) && $d['pieces_count'] !== null && $d['pieces_count'] !== '' ? intval($d['pieces_count']) : null,
               isset($d['is_stop'])       ? intval($d['is_stop'])     : 0,
               $id
           ));
        json_out(array('ok'=>true));
    }

    // --- Импорт/синхронизация из crm-love (только owner) ---
    if ($action === 'menu_import' && $srole === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $CRM_URL = 'https://test.xn--90acqmqobo9b7bse.xn--p1ai/api/v1/public/menu';
        $CRM_IMG = 'https://test.xn--90acqmqobo9b7bse.xn--p1ai';
        $ctx = stream_context_create(array('http'=>array('timeout'=>15,'ignore_errors'=>true),
            'ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false)));
        $raw = @file_get_contents($CRM_URL, false, $ctx);
        if (!$raw) json_out(array('ok'=>false,'error'=>'Нет ответа от crm-love'));
        $menu = json_decode($raw, true);
        if (empty($menu['categories'])) json_out(array('ok'=>false,'error'=>'Пустой ответ'));

        $imported = 0; $updated = 0;
        foreach ($menu['categories'] as $cat) {
            $pdo->prepare('INSERT INTO menu_categories (crm_id,name,slug,sort_order,is_active) VALUES (?,?,?,?,1)
                ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug), sort_order=VALUES(sort_order)')
               ->execute(array($cat['id'], $cat['name'], $cat['slug'], $cat['sort_order']));
            $catRow = $pdo->prepare('SELECT id FROM menu_categories WHERE crm_id=?');
            $catRow->execute(array($cat['id']));
            $catId = $catRow->fetchColumn();
            foreach ($cat['items'] as $i => $item) {
                $imgUrl = $item['image_url'] ? $CRM_IMG . $item['image_url'] : null;
                $exists = $pdo->prepare('SELECT id FROM menu_items WHERE crm_id=?');
                $exists->execute(array($item['id']));
                if ($exists->fetchColumn()) {
                    // Обновляем только название, цену, вес, картинку — НЕ трогаем fp_article_id и description
                    $pdo->prepare('UPDATE menu_items SET name=?, price=?, weight_grams=?, is_active=1, sort_order=? WHERE crm_id=?')
                       ->execute(array($item['name'], $item['base_price'], $item['weight_grams'], $i, $item['id']));
                    $updated++;
                } else {
                    $pdo->prepare('INSERT INTO menu_items (crm_id,category_id,name,price,weight_grams,image_url,is_stop,is_active,sort_order) VALUES (?,?,?,?,?,?,?,1,?)')
                       ->execute(array($item['id'], $catId, $item['name'], $item['base_price'], $item['weight_grams'], $imgUrl, $item['is_stop']?1:0, $i));
                    $imported++;
                }
            }
        }
        json_out(array('ok'=>true, 'imported'=>$imported, 'updated'=>$updated));
    }

    // --- Список отзывов (только owner/admin) ---
    if ($action === 'reviews_list' && ($srole === 'owner' || $srole === 'admin')) {
        $where = array('1=1');
        $params = array();
        if (!empty($_GET['only_negative'])) { $where[] = 'r.rating IS NOT NULL AND r.rating <= 4'; }
        $stmt = $pdo->prepare(
            'SELECT r.*, o.fp_order_id FROM reviews r LEFT JOIN orders o ON o.id=r.order_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT 200'
        );
        $stmt->execute($params);
        json_out(array('ok'=>true,'reviews'=>$stmt->fetchAll()));
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
            'orders_today'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today' AND (is_test IS NULL OR is_test=0)")->fetchColumn(),
            'revenue_today' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled' AND (is_test IS NULL OR is_test=0)")->fetchColumn(),
            'orders_month'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND (is_test IS NULL OR is_test=0)")->fetchColumn(),
            'revenue_month' => $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM orders WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND status!='cancelled' AND (is_test IS NULL OR is_test=0)")->fetchColumn(),
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
  .order-card.order-test{border-style:dashed;opacity:.8}
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
      if ($view_as) echo ' <span style="color:#e8a847;font-weight:700">· 👁️ режим просмотра</span>';
    ?></div>
  </div>
  <div class="header-right">
    <?php if ($srole !== 'courier'): ?>
    <span class="refresh-info" id="refreshInfo"></span>
    <?php endif; ?>
    <?php if ($view_as): ?>
    <a class="logout-btn" href="/api/admin/" style="background:#2a1a1a;color:#e8a847;border-color:#3a2a1a">← Вернуться в БОСС-панель</a>
    <?php else: ?>
    <a class="logout-btn" href="?logout=1">Выйти</a>
    <?php endif; ?>
  </div>
</div>

<!-- Табы навигации -->
<div class="tabs">
  <div class="tab-btn active" onclick="showPage('orders')" id="ptab-orders">
    📦 Заказы <span class="cnt" id="cnt-active" style="display:none"></span>
  </div>
  <?php if (($srole === 'owner' || $srole === 'admin')): ?>
  <div class="tab-btn" onclick="showPage('reviews')" id="ptab-reviews">❤️ Отзывы с Любовью <span class="cnt" id="cnt-negative" style="display:none"></span></div>
  <div class="tab-btn" onclick="showPage('menu')" id="ptab-menu">🍣 Меню</div>
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

<!-- === СТРАНИЦА: ОТЗЫВЫ === -->
<div class="page" id="page-reviews">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-top:4px;flex-wrap:wrap;gap:10px">
    <div style="font-size:1rem;font-weight:600;color:#e8a847">❤️ Отзывы с Любовью</div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-sm" id="revFilterAll" onclick="loadReviews(false)" style="border-color:#e8a847;color:#e8a847;background:rgba(232,168,71,0.12)">Все</button>
      <button class="btn btn-sm" id="revFilterNeg" onclick="loadReviews(true)" style="border-color:#333;color:#888;background:#1a1a1a">😕 Негативные</button>
    </div>
  </div>
  <!-- Ручная отправка -->
  <div style="background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:16px;margin-bottom:16px">
    <div style="font-size:0.82rem;color:#888;margin-bottom:10px">📨 Отправить запрос вручную (клиент не из сайта)</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" id="manualName" placeholder="Имя клиента" style="flex:1;min-width:120px;background:#111;border:1px solid #333;border-radius:8px;padding:9px 12px;color:#eee;font-size:0.9rem;outline:none">
      <input type="tel" id="manualPhone" placeholder="89001234567" maxlength="11" style="flex:1;min-width:140px;background:#111;border:1px solid #333;border-radius:8px;padding:9px 12px;color:#eee;font-size:0.9rem;outline:none" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
      <button onclick="sendManualReview()" class="btn btn-primary btn-sm" style="white-space:nowrap">Создать ссылку</button>
    </div>
    <div id="manualResult" style="display:none;margin-top:10px;padding:10px;background:#111;border-radius:8px;font-size:0.85rem;word-break:break-all;color:#e8a847"></div>
  </div>
  <!-- Сводка -->
  <div id="reviewsSummary" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:16px"></div>
  <!-- Список -->
  <div id="reviewsList"><div class="empty">Загрузка…</div></div>
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

<!-- === СТРАНИЦА: МЕНЮ === -->
<div class="page" id="page-menu">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;padding-top:4px">
    <div style="font-size:1rem;font-weight:600;color:#ccc">🍣 Меню <span id="menuCount" style="color:#555;font-size:0.85rem"></span></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="text" id="menuSearch" placeholder="Поиск…" oninput="menuFilter()" style="background:#1a1a1a;border:1px solid #333;color:#eee;border-radius:8px;padding:8px 12px;font-size:0.85rem;outline:none;width:200px">
      <?php if ($srole === 'owner'): ?>
      <button onclick="menuImport()" id="menuImportBtn" style="padding:8px 16px;border-radius:8px;border:1px solid #e8a847;background:rgba(232,168,71,0.1);color:#e8a847;font-size:0.85rem;cursor:pointer;font-weight:600">🔄 Синхронизировать из crm-love</button>
      <?php endif; ?>
    </div>
  </div>
  <div style="font-size:0.78rem;color:#555;margin-bottom:16px">Заполни артикул FrontPad — и позиция корректно уйдёт в заказ. Описание и фото видны только здесь (пока).</div>
  <div id="menuContent"><div style="color:#555;padding:40px;text-align:center">Загрузка…</div></div>
</div>

<!-- Модал: редактировать позицию меню -->
<div class="modal-bg" id="menuEditModal" onclick="if(event.target===this)closeMenuEdit()">
  <div class="modal" style="max-width:500px">
    <h3 id="menuEditTitle">Редактировать позицию</h3>
    <input type="hidden" id="menuEditId">
    <label>Артикул FrontPad</label>
    <input type="number" id="menuEditFp" placeholder="Например: 101" class="inp">
    <label>Цена (₽)</label>
    <input type="number" id="menuEditPrice" class="inp">
    <div style="display:flex;gap:10px">
      <div style="flex:1">
        <label>Вес (г)</label>
        <input type="number" id="menuEditWeight" placeholder="280" class="inp">
      </div>
      <div style="flex:1">
        <label>Количество (шт)</label>
        <input type="number" id="menuEditPieces" placeholder="8" class="inp">
      </div>
    </div>
    <label>Фото URL</label>
    <input type="text" id="menuEditImg" placeholder="https://..." class="inp">
    <div id="menuEditImgPreview" style="margin:8px 0;min-height:80px"></div>
    <label>Состав (ингредиенты — под фото в карточке)</label>
    <textarea id="menuEditDesc" rows="3" style="width:100%;background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:12px;font-size:0.9rem;font-family:inherit;resize:vertical;outline:none" placeholder="Лосось, сливочный сыр, нори…"></textarea>
    <label style="margin-top:12px">Вкусное описание (в попапе по клику ℹ)</label>
    <textarea id="menuEditFlavor" rows="3" style="width:100%;background:#222;border:1px solid #333;color:#eee;border-radius:8px;padding:12px;font-size:0.9rem;font-family:inherit;resize:vertical;outline:none" placeholder="Нежный лосось тает во рту, сливочный сыр добавляет мягкости, а хрустящая нори собирает всё вместе…"></textarea>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px;cursor:pointer">
      <input type="checkbox" id="menuEditStop"> Стоп-лист (временно недоступно)
    </label>
    <div style="display:flex;gap:10px;margin-top:18px">
      <button onclick="menuSave()" style="flex:1;background:#e8a847;border:none;color:#000;border-radius:8px;padding:12px;font-size:0.95rem;font-weight:700;cursor:pointer">Сохранить</button>
      <button onclick="closeMenuEdit()" style="padding:12px 20px;border-radius:8px;border:1px solid #333;background:transparent;color:#aaa;cursor:pointer">Отмена</button>
    </div>
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
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:4px">
      <?php if ($srole === 'owner'): ?>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="role-chk" value="admin"> Администратор
      </label>
      <?php endif; ?>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="role-chk" value="operator" checked> Оператор
      </label>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="role-chk" value="courier"> Курьер
      </label>
    </div>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeAddStaff()">Отмена</button>
      <button class="btn btn-primary" onclick="addStaff()">Добавить</button>
    </div>
  </div>
</div>

<!-- Модал: редактировать сотрудника -->
<div class="modal-bg" id="editStaffModal">
  <div class="modal">
    <h3>Редактировать сотрудника</h3>
    <input type="hidden" id="editStaffId">
    <label>Имя</label>
    <input type="text" id="editName" style="width:100%">
    <label style="margin-top:12px">Роли</label>
    <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
      <?php if ($srole === 'owner'): ?>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="edit-role-chk" value="admin"> Администратор
      </label>
      <?php endif; ?>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="edit-role-chk" value="operator"> Оператор
      </label>
      <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:0.88rem;color:#ccc;cursor:pointer">
        <input type="checkbox" class="edit-role-chk" value="courier"> Курьер
      </label>
    </div>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeEditModal()">Отмена</button>
      <button class="btn btn-primary" onclick="saveEdit()">Сохранить</button>
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
var VIEW_AS       = '<?php echo $view_as; ?>';
var currentPage   = 'orders';
var statusFilter  = '';
var refreshTimer  = null;
var REFRESH_SEC   = 30;
var countdown     = REFRESH_SEC;

// Все AJAX-запросы должны сохранять ?view=X, чтобы бэкенд знал эффективную роль вкладки
(function() {
  if (!VIEW_AS) return;
  var _origFetch = window.fetch;
  window.fetch = function(url, opts) {
    if (typeof url === 'string' && url.charAt(0) === '?') {
      url = url + (url.indexOf('?') === url.length - 1 ? '' : '&') + 'view=' + VIEW_AS;
    }
    return _origFetch.call(this, url, opts);
  };
})();

// --- Дропдаун "Смотреть как" ---
function toggleViewAs() {
  var m = document.getElementById('viewAsMenu');
  if (!m) return;
  m.style.display = (m.style.display === 'none' || !m.style.display) ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.view-as-wrap');
  if (!wrap) return;
  if (!wrap.contains(e.target)) {
    var m = document.getElementById('viewAsMenu');
    if (m) m.style.display = 'none';
  }
});

// --- Навигация по страницам ---
function showPage(name) {
  document.querySelectorAll('.page').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
  var p = document.getElementById('page-' + name);
  if (p) p.classList.add('active');
  var b = document.getElementById('ptab-' + name);
  if (b) b.classList.add('active');
  currentPage = name;
  if (name === 'orders')  loadOrders();
  if (name === 'staff')   loadStaff();
  if (name === 'log')     loadLog();
  if (name === 'reviews') loadReviews(false);
  if (name === 'menu')    loadMenu();
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
    done:      [{s:'delivering',l:'↩️ Вернуть в работу'},{s:'cancelled',l:'❌ Отмена'}],
    cancelled: [{s:'new',l:'↩️ Восстановить заказ'}]
  };
  var courierBtns = {delivering:[{s:'done',l:'✅ Выполнен'}]};

  var html = '<div class="orders-list">';
  orders.forEach(function(o) {
    var st   = o.status || 'new';
    var btns = (ROLE === 'courier' ? (courierBtns[st] || []) : (nextMap[st] || []))
      .map(function(b){ return '<button class="s-btn '+b.s+'" onclick="changeStatus('+o.id+',\''+b.s+'\')">'+b.l+'</button>'; }).join('');

    var client = o.user_name ? esc(o.user_name) : '—';
    var phone  = o.user_phone ? fmt_phone(o.user_phone) : '';
    var isTest = parseInt(o.is_test) === 1;
    var fpInfo = o.fp_order_id
      ? '<small style="color:#555;font-size:0.72rem">FP:'+o.fp_order_id+'</small>'
      : '<small style="color:#f97316;font-size:0.72rem">⚠️ Нет в FP</small>';
    var testBadge = isTest ? ' <span style="background:rgba(232,168,71,0.15);color:#e8a847;border:1px solid rgba(232,168,71,0.3);border-radius:6px;font-size:0.7rem;padding:1px 7px;font-weight:700">🧪 ТЕСТ</span>' : '';
    var deleteBtn = (ROLE === 'owner')
      ? '<button onclick="deleteAnyOrder('+o.id+')" style="margin-left:auto;padding:3px 10px;border-radius:6px;border:1px solid #555;background:transparent;color:#888;font-size:0.75rem;cursor:pointer">🗑</button>'
      : (isTest ? '<button onclick="deleteTestOrder('+o.id+')" style="margin-left:auto;padding:3px 10px;border-radius:6px;border:1px solid #e8a847;background:transparent;color:#e8a847;font-size:0.75rem;cursor:pointer">Удалить</button>' : '');

    html += '<div class="order-card s-'+st+(isTest?' order-test':'')+'">'
      + '<div class="order-head">'
      +   '<div><span class="order-id">'+(isTest?'#000':('#'+o.id))+'</span> '+testBadge+' '+fpInfo+'</div>'
      +   '<div style="display:flex;gap:8px;align-items:center">'
      +     (deleteBtn)
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
    if (r.ok) {
      if (status === 'done' && r.review_link) {
        showReviewLinkAlert(r.review_link, r.review_when);
      } else {
        showAlert(status==='done'?'✅ Выполнен':('Статус: '+status), false);
      }
      loadOrders();
    } else showAlert(r.error||'Ошибка', true);
  });
}

function showReviewLinkAlert(link, when) {
  var whenText = when ? ' Запрос отзыва уйдёт ' + when + '.' : '';
  showAlert('✅ Выполнен!' + whenText, false);
}

// === ОТЗЫВЫ ===
function loadReviews(onlyNegative) {
  var allBtn = document.getElementById('revFilterAll');
  var negBtn = document.getElementById('revFilterNeg');
  if (allBtn && negBtn) {
    if (onlyNegative) {
      allBtn.style.borderColor='#333'; allBtn.style.color='#888'; allBtn.style.background='#1a1a1a';
      negBtn.style.borderColor='#e8a847'; negBtn.style.color='#e8a847'; negBtn.style.background='rgba(232,168,71,0.12)';
    } else {
      allBtn.style.borderColor='#e8a847'; allBtn.style.color='#e8a847'; allBtn.style.background='rgba(232,168,71,0.12)';
      negBtn.style.borderColor='#333'; negBtn.style.color='#888'; negBtn.style.background='#1a1a1a';
    }
  }
  var url = '?action=reviews_list' + (onlyNegative ? '&only_negative=1' : '');
  fetch(url).then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    renderReviews(r.reviews);
  });
}

function renderReviews(reviews) {
  var list = document.getElementById('reviewsList');
  var summary = document.getElementById('reviewsSummary');

  // Считаем сводку
  var total = reviews.length;
  var answered = reviews.filter(function(r){ return r.rating; }).length;
  var negative = reviews.filter(function(r){ return r.rating && r.rating <= 4; }).length;
  var positive = reviews.filter(function(r){ return r.rating && r.rating >= 5; }).length;
  var avgRating = answered > 0
    ? (reviews.filter(function(r){ return r.rating; }).reduce(function(s,r){ return s + parseInt(r.rating); }, 0) / answered).toFixed(1)
    : '—';

  // Бейдж на табе
  var cntEl = document.getElementById('cnt-negative');
  if (cntEl) { if (negative > 0) { cntEl.textContent = negative; cntEl.style.display = ''; } else { cntEl.style.display = 'none'; } }

  if (summary) {
    summary.innerHTML = [
      ['📨 Отправлено', total],
      ['⭐ Ответили', answered],
      ['😊 Позитивных', positive],
      ['😕 Перехвачено', negative],
      ['📊 Средняя', avgRating]
    ].map(function(s){
      return '<div style="background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:14px 12px;text-align:center">'
        + '<div style="font-size:1.4rem;font-weight:700;color:#e8a847">' + s[1] + '</div>'
        + '<div style="font-size:0.75rem;color:#555;margin-top:4px">' + s[0] + '</div>'
        + '</div>';
    }).join('');
  }

  if (!reviews.length) { list.innerHTML = '<div class="empty">Отзывов пока нет</div>'; return; }

  var html = '';
  reviews.forEach(function(r) {
    var stars = r.rating ? str_repeat('⭐', parseInt(r.rating)) + ' (' + r.rating + '/5)' : '—';
    var isNeg = r.rating && parseInt(r.rating) <= 4;
    var border = isNeg ? '#c66' : (r.rating ? '#44cc88' : '#2a2a2a');
    var link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' + r.token;
    html += '<div style="background:#1a1a1a;border:1px solid ' + border + ';border-radius:12px;padding:14px;margin-bottom:10px">'
      + '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">'
      +   '<div style="font-size:0.82rem;color:#555">' + fmtDate(r.created_at) + ' · Заказ #' + r.order_id + (r.phone ? ' · ' + r.phone : '') + '</div>'
      +   '<div style="font-size:0.95rem">' + (r.rating ? stars : '<span style="color:#555">Не ответил</span>') + '</div>'
      + '</div>'
      + (r.comment ? '<div style="margin-top:10px;font-size:0.9rem;color:#ccc;background:#161616;border-radius:8px;padding:10px">' + esc(r.comment) + '</div>' : '')
      + (!r.answered_at ? '<div style="margin-top:8px;display:flex;align-items:center;gap:10px">'
          + '<span style="font-size:0.78rem;color:#555">Ссылка:</span>'
          + '<button onclick="copyLink(\'' + link + '\')" style="padding:3px 12px;border-radius:6px;border:1px solid #333;background:transparent;color:#e8a847;font-size:0.78rem;cursor:pointer">📋 Копировать</button>'
          + '</div>' : '')
      + '</div>';
  });
  list.innerHTML = html;
}

function sendManualReview() {
  var name  = document.getElementById('manualName').value.trim();
  var phone = document.getElementById('manualPhone').value.replace(/\D/g,'');
  if (!phone) { showAlert('Введите номер телефона', true); return; }
  fetch('?action=manual_review', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({name: name, phone: phone})
  }).then(function(r){ return r.text(); }).then(function(text) {
    var r;
    try { r = JSON.parse(text); } catch(e) {
      showAlert('Ошибка сервера: ' + text.substring(0, 120), true); return;
    }
    if (r.ok) {
      var res = document.getElementById('manualResult');
      res.style.display = '';
      res.innerHTML = '📋 Ссылка создана:<br><b>' + r.link + '</b><br>'
        + '<button onclick="copyLink(\'' + r.link + '\')" style="margin-top:8px;padding:4px 12px;border-radius:6px;border:1px solid #e8a847;background:transparent;color:#e8a847;cursor:pointer;font-size:0.8rem">Скопировать</button>';
      document.getElementById('manualName').value = '';
      document.getElementById('manualPhone').value = '';
    } else { showAlert(r.error||'Ошибка', true); }
  }).catch(function(e){ showAlert('Сеть: ' + e.message, true); });
}

function str_repeat(s, n) { var r=''; for(var i=0;i<n;i++) r+=s; return r; }
function copyLink(link) {
  if (navigator.clipboard) { navigator.clipboard.writeText(link).then(function(){ showAlert('📋 Ссылка скопирована', false); }); }
  else { showAlert('Ссылка: ' + link, false); }
}

function deleteAnyOrder(oid) {
  if (!confirm('Удалить заказ #' + oid + '?\n\nЭто действие нельзя отменить.')) return;
  fetch('?action=delete_order', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({order_id: oid})
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('🗑️ Заказ #' + oid + ' удалён', false); loadOrders(); }
    else showAlert(r.error || 'Ошибка', true);
  }).catch(function(){ showAlert('Ошибка сети', true); });
}

function deleteTestOrder(oid) {
  if (!confirm('Удалить тестовый заказ #' + oid + '?')) return;
  fetch('?action=delete_test_order', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({order_id: oid})
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('🗑️ Тестовый заказ удалён', false); loadOrders(); }
    else showAlert(r.error||'Ошибка', true);
  });
}

// === МЕНЮ ===
var _menuData = null;

function loadMenu() {
  fetch('?action=menu_list').then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) { document.getElementById('menuContent').innerHTML = '<div style="color:#e05a5a">Ошибка: ' + (r.error||'') + '</div>'; return; }
    _menuData = r;
    renderMenu(r);
  }).catch(function(){ document.getElementById('menuContent').innerHTML = '<div style="color:#e05a5a">Ошибка загрузки. Сначала запусти миграцию: <a href="../menu/migrate.php" target="_blank" style="color:#e8a847">api/menu/migrate.php</a></div>'; });
}

var GROUP_NAMES = {
  'spaysi':'Соус спайси','syrny':'Соус сырный','teriaki':'Соус терияки',
  'unagi':'Соус унаги','firmen':'Соус фирменный (фри/наггетсы)',
  'soeviy':'Соевый соус','soeviy_chef':'Соевый соус от шефа',
  'imbir':'Имбирь маринованный','kokteil':'Молочный коктейль'
};

function renderMenu(r) {
  var cats  = r.categories || [];
  var items = r.items || [];
  var q = (document.getElementById('menuSearch')||{value:''}).value.toLowerCase();

  var catMap = {};
  cats.forEach(function(c){ catMap[c.id] = c; });
  // Группируем по категории, затем по group_key
  var byCat = {};
  cats.forEach(function(c){ byCat[c.id] = {singles:[], groups:{}}; });
  var total = 0;
  items.forEach(function(item) {
    if (q && item.name.toLowerCase().indexOf(q) === -1 &&
        !(item.group_key && GROUP_NAMES[item.group_key] && GROUP_NAMES[item.group_key].toLowerCase().indexOf(q) !== -1)) return;
    if (!byCat[item.category_id]) byCat[item.category_id] = {singles:[], groups:{}};
    if (item.group_key) {
      if (!byCat[item.category_id].groups[item.group_key]) byCat[item.category_id].groups[item.group_key] = [];
      byCat[item.category_id].groups[item.group_key].push(item);
    } else {
      byCat[item.category_id].singles.push(item);
    }
    total++;
  });

  document.getElementById('menuCount').textContent = '(' + total + ' позиций)';

  var html = '';
  cats.forEach(function(cat) {
    var bucket = byCat[cat.id] || {singles:[], groups:{}};
    var singles = bucket.singles;
    var groups = bucket.groups;
    var hasItems = singles.length || Object.keys(groups).length;
    if (!hasItems) return;
    var displayCount = singles.length + Object.keys(groups).length;
    html += '<div style="margin-bottom:24px">'
      + '<div style="font-size:0.9rem;font-weight:700;color:#e8a847;margin-bottom:10px;padding:6px 0;border-bottom:1px solid #2a2a2a">'
      + esc(cat.name) + ' <span style="color:#555;font-weight:400">(' + displayCount + ')</span></div>'
      + '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">';

    // Сгруппированные позиции (соусы с вариантами объёма)
    Object.keys(groups).forEach(function(gk) {
      var variants = groups[gk];
      var gname = GROUP_NAMES[gk] || gk;
      var allHaveFp = variants.every(function(v){ return v.fp_article_id; });
      var firstImg = variants[0].image_url;
      var img = firstImg
        ? '<img src="'+esc(firstImg)+'" style="width:56px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0" onerror="this.style.display=\'none\'">'
        : '<div style="width:56px;height:56px;border-radius:8px;background:#222;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.4rem">🫙</div>';
      var fpStatus = allHaveFp
        ? '<span style="background:rgba(68,204,136,0.15);color:#44cc88;border-radius:5px;padding:1px 7px;font-size:0.72rem">все артикулы ✓</span>'
        : '<span style="background:rgba(249,115,22,0.15);color:#f97316;border-radius:5px;padding:1px 7px;font-size:0.72rem">не все артикулы</span>';
      var variantPills = variants.map(function(v) {
        var label = v.variant_label || v.name;
        var fp = v.fp_article_id ? ' FP:'+v.fp_article_id : ' ⚠️';
        return '<span onclick="openMenuEdit('+v.id+')" title="Изменить: '+esc(v.name)+'" '
          +'style="display:inline-flex;align-items:center;gap:4px;background:#222;border:1px solid #333;border-radius:6px;padding:3px 8px;font-size:0.75rem;cursor:pointer;color:#ccc">'
          + esc(label) + '<span style="color:#888;font-size:0.7rem">'+fp+'</span></span>';
      }).join('');
      html += '<div style="background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:12px;display:flex;gap:10px;align-items:flex-start">'
        + img
        + '<div style="flex:1;min-width:0">'
        +   '<div style="font-size:0.85rem;font-weight:600;color:#eee;margin-bottom:4px">'+esc(gname)+'</div>'
        +   '<div style="margin-bottom:6px">'+fpStatus+'</div>'
        +   '<div style="display:flex;flex-wrap:wrap;gap:4px">'+variantPills+'</div>'
        + '</div></div>';
    });

    // Одиночные позиции
    singles.forEach(function(item) {
      var img = item.image_url
        ? '<img src="'+esc(item.image_url)+'" style="width:56px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0" onerror="this.style.display=\'none\'">'
        : '<div style="width:56px;height:56px;border-radius:8px;background:#222;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.4rem">🍱</div>';
      var fpBadge = item.fp_article_id
        ? '<span style="background:rgba(68,204,136,0.15);color:#44cc88;border-radius:5px;padding:1px 7px;font-size:0.72rem">FP:'+item.fp_article_id+'</span>'
        : '<span style="background:rgba(249,115,22,0.15);color:#f97316;border-radius:5px;padding:1px 7px;font-size:0.72rem">без артикула</span>';
      var stopBadge = parseInt(item.is_stop) ? '<span style="background:rgba(224,90,90,0.15);color:#e05a5a;border-radius:5px;padding:1px 7px;font-size:0.72rem;margin-left:4px">стоп</span>' : '';
      html += '<div style="background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:12px;display:flex;gap:10px;align-items:flex-start">'
        + img
        + '<div style="flex:1;min-width:0">'
        +   '<div style="font-size:0.85rem;font-weight:600;color:#eee;margin-bottom:4px;line-height:1.3">'+esc(item.name)+'</div>'
        +   '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px">'+fpBadge+stopBadge+'</div>'
        +   '<div style="font-size:0.8rem;color:#e8a847;margin-bottom:6px">'+item.price+' ₽'+(item.weight_grams?' · '+item.weight_grams+' г':'')+'</div>'
        +   (item.description ? '<div style="font-size:0.75rem;color:#666;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="'+esc(item.description)+'">'+esc(item.description)+'</div>' : '')
        +   '<div style="display:flex;gap:6px;flex-wrap:wrap">'
        +   '<button onclick="openMenuEdit('+item.id+')" style="font-size:0.75rem;padding:4px 12px;border-radius:6px;border:1px solid #333;background:transparent;color:#aaa;cursor:pointer">✏️ Изменить</button>'
        +   '<button onclick="toggleActive('+item.id+',this)" style="font-size:0.75rem;padding:4px 10px;border-radius:6px;border:1px solid '+(parseInt(item.is_active)?'rgba(68,204,136,0.3)':'rgba(249,115,22,0.3)')+';background:transparent;color:'+(parseInt(item.is_active)?'#44cc88':'#f97316')+';cursor:pointer">'+(parseInt(item.is_active)?'👁 Активен':'🚫 Скрыт')+'</button>'
        +   '</div>'
        + '</div></div>';
    });
    html += '</div></div>';
  });

  document.getElementById('menuContent').innerHTML = html || '<div style="color:#555;padding:20px">Ничего не найдено</div>';
}

function menuFilter() {
  if (_menuData) renderMenu(_menuData);
}

function openMenuEdit(id) {
  if (!_menuData) return;
  var item = null;
  _menuData.items.forEach(function(i){ if (i.id === id) item = i; });
  if (!item) return;
  document.getElementById('menuEditId').value   = item.id;
  document.getElementById('menuEditTitle').textContent = esc(item.name);
  document.getElementById('menuEditFp').value   = item.fp_article_id || '';
  document.getElementById('menuEditPrice').value = item.price || '';
  document.getElementById('menuEditWeight').value = item.weight_grams || '';
  document.getElementById('menuEditPieces').value = item.pieces_count || '';
  document.getElementById('menuEditImg').value   = item.image_url || '';
  document.getElementById('menuEditDesc').value   = item.description || '';
  document.getElementById('menuEditFlavor').value = item.flavor_description || '';
  document.getElementById('menuEditStop').checked = parseInt(item.is_stop) === 1;
  menuUpdateImgPreview(item.image_url);
  document.getElementById('menuEditModal').style.display = 'flex';
  document.getElementById('menuEditImg').oninput = function(){ menuUpdateImgPreview(this.value); };
}

function menuUpdateImgPreview(url) {
  var el = document.getElementById('menuEditImgPreview');
  if (url && url.trim()) {
    el.innerHTML = '<img src="'+esc(url)+'" style="max-width:100%;max-height:120px;border-radius:8px;object-fit:cover" onerror="this.parentNode.innerHTML=\'<span style=color:#e05a5a>Фото не загружается</span>\'">';
  } else {
    el.innerHTML = '';
  }
}

function closeMenuEdit() {
  document.getElementById('menuEditModal').style.display = 'none';
}

function menuSave() {
  var id   = parseInt(document.getElementById('menuEditId').value);
  var fpRaw = document.getElementById('menuEditFp').value.trim();
  fetch('?action=menu_update', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      id:            id,
      fp_article_id: fpRaw !== '' ? parseInt(fpRaw) : null,
      price:         parseFloat(document.getElementById('menuEditPrice').value) || 0,
      weight_grams:  (function(v){ v = parseInt(document.getElementById('menuEditWeight').value); return v > 0 ? v : null; })(),
      pieces_count:  (function(v){ v = parseInt(document.getElementById('menuEditPieces').value); return v > 0 ? v : null; })(),
      image_url:     document.getElementById('menuEditImg').value.trim() || null,
      description:        document.getElementById('menuEditDesc').value.trim()   || null,
      flavor_description: document.getElementById('menuEditFlavor').value.trim() || null,
      is_stop:       document.getElementById('menuEditStop').checked ? 1 : 0
    })
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { closeMenuEdit(); showAlert('✅ Сохранено', false); loadMenu(); }
    else showAlert(r.error || 'Ошибка', true);
  }).catch(function(){ showAlert('Ошибка сети', true); });
}

function toggleActive(id, btn) {
  fetch('?action=menu_toggle_active', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id})})
  .then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) { showAlert(r.error||'Ошибка', true); return; }
    var active = r.is_active;
    btn.textContent = active ? '👁 Активен' : '🚫 Скрыт';
    btn.style.color  = active ? '#44cc88' : '#f97316';
    btn.style.borderColor = active ? 'rgba(68,204,136,0.3)' : 'rgba(249,115,22,0.3)';
    showAlert(active ? '✅ Позиция активна — видна на сайте' : '🚫 Позиция скрыта с сайта', !active);
  }).catch(function(){ showAlert('Ошибка сети', true); });
}

function menuImport() {
  var btn = document.getElementById('menuImportBtn');
  if (!btn) return;
  btn.disabled = true; btn.textContent = '⏳ Синхронизирую…';
  fetch('?action=menu_import', {method:'POST'})
  .then(function(r){ return r.json(); })
  .then(function(r) {
    btn.disabled = false; btn.textContent = '🔄 Синхронизировать из crm-love';
    if (r.ok) { showAlert('✅ Импорт: добавлено ' + r.imported + ', обновлено ' + r.updated, false); loadMenu(); }
    else showAlert('Ошибка: ' + (r.error||''), true);
  }).catch(function(){
    btn.disabled = false; btn.textContent = '🔄 Синхронизировать из crm-love';
    showAlert('Ошибка сети', true);
  });
}

// === СОТРУДНИКИ (owner) ===
function loadStaff() {
  fetch('?action=staff_list').then(function(r){ return r.json(); }).then(function(r) {
    if (!r.ok) return;
    var rLabels = {owner:'Управляющий', admin:'Администратор', operator:'Оператор', courier:'Курьер'};
    var html = '';
    r.staff.forEach(function(s) {
      var staffRoles = s.role ? s.role.split(',') : [];
      var badgesHtml = staffRoles.map(function(role) {
        role = role.trim();
        return '<span class="badge badge-'+role+'" style="margin-right:3px">'+(rLabels[role]||role)+'</span>';
      }).join('');
      var isActive  = parseInt(s.active) === 1;
      var toggleBtn = isActive
        ? '<button class="btn btn-sm btn-danger" onclick="toggleStaff('+s.id+')">Откл.</button>'
        : '<button class="btn btn-sm" style="background:#1a3a1a;color:#44cc88;border:1px solid #2a5a2a" onclick="toggleStaff('+s.id+')">Вкл.</button>';
      // Роли передаём как строку через запятую — без JSON, без конфликта кавычек
      var rolesStr = staffRoles.join(',');
      html += '<tr style="'+(isActive?'':'opacity:.5')+'">'
        + '<td><b>'+esc(s.name)+'</b></td>'
        + '<td style="color:#888">'+esc(s.login)+'</td>'
        + '<td>'+badgesHtml+'</td>'
        + '<td>'+(isActive?'<span style="color:#44cc88">Активен</span>':'<span style="color:#666">Откл.</span>')+'</td>'
        + '<td style="display:flex;gap:6px;flex-wrap:wrap">'
        +   '<button class="btn btn-sm btn-primary" onclick="openEditModal('+s.id+',\''+esc(s.name)+'\',\''+rolesStr+'\')">Изменить</button>'
        +   '<button class="btn btn-sm btn-ghost" onclick="openPassModal('+s.id+')">Пароль</button>'
        +   toggleBtn
        + '</td>'
        + '</tr>';
    });
    document.getElementById('staffTable').innerHTML = html || '<tr><td colspan="5" style="color:#555">Нет сотрудников</td></tr>';
  });
}
function openAddStaff() { document.getElementById('addStaffModal').classList.add('open'); }
function closeAddStaff(){ document.getElementById('addStaffModal').classList.remove('open'); }
function addStaff() {
  var roles = [];
  document.querySelectorAll('.role-chk:checked').forEach(function(c){ roles.push(c.value); });
  var payload = {
    name:  document.getElementById('newName').value.trim(),
    login: document.getElementById('newLogin').value.trim(),
    pass:  document.getElementById('newPass').value,
    roles: roles
  };
  fetch('?action=staff_add', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
  .then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('Сотрудник добавлен', false); closeAddStaff(); loadStaff();
      // Сбросить форму
      document.getElementById('newName').value=''; document.getElementById('newLogin').value=''; document.getElementById('newPass').value='';
      document.querySelectorAll('.role-chk').forEach(function(c){ c.checked = c.value==='operator'; });
    } else showAlert(r.error||'Ошибка', true);
  });
}
function openEditModal(id, name, rolesStr) {
  var roles = rolesStr ? rolesStr.split(',') : [];
  document.getElementById('editStaffId').value = id;
  document.getElementById('editName').value = name;
  document.querySelectorAll('.edit-role-chk').forEach(function(c) {
    c.checked = roles.indexOf(c.value) !== -1;
  });
  document.getElementById('editStaffModal').classList.add('open');
}
function closeEditModal() { document.getElementById('editStaffModal').classList.remove('open'); }
function saveEdit() {
  var id    = parseInt(document.getElementById('editStaffId').value);
  var name  = document.getElementById('editName').value.trim();
  var roles = [];
  document.querySelectorAll('.edit-role-chk:checked').forEach(function(c){ roles.push(c.value); });
  if (!name)          { showAlert('Введите имя', true); return; }
  if (!roles.length)  { showAlert('Выберите хотя бы одну роль', true); return; }
  fetch('?action=staff_edit', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id:id, name:name, roles:roles})
  }).then(function(r){ return r.json(); }).then(function(r) {
    if (r.ok) { showAlert('Сохранено', false); closeEditModal(); loadStaff(); }
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

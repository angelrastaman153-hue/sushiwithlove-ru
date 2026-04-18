<?php
// Мост из БОСС-панели (api/admin) в staff-панель (api/staff)
// Открывает staff-панель с нужной ролью без повторного ввода пароля.
// Безопасно: требует авторизации в admin-панели (cookie PHPSESSID[admin]=true).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// 1) Читаем admin-сессию
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: /api/admin/');
    exit;
}

$to = isset($_GET['to']) ? $_GET['to'] : 'owner';
$valid = array('owner', 'admin', 'operator', 'courier');
if (!in_array($to, $valid, true)) $to = 'owner';

// 2) Закрываем admin-сессию, открываем staff-сессию
session_write_close();

// 3) Находим запись владельца в таблице staff
$pdo = db();
$stmt = $pdo->query("SELECT id, name, role FROM staff WHERE role LIKE '%owner%' AND active=1 ORDER BY id ASC LIMIT 1");
$owner = $stmt->fetch();

if (!$owner) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>⚠️ Нет владельца в таблице staff</h3>';
    echo '<p>Зайди в <a href="/api/staff/">/api/staff/</a> и создай первого владельца,';
    echo ' или добавь запись в таблицу <code>staff</code> с ролью <code>owner,admin,operator,courier</code>.</p>';
    exit;
}

// 4) Логинимся в staff-сессию как владелец
session_name('swl_staff');
session_start();
$_SESSION['staff_id']   = (int)$owner['id'];
$_SESSION['staff_role'] = 'owner'; // реальная роль всегда owner
$_SESSION['staff_name'] = $owner['name'];
session_write_close();

// 5) Редирект на staff-панель с нужным view
if ($to === 'owner') {
    header('Location: /api/staff/');
} else {
    header('Location: /api/staff/?view=' . $to);
}
exit;

<?php
// POST /api/auth/tg.php
// Принимает данные от Telegram Login Widget, проверяет подпись, создаёт/находит пользователя

require_once __DIR__ . '/../db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Получаем данные (GET — от Telegram Login Widget редиректом, POST — из JS)
$data = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? json_decode(file_get_contents('php://input'), true)
    : $_GET;

if (empty($data) || !isset($data['hash'])) {
    json_out(array('ok' => false, 'error' => 'No data'), 400);
}

// Проверяем подпись Telegram
$hash = $data['hash'];
unset($data['hash']);

$check_arr = array();
foreach ($data as $k => $v) {
    $check_arr[] = $k . '=' . $v;
}
sort($check_arr);
$check_string = implode("\n", $check_arr);

$secret = hash('sha256', BOT_TOKEN, true);
$valid_hash = hash_hmac('sha256', $check_string, $secret);

if ($valid_hash !== $hash) {
    json_out(array('ok' => false, 'error' => 'Invalid signature'), 403);
}

// Проверяем свежесть данных (не старше 24 часов)
if ((time() - intval($data['auth_date'])) > 86400) {
    json_out(array('ok' => false, 'error' => 'Auth data expired'), 403);
}

$tg_id   = intval($data['id']);
$name    = isset($data['first_name']) ? $data['first_name'] : '';
if (isset($data['last_name'])) $name .= ' ' . $data['last_name'];
$name = trim($name);

$pdo = db();

// Ищем существующего пользователя
$stmt = $pdo->prepare('SELECT * FROM users WHERE tg_id = ?');
$stmt->execute(array($tg_id));
$user = $stmt->fetch();

if (!$user) {
    // Новый пользователь — регистрируем
    $welcome = intval(loyalty_config('welcome_bonus'));

    $stmt = $pdo->prepare('
        INSERT INTO users (tg_id, name, points, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute(array($tg_id, $name, $welcome));
    $user_id = $pdo->lastInsertId();

    // Пишем в лог баллов
    $pdo->prepare('
        INSERT INTO points_log (user_id, delta, reason, created_at)
        VALUES (?, ?, "welcome", NOW())
    ')->execute(array($user_id, $welcome));

    $is_new = true;
} else {
    $user_id = $user['id'];
    // Обновляем имя если изменилось
    if ($name && $name !== $user['name']) {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')
            ->execute(array($name, $user_id));
    }
    $is_new = false;
}

// Создаём сессию
$token = create_session($user_id);

// Получаем актуальные данные пользователя
$stmt = $pdo->prepare('SELECT id, name, phone, points, created_at FROM users WHERE id = ?');
$stmt->execute(array($user_id));
$user_data = $stmt->fetch();

json_out(array(
    'ok'     => true,
    'token'  => $token,
    'is_new' => $is_new,
    'user'   => $user_data
));

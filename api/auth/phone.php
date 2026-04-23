<?php
// POST /api/auth/phone.php
// Вход/регистрация по номеру телефона (без SMS — клиент подтверждает галочкой)

require_once __DIR__ . '/../db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data  = json_decode(file_get_contents('php://input'), true);
$phone = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : '';
$name  = isset($data['name'])  ? trim($data['name'])  : '';
$bday  = isset($data['bday'])  ? trim($data['bday'])  : null;

if (strlen($phone) < 10) {
    json_out(array('ok' => false, 'error' => 'Некорректный номер телефона'), 400);
}
if (!$name) {
    json_out(array('ok' => false, 'error' => 'Укажите имя'), 400);
}
if ($bday === '') $bday = null;

$pdo = db();

// Ищем по телефону
$stmt = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
$stmt->execute(array($phone));
$user = $stmt->fetch();

if (!$user) {
    // Новый пользователь
    $welcome = intval(loyalty_config('welcome_bonus'));
    $pdo->prepare('INSERT INTO users (phone, name, birth_date, points, created_at) VALUES (?,?,?,?,NOW())')
        ->execute(array($phone, $name, $bday, $welcome));
    $user_id = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO points_log (user_id, delta, reason, created_at) VALUES (?,?,"welcome",NOW())')
        ->execute(array($user_id, $welcome));
} else {
    $user_id = $user['id'];
    // Обновляем имя/дату рождения если не были заполнены
    $pdo->prepare('UPDATE users SET
        name       = COALESCE(NULLIF(name,""), ?),
        birth_date = COALESCE(birth_date, ?)
        WHERE id = ?')
        ->execute(array($name, $bday, $user_id));
}

$token = create_session($user_id);

$stmt = $pdo->prepare('SELECT id, name, phone, birth_date, points, created_at FROM users WHERE id = ?');
$stmt->execute(array($user_id));
$user_data = $stmt->fetch();

json_out(array('ok' => true, 'token' => $token, 'user' => $user_data));

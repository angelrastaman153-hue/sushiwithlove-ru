<?php
// POST /api/profile.php — обновить телефон и дату рождения
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = auth_required();
$data = json_decode(file_get_contents('php://input'), true);

$phone      = isset($data['phone'])      ? preg_replace('/\D/', '', $data['phone']) : null;
$birth_date = isset($data['birth_date']) ? $data['birth_date'] : null;
$name       = isset($data['name'])       ? trim($data['name']) : null;

$updates = array();
$params  = array();

if ($name && $name !== $user['name']) {
    $updates[] = 'name = ?'; $params[] = $name;
}

if ($phone && strlen($phone) === 11 && !$user['phone']) {
    // Телефон можно задать только один раз (если ещё не установлен)
    $updates[] = 'phone = ?'; $params[] = $phone;
}

if ($birth_date && !$user['birth_date']) {
    // Дата рождения — только один раз
    // Валидация формата YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $updates[] = 'birth_date = ?'; $params[] = $birth_date;
    }
}

if (empty($updates)) {
    json_out(array('ok' => false, 'error' => 'Nothing to update'), 400);
}

$params[] = $user['id'];
db()->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')
   ->execute($params);

// Возвращаем обновлённый профиль
$stmt = db()->prepare('SELECT id, name, phone, birth_date, points, created_at FROM users WHERE id = ?');
$stmt->execute(array($user['id']));
$updated = $stmt->fetch();

json_out(array('ok' => true, 'user' => $updated));

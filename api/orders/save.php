<?php
// POST /api/orders/save.php
// Вызывается из JS СРАЗУ при оформлении заказа (fp_order_id = null → статус pending)
// После успешной отправки в FrontPad — link_fp.php привязывает fp_order_id и меняет статус на new

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data  = json_decode(file_get_contents('php://input'), true);
$token = isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : '';

// user_id — только если есть токен и он валиден
$user_id = null;
if ($token) {
    $stmt = db()->prepare('
        SELECT u.id FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ? AND s.expires_at > NOW()
    ');
    $stmt->execute(array($token));
    $row = $stmt->fetch();
    if ($row) $user_id = $row['id'];
}

$client_phone  = isset($data['phone'])         ? preg_replace('/\D/', '', $data['phone']) : null;
$client_name   = isset($data['name'])          ? trim($data['name'])           : null;
$fp_order_id   = isset($data['fp_order_id'])   ? $data['fp_order_id']   : null;
$items_total   = isset($data['items_total'])   ? intval($data['items_total'])   : 0;
$delivery_cost = isset($data['delivery_cost']) ? intval($data['delivery_cost']) : 0;
$promo_code    = isset($data['promo_code'])    ? $data['promo_code']    : null;
$promo_discount= isset($data['promo_discount'])? intval($data['promo_discount']): 0;
$points_spent  = isset($data['points_spent'])  ? intval($data['points_spent'])  : 0;
$total_paid    = isset($data['total_paid'])    ? intval($data['total_paid'])    : 0;
$is_test       = isset($data['is_test'])       ? (int)$data['is_test']          : 0;

$pdo = db();

// Списываем баллы сразу (если были использованы)
if ($user_id && $points_spent > 0) {
    $pdo->prepare('UPDATE users SET points = points - ? WHERE id = ? AND points >= ?')
        ->execute(array($points_spent, $user_id, $points_spent));
    $pdo->prepare('INSERT INTO points_log (user_id, delta, reason, created_at) VALUES (?,?,?,NOW())')
        ->execute(array($user_id, -$points_spent, 'spend'));
}

// Сохраняем заказ: pending если нет fp_order_id (FrontPad ещё не ответил), new если уже есть
$status = $fp_order_id ? 'new' : 'pending';
$pdo->prepare('
    INSERT INTO orders
      (user_id, fp_order_id, items_total, delivery_cost, promo_code, promo_discount,
       points_spent, total_paid, status, is_test, client_phone, client_name, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
')->execute(array($user_id, $fp_order_id, $items_total, $delivery_cost,
                  $promo_code, $promo_discount, $points_spent, $total_paid, $status,
                  $is_test, $client_phone, $client_name));

$order_id = $pdo->lastInsertId();

// Обновляем last_order_at
if ($user_id) {
    $pdo->prepare('UPDATE users SET last_order_at = NOW() WHERE id = ?')
        ->execute(array($user_id));
}

// Уведомление в ВК
$vk_msg = vk_format_order($order_id, $data);
vk_send($vk_msg);

json_out(array('ok' => true, 'order_id' => $order_id));

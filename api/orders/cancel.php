<?php
// POST /api/orders/cancel.php
// Клиентская отмена заказа из личного кабинета.
// Условия: статус = 'new' И до времени доставки > 30 минут (если delivery_date указано).

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vk_notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$token = isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : '';
$data  = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;

function cancel_error($code) {
    echo json_encode(array('ok' => false, 'error' => $code), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$token || !$order_id) cancel_error('bad_request');

// Проверяем авторизацию
$stmt = db()->prepare('
    SELECT u.id FROM sessions s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ? AND s.expires_at > NOW()
');
$stmt->execute(array($token));
$row = $stmt->fetch();
if (!$row) cancel_error('auth');
$user_id = $row['id'];

// Получаем заказ
$stmt = db()->prepare('
    SELECT id, status, delivery_date, fp_order_id, client_name, client_phone
    FROM orders
    WHERE id = ? AND user_id = ?
');
$stmt->execute(array($order_id, $user_id));
$order = $stmt->fetch();
if (!$order) cancel_error('not_found');

// Проверяем статус
if ($order['status'] !== 'new') cancel_error('already_in_work');

// Проверяем буфер времени: нельзя отменить если до доставки < 30 минут
if (!empty($order['delivery_date'])) {
    $delivery_ts = strtotime($order['delivery_date']);
    if ($delivery_ts && (($delivery_ts - time()) < 30 * 60)) {
        cancel_error('too_late');
    }
}

// Отменяем
db()->prepare('UPDATE orders SET status = ? WHERE id = ?')
    ->execute(array('cancelled', $order_id));

// Уведомление в ВК-кухню
$fp_ref  = $order['fp_order_id'] ? ' (FP №' . $order['fp_order_id'] . ')' : '';
$who     = trim(($order['client_name'] ?: '') . ' · ' . ($order['client_phone'] ?: ''));
vk_send("❌ ОТМЕНА ЗАКАЗА #" . $order_id . $fp_ref . "\n"
      . "Клиент отменил через личный кабинет\n"
      . "👤 " . ($who ?: '—'));

echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

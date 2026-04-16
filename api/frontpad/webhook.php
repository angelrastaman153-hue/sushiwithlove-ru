<?php
// Вебхук от FrontPad — срабатывает при смене статуса заказа
// Настроить в FrontPad: Настройки → API → URL вебхука:
// https://xn--90acqmqobo9b7bse.xn--p1ai/api/frontpad/webhook.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

// Лог для отладки
$log_file = __DIR__ . '/webhook_log.txt';
$raw = file_get_contents('php://input');
file_put_contents($log_file, date('Y-m-d H:i:s') . ' ' . $raw . "\n", FILE_APPEND);

if (!$raw) { echo 'ok'; exit; }

$data = json_decode($raw);
if (!$data) { echo 'ok'; exit; }

$action   = isset($data->action)   ? $data->action   : '';
$fp_id    = isset($data->order_id) ? intval($data->order_id) : 0;
$status   = isset($data->status)   ? intval($data->status)   : 0;

// Нас интересует только смена статуса на «Выполнен» (Код API = 10)
if ($action !== 'change_status' || $status !== 10 || !$fp_id) {
    echo 'ok'; exit;
}

$pdo = db();

// Ищем заказ в нашей БД по fp_order_id
$stmt = $pdo->prepare('SELECT * FROM orders WHERE fp_order_id = ? LIMIT 1');
$stmt->execute(array($fp_id));
$order = $stmt->fetch();

$phone = null;
$order_id = null;

if ($order) {
    $phone    = $order['client_phone'];
    $order_id = $order['id'];
}

// Проверяем — не создавали ли уже отзыв для этого fp_order_id
$exists = $pdo->prepare('SELECT id FROM reviews WHERE order_id = ? AND source != "manual" LIMIT 1');
$exists->execute(array($order_id ?: 0));
if ($exists->fetch()) {
    echo 'ok'; exit; // уже есть
}

// Генерируем токен (PHP 5.6 совместимо)
if (function_exists('random_bytes')) {
    $token = bin2hex(random_bytes(16));
} else {
    $token = md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true));
}

// Вычисляем время отправки: +1 час, но не позже 22:00 (Курган UTC+5)
$send_utc = time() + 3600;
$send_h   = intval(gmdate('H', $send_utc + 18000));
if ($send_h < 10 || $send_h >= 22) {
    $d = gmdate('Y-m-d', time() + 18000 + ($send_h >= 22 ? 86400 : 0));
    $send_utc = strtotime($d . ' 05:00:00'); // 10:00 Курган = 05:00 UTC
}
$scheduled_for = gmdate('Y-m-d H:i:s', $send_utc);

// Сохраняем запись отзыва
$pdo->prepare(
    'INSERT INTO reviews (order_id, token, phone, source, scheduled_for) VALUES (?,?,?,?,?)'
)->execute(array(
    $order_id ?: 0,
    $token,
    $phone,
    'frontpad',
    $scheduled_for
));

echo 'ok';

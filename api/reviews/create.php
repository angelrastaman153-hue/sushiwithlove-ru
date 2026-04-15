<?php
// POST /api/reviews/create.php — создаёт токен отзыва и уведомляет в ВК
// Вызывается из staff-панели при смене статуса на "done"

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data     = json_decode(file_get_contents('php://input'), true);
$order_id = intval(isset($data['order_id']) ? $data['order_id'] : 0);
$phone    = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : '';
$name     = isset($data['name'])  ? $data['name']  : '';

if (!$order_id) { json_out(array('ok' => false, 'error' => 'Нет order_id')); }

$pdo = db();

// Не создавать повторно если уже есть
$existing = $pdo->prepare('SELECT token FROM reviews WHERE order_id = ?');
$existing->execute(array($order_id));
$row = $existing->fetch();
if ($row) {
    $link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' . $row['token'];
    json_out(array('ok' => true, 'token' => $row['token'], 'link' => $link, 'already' => true));
}

// Создаём токен
$token = bin2hex(random_bytes(16));
$pdo->prepare('INSERT INTO reviews (order_id, token, phone, source) VALUES (?,?,?,?)')
   ->execute(array($order_id, $token, $phone ?: null, 'site'));

$link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' . $token;

// Уведомление в ВК-беседу кухни (оператор может скопировать и отправить клиенту)
$client_info = $name ? $name : ($phone ? $phone : 'клиент');
$msg = "⭐ ОТЗЫВ — заказ #" . $order_id . "\n"
     . "Клиент: " . $client_info . "\n"
     . "Ссылка для отзыва:\n" . $link . "\n"
     . "Отправьте клиенту в ВКонтакте или любым удобным способом.";
vk_send($msg);

json_out(array('ok' => true, 'token' => $token, 'link' => $link));

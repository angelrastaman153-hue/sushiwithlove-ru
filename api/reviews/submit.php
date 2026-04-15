<?php
// POST /api/reviews/submit.php — принимает оценку и комментарий от клиента

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data    = json_decode(file_get_contents('php://input'), true);
$token   = isset($data['token'])   ? preg_replace('/[^a-f0-9]/', '', $data['token']) : '';
$rating  = isset($data['rating'])  ? intval($data['rating'])  : 0;
$comment = isset($data['comment']) ? trim($data['comment'])   : '';
$consent = !empty($data['consent']) ? 1 : 0;

if (!$token || $rating < 1 || $rating > 5) {
    json_out(array('ok' => false, 'error' => 'Неверные данные'));
}

$pdo = db();

$review = $pdo->prepare('SELECT * FROM reviews WHERE token = ?');
$review->execute(array($token));
$r = $review->fetch();

if (!$r) { json_out(array('ok' => false, 'error' => 'Ссылка недействительна')); }
if ($r['answered_at']) { json_out(array('ok' => false, 'error' => 'already_answered')); }

$pdo->prepare('UPDATE reviews SET rating=?, comment=?, consent=?, answered_at=NOW() WHERE token=?')
   ->execute(array($rating, $comment ?: null, $consent, $token));

// Если оценка 1-4 — уведомляем владельца в ВК
if ($rating <= 4) {
    $stars = str_repeat('⭐', $rating);
    $msg = "😕 НЕГАТИВНЫЙ ОТЗЫВ — заказ #" . $r['order_id'] . "\n"
         . "Оценка: " . $stars . " (" . $rating . "/5)\n"
         . ($comment ? "Комментарий: " . $comment . "\n" : "Без комментария\n")
         . ($r['phone'] ? "Телефон: " . $r['phone'] : '');
    vk_send($msg);
}

json_out(array('ok' => true, 'rating' => $rating));

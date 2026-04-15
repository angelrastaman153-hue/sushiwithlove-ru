<?php
// Напоминание об отзыве — запускается кроном раз в сутки
// Cron Beget: 0 10 * * * curl -s https://xn--90acqmqobo9b7bse.xn--p1ai/api/reviews/remind.php?key=swlRemind2026
// Находит отзывы без ответа старше 5 дней → отправляет напоминание в ВК-беседу

define('REMIND_KEY', 'swlRemind2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== REMIND_KEY) {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

$pdo = db();

// Добавляем reminded_at если нет (безопасно)
try {
    $pdo->exec("ALTER TABLE reviews ADD COLUMN reminded_at DATETIME DEFAULT NULL");
} catch (PDOException $e) { /* уже есть */ }

// Ищем: нет ответа, 5+ дней назад, ещё не напоминали, не ручные (order_id=0)
$stmt = $pdo->query(
    "SELECT * FROM reviews
     WHERE answered_at IS NULL
       AND reminded_at IS NULL
       AND created_at < NOW() - INTERVAL 5 DAY
       AND source != 'manual'
     LIMIT 50"
);
$rows = $stmt->fetchAll();

$count = 0;
foreach ($rows as $r) {
    $link = 'https://xn--90acqmqobo9b7bse.xn--p1ai/review.php?t=' . $r['token'];
    $client = $r['phone'] ?: ('заказ #' . $r['order_id']);

    $msg = "🔔 НАПОМИНАНИЕ — отзыв\n"
         . "Клиент: " . $client . " не оставил отзыв (5 дней)\n"
         . "Ссылка:\n" . $link . "\n"
         . "Отправьте клиенту повторно.";
    vk_send($msg);

    $pdo->prepare('UPDATE reviews SET reminded_at=NOW() WHERE id=?')
       ->execute(array($r['id']));
    $count++;
}

echo 'OK: напоминаний отправлено ' . $count . "\n";
if (!$count) echo "Нет просроченных запросов без ответа.\n";

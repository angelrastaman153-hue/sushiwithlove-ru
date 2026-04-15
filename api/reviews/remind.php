<?php
// Крон-скрипт: отправляет запросы отзывов и напоминания
// Запускать каждые 30 минут: */30 * * * *
// URL для Beget Cron: https://xn--90acqmqobo9b7bse.xn--p1ai/api/reviews/remind.php?key=swlRemind2026

define('REMIND_KEY', 'swlRemind2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== REMIND_KEY) {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

$pdo = db();

// Добавляем новые поля если ещё нет (безопасно)
$alter = array(
    "ALTER TABLE reviews ADD COLUMN scheduled_for DATETIME DEFAULT NULL",
    "ALTER TABLE reviews ADD COLUMN notified_at   DATETIME DEFAULT NULL",
    "ALTER TABLE reviews ADD COLUMN reminded_at   DATETIME DEFAULT NULL",
);
foreach ($alter as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* уже есть */ }
}

$site = 'https://xn--90acqmqobo9b7bse.xn--p1ai';
$sent = 0;
$reminded = 0;

// ШАГ 1: Первичная отправка — scheduled_for наступил, ещё не отправляли
$stmt = $pdo->query(
    "SELECT * FROM reviews
     WHERE notified_at IS NULL
       AND scheduled_for IS NOT NULL
       AND scheduled_for <= NOW()
       AND answered_at IS NULL
     LIMIT 20"
);
foreach ($stmt->fetchAll() as $r) {
    $link   = $site . '/review.php?t=' . $r['token'];
    $client = $r['phone'] ?: ('заказ #' . $r['order_id']);
    $prefix = $r['order_id'] ? ('заказ #' . $r['order_id']) : 'ручной запрос';

    $msg = "⭐ ЗАПРОС ОТЗЫВА — " . $prefix . "\n"
         . "Клиент: " . $client . "\n\n"
         . "Отправьте клиенту это сообщение:\n"
         . "«Спасибо за заказ! Если вам понравилось — оставьте, пожалуйста, отзыв: " . $link . "»";
    vk_send($msg);

    $pdo->prepare('UPDATE reviews SET notified_at=NOW() WHERE id=?')->execute(array($r['id']));
    $sent++;
}

// ШАГ 2: Напоминание — 5 дней без ответа после первичной отправки
$stmt2 = $pdo->query(
    "SELECT * FROM reviews
     WHERE notified_at IS NOT NULL
       AND answered_at IS NULL
       AND reminded_at IS NULL
       AND notified_at < NOW() - INTERVAL 5 DAY
       AND source != 'manual'
     LIMIT 20"
);
foreach ($stmt2->fetchAll() as $r) {
    $link   = $site . '/review.php?t=' . $r['token'];
    $client = $r['phone'] ?: ('заказ #' . $r['order_id']);

    $msg = "🔔 НАПОМИНАНИЕ (5 дней) — заказ #" . $r['order_id'] . "\n"
         . "Клиент: " . $client . " не оставил отзыв\n\n"
         . "Отправьте повторно:\n"
         . "«Добрый день! Напомним про отзыв — он очень важен для нас: " . $link . "»";
    vk_send($msg);

    $pdo->prepare('UPDATE reviews SET reminded_at=NOW() WHERE id=?')->execute(array($r['id']));
    $reminded++;
}

echo "OK: отправлено первичных=" . $sent . ", напоминаний=" . $reminded . "\n";

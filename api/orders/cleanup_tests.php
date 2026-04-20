<?php
// ОДНОРАЗОВЫЙ СКРИПТ — удаляет ВСЕ заказы кроме боевых #1, #2, #3
// (по display_number). Каскадом чистит order_log, reviews, points_log.
// Баллы в users.points НЕ пересчитывает (остаются как есть).
//
// Открыть: https://сушислюбовью.рф/api/orders/cleanup_tests.php?key=swlTest2026
// Для боевого удаления добавь &apply=1 (без — только превью)

define('SETUP_KEY', 'swlTest2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

// Что оставляем: боевые заказы с display_number 1,2,3
$keepRows = $pdo->query("
    SELECT id, display_number, is_test, client_name, total_paid, created_at
    FROM orders
    WHERE (is_test IS NULL OR is_test=0) AND display_number IN (1,2,3)
    ORDER BY display_number
")->fetchAll();

// Что удаляем: всё остальное
$deleteRows = $pdo->query("
    SELECT id, display_number, is_test, client_name, total_paid, created_at
    FROM orders
    WHERE NOT ((is_test IS NULL OR is_test=0) AND display_number IN (1,2,3))
    ORDER BY id
")->fetchAll();

echo "=== ОСТАВЛЯЕМ (" . count($keepRows) . ") ===\n";
foreach ($keepRows as $r) {
    echo sprintf("  #%s | id=%d | %s | %s ₽ | %s\n",
        $r['display_number'] ?: '—', $r['id'], $r['client_name'] ?: '—',
        $r['total_paid'], $r['created_at']);
}

echo "\n=== УДАЛЯЕМ (" . count($deleteRows) . ") ===\n";
foreach ($deleteRows as $r) {
    $label = $r['is_test'] ? 'ТЕСТ' : ('#' . ($r['display_number'] ?: '—'));
    echo sprintf("  %s | id=%d | %s | %s ₽ | %s\n",
        $label, $r['id'], $r['client_name'] ?: '—',
        $r['total_paid'], $r['created_at']);
}

if (!$apply) {
    echo "\n--- ПРЕВЬЮ. Для удаления добавь ?apply=1 ---\n";
    echo "Пример: " . $_SERVER['PHP_SELF'] . "?key=" . SETUP_KEY . "&apply=1\n";
    exit;
}

if (empty($deleteRows)) {
    echo "\nНечего удалять.\n";
    exit;
}

$deleteIds = array_map(function($r){ return (int)$r['id']; }, $deleteRows);
$placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

$pdo->beginTransaction();
try {
    // order_log
    $n1 = $pdo->prepare("DELETE FROM order_log WHERE order_id IN ($placeholders)");
    $n1->execute($deleteIds);
    echo "\nУдалено из order_log: " . $n1->rowCount() . "\n";

    // reviews
    try {
        $n2 = $pdo->prepare("DELETE FROM reviews WHERE order_id IN ($placeholders)");
        $n2->execute($deleteIds);
        echo "Удалено из reviews: " . $n2->rowCount() . "\n";
    } catch (Exception $e) {
        echo "reviews: таблица отсутствует — пропускаем\n";
    }

    // points_log
    $n3 = $pdo->prepare("DELETE FROM points_log WHERE order_id IN ($placeholders)");
    $n3->execute($deleteIds);
    echo "Удалено из points_log: " . $n3->rowCount() . "\n";

    // orders
    $n4 = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
    $n4->execute($deleteIds);
    echo "Удалено из orders: " . $n4->rowCount() . "\n";

    $pdo->commit();
    echo "\n✅ Готово! Баллы в users.points не тронуты.\n";
    echo "Удали этот файл с сервера.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Ошибка: " . $e->getMessage() . "\n";
}

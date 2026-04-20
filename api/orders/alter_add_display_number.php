<?php
// ОДНОРАЗОВЫЙ СКРИПТ — добавляет колонку display_number в orders
// и проставляет её для всех существующих боевых заказов (is_test=0)
// Нумерация: 1, 2, 3, ... по порядку created_at (тестовые пропускаются)
//
// Открыть: https://сушислюбовью.рф/api/orders/alter_add_display_number.php?key=swlTest2026

define('SETUP_KEY', 'swlTest2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN display_number INT DEFAULT NULL AFTER is_test");
    echo "OK: колонка display_number добавлена\n";
} catch (PDOException $e) {
    echo "INFO: колонка уже есть (" . $e->getMessage() . ")\n";
}

try {
    $pdo->exec("CREATE INDEX idx_orders_display_number ON orders(display_number)");
    echo "OK: индекс создан\n";
} catch (PDOException $e) {
    echo "INFO: индекс уже есть\n";
}

// Бэкфилл: проставить display_number боевым заказам по порядку created_at
$rows = $pdo->query("SELECT id FROM orders WHERE (is_test IS NULL OR is_test=0) ORDER BY created_at ASC, id ASC")->fetchAll();
$stmt = $pdo->prepare("UPDATE orders SET display_number=? WHERE id=?");
$n = 0;
foreach ($rows as $r) {
    $n++;
    $stmt->execute(array($n, $r['id']));
}
echo "OK: проставлено display_number для {$n} боевых заказов\n";

echo "\nГотово. Удали этот файл с сервера!\n";

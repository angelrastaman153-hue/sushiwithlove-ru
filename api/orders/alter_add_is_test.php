<?php
// ОДНОРАЗОВЫЙ СКРИПТ — добавляет колонку is_test в таблицу orders
// Открыть: https://сушислюбовью.рф/api/orders/alter_add_is_test.php?key=swlTest2026

define('SETUP_KEY', 'swlTest2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
$pdo = db();

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN is_test TINYINT DEFAULT 0 AFTER status");
    echo 'OK: колонка is_test добавлена в таблицу orders<br>';
} catch (PDOException $e) {
    echo 'ERR: ' . $e->getMessage() . '<br>';
}

echo '<br><b>Готово. Удали этот файл с сервера!</b>';

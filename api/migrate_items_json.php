<?php
// Одноразовая миграция: добавить поля orders для отображения в админке
// Открыть: https://сушислюбовью.рф/api/migrate_items_json.php
// После успешной миграции файл удалить.

require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

$columns = array(
    'items_json'    => "ALTER TABLE orders ADD COLUMN items_json TEXT NULL AFTER total_paid",
    'delivery_type' => "ALTER TABLE orders ADD COLUMN delivery_type VARCHAR(20) NULL AFTER items_json",
    'address'       => "ALTER TABLE orders ADD COLUMN address VARCHAR(255) NULL AFTER delivery_type",
    'pay_type'      => "ALTER TABLE orders ADD COLUMN pay_type VARCHAR(20) NULL AFTER address",
    'comment'       => "ALTER TABLE orders ADD COLUMN comment TEXT NULL AFTER pay_type"
);

foreach ($columns as $col => $sql) {
    try {
        $exists = $pdo->query("SHOW COLUMNS FROM orders LIKE '" . $col . "'")->fetch();
        if ($exists) {
            echo "OK: " . $col . " уже есть\n";
        } else {
            $pdo->exec($sql);
            echo "OK: " . $col . " добавлен\n";
        }
    } catch (Exception $e) {
        echo "ERROR (" . $col . "): " . $e->getMessage() . "\n";
    }
}

echo "\nГотово. Теперь удали api/migrate_items_json.php с сервера.\n";

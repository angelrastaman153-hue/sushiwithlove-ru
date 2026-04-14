<?php
// ОДНОРАЗОВЫЙ СКРИПТ — добавляет роль 'admin' в таблицу staff
// Открыть: https://сушислюбовью.рф/api/staff/alter_admin_role.php?key=swlAlter2026

define('SETUP_KEY', 'swlAlter2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
$pdo = db();

try {
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN role ENUM('owner','admin','operator','courier') DEFAULT 'operator'");
    echo 'OK: role ENUM updated (added admin)<br>';
} catch (PDOException $e) {
    echo 'ERR: ' . $e->getMessage() . '<br>';
}

echo '<br><b>Готово. Удали этот файл с сервера!</b>';

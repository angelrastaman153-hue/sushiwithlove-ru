<?php
// ОДНОРАЗОВЫЙ СКРИПТ — меняет role с ENUM на SET (поддержка нескольких ролей)
// Открыть: https://сушислюбовью.рф/api/staff/alter_role_to_set.php?key=swlSet2026

define('SETUP_KEY', 'swlSet2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
$pdo = db();

try {
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN role SET('owner','admin','operator','courier') DEFAULT 'operator'");
    echo 'OK: role изменён с ENUM на SET (поддержка нескольких ролей)<br>';
} catch (PDOException $e) {
    echo 'ERR: ' . $e->getMessage() . '<br>';
}

echo '<br><b>Готово. Удали этот файл с сервера!</b>';

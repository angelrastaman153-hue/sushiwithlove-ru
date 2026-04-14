<?php
// ОДНОРАЗОВЫЙ СКРИПТ — запустить один раз, потом УДАЛИТЬ с сервера!
// Открыть: https://сушислюбовью.рф/api/staff/migrate.php?key=swlStaff2026

define('SETUP_KEY', 'swlStaff2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
$pdo = db();

$queries = array(
    "CREATE TABLE IF NOT EXISTS staff (
      id         INT AUTO_INCREMENT PRIMARY KEY,
      name       VARCHAR(100) NOT NULL,
      login      VARCHAR(50) NOT NULL UNIQUE,
      password   VARCHAR(255) NOT NULL,
      role       ENUM('owner','operator','courier') DEFAULT 'operator',
      active     TINYINT DEFAULT 1,
      created_at DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS order_log (
      id          INT AUTO_INCREMENT PRIMARY KEY,
      order_id    INT NOT NULL,
      staff_id    INT,
      staff_name  VARCHAR(100),
      from_status VARCHAR(30),
      to_status   VARCHAR(30),
      created_at  DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);

foreach ($queries as $q) {
    try { $pdo->exec($q); echo 'OK: ' . substr($q, 0, 60) . '...<br>'; }
    catch (PDOException $e) { echo 'ERR: ' . $e->getMessage() . '<br>'; }
}

// Начальные аккаунты
$accounts = array(
    array('Михаил Богачёв',  'mikhail', 'swlBoss2026',   'owner'),
    array('Милана Романова', 'milana',  'swlMilana2026', 'operator'),
);
foreach ($accounts as $a) {
    $hash = password_hash($a[2], PASSWORD_DEFAULT);
    try {
        $pdo->prepare('INSERT IGNORE INTO staff (name, login, password, role) VALUES (?,?,?,?)')
            ->execute(array($a[0], $a[1], $hash, $a[3]));
        echo 'Staff added: ' . $a[0] . ' [' . $a[3] . ']<br>';
    } catch (PDOException $e) {
        echo 'Staff ERR: ' . $e->getMessage() . '<br>';
    }
}

echo '<br><b>Готово. Удали этот файл с сервера!</b>';

<?php
// ОДНОРАЗОВЫЙ СКРИПТ — создаёт таблицу reviews + добавляет phone/name в orders
// Открыть: https://сушислюбовью.рф/api/reviews/migrate.php?key=swlReview2026

define('SETUP_KEY', 'swlReview2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
$pdo = db();

// Таблица отзывов
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        order_id    INT NOT NULL,
        token       VARCHAR(64) NOT NULL UNIQUE,
        phone       VARCHAR(20) DEFAULT NULL,
        rating      TINYINT DEFAULT NULL,
        comment     TEXT DEFAULT NULL,
        source      VARCHAR(20) DEFAULT 'site',
        consent     TINYINT DEFAULT 0,
        created_at  DATETIME DEFAULT NOW(),
        answered_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo 'OK: таблица reviews создана<br>';
} catch (PDOException $e) {
    echo 'ERR reviews: ' . $e->getMessage() . '<br>';
}

// Добавляем phone в orders (если нет)
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN client_phone VARCHAR(20) DEFAULT NULL AFTER is_test");
    echo 'OK: колонка client_phone добавлена в orders<br>';
} catch (PDOException $e) {
    echo 'SKIP client_phone: ' . $e->getMessage() . '<br>';
}

// Добавляем name в orders (если нет)
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN client_name VARCHAR(100) DEFAULT NULL AFTER client_phone");
    echo 'OK: колонка client_name добавлена в orders<br>';
} catch (PDOException $e) {
    echo 'SKIP client_name: ' . $e->getMessage() . '<br>';
}

echo '<br><b>Готово. Удали этот файл с сервера!</b>';

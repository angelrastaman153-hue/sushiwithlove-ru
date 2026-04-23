<?php
// ОДНОРАЗОВЫЙ СКРИПТ — запустить один раз, потом удалить с сервера!
// Открыть в браузере: https://сушислюбовью.рф/api/setup.php?key=ВСТАВЬ_СЕКРЕТ

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SETUP_KEY', 'swl2026setup');

if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/db.php';
$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  phone         VARCHAR(20) UNIQUE,
  email         VARCHAR(100) UNIQUE,
  tg_id         BIGINT UNIQUE,
  vk_id         BIGINT UNIQUE,
  name          VARCHAR(100),
  birth_date    DATE,
  points        INT DEFAULT 100,
  last_order_at DATETIME,
  created_at    DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  id         VARCHAR(64) PRIMARY KEY,
  user_id    INT NOT NULL,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT,
  fp_order_id    VARCHAR(50),
  items_total    INT NOT NULL,
  delivery_cost  INT DEFAULT 0,
  promo_code     VARCHAR(20),
  promo_discount INT DEFAULT 0,
  points_spent   INT DEFAULT 0,
  points_earned  INT DEFAULT 0,
  total_paid     INT NOT NULL,
  status         ENUM('new','cooking','delivering','done','cancelled') DEFAULT 'new',
  created_at     DATETIME DEFAULT NOW(),
  delivered_at   DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS points_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  order_id   INT,
  delta      INT NOT NULL,
  reason     VARCHAR(100),
  created_at DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loyalty_config (
  key_name VARCHAR(50) PRIMARY KEY,
  value    VARCHAR(200),
  comment  VARCHAR(200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO loyalty_config VALUES
  ('earn_pct',        '3',   '% начисления от суммы товаров'),
  ('spend_max_pct',   '30',  'Макс % списания от суммы товаров'),
  ('min_order_spend', '0',   'Мин сумма заказа для списания (0 = нет ограничения)'),
  ('welcome_bonus',   '100', 'Баллы за регистрацию'),
  ('expire_days',     '180', 'Дней до сгорания баллов');
";

foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) {
    try { $pdo->exec($q); echo "OK: " . substr($q, 0, 60) . "...<br>"; }
    catch (PDOException $e) { echo "ERR: " . $e->getMessage() . "<br>"; }
}

// Миграции — безопасно запускать повторно (ALTER IGNORE не существует, поэтому try/catch)
$migrations = array(
    "ALTER TABLE orders ADD COLUMN delivery_date DATETIME NULL COMMENT 'Желаемая дата/время доставки'",
);
foreach ($migrations as $q) {
    try { $pdo->exec($q); echo "Migration OK: " . substr($q, 0, 80) . "...<br>"; }
    catch (PDOException $e) { echo "Migration skip (already exists?): " . $e->getMessage() . "<br>"; }
}

echo "<br><b>Готово. Удали этот файл с сервера!</b>";

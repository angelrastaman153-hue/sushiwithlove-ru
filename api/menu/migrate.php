<?php
// Запустить один раз: создаёт таблицы menu_categories и menu_items
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS menu_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  crm_id      VARCHAR(36) UNIQUE,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) NOT NULL,
  sort_order  INT DEFAULT 0,
  is_active   TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "menu_categories — OK\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS menu_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  crm_id         VARCHAR(36) UNIQUE,
  category_id    INT NOT NULL,
  name           VARCHAR(200) NOT NULL,
  description    TEXT,
  price          DECIMAL(10,2) NOT NULL DEFAULT 0,
  weight_grams   INT,
  image_url      VARCHAR(500),
  fp_article_id  INT DEFAULT NULL COMMENT 'Артикул FrontPad',
  is_active      TINYINT DEFAULT 1,
  is_stop        TINYINT DEFAULT 0,
  sort_order     INT DEFAULT 0,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES menu_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "menu_items — OK\n";
echo "\nГотово. Теперь зайди в панель → вкладка Меню → Синхронизировать.\n";

<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS analytics_sessions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  session_id   VARCHAR(64) NOT NULL UNIQUE,
  utm_source   VARCHAR(100) DEFAULT NULL,
  utm_medium   VARCHAR(100) DEFAULT NULL,
  utm_campaign VARCHAR(200) DEFAULT NULL,
  referrer     VARCHAR(500) DEFAULT NULL,
  device       VARCHAR(20)  DEFAULT NULL,
  funnel_stage VARCHAR(20)  DEFAULT 'landed',
  order_id     INT          DEFAULT NULL,
  created_at   DATETIME     DEFAULT NOW(),
  updated_at   DATETIME     DEFAULT NOW() ON UPDATE NOW(),
  INDEX idx_created (created_at),
  INDEX idx_stage   (funnel_stage),
  INDEX idx_source  (utm_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "OK: таблица analytics_sessions создана\n";

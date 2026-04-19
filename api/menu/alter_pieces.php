<?php
// Миграция: добавляет колонку pieces_count в menu_items.
// Запустить один раз: /api/menu/alter_pieces.php
require_once __DIR__ . "/../db.php";
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

try {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN pieces_count INT DEFAULT NULL");
    echo "Добавлена колонка pieces_count\n";
} catch (Exception $e) {
    echo "pieces_count уже есть\n";
}
echo "Готово!\n";

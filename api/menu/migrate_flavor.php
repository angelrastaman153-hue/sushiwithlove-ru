<?php
/**
 * Миграция: добавляет колонку flavor_description в menu_items.
 * Это «вкусное описание» — короткая маркетинговая аннотация,
 * которая показывается только в попапе/оверлее карточки.
 * Базовое description остаётся как состав (ингредиенты).
 *
 * Запуск: /api/menu/migrate_flavor.php  (только залогиненный админ)
 * Удалить файл с сервера после применения.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: text/html; charset=utf-8');

session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo '<html><head><meta charset="utf-8"></head><body style="font-family:system-ui;padding:40px">';
    echo '<h2>Доступ только для владельца</h2>';
    echo '<p>Сначала залогинься: <a href="/api/admin/">/api/admin/</a></p>';
    echo '</body></html>';
    exit;
}

$pdo = db();

echo '<html><head><meta charset="utf-8"><title>Миграция flavor_description</title></head>';
echo '<body style="font-family:system-ui;max-width:600px;margin:40px auto;padding:0 20px">';
echo '<h1>Миграция menu_items.flavor_description</h1>';

try {
    $col = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'flavor_description'")->fetch();
    if ($col) {
        echo '<p style="color:#888">ℹ️ Колонка <code>flavor_description</code> уже существует — миграция не нужна.</p>';
    } else {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN flavor_description TEXT DEFAULT NULL AFTER description");
        echo '<p style="color:#090;font-weight:700">✅ Колонка <code>flavor_description</code> добавлена.</p>';
    }
    echo '<p><a href="/api/admin/">← Назад в админку</a></p>';
    echo '<p style="color:#c00"><b>Важно:</b> удали файл <code>/api/menu/migrate_flavor.php</code> с сервера после успешной миграции.</p>';
} catch (Exception $e) {
    echo '<p style="color:#c00">❌ Ошибка: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</body></html>';

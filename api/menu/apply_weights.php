<?php
// Массовое заполнение веса и количества штук для товаров.
// Формат строки: ['Точное имя из БД' => ['w' => 280, 'p' => 8]]
//   w — вес в граммах (null = не менять)
//   p — количество штук (null = не менять)
//
// Использование:
//   1. Отредактируй массив ниже (заполни для своих позиций).
//   2. Запусти один раз: /api/menu/apply_weights.php
//   3. Посмотри отчёт — что обновлено, что не найдено.
//
// ВАЖНО: сначала запусти /api/menu/alter_pieces.php (миграция колонки).

require_once __DIR__ . "/../db.php";
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

// ─── ЗАПОЛНИ ЗДЕСЬ ────────────────────────────────────────────────────────────
// Имена должны совпадать с menu_items.name (с учётом регистра и пробелов).
// Можно заполнять только часть полей — укажи w или p, другое оставь null.
$data = [
    // Пример (удали эти строки и напиши свои):
    // 'Белая филадельфия'   => ['w' => 280, 'p' => 8],
    // 'Филадельфия классик' => ['w' => 300, 'p' => 8],
    // 'Сет Темпурный'       => ['w' => 820, 'p' => 40],
    // 'БАБЛ МИЛК манго 0,3л'=> ['w' => null, 'p' => null], // у напитков штуки обычно не нужны
];
// ──────────────────────────────────────────────────────────────────────────────

if (empty($data)) {
    echo "Массив \$data пуст. Заполни его и запусти снова.\n";
    exit;
}

$stmtW  = $pdo->prepare("UPDATE menu_items SET weight_grams=? WHERE TRIM(name)=TRIM(?)");
$stmtP  = $pdo->prepare("UPDATE menu_items SET pieces_count=? WHERE TRIM(name)=TRIM(?)");
$stmtWP = $pdo->prepare("UPDATE menu_items SET weight_grams=?, pieces_count=? WHERE TRIM(name)=TRIM(?)");

$ok = 0; $notFound = [];
foreach ($data as $name => $d) {
    $w = isset($d['w']) ? $d['w'] : null;
    $p = isset($d['p']) ? $d['p'] : null;
    if ($w !== null && $p !== null) {
        $stmtWP->execute([$w, $p, $name]);
        $affected = $stmtWP->rowCount();
    } elseif ($w !== null) {
        $stmtW->execute([$w, $name]);
        $affected = $stmtW->rowCount();
    } elseif ($p !== null) {
        $stmtP->execute([$p, $name]);
        $affected = $stmtP->rowCount();
    } else {
        continue;
    }
    if ($affected > 0) $ok++;
    else $notFound[] = $name;
}

echo "Обновлено: {$ok}\n";
if ($notFound) {
    echo "Не найдено в menu_items (проверь точное имя):\n";
    foreach ($notFound as $n) echo "  - {$n}\n";
}
echo "Готово!\n";

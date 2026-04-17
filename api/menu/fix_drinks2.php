<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$stmt = $pdo->prepare("UPDATE menu_items SET group_key=?, variant_label=? WHERE TRIM(name)=TRIM(?)");

// 1. Имбирь — реальные имена в БД это "ИМБИРЬ, Xг"
$imbir = [
    ['imbir', '30 г',  'ИМБИРЬ, 30г'],
    ['imbir', '50 г',  'ИМБИРЬ, 50г'],
    ['imbir', '100 г', 'ИМБИРЬ, 100г'],
];
foreach ($imbir as $f) {
    $stmt->execute([$f[0], $f[1], $f[2]]);
    echo ($stmt->rowCount() > 0 ? 'OK' : 'НЕТ') . ": {$f[2]}\n";
}

// 2. Соки — переименовываем "Сок, 1л" и добавляем вкусы
$catNapitki = $pdo->query("SELECT id FROM menu_categories WHERE name='Напитки' LIMIT 1")->fetchColumn();

// Переименуем существующий "Сок, 1л" в "Сок яблоко 1л"
$pdo->exec("UPDATE menu_items SET name='Сок яблоко 1л', group_key='sok', variant_label='Яблоко', sort_order=185 WHERE TRIM(name)='Сок, 1л'");
echo "\nСок, 1л → Сок яблоко 1л: " . ($pdo->query("SELECT COUNT(*) FROM menu_items WHERE name='Сок яблоко 1л'")->fetchColumn() ? 'OK' : 'НЕТ') . "\n";

// Добавляем остальные вкусы (fp_article_id пока совпадает — уточни у оператора если разные)
$soки = [
    ['Сок апельсин 1л',    'sok', 'Апельсин',   141, 186],
    ['Сок манго 1л',       'sok', 'Манго',       141, 187],
    ['Сок мультифрукт 1л', 'sok', 'Мультифрукт', 141, 188],
];
$ins = $pdo->prepare("INSERT IGNORE INTO menu_items (category_id,name,price,fp_article_id,group_key,variant_label,sort_order,is_active) VALUES (?,?,199,?,?,?,?,1)");
$chk = $pdo->prepare("SELECT id FROM menu_items WHERE name=? LIMIT 1");
foreach ($soки as $s) {
    $chk->execute([$s[0]]);
    if (!$chk->fetchColumn()) {
        $ins->execute([$catNapitki, $s[0], $s[3], $s[1], $s[2], $s[4]]);
        echo "Добавлен: {$s[0]}\n";
    } else {
        // Обновить group_key если уже есть
        $pdo->prepare("UPDATE menu_items SET group_key=?, variant_label=? WHERE name=?")->execute([$s[1],$s[2],$s[0]]);
        echo "Уже есть: {$s[0]}\n";
    }
}

// 3. Финальная проверка — все группы в добавках
echo "\n=== ИТОГ: Добавки к роллам с group_key ===\n";
$rows = $pdo->query("
    SELECT mi.name, mi.group_key, mi.variant_label, mi.fp_article_id
    FROM menu_items mi JOIN menu_categories mc ON mc.id=mi.category_id
    WHERE mc.name='Добавки к роллам' AND mi.is_active=1
    ORDER BY mi.group_key, mi.sort_order
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $gk = $r['group_key'] ? "[{$r['group_key']}:{$r['variant_label']}]" : '[БЕЗ ГРУППЫ]';
    $fp = $r['fp_article_id'] ? "fp={$r['fp_article_id']}" : 'fp=нет';
    echo "  {$r['name']} — {$gk} {$fp}\n";
}
echo "\nГотово!\n";

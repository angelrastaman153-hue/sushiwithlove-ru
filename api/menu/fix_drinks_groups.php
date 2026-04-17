<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

// === ДИАГНОСТИКА: что сейчас в Напитках и Добавках ===
echo "=== НАПИТКИ (текущее состояние) ===\n";
$rows = $pdo->query("
  SELECT mi.id, mi.name, mi.price, mi.is_active, mi.group_key, mi.variant_label, mi.fp_article_id
  FROM menu_items mi
  JOIN menu_categories mc ON mc.id = mi.category_id
  WHERE mc.name IN ('Напитки','Добавки к роллам')
  ORDER BY mc.name, mi.sort_order, mi.name
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $active = $r['is_active'] ? '' : ' [СКРЫТ]';
    $gk = $r['group_key'] ? " [gk={$r['group_key']}:{$r['variant_label']}]" : '';
    $fp = $r['fp_article_id'] ? " fp={$r['fp_article_id']}" : '';
    echo "  #{$r['id']} {$r['name']} — {$r['price']}р{$fp}{$gk}{$active}\n";
}

echo "\n=== ПРИМЕНЯЕМ ГРУППЫ ===\n";

$stmt = $pdo->prepare("UPDATE menu_items SET group_key=?, variant_label=? WHERE TRIM(name)=TRIM(?)");

$fixes = [
    // Соевый соус — ОБА варианта в одну группу 'soeviy'
    ['soeviy', '40 мл',  'Соевый соус (баночка 40мл)'],
    ['soeviy', '100 мл', 'Соевый соус фирменный (бутылочка) 100мл'],
    // Соус барбекю
    ['barbekyu', '30 г', 'СОУС БАРБЕКЮ, 30г'],
    ['barbekyu', '50 г', 'СОУС БАРБЕКЮ, 50г'],
    // Соус ореховый
    ['orehoviy', '30 г', 'СОУС ОРЕХОВЫЙ, 30г'],
    ['orehoviy', '50 г', 'СОУС ОРЕХОВЫЙ, 50г'],
];

$notFound = [];
foreach ($fixes as $f) {
    $stmt->execute([$f[0], $f[1], $f[2]]);
    if ($stmt->rowCount() > 0) echo "OK: {$f[2]}\n";
    else $notFound[] = $f[2];
}
if ($notFound) echo "Не найдено: " . implode(', ', $notFound) . "\n";

// === ЛИМОНАДЫ — проверяем и добавляем если нет ===
echo "\n=== ЛИМОНАДЫ ===\n";
$catNapitki = $pdo->query("SELECT id FROM menu_categories WHERE name='Напитки' LIMIT 1")->fetchColumn();
if (!$catNapitki) { echo "Категория Напитки не найдена!\n"; exit; }

$lims = [
    ['name'=>'Лимонад Слива 0,5л',      'price'=>99, 'group_key'=>'limonad', 'variant_label'=>'Слива',      'sort_order'=>200],
    ['name'=>'Лимонад Груша 0,5л',       'price'=>99, 'group_key'=>'limonad', 'variant_label'=>'Груша',       'sort_order'=>201],
    ['name'=>'Лимонад Смородина 0,5л',   'price'=>99, 'group_key'=>'limonad', 'variant_label'=>'Смородина',   'sort_order'=>202],
    ['name'=>'Лимонад Барбарис 0,5л',    'price'=>99, 'group_key'=>'limonad', 'variant_label'=>'Барбарис',    'sort_order'=>203],
];
$ins = $pdo->prepare("INSERT IGNORE INTO menu_items (category_id,name,price,group_key,variant_label,sort_order,is_active) VALUES (?,?,?,?,?,?,1)");
$chk = $pdo->prepare("SELECT id FROM menu_items WHERE name=? LIMIT 1");
foreach ($lims as $l) {
    $chk->execute([$l['name']]);
    $exists = $chk->fetchColumn();
    if ($exists) { echo "Уже есть: {$l['name']}\n"; }
    else {
        $ins->execute([$catNapitki, $l['name'], $l['price'], $l['group_key'], $l['variant_label'], $l['sort_order']]);
        echo "Добавлен: {$l['name']}\n";
    }
}

// === СОКИ по вкусам — проверяем ===
echo "\n=== СОКИ ===\n";
$sokRows = $pdo->query("SELECT id, name, group_key, variant_label, is_active FROM menu_items WHERE name LIKE '%ок%' OR name LIKE '%СОК%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sokRows as $r) {
    $a = $r['is_active'] ? '' : ' [СКРЫТ]';
    $g = $r['group_key'] ? " [gk={$r['group_key']}]" : '';
    echo "  #{$r['id']} {$r['name']}{$g}{$a}\n";
}

echo "\nГотово!\n";

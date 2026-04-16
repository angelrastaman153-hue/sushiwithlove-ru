<?php
require_once __DIR__ . "/../db.php";
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

// 1. Напитки
$drinks = [
    [142, 'Кока кола, 0.5л'],
    [141, 'Сок, 1л'],
    [420, 'Морс клюквенный (0,33л)'],
];
$stmt = $pdo->prepare("UPDATE menu_items SET fp_article_id=? WHERE name=?");
$count = 0;
foreach ($drinks as $d) { $stmt->execute([$d[0], $d[1]]); $count += $stmt->rowCount(); }
echo "Напитки: {$count} позиций обновлено\n";

// 2. Разбить имбирь на 3 позиции
$ib = $pdo->query("SELECT * FROM menu_items WHERE name='ИМБИРЬ МАРИНОВАННЫЙ' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($ib) {
    $pdo->prepare("UPDATE menu_items SET name='Имбирь маринованный 30г', fp_article_id=123, price=30, group_key='imbir', variant_label='30г' WHERE id=?")
        ->execute([$ib['id']]);
    $pdo->prepare("INSERT IGNORE INTO menu_items (category_id,name,price,sort_order,is_active,fp_article_id,group_key,variant_label) VALUES (?,?,?,?,1,?,?,?)")
        ->execute([$ib['category_id'],'Имбирь маринованный 50г',50,$ib['sort_order']+1,120,'imbir','50г']);
    $pdo->prepare("INSERT IGNORE INTO menu_items (category_id,name,price,sort_order,is_active,fp_article_id,group_key,variant_label) VALUES (?,?,?,?,1,?,?,?)")
        ->execute([$ib['category_id'],'Имбирь маринованный 100г',65,$ib['sort_order']+2,128,'imbir','100г']);
    echo "Имбирь: разбит на 3 позиции (123, 120, 128)\n";
} else {
    echo "Имбирь: не найден (уже разбит?)\n";
}

// 3. Исправить group_key для уже существующей записи имбиря 30г (на случай если она создалась без group_key)
$pdo->exec("UPDATE menu_items SET group_key='imbir', variant_label='30г' WHERE name='Имбирь маринованный 30г' AND (group_key IS NULL OR group_key='')");
$pdo->exec("UPDATE menu_items SET group_key='imbir', variant_label='50г' WHERE name='Имбирь маринованный 50г' AND (group_key IS NULL OR group_key='')");
$pdo->exec("UPDATE menu_items SET group_key='imbir', variant_label='100г' WHERE name='Имбирь маринованный 100г' AND (group_key IS NULL OR group_key='')");

echo "Готово!\n";

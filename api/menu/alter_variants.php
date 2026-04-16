<?php
require_once __DIR__ . "/../db.php";
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

// Добавляем колонки для группировки вариантов
try {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN group_key VARCHAR(50) DEFAULT NULL");
    echo "Добавлена колонка group_key\n";
} catch (Exception $e) { echo "group_key уже есть\n"; }

try {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN variant_label VARCHAR(50) DEFAULT NULL");
    echo "Добавлена колонка variant_label\n";
} catch (Exception $e) { echo "variant_label уже есть\n"; }

// Группы соусов: [group_key, variant_label, name_in_db]
$groups = [
    ['spaysi',   '30г',  'Соус спайси (острый), 30г'],
    ['spaysi',   '50г',  'Соус спайси (острый), 50г'],
    ['syrny',    '30г',  'Соус сырный, 30 гр'],
    ['syrny',    '50г',  'Соус сырный, 50 гр'],
    ['teriaki',  '30г',  'Соус терияки, 30 гр'],
    ['teriaki',  '50г',  'Соус терияки, 50 гр'],
    ['unagi',    '30г',  'Соус унаги, 30 гр'],
    ['unagi',    '50г',  'Соус унаги, 50 гр'],
    ['firmen',   '30г',  'СОУС ФИРМЕННЫЙ (фри / наггетсы), 30гр'],
    ['firmen',   '50г',  'СОУС ФИРМЕННЫЙ (фри / наггетсы), 50гр'],
    ['soeviy',   '40мл', 'Соевый соус (баночка 40мл)'],
    ['soeviy_chef', '100мл', 'Соевый соус фирменный (бутылочка) 100мл'],
    ['imbir',    '50г',  'ИМБИРЬ МАРИНОВАННЫЙ'],
    // Коктейли
    ['kokteil',  '0,3л', 'ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,3л'],
    ['kokteil',  '0,4л', 'ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,4л'],
    ['kokteil',  '0,5л', 'ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,5л'],
];

$stmt = $pdo->prepare("UPDATE menu_items SET group_key=?, variant_label=? WHERE name=?");
$count = 0;
foreach ($groups as $g) {
    $stmt->execute([$g[0], $g[1], $g[2]]);
    $count += $stmt->rowCount();
}
echo "Группы вариантов: {$count} позиций обновлено\n";
echo "Готово!\n";

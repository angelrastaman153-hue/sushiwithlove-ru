<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$groups = [
    // Васаби
    ['ВАСАБИ, 20г',              'vasabi',    '20 г'],
    ['ВАСАБИ, 40г',              'vasabi',    '40 г'],
    ['ВАСАБИ, 60г',              'vasabi',    '60 г'],
    // БАБЛ МИЛК
    ['БАБЛ МИЛК манго 0,3л',    'bablmilk',  '0,3 л'],
    ['БАБЛ МИЛК манго 0,4л',    'bablmilk',  '0,4 л'],
    ['БАБЛ МИЛК манго 0,5л',    'bablmilk',  '0,5 л'],
    // Коктейль (если ещё нет)
    ['ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,3л', 'kokteil', '0,3 л'],
    ['ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,4л', 'kokteil', '0,4 л'],
    ['ВКУСНЫЙ МОЛОЧНЫЙ КОКТЕЙЛЬ 0,5л', 'kokteil', '0,5 л'],
    // Имбирь (если ещё нет)
    ['Имбирь маринованный 30г',  'imbir',     '30 г'],
    ['Имбирь маринованный 50г',  'imbir',     '50 г'],
    ['Имбирь маринованный 100г', 'imbir',     '100 г'],
];

$stmt = $pdo->prepare("UPDATE menu_items SET group_key=?, variant_label=? WHERE TRIM(name)=TRIM(?)");
$count = 0;
$notFound = [];
foreach ($groups as $g) {
    $stmt->execute([$g[1], $g[2], $g[0]]);
    if ($stmt->rowCount() > 0) { $count++; echo "OK: {$g[0]}\n"; }
    else { $notFound[] = $g[0]; }
}

echo "\nОбновлено: $count позиций\n";
if ($notFound) echo "Не найдено: " . implode(', ', $notFound) . "\n";
echo "Готово!\n";

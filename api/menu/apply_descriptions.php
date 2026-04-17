<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$file = __DIR__ . '/card-descriptions.csv';
if (!file_exists($file)) { die("CSV не найден: $file\n"); }

$handle = fopen($file, 'r');
$header = fgetcsv($handle); // пропускаем заголовок

// Индексы колонок: sku,name,main_category,price,weight_grams,description_temp,composition_temp,source_note
// 0    1     2              3      4            5                6                7

$stmt = $pdo->prepare("UPDATE menu_items SET description=? WHERE LOWER(TRIM(name))=LOWER(TRIM(?))");
$count = 0;
$skipped = 0;
$notFound = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 6) continue;
    $name = trim($row[1]);
    $desc = trim($row[5]);
    if ($name === '' || $desc === '') { $skipped++; continue; }

    $stmt->execute([$desc, $name]);
    if ($stmt->rowCount() > 0) {
        $count++;
    } else {
        $notFound[] = $name;
    }
}
fclose($handle);

echo "Обновлено: $count позиций\n";
echo "Пропущено (нет описания): $skipped\n";
echo "Не найдено в БД: " . count($notFound) . "\n";
if ($notFound) {
    echo "\nНе нашли в menu_items:\n";
    foreach ($notFound as $n) echo "  - $n\n";
}
echo "\nГотово!\n";

<?php
/**
 * Разовая утилита: находит в menu_items.description коды полуфабрикатов
 * (ц1, р1, о5, 52, «рис 120», «тортилья 1» и т.п.) и убирает их.
 *
 * Usage:
 *   /api/menu/clean_descriptions.php          — preview (diff)
 *   /api/menu/clean_descriptions.php?apply=1  — применить к БД
 *
 * Удалить файл с сервера после использования.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo 'Залогинься в /api/admin/';
    exit;
}

header('Content-Type: text/html; charset=utf-8');

function clean_desc($s) {
    $s = (string)$s;
    // нормализуем пробелы и неразрывные
    $s = preg_replace('/\x{00A0}/u', ' ', $s);

    // снимаем префикс "Состав:" для работы над телом
    $prefix = '';
    if (preg_match('/^\s*Состав\s*:\s*/ui', $s, $m)) {
        $prefix = $m[0];
        $s = mb_substr($s, mb_strlen($m[0]));
    }

    // режем на токены по запятой
    $tokens = array_map('trim', explode(',', $s));
    $kept = array();

    foreach ($tokens as $t) {
        if ($t === '') continue;

        $low = mb_strtolower($t, 'UTF-8');

        // 1) чистые числа: "52", "120"
        if (preg_match('/^\d+$/u', $low)) continue;

        // 2) короткие буквенно-цифровые коды: "ц1", "р1", "о5", "ц25", "о100"
        if (preg_match('/^[а-яёa-z]{1,3}\d+$/u', $low)) continue;

        // 3) "слово число" где число явно техническое (рис 120, тортилья 1)
        //    Удаляем хвостовое число из такого токена, оставляя само слово.
        $t2 = preg_replace('/\s+\d+\s*$/u', '', $t);
        if ($t2 !== $t) {
            $t = trim($t2);
        }

        if ($t !== '') $kept[] = $t;
    }

    $body = implode(', ', $kept);
    // если после чистки тело не заканчивается точкой — добавим, если исходник заканчивался
    $new = $prefix . $body;
    if ($body !== '' && preg_match('/\.$/u', trim($s)) && !preg_match('/\.$/u', $new)) {
        $new .= '.';
    }
    return $new;
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

$pdo = db();
$rows = $pdo->query('SELECT id, name, description FROM menu_items WHERE description IS NOT NULL AND description <> ""')->fetchAll(PDO::FETCH_ASSOC);

$changed = array();
foreach ($rows as $r) {
    $new = clean_desc($r['description']);
    if ($new !== $r['description']) {
        $changed[] = array(
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'old' => $r['description'],
            'new' => $new,
        );
    }
}

echo '<html><head><meta charset="utf-8"><title>Чистка описаний</title>';
echo '<style>body{font-family:system-ui;max-width:1000px;margin:20px auto;padding:0 20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px;font-size:14px;vertical-align:top}tr.ok{background:#e8f5e9}del{color:#c00;background:#fee}ins{color:#090;background:#efe;text-decoration:none}</style>';
echo '</head><body>';
echo '<h1>Чистка описаний меню</h1>';

if (!$changed) {
    echo '<p>✅ Ничего чистить не надо. Все описания чистые.</p>';
    echo '</body></html>';
    exit;
}

echo '<p>Найдено позиций с мусорными кодами: <b>' . count($changed) . '</b></p>';

if ($apply) {
    $upd = $pdo->prepare('UPDATE menu_items SET description = :d WHERE id = :id');
    foreach ($changed as $c) {
        $upd->execute(array(':d' => $c['new'], ':id' => $c['id']));
    }
    echo '<p style="color:#090;font-weight:700">✅ Применено: обновлено ' . count($changed) . ' позиций.</p>';
    echo '<p><a href="/api/admin/">← Назад в админку</a></p>';
} else {
    echo '<p><a href="?apply=1" style="background:#c00;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Применить чистку</a> &nbsp; <a href="/api/admin/">отменить</a></p>';
}

echo '<table><thead><tr><th>ID</th><th>Позиция</th><th>Было</th><th>Стало</th></tr></thead><tbody>';
foreach ($changed as $c) {
    echo '<tr>';
    echo '<td>' . $c['id'] . '</td>';
    echo '<td>' . htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td><del>' . htmlspecialchars($c['old'], ENT_QUOTES, 'UTF-8') . '</del></td>';
    echo '<td><ins>' . htmlspecialchars($c['new'], ENT_QUOTES, 'UTF-8') . '</ins></td>';
    echo '</tr>';
}
echo '</tbody></table>';

echo '</body></html>';

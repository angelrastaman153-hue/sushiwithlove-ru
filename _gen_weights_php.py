"""
Парсит vk_market_export.csv → извлекает вес/штуки → генерирует api/menu/apply_weights_from_vk.php.
PHP-скрипт потом загружается на сервер и открывается владельцем для preview+apply.
"""
import csv
import re
import sys
import json

sys.stdout.reconfigure(encoding='utf-8')


def parse(name: str, desc: str):
    full = ((name or '') + ' ' + (desc or '')).replace('\xa0', ' ')
    is_set = bool(re.match(r'\s*сет\b', name, re.IGNORECASE)) or 'сет' in name.lower()[:15]

    w = p = None

    # Паттерн "XX шт - YYY г" / "XXшт - YYYг"
    m = re.search(r'(\d{1,3})\s*шт[\.]?\s*[-–—,]\s*(\d{2,4})\s*г', full, re.IGNORECASE)
    if m and not is_set:
        p, w = int(m.group(1)), int(m.group(2))
    elif m and is_set:
        # Для сета не верим "8 шт - 185 г" — это один из роллов. Берём только вес одного — пропустим.
        pass

    # Отдельный вес "вес: XXX г" / "вес XXX г"
    if w is None:
        m = re.search(r'вес[\s:]+(\d{2,4})\s*г', full, re.IGNORECASE)
        if m:
            w = int(m.group(1))

    # Вес в конце имени через запятую: "соус устричный, 30г"
    if w is None:
        m = re.search(r',\s*(\d{2,4})\s*г(?:р|\b)', name, re.IGNORECASE)
        if m:
            w = int(m.group(1))

    # Для сетов: общее количество штук — последнее "XX шт" которое НЕ после дефиса (чтобы не взять "- 8шт" от внутренностей)
    if is_set:
        # Ищем явное "30 шт" / "24шт" не после тире/скобки
        # Суммируем все "(Xшт)" в начале строк состава — это компоненты сета
        parts = re.findall(r'\((\d{1,3})\s*шт\)', full)
        if parts:
            try:
                total = sum(int(x) for x in parts)
                if 4 <= total <= 60:
                    p = total
            except Exception:
                pass
        if p is None:
            # Берём максимальное XX шт (обычно это итог для сета)
            nums = [int(x) for x in re.findall(r'(\d{1,3})\s*шт', full)]
            nums = [n for n in nums if 4 <= n <= 60]
            if nums:
                p = max(nums)
    else:
        # Обычное блюдо: первое "XX шт"
        if p is None:
            m = re.search(r'(\d{1,3})\s*шт', full, re.IGNORECASE)
            if m:
                p = int(m.group(1))

    # Sanity
    if w is not None and not (20 <= w <= 3000):
        w = None
    if p is not None and not (1 <= p <= 60):
        p = None

    return w, p


def main():
    rows = list(csv.DictReader(open('vk_market_export.csv', encoding='utf-8-sig')))
    result = []
    for r in rows:
        name = r['Название'].strip()
        desc = r['Описание / Состав'] or ''
        w, p = parse(name, desc)
        if w or p:
            result.append({'name': name, 'w': w, 'p': p})

    print(f'Total rows: {len(rows)}')
    print(f'С хотя бы одним полем: {len(result)}')
    print(f'  с весом: {sum(1 for r in result if r["w"])}')
    print(f'  со штуками: {sum(1 for r in result if r["p"])}')

    # Генерируем PHP
    php_lines = []
    for r in result:
        name_escaped = r['name'].replace("'", "\\'")
        w = r['w'] if r['w'] else 'null'
        p = r['p'] if r['p'] else 'null'
        php_lines.append(f"    '{name_escaped}' => ['w' => {w}, 'p' => {p}],")

    php = '''<?php
/**
 * Массовое обновление menu_items.weight_grams и pieces_count из данных VK Market Export.
 * Парсер: _gen_weights_php.py (локальный).
 *
 * Использование:
 *   PREVIEW (безопасно): /api/menu/apply_weights_from_vk.php?key=swlTest2026
 *   APPLY:               /api/menu/apply_weights_from_vk.php?key=swlTest2026&apply=1
 *
 * После применения — удали этот файл с сервера.
 */

define('SETUP_KEY', 'swlTest2026');
if ((isset($_GET['key']) ? $_GET['key'] : '') !== SETUP_KEY) {
    http_response_code(403); die('Forbidden');
}

require __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

// name => [w, p]
$data = [
''' + '\n'.join(php_lines) + '''
];

// Нормализатор имени для поиска (lower + схлопывание пробелов + убрать знаки)
function norm($s) {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[«»"\\'\\.,:;!?()\\[\\]{}#№]/u', ' ', $s);
    $s = preg_replace('/\\s+/u', ' ', $s);
    return trim($s);
}

// Загружаем все позиции из БД
$items = $pdo->query("SELECT id, name, weight_grams, pieces_count FROM menu_items")->fetchAll(PDO::FETCH_ASSOC);
$byNorm = [];
foreach ($items as $it) { $byNorm[norm($it['name'])] = $it; }

$stats = ['match'=>0, 'miss'=>0, 'upd_w'=>0, 'upd_p'=>0, 'same'=>0];
$missed = [];
$updates = [];

foreach ($data as $name => $wp) {
    $n = norm($name);
    if (!isset($byNorm[$n])) {
        // fallback: ищем по startswith / substring
        $found = null;
        foreach ($byNorm as $kn => $it) {
            if (strpos($kn, $n) !== false || strpos($n, $kn) !== false) {
                $found = $it; break;
            }
        }
        if (!$found) { $stats['miss']++; $missed[] = $name; continue; }
        $it = $found;
    } else {
        $it = $byNorm[$n];
    }
    $stats['match']++;
    $newW = $wp['w'];
    $newP = $wp['p'];
    $curW = $it['weight_grams'] ? (int)$it['weight_grams'] : null;
    $curP = $it['pieces_count'] ? (int)$it['pieces_count'] : null;

    $setW = ($newW !== null && $newW !== $curW);
    $setP = ($newP !== null && $newP !== $curP);
    if (!$setW && !$setP) { $stats['same']++; continue; }

    $updates[] = [
        'id' => $it['id'],
        'name' => $it['name'],
        'curW' => $curW, 'newW' => $setW ? $newW : null,
        'curP' => $curP, 'newP' => $setP ? $newP : null,
    ];
}

echo "=== СТАТИСТИКА ===\\n";
echo "В справочнике VK: " . count($data) . "\\n";
echo "В БД позиций:     " . count($items) . "\\n";
echo "Сопоставлено:     " . $stats['match'] . "\\n";
echo "Не найдено в БД:  " . $stats['miss'] . "\\n";
echo "Будет изменено:   " . count($updates) . "\\n";
echo "Без изменений:    " . $stats['same'] . "\\n\\n";

if ($stats['miss'] > 0) {
    echo "=== НЕ НАЙДЕНО В БД (пропускаем) ===\\n";
    foreach ($missed as $m) echo "  - $m\\n";
    echo "\\n";
}

echo "=== ИЗМЕНЕНИЯ (" . count($updates) . ") ===\\n";
foreach ($updates as $u) {
    $parts = [];
    if ($u['newW'] !== null) $parts[] = sprintf("вес %s→%d г", $u['curW'] === null ? '—' : $u['curW'], $u['newW']);
    if ($u['newP'] !== null) $parts[] = sprintf("штук %s→%d", $u['curP'] === null ? '—' : $u['curP'], $u['newP']);
    echo sprintf("  [id=%d] %s: %s\\n", $u['id'], $u['name'], implode(', ', $parts));
}

if (!$apply) {
    echo "\\n--- ПРЕВЬЮ. Для применения добавь ?apply=1 ---\\n";
    exit;
}

if (empty($updates)) { echo "\\nНечего обновлять.\\n"; exit; }

$stmtW  = $pdo->prepare("UPDATE menu_items SET weight_grams=? WHERE id=?");
$stmtP  = $pdo->prepare("UPDATE menu_items SET pieces_count=? WHERE id=?");
$stmtWP = $pdo->prepare("UPDATE menu_items SET weight_grams=?, pieces_count=? WHERE id=?");

$pdo->beginTransaction();
try {
    foreach ($updates as $u) {
        if ($u['newW'] !== null && $u['newP'] !== null) {
            $stmtWP->execute([$u['newW'], $u['newP'], $u['id']]);
        } elseif ($u['newW'] !== null) {
            $stmtW->execute([$u['newW'], $u['id']]);
        } elseif ($u['newP'] !== null) {
            $stmtP->execute([$u['newP'], $u['id']]);
        }
    }
    $pdo->commit();
    echo "\\n[OK] Обновлено: " . count($updates) . " позиций.\\n";
    echo "Удали этот файл с сервера.\\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\\n[ERR] " . $e->getMessage() . "\\n";
}
'''

    with open('api/menu/apply_weights_from_vk.php', 'w', encoding='utf-8', newline='\n') as f:
        f.write(php)
    print('Written: api/menu/apply_weights_from_vk.php')


if __name__ == '__main__':
    main()

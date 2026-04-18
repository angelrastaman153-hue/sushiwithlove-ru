<?php
// Одноразовый скрипт — назначить владельцам все 4 роли
// Зайти в браузере: /api/staff/set_owner_roles.php
// После выполнения — удалить файл с сервера

require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$all_roles = 'owner,admin,operator,courier';

// Находим всех владельцев (в их role есть 'owner')
$stmt = $pdo->query("SELECT id, login, name, role FROM staff WHERE role LIKE '%owner%'");
$owners = $stmt->fetchAll();

if (!$owners) {
    echo "Не найдено ни одного владельца (role содержит 'owner').\n";
    exit;
}

echo "Найдено владельцев: " . count($owners) . "\n\n";

foreach ($owners as $o) {
    $was = $o['role'];
    if ($was === $all_roles) {
        echo "✓ {$o['login']} ({$o['name']}) — уже все роли назначены\n";
        continue;
    }
    $pdo->prepare('UPDATE staff SET role = ? WHERE id = ?')
        ->execute(array($all_roles, $o['id']));
    echo "→ {$o['login']} ({$o['name']}): [{$was}] → [{$all_roles}]\n";
}

echo "\nГотово. Теперь удали файл api/staff/set_owner_roles.php с сервера.\n";

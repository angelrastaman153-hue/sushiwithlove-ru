<?php
// GET /api/me.php — профиль текущего пользователя
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = auth_required();

// Последние 10 заказов
$stmt = db()->prepare('
    SELECT id, fp_order_id, items_total, delivery_cost, promo_discount,
           points_spent, points_earned, total_paid, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
');
$stmt->execute(array($user['id']));
$orders = $stmt->fetchAll();

json_out(array(
    'ok'   => true,
    'user' => array(
        'id'         => $user['id'],
        'name'       => $user['name'],
        'phone'      => $user['phone'],
        'birth_date' => $user['birth_date'],
        'points'     => intval($user['points']),
        'created_at' => $user['created_at'],
    ),
    'orders' => $orders
));

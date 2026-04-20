<?php
// POST /api/orders/save.php
// Вызывается из JS СРАЗУ при оформлении заказа (fp_order_id = null → статус pending)
// После успешной отправки в FrontPad — link_fp.php привязывает fp_order_id и меняет статус на new

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vk_notify.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data  = json_decode(file_get_contents('php://input'), true);
$token = isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : '';

// user_id — только если есть токен и он валиден
$user_id = null;
if ($token) {
    $stmt = db()->prepare('
        SELECT u.id FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ? AND s.expires_at > NOW()
    ');
    $stmt->execute(array($token));
    $row = $stmt->fetch();
    if ($row) $user_id = $row['id'];
}

$client_phone  = isset($data['phone'])         ? preg_replace('/\D/', '', $data['phone']) : null;
$client_name   = isset($data['name'])          ? trim($data['name'])           : null;
$fp_order_id   = isset($data['fp_order_id'])   ? $data['fp_order_id']   : null;
$items_total   = isset($data['items_total'])   ? intval($data['items_total'])   : 0;
$delivery_cost = isset($data['delivery_cost']) ? intval($data['delivery_cost']) : 0;
$promo_code    = isset($data['promo_code'])    ? $data['promo_code']    : null;
$promo_discount= isset($data['promo_discount'])? intval($data['promo_discount']): 0;
$points_spent  = isset($data['points_spent'])  ? intval($data['points_spent'])  : 0;
$total_paid    = isset($data['total_paid'])    ? intval($data['total_paid'])    : 0;
$is_test       = isset($data['is_test'])       ? (int)$data['is_test']          : 0;

// Дополнительные поля для отображения в админке (FP-стиль)
$delivery_type = isset($data['delivery_type']) ? (string)$data['delivery_type'] : null; // 'delivery' / 'self'
$address = null;
if ($delivery_type === 'self') {
    $address = 'Самовывоз';
} else {
    $street = isset($data['street']) ? trim($data['street']) : '';
    $home   = isset($data['home'])   ? trim($data['home'])   : '';
    if ($street || $home) $address = trim($street . ' ' . $home);
}
$pay_type = null;
if (isset($data['pay'])) {
    $pay_type = ((int)$data['pay'] === 2) ? 'cash' : 'qr';
}
$comment_txt = isset($data['comment']) ? trim((string)$data['comment']) : null;
if ($comment_txt === '') $comment_txt = null;

// Позиции заказа (минимальный набор полей — что нужно для просмотра в админке)
$items_json = null;
if (isset($data['items']) && is_array($data['items'])) {
    $slim = array();
    foreach ($data['items'] as $it) {
        $slim[] = array(
            'id'     => isset($it['id'])     ? (int)$it['id']     : 0,
            'name'   => isset($it['name'])   ? (string)$it['name']: '',
            'qty'    => isset($it['qty'])    ? (int)$it['qty']    : 1,
            'price'  => isset($it['price'])  ? (float)$it['price']: 0,
            'isGift' => !empty($it['isGift']) ? 1 : 0
        );
    }
    $items_json = json_encode($slim, JSON_UNESCAPED_UNICODE);
}

$pdo = db();

// Списываем баллы сразу (если были использованы)
if ($user_id && $points_spent > 0) {
    $pdo->prepare('UPDATE users SET points = points - ? WHERE id = ? AND points >= ?')
        ->execute(array($points_spent, $user_id, $points_spent));
    $pdo->prepare('INSERT INTO points_log (user_id, delta, reason, created_at) VALUES (?,?,?,NOW())')
        ->execute(array($user_id, -$points_spent, 'spend'));
}

// Сохраняем заказ: pending если нет fp_order_id (FrontPad ещё не ответил), new если уже есть
$status = $fp_order_id ? 'new' : 'pending';

// Для боевых заказов — считаем display_number (последовательная нумерация, тесты пропускаются)
$display_number = null;
if (!$is_test) {
    try {
        $display_number = 1 + (int)$pdo->query("SELECT COALESCE(MAX(display_number),0) FROM orders WHERE (is_test IS NULL OR is_test=0)")->fetchColumn();
    } catch (Exception $e) {
        $display_number = null; // колонки может ещё не быть до миграции
    }
}

try {
    $pdo->prepare('
        INSERT INTO orders
          (user_id, fp_order_id, items_total, delivery_cost, promo_code, promo_discount,
           points_spent, total_paid, items_json, delivery_type, address, pay_type, comment,
           status, is_test, display_number, client_phone, client_name, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ')->execute(array($user_id, $fp_order_id, $items_total, $delivery_cost,
                      $promo_code, $promo_discount, $points_spent, $total_paid, $items_json,
                      $delivery_type, $address, $pay_type, $comment_txt,
                      $status, $is_test, $display_number, $client_phone, $client_name));
} catch (Exception $e) {
    // Fallback если миграция display_number ещё не применена
    $pdo->prepare('
        INSERT INTO orders
          (user_id, fp_order_id, items_total, delivery_cost, promo_code, promo_discount,
           points_spent, total_paid, items_json, delivery_type, address, pay_type, comment,
           status, is_test, client_phone, client_name, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ')->execute(array($user_id, $fp_order_id, $items_total, $delivery_cost,
                      $promo_code, $promo_discount, $points_spent, $total_paid, $items_json,
                      $delivery_type, $address, $pay_type, $comment_txt,
                      $status, $is_test, $client_phone, $client_name));
}

$order_id = $pdo->lastInsertId();

// Обновляем last_order_at + сохраняем телефон/имя в профиль (если раньше не было)
if ($user_id) {
    $pdo->prepare('UPDATE users SET last_order_at = NOW() WHERE id = ?')
        ->execute(array($user_id));
    if ($client_phone && strlen($client_phone) >= 10) {
        $pdo->prepare('UPDATE users SET phone = ? WHERE id = ? AND (phone IS NULL OR phone = "")')
            ->execute(array($client_phone, $user_id));
    }
    if ($client_name) {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ? AND (name IS NULL OR name = "")')
            ->execute(array($client_name, $user_id));
    }
}

// Уведомление в ВК
$vk_msg = vk_format_order($order_id, $data);
vk_send($vk_msg);

json_out(array('ok' => true, 'order_id' => $order_id));

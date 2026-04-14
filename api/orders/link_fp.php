<?php
// POST /api/orders/link_fp.php
// Привязывает fp_order_id к заказу и меняет статус pending → new
// Вызывается из JS после успешного ответа от FrontPad

require_once __DIR__ . '/../db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['order_id']) || !isset($data['fp_order_id'])) {
    echo json_encode(array('ok' => false, 'error' => 'missing params'));
    exit;
}

$order_id    = intval($data['order_id']);
$fp_order_id = $data['fp_order_id'];

db()->prepare('UPDATE orders SET fp_order_id = ?, status = "new" WHERE id = ? AND status = "pending"')
   ->execute(array($fp_order_id, $order_id));

echo json_encode(array('ok' => true));

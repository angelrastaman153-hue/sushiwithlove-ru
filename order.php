<?php
/**
 * Прокси-скрипт для приёма заказов с лендинга и отправки во Frontpad.
 * Загрузить на Beget в ту же папку, что и index.html (или в корень домена).
 *
 * Секретный ключ FP взять из integracia-vk-fp/callback.php → константа FP_SECRET.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ======================================================
// ВСТАВЬТЕ СЮДА ВАШ СЕКРЕТНЫЙ КЛЮЧ FRONTPAD
// (скопировать из integracia-vk-fp/callback.php → FP_SECRET)
// ======================================================
define('FP_SECRET', 'ВСТАВЬТЕ_СЕКРЕТНЫЙ_КЛЮЧ_FRONTPAD');
define('FP_API',    'https://app.frontpad.ru/api/index.php?new_order');

// ======================================================
// Читаем JSON из тела запроса
// ======================================================
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request', 'msg' => 'Пустой или невалидный JSON']);
    exit;
}

// ======================================================
// Валидация обязательных полей
// ======================================================
$phone = preg_replace('/\D/', '', $data['phone'] ?? '');
$name  = trim($data['name'] ?? '');
$items = $data['items'] ?? [];

if (strlen($phone) < 10) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_phone']);
    exit;
}
if (!$name) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_name']);
    exit;
}
if (empty($items)) {
    http_response_code(422);
    echo json_encode(['error' => 'empty_cart']);
    exit;
}

// ======================================================
// Формируем запрос к Frontpad
// ======================================================

// Способ оплаты: 1=онлайн, 2=наличные, 3=карта курьеру, 4=перевод/QR
$payMap = ['1' => '1', '2' => '2', '3' => '3', '4' => '4'];
$pay    = $payMap[$data['pay'] ?? '2'] ?? '2';

// Адрес: если самовывоз — ставим специальный маркер
$deliveryType = $data['delivery_type'] ?? 'delivery';
$street       = $deliveryType === 'self' ? 'Самовывоз' : trim($data['street'] ?? '');
$home         = $deliveryType === 'self' ? '' : trim($data['home'] ?? '');

$post = [
    'secret' => FP_SECRET,
    'phone'  => $phone,
    'name'   => $name,
    'street' => $street,
    'home'   => $home,
    'pod'    => trim($data['entrance'] ?? ''),
    'et'     => trim($data['floor'] ?? ''),
    'apart'  => trim($data['flat'] ?? ''),
    'descr'  => trim($data['comment'] ?? ''),
    'pay'    => $pay,
];

// Товары: product[0]=арт, product_kol[0]=кол-во
foreach (array_values($items) as $i => $item) {
    $art = (int)($item['id'] ?? 0);
    $qty = max(1, (int)($item['qty'] ?? 1));
    if ($art <= 0) continue; // пропускаем позиции без артикула
    $post["product[$i]"]     = $art;
    $post["product_kol[$i]"] = $qty;
}

// ======================================================
// Отправляем в Frontpad
// ======================================================
$ch = curl_init(FP_API);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'curl_error', 'msg' => $curlErr]);
    exit;
}

// Frontpad возвращает JSON: {"result":"success","id":12345} или {"result":"error",...}
$fp = json_decode($response, true);

if (isset($fp['result']) && $fp['result'] === 'success') {
    echo json_encode(['ok' => true, 'order_id' => $fp['id'] ?? null]);
} else {
    http_response_code(200); // не 500 — клиент должен увидеть сообщение
    echo json_encode(['ok' => false, 'fp_response' => $fp, 'raw' => $response]);
}

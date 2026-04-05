<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

define('FP_SECRET', '2kBSSKdHKKff5fEE743RS2dTtfdD5KfS2GAEeDbSah25srzkfi5298GseNY8i5R8BbDFEFkFRaz7sThA6bk5aYiey7dG2TEkZ7TnB7Td7ean3iNTk3NnH2t6TABGf6T53Dfrz4yt7zGZGhFY9e8H5sDNS4f2Y4RKt24RQi9KHGydS32R7Zk3dQBYFGGrAk3S9SzQ5ynB5HEHAsktdBffbH4HdA2ENQhNtFni4DKYadsBzFTD64H7ABFE3s');
define('FP_API',    'https://app.frontpad.ru/api/index.php?new_order');

function send($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    send(['ok' => false, 'error' => 'bad_request']);
}

$phone = preg_replace('/\D/', '', $data['phone'] ?? '');
$name  = trim($data['name'] ?? '');
$items = $data['items'] ?? [];

if (strlen($phone) < 10) send(['ok' => false, 'error' => 'invalid_phone']);
if (!$name)              send(['ok' => false, 'error' => 'invalid_name']);
if (empty($items))       send(['ok' => false, 'error' => 'empty_cart']);

$deliveryType = $data['delivery_type'] ?? 'delivery';
$street = $deliveryType === 'self' ? 'Самовывоз' : trim($data['street'] ?? '');
$home   = $deliveryType === 'self' ? '' : trim($data['home'] ?? '');

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
    'pay'    => $data['pay'] ?? '2',
];

foreach (array_values($items) as $i => $item) {
    $art = (int)($item['id'] ?? 0);
    $qty = max(1, (int)($item['qty'] ?? 1));
    if ($art <= 0) continue;
    $post["product[$i]"]     = $art;
    $post["product_kol[$i]"] = $qty;
}

$postStr = http_build_query($post);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                     "Content-Length: " . strlen($postStr) . "\r\n",
        'content' => $postStr,
        'timeout' => 15,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$response = @file_get_contents(FP_API, false, $ctx);

if ($response === false) {
    send(['ok' => false, 'error' => 'network_error']);
}

$fp = json_decode($response, true);

if (isset($fp['result']) && $fp['result'] === 'success') {
    send(['ok' => true, 'order_id' => $fp['id'] ?? null]);
} else {
    send(['ok' => false, 'fp_response' => $fp, 'raw' => $response]);
}

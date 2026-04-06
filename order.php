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

function fp_get($arr, $key, $default) {
    return isset($arr[$key]) ? $arr[$key] : $default;
}

function send_json($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    send_json(array('ok' => false, 'error' => 'bad_request'));
}

$phone = preg_replace('/\D/', '', fp_get($data, 'phone', ''));
$name  = trim(fp_get($data, 'name', ''));
$items = fp_get($data, 'items', array());

if (strlen($phone) < 10) send_json(array('ok' => false, 'error' => 'invalid_phone'));
if (!$name)              send_json(array('ok' => false, 'error' => 'invalid_name'));
if (empty($items))       send_json(array('ok' => false, 'error' => 'empty_cart'));

$deliveryType = fp_get($data, 'delivery_type', 'delivery');
$street = $deliveryType === 'self' ? 'Самовывоз' : trim(fp_get($data, 'street', ''));
$home   = $deliveryType === 'self' ? '' : trim(fp_get($data, 'home', ''));

// Формируем комментарий с доп. данными
$commentParts = array();
$userComment = trim(fp_get($data, 'comment', ''));
if ($userComment) $commentParts[] = $userComment;

$pay = (int) fp_get($data, 'pay', 1);
$cashFrom = trim(fp_get($data, 'cash_from', ''));
if ($pay === 2) {
    if ($cashFrom) {
        $cashFromNum = (int) preg_replace('/\D/', '', $cashFrom);
        $orderTotal  = (int) fp_get($data, 'order_total', 0);
        $changeNote  = 'Сдача с: ' . $cashFrom;
        if ($cashFromNum > 0 && $orderTotal > 0) {
            $change = $cashFromNum - $orderTotal;
            $changeNote .= ' (сдача: ' . $change . ' ₽)';
        }
        $commentParts[] = $changeNote;
    } else {
        $commentParts[] = 'Без сдачи';
    }
}

$preorderDate = trim(fp_get($data, 'preorder_date', ''));
$preorderTime = trim(fp_get($data, 'preorder_time', ''));
if ($preorderDate || $preorderTime) {
    $months = array(
        1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
        7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'
    );
    $dateStr = $preorderDate;
    if ($preorderDate && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $preorderDate, $m)) {
        $dateStr = (int)$m[3] . ' ' . $months[(int)$m[2]] . ' ' . $m[1];
    }
    $parts = array_filter(array($dateStr, $preorderTime));
    $commentParts[] = 'Предзаказ на: ' . implode(', ', $parts);
}

$descr = implode(' | ', array_filter($commentParts));

$post = array(
    'secret' => FP_SECRET,
    'phone'  => $phone,
    'name'   => $name,
    'street' => $street,
    'home'   => $home,
    'pod'    => trim(fp_get($data, 'entrance', '')),
    'et'     => trim(fp_get($data, 'floor', '')),
    'apart'  => trim(fp_get($data, 'flat', '')),
    'descr'  => $descr,
    'pay'    => fp_get($data, 'pay', '2'),
    'person' => max(0, (int) fp_get($data, 'chopsticks', 1)),
    'point'  => 746,
);

foreach (array_values($items) as $i => $item) {
    $art = (int) fp_get($item, 'id', 0);
    $qty = max(1, (int) fp_get($item, 'qty', 1));
    if ($art <= 0) continue;
    $post['product[' . $i . ']']     = $art;
    $post['product_kol[' . $i . ']'] = $qty;
}

$postStr = http_build_query($post);

$ctx = stream_context_create(array(
    'http' => array(
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postStr) . "\r\n",
        'content'       => $postStr,
        'timeout'       => 15,
        'ignore_errors' => true,
    ),
    'ssl' => array(
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ),
));

$response = @file_get_contents(FP_API, false, $ctx);

if ($response === false) {
    send_json(array('ok' => false, 'error' => 'network_error'));
}

$fp = json_decode($response, true);

if (isset($fp['result']) && $fp['result'] === 'success') {
    send_json(array('ok' => true, 'order_id' => isset($fp['id']) ? $fp['id'] : null));
} else {
    send_json(array('ok' => false, 'fp_response' => $fp, 'raw' => $response));
}

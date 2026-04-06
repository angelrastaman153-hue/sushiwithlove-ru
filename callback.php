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
define('LOG_FILE',  __DIR__ . '/callbacks.log');

function send_json($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) send_json(['ok' => false, 'error' => 'bad_request']);

$phone = preg_replace('/\D/', '', isset($data['phone']) ? $data['phone'] : '');
$name  = trim(isset($data['name']) ? $data['name'] : '');

if (strlen($phone) < 10) send_json(['ok' => false, 'error' => 'invalid_phone']);
if (!$name)              send_json(['ok' => false, 'error' => 'invalid_name']);

// Запись в лог-файл (всегда, как страховка)
$logLine = date('Y-m-d H:i:s') . ' | ' . $name . ' | ' . $phone . PHP_EOL;
@file_put_contents(LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);

// Попытка отправить в Frontpad
$descr = '⚡ ПЕРЕЗВОНИТЬ: ' . $name;

$post = [
    'secret'        => FP_SECRET,
    'phone'         => $phone,
    'name'          => $name,
    'street'        => 'Заявка с сайта — перезвонить',
    'home'          => '',
    'descr'         => $descr,
    'pay'           => '2',
    'person'        => 0,
    'point'         => 746,
    'product[0]'    => 602,
    'product_kol[0]'=> 1,
];

$postStr = http_build_query($post);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postStr) . "\r\n",
        'content'       => $postStr,
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$response = @file_get_contents(FP_API, false, $ctx);
$fp = $response ? json_decode($response, true) : null;

// Даже если Frontpad не принял — лог уже есть, говорим клиенту "ок"
send_json(['ok' => true, 'fp' => $fp]);

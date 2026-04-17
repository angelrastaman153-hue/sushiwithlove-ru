<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['session_id'])) { echo json_encode(['ok'=>false]); exit; }

$sid      = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $data['session_id']), 0, 64);
$source   = isset($data['utm_source'])   ? substr($data['utm_source'],   0, 100) : null;
$medium   = isset($data['utm_medium'])   ? substr($data['utm_medium'],   0, 100) : null;
$campaign = isset($data['utm_campaign']) ? substr($data['utm_campaign'], 0, 200) : null;
$referrer = isset($data['referrer'])     ? substr($data['referrer'],     0, 500) : null;
$device   = isset($data['device'])       ? substr($data['device'],       0, 20)  : null;
$stage    = isset($data['stage'])        ? $data['stage']                        : null;
$order_id = isset($data['order_id'])     ? intval($data['order_id'])             : null;

$pdo = db();

// Проверяем существует ли сессия
$exists = $pdo->prepare('SELECT id, funnel_stage FROM analytics_sessions WHERE session_id=?');
$exists->execute([$sid]);
$row = $exists->fetch(PDO::FETCH_ASSOC);

$stageOrder = ['landed'=>0,'browsed'=>1,'cart'=>2,'checkout'=>3,'ordered'=>4];

if (!$row) {
    // Новая сессия
    $pdo->prepare('INSERT INTO analytics_sessions (session_id,utm_source,utm_medium,utm_campaign,referrer,device,funnel_stage,order_id) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$sid, $source, $medium, $campaign, $referrer, $device, $stage ?: 'landed', $order_id]);
} else {
    // Обновляем только если этап продвинулся вперёд (не откатываем)
    $updates = [];
    $params  = [];
    if ($stage && isset($stageOrder[$stage]) && $stageOrder[$stage] > ($stageOrder[$row['funnel_stage']] ?? 0)) {
        $updates[] = 'funnel_stage=?'; $params[] = $stage;
    }
    if ($order_id) { $updates[] = 'order_id=?'; $params[] = $order_id; }
    if ($updates) {
        $params[] = $sid;
        $pdo->prepare('UPDATE analytics_sessions SET '.implode(',', $updates).' WHERE session_id=?')->execute($params);
    }
}

echo json_encode(['ok'=>true]);

<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 минут

$pdo = db();
$cats = $pdo->query(
    'SELECT id, crm_id, name, slug, sort_order FROM menu_categories WHERE is_active=1 ORDER BY sort_order, name'
)->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query(
    'SELECT id, category_id, name, price, weight_grams, image_url, description, flavor_description, fp_article_id, is_stop, sort_order, group_key, variant_label
     FROM menu_items WHERE is_active=1 ORDER BY sort_order, name'
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'categories'=>$cats,'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

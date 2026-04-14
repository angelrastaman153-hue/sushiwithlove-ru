<?php
// Отправка уведомлений в ВК-беседу кухни
// Требует: VK_TOKEN, VK_CHAT_ID в config.php

function vk_send($message) {
    if (!defined('VK_TOKEN') || !defined('VK_PEER_IDS') || !VK_PEER_IDS) return false;

    $peers = array_filter(array_map('trim', explode(',', VK_PEER_IDS)));
    foreach ($peers as $peer_id) {
        $params = array(
            'peer_id'      => intval($peer_id),
            'message'      => $message,
            'random_id'    => mt_rand(1, 9999999),
            'access_token' => VK_TOKEN,
            'v'            => '5.131'
        );
        $url = 'https://api.vk.com/method/messages.send?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
    return true;
}

// Форматирует сообщение о новом заказе
function vk_format_order($order_id, $data) {
    $type = ($data['delivery_type'] === 'self') ? '🏠 Самовывоз' : '🚗 Доставка';
    $pay  = ($data['pay'] == '2') ? '💳 Безналичная' : '💵 Наличные' . ($data['cash_from'] ? ' (сдача с ' . $data['cash_from'] . ' ₽)' : '');

    $items_text = '';
    if (!empty($data['items'])) {
        foreach ($data['items'] as $item) {
            $items_text .= "\n  • " . $item['name'] . ' × ' . $item['qty'];
            if (!empty($item['isGift'])) $items_text .= ' 🎁';
            else $items_text .= ' — ' . ($item['price'] * $item['qty']) . ' ₽';
        }
    }

    $address = '';
    if ($data['delivery_type'] !== 'self') {
        $address = "\n📍 " . $data['street'] . ', ' . $data['home'];
        if (!empty($data['entrance'])) $address .= ', подъезд ' . $data['entrance'];
        if (!empty($data['floor']))    $address .= ', эт. ' . $data['floor'];
        if (!empty($data['flat']))     $address .= ', кв. ' . $data['flat'];
    }

    $promo = !empty($data['promo_code']) ? "\n🏷 Промокод: " . $data['promo_code'] : '';
    $comment = !empty($data['comment']) ? "\n💬 " . $data['comment'] : '';
    $time = !empty($data['preorder_time']) ? "\n⏰ К " . $data['preorder_time'] : '';

    return "🆕 НОВЫЙ ЗАКАЗ #" . $order_id . "\n"
         . "━━━━━━━━━━━━━━━━━\n"
         . "👤 " . $data['name'] . " · " . $data['phone'] . "\n"
         . $type . $address . "\n"
         . $pay . $promo . $time
         . "\n\n🛒 Состав:" . $items_text . "\n"
         . "━━━━━━━━━━━━━━━━━\n"
         . "💰 Итого: " . $data['order_total'] . " ₽";
}

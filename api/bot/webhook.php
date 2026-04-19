<?php
// Telegram Bot Webhook — Sushi with Love
// Обрабатывает /start, /menu, /help и callback_query от inline-кнопок

require_once __DIR__ . '/../config.php';

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

$message  = isset($update['message'])        ? $update['message']        : null;
$callback = isset($update['callback_query']) ? $update['callback_query'] : null;

$MINI_APP_URL = SITE_URL . '/tg-app/';

// Inline-кнопки в теле приветственного сообщения
$inline_kb = array(
    'inline_keyboard' => array(
        array(
            array('text' => '🍣 Открыть меню', 'web_app' => array('url' => $MINI_APP_URL)),
        ),
        array(
            array('text' => '📞 Контакты',    'callback_data' => 'contacts'),
            array('text' => '🎁 Акции',       'callback_data' => 'promo'),
        ),
        array(
            array('text' => '🌐 Сайт',        'url' => SITE_URL),
            array('text' => 'ℹ️ О нас',       'callback_data' => 'about'),
        ),
    ),
);

function tg_api($method, $params) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

function tg_send($chat_id, $text, $keyboard = null) {
    $params = array('chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML');
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    tg_api('sendMessage', $params);
}

function tg_answer_callback($cb_id, $text = '') {
    tg_api('answerCallbackQuery', array('callback_query_id' => $cb_id, 'text' => $text));
}

// ── Callback от inline-кнопок ────────────────────────────────────────────────
if ($callback) {
    $chat_id = $callback['message']['chat']['id'];
    $cb_id   = $callback['id'];
    $data    = isset($callback['data']) ? $callback['data'] : '';

    tg_answer_callback($cb_id);

    if ($data === 'contacts') {
        tg_send($chat_id,
            "📞 <b>Телефон:</b> +7 (352) 266-20-70\n" .
            "📞 <b>Телефон:</b> +7 (922) 578-20-70\n\n" .
            "⏰ <b>Режим работы:</b> 10:00–22:00\n" .
            "📍 <b>Самовывоз:</b> г. Курган, ул. Гоголя, 7"
        );
    } elseif ($data === 'promo') {
        tg_send($chat_id,
            "🎁 <b>Текущие акции:</b>\n\n" .
            "• <b>СВ10</b> — скидка 10% при самовывозе\n" .
            "• <b>1LOVE</b> — скидка 10% на первый заказ\n" .
            "• <b>BDAY</b> — скидка в день рождения\n\n" .
            "Применяй промокод в корзине при оформлении 🍣"
        );
    } elseif ($data === 'about') {
        tg_send($chat_id,
            "🍣 <b>Суши с Любовью</b>\n\n" .
            "Готовим с любовью и доставляем по Кургану и пригороду.\n\n" .
            "• Доставка по центру — 30–40 мин\n" .
            "• Бесплатная доставка от 900 ₽\n" .
            "• Самовывоз с ул. Гоголя, 7"
        );
    }
    exit;
}

// ── Обычное сообщение ────────────────────────────────────────────────────────
if (!$message) exit;
$chat_id = $message['chat']['id'];
$text    = isset($message['text']) ? trim($message['text']) : '';

$cmd = strtolower(explode('@', explode(' ', $text)[0])[0]);

$is_start = ($cmd === '/start' || $cmd === '/menu');
$is_help  = ($cmd === '/help');

if ($is_start) {
    $first    = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    $greeting = $first ? "Привет, {$first}! 👋\n\n" : "Привет! 👋\n\n";
    tg_send($chat_id,
        $greeting .
        "Я бот <b>Суши с Любовью</b> — доставка по Кургану 🍣\n\n" .
        "✅ Без минимальной суммы на самовывоз\n" .
        "✅ Бесплатная доставка от 900 ₽\n" .
        "✅ Работаем 10:00–22:00",
        $inline_kb
    );
} elseif ($is_help) {
    tg_send($chat_id,
        "📞 <b>Телефон:</b> +7 (352) 266-20-70\n" .
        "📞 <b>Телефон:</b> +7 (922) 578-20-70\n\n" .
        "⏰ <b>Режим работы:</b> 10:00–22:00\n" .
        "📍 <b>Самовывоз:</b> г. Курган, ул. Гоголя, 7",
        $inline_kb
    );
} else {
    tg_send($chat_id,
        "Нажми <b>🍣 Открыть меню</b> — там всё меню и заказ 🍣",
        $inline_kb
    );
}

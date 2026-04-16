<?php
// Telegram Bot Webhook — Sushi with Love
// Отвечает на /start, /menu и любые сообщения кнопкой открытия Mini App

require_once __DIR__ . '/../config.php';

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

$message  = isset($update['message'])            ? $update['message']            : null;
$callback = isset($update['callback_query'])     ? $update['callback_query']     : null;

$chat_id = null;
$text    = '';

if ($message) {
    $chat_id = $message['chat']['id'];
    $text    = isset($message['text']) ? trim($message['text']) : '';
}

if (!$chat_id) exit;

$MINI_APP_URL = SITE_URL . '/tg-app/';

// Клавиатура с кнопкой открытия Mini App
$keyboard = array(
    'inline_keyboard' => array(
        array(
            array(
                'text'    => '🍣 Открыть меню',
                'web_app' => array('url' => $MINI_APP_URL),
            ),
        ),
    ),
);

function tg_send($chat_id, $text, $keyboard = null) {
    $params = array(
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
    );
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

$cmd = strtolower(explode(' ', $text)[0]);
$cmd = explode('@', $cmd)[0]; // убираем @botname

if ($cmd === '/start' || $cmd === '/menu') {
    $first = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    $greeting = $first ? "Привет, {$first}! 👋\n\n" : "Привет! 👋\n\n";
    tg_send($chat_id,
        $greeting .
        "Я бот <b>Sushi with Love</b> — доставка суши по Кургану 🍣\n\n" .
        "Нажми кнопку ниже, чтобы открыть меню и оформить заказ:",
        $keyboard
    );
} elseif ($cmd === '/help') {
    tg_send($chat_id,
        "📞 <b>Телефон:</b> +7 (352) 266-20-70\n" .
        "📞 <b>Телефон:</b> +7 (922) 578-20-70\n\n" .
        "⏰ <b>Режим работы:</b> 10:00–22:00\n" .
        "📍 <b>Самовывоз:</b> г. Курган, ул. Гоголя, 7\n\n" .
        "Для заказа нажми кнопку <b>«Заказать суши»</b> внизу экрана.",
        null
    );
} else {
    // Любое другое сообщение — показываем кнопку
    tg_send($chat_id,
        "Нажми кнопку <b>«Заказать суши»</b> внизу экрана — там всё меню 🍣",
        $keyboard
    );
}

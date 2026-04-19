<?php
// Telegram Bot Polling — запускается через cron каждую минуту
// Cron на Beget: * * * * * php /home/angelros/sushislyubovjyu.rf/public_html/api/bot/poll.php

require_once __DIR__ . '/../config.php';

define('OFFSET_FILE', __DIR__ . '/poll_offset.txt');

$MINI_APP_URL = SITE_URL . '/tg-app/';

// Читаем offset (чтобы не обрабатывать старые обновления)
$offset = file_exists(OFFSET_FILE) ? (int) trim(file_get_contents(OFFSET_FILE)) : 0;

// Получаем обновления
$url     = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getUpdates?timeout=0&limit=50&offset=' . $offset;
$updates = @json_decode(file_get_contents($url), true);

if (empty($updates['ok']) || empty($updates['result'])) exit;

// Постоянная reply-клавиатура внизу чата
function make_keyboard($mini_app_url) {
    return json_encode(array(
        'keyboard' => array(
            array(array(
                'text'    => '🍣 Открыть меню',
                'web_app' => array('url' => $mini_app_url),
            )),
            array(array('text' => '📞 Контакты')),
        ),
        'resize_keyboard' => true,
        'is_persistent'   => true,
    ));
}

// Отправка сообщения
function tg_send($chat_id, $text, $keyboard = null) {
    $params = http_build_query(array(
        'chat_id'      => $chat_id,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => $keyboard,
    ));
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage?' . $params;
    @file_get_contents($url);
}

foreach ($updates['result'] as $update) {
    $offset = $update['update_id'] + 1;

    $message = isset($update['message']) ? $update['message'] : null;
    if (!$message) continue;

    $chat_id = $message['chat']['id'];
    $text    = isset($message['text']) ? trim($message['text']) : '';
    $cmd     = strtolower(explode('@', explode(' ', $text)[0])[0]);

    $is_start = ($cmd === '/start' || $cmd === '/menu');
    $is_help  = ($cmd === '/help'  || $text === '📞 Контакты');

    if ($is_start) {
        $first = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
        $hello = $first ? "Привет, {$first}! 👋\n\n" : "Привет! 👋\n\n";
        tg_send($chat_id,
            $hello .
            "Я бот <b>сушислюбовью.рф</b> — доставка суши по Кургану 🍣\n\n" .
            "Нажми кнопку ниже чтобы открыть меню и оформить заказ:",
            make_keyboard($MINI_APP_URL)
        );
    } elseif ($is_help) {
        tg_send($chat_id,
            "📞 <b>Телефон:</b> +7 (352) 266-20-70\n" .
            "📞 <b>Телефон:</b> +7 (922) 578-20-70\n\n" .
            "⏰ <b>Режим работы:</b> 10:00–22:00\n" .
            "📍 <b>Самовывоз:</b> г. Курган, ул. Гоголя, 7",
            make_keyboard($MINI_APP_URL)
        );
    } else {
        tg_send($chat_id,
            "Нажми <b>🍣 Открыть меню</b> внизу экрана — там всё меню и заказ 🍣",
            make_keyboard($MINI_APP_URL)
        );
    }
}

// Сохраняем новый offset
file_put_contents(OFFSET_FILE, $offset);

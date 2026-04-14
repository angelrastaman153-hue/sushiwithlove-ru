<?php
// VK Callback API — обработчик событий группы ССЛ-КУХНЯ
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vk_notify.php';

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) { echo 'ok'; exit; }

// Проверка секретного ключа (только если задан)
if (VK_SECRET !== '' && isset($data['secret']) && $data['secret'] !== VK_SECRET) { echo 'ok'; exit; }
if (VK_SECRET !== '' && !isset($data['secret'])) { echo 'ok'; exit; }

// Подтверждение адреса сервера
if ($data['type'] === 'confirmation' && $data['group_id'] == VK_GROUP_ID) {
    echo VK_CONFIRMATION; exit;
}

// Новое сообщение
if ($data['type'] === 'message_new') {
    $msg = $data['object']['message'] ?? $data['object'] ?? array();
    $peer_id = $msg['peer_id'] ?? null;
    $text    = trim($msg['text'] ?? '');
    $from_id = $msg['from_id'] ?? null;

    // Игнорируем сообщения от самого бота
    if ($from_id && $from_id < 0) { echo 'ok'; exit; }

    if ($text && $peer_id) {
        // Убираем лишнее, ищем артикул
        $query = preg_replace('/[^a-zA-Z0-9а-яА-Я\-]/u', '', $text);

        $reply = ttk_lookup($query);
        if ($reply) {
            vk_send_to($peer_id, $reply['text'], $reply['photo'] ?? null);
        }
        // Если не нашли — молчим (не засоряем чат)
    }
}

echo 'ok';

// ============================================================
// Поиск ТТК в Google Sheets
// ============================================================
function ttk_lookup($query) {
    if (!$query) return null;

    $url = 'https://docs.google.com/spreadsheets/d/' . GS_SHEET_ID . '/export?format=csv';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $csv = curl_exec($ch);
    curl_close($ch);

    if (!$csv) return null;

    $rows = array_map('str_getcsv', explode("\n", $csv));
    if (empty($rows)) return null;

    $query_lower = mb_strtolower(trim($query), 'UTF-8');

    foreach ($rows as $i => $row) {
        if ($i === 0) continue; // пропускаем заголовок
        if (count($row) < 2) continue;

        $code  = trim($row[0] ?? '');
        $name  = trim($row[1] ?? '');
        $photo = trim($row[2] ?? '');
        $weight= trim($row[3] ?? '');
        $recipe= trim($row[4] ?? '');
        $active= trim($row[5] ?? '');

        // Поиск по коду или названию
        if (mb_strtolower($code, 'UTF-8') === $query_lower ||
            mb_strtolower($name, 'UTF-8') === $query_lower ||
            (strlen($query) >= 3 && mb_stripos($name, $query, 0, 'UTF-8') !== false)) {

            if ($active && mb_strtolower($active, 'UTF-8') === 'архив') {
                return array('text' => '📦 ' . $name . ' [' . $code . '] — в АРХИВЕ', 'photo' => null);
            }

            $text = '📋 ' . $name . ' [' . $code . ']';
            if ($weight) $text .= "\n⚖️ Вес: " . $weight . ' г';
            if ($recipe) $text .= "\n\n🍽 Состав и приготовление:\n" . $recipe;

            return array('text' => $text, 'photo' => ($photo && strpos($photo, 'http') === 0) ? $photo : null);
        }
    }
    return null;
}

// Отправка сообщения с фото (если есть)
function vk_send_to($peer_id, $text, $photo_url = null) {
    $attachment = '';

    // Если есть фото — загружаем в ВК
    if ($photo_url) {
        $attachment = vk_upload_photo($peer_id, $photo_url);
    }

    $params = array(
        'peer_id'      => $peer_id,
        'message'      => $text,
        'random_id'    => mt_rand(1, 9999999),
        'access_token' => VK_TOKEN,
        'v'            => '5.131'
    );
    if ($attachment) $params['attachment'] = $attachment;

    $url = 'https://api.vk.com/method/messages.send?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_exec($ch);
    curl_close($ch);
}

// Загрузка фото из Google Drive в ВК
function vk_upload_photo($peer_id, $photo_url) {
    // 1. Получаем upload server
    $res = json_decode(file_get_contents(
        'https://api.vk.com/method/photos.getMessagesUploadServer?peer_id=' . $peer_id
        . '&access_token=' . VK_TOKEN . '&v=5.131'
    ), true);
    if (empty($res['response']['upload_url'])) return '';
    $upload_url = $res['response']['upload_url'];

    // 2. Скачиваем фото
    $ch = curl_init($photo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $img_data = curl_exec($ch);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if (!$img_data) return '';

    // 3. Загружаем во ВК через tmpfile
    $ext = (strpos($ct, 'png') !== false) ? 'png' : 'jpg';
    $tmp = tempnam(sys_get_temp_dir(), 'vkp') . '.' . $ext;
    file_put_contents($tmp, $img_data);

    $ch = curl_init($upload_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('photo' => new CURLFile($tmp)));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $upload = json_decode(curl_exec($ch), true);
    curl_close($ch);
    @unlink($tmp);

    if (empty($upload['photo'])) return '';

    // 4. Сохраняем фото
    $save = json_decode(file_get_contents(
        'https://api.vk.com/method/photos.saveMessagesPhoto'
        . '?server=' . $upload['server']
        . '&photo=' . urlencode($upload['photo'])
        . '&hash=' . $upload['hash']
        . '&access_token=' . VK_TOKEN . '&v=5.131'
    ), true);

    if (!empty($save['response'][0])) {
        $p = $save['response'][0];
        return 'photo' . $p['owner_id'] . '_' . $p['id'];
    }
    return '';
}

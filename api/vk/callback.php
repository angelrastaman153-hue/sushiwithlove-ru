<?php
// VK Callback API — без внешних зависимостей на старте
define('_VK_CONFIRMATION', '813d4204');
define('_VK_GROUP_ID',     237666301);
define('_VK_SECRET',       '');
define('_GS_SHEET_ID',     '10vZ9_4tPf23o3E3ETdIqHxQmgDc4_hm0Jtrpu4i_PnA');
define('_VK_TOKEN',        'vk1.a.KAQexgBeEkvKhUwRWmHj8ZSFtXHjeg99gPwpYTjaKS21sJTEZIQozbv4J5OMy1XsQ7T7m8qFjVLQ7EuyEZnpBBw_7adEOYpTcBGZKIOgXGIYyptk2ixJwico1MP3v-WzVLR3-o9dLPfhwmrb_-eNMilpRfi_mR46RS_-IatWRx_EVjzEvTW2ifWcx8lmALrp1n2xVGZC0WrdlyAILq8xbA');

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) { echo 'ok'; exit; }

// Подтверждение адреса сервера
if ($data['type'] === 'confirmation' && $data['group_id'] == _VK_GROUP_ID) {
    echo _VK_CONFIRMATION;
    exit;
}

// Проверка секретного ключа (только если задан)
if (_VK_SECRET !== '' && (!isset($data['secret']) || $data['secret'] !== _VK_SECRET)) {
    echo 'ok'; exit;
}

// Новое сообщение
if ($data['type'] === 'message_new') {
    $msg     = isset($data['object']['message']) ? $data['object']['message'] : (isset($data['object']) ? $data['object'] : array());
    $peer_id = isset($msg['peer_id']) ? $msg['peer_id'] : null;
    $text    = isset($msg['text'])    ? trim($msg['text']) : '';
    $from_id = isset($msg['from_id']) ? $msg['from_id'] : null;

    if ($from_id && $from_id < 0) { echo 'ok'; exit; }

    if ($text && $peer_id) {
        $query = preg_replace('/[^a-zA-Z0-9\x{0410}-\x{044F}\x{0451}\x{0401}\-]/u', '', $text);
        $reply = ttk_lookup($query);
        if ($reply) {
            vk_send_to($peer_id, $reply['text'], isset($reply['photo']) ? $reply['photo'] : null);
        }
    }
}

echo 'ok';

function gdrive_to_direct($url) {
    $file_id = null;

    // https://drive.google.com/file/d/FILE_ID/view...
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_\-]+)/', $url, $m)) {
        $file_id = $m[1];
    }
    // https://drive.google.com/open?id=FILE_ID
    elseif (preg_match('/[?&]id=([a-zA-Z0-9_\-]+)/', $url, $m)) {
        $file_id = $m[1];
    }

    if ($file_id) {
        // Прямой CDN-URL Google (работает без редиректов и авторизации)
        return 'https://lh3.googleusercontent.com/d/' . $file_id;
    }

    return $url;
}

function ttk_lookup($query) {
    if (!$query) return null;

    $url = 'https://docs.google.com/spreadsheets/d/' . _GS_SHEET_ID . '/export?format=csv';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $csv = curl_exec($ch);
    curl_close($ch);

    if (!$csv) return null;

    // fgetcsv корректно обрабатывает многострочные ячейки в кавычках
    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, $csv);
    $rows = array();
    $fh = fopen($tmp, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        $rows[] = $row;
    }
    fclose($fh);
    @unlink($tmp);
    if (empty($rows)) return null;

    $query_lower = mb_strtolower(trim($query), 'UTF-8');

    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        if (count($row) < 2) continue;

        $code   = trim(isset($row[0]) ? $row[0] : '');
        $name   = trim(isset($row[1]) ? $row[1] : '');
        $photo  = trim(isset($row[2]) ? $row[2] : '');
        $weight = trim(isset($row[3]) ? $row[3] : '');
        $recipe = trim(isset($row[4]) ? $row[4] : '');
        $active = trim(isset($row[5]) ? $row[5] : '');

        if (mb_strtolower($code, 'UTF-8') === $query_lower ||
            mb_strtolower($name, 'UTF-8') === $query_lower ||
            (mb_strlen($query, 'UTF-8') >= 3 && mb_stripos($name, $query, 0, 'UTF-8') !== false)) {

            if ($active && mb_strtolower($active, 'UTF-8') === 'архив') {
                return array('text' => '📦 ' . $name . ' [' . $code . '] — в АРХИВЕ', 'photo' => null);
            }

            $text = '📋 ' . $name . ' [' . $code . ']';
            if ($weight) $text .= "\n⚖️ Вес: " . $weight . ' г';
            if ($recipe) $text .= "\n\n🍽 Состав и приготовление:\n" . $recipe;

            $photo_url = null;
            if ($photo && strpos($photo, 'http') === 0) {
                $photo_url = gdrive_to_direct($photo);
            }
            return array('text' => $text, 'photo' => $photo_url);
        }
    }
    return null;
}

function vk_send_to($peer_id, $text, $photo_url = null) {
    $attachment = '';
    if ($photo_url) {
        $attachment = vk_upload_photo($peer_id, $photo_url);
    }

    $params = array(
        'peer_id'      => $peer_id,
        'message'      => $text,
        'random_id'    => mt_rand(1, 9999999),
        'access_token' => _VK_TOKEN,
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

function vk_curl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function vk_upload_photo($peer_id, $photo_url) {
    // 1. Получаем upload server
    $res = json_decode(vk_curl(
        'https://api.vk.com/method/photos.getMessagesUploadServer?peer_id=' . $peer_id
        . '&access_token=' . _VK_TOKEN . '&v=5.131'
    ), true);
    if (empty($res['response']['upload_url'])) return '';
    $upload_url = $res['response']['upload_url'];

    // 2. Скачиваем фото
    $ch = curl_init($photo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $img_data = curl_exec($ch);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if (!$img_data || strlen($img_data) < 100) return '';

    // 3. Загружаем во ВК
    $ext = (strpos($ct, 'png') !== false) ? 'png' : 'jpg';
    $tmp = tempnam(sys_get_temp_dir(), 'vkp') . '.' . $ext;
    file_put_contents($tmp, $img_data);

    $ch = curl_init($upload_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('photo' => new CURLFile($tmp)));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $upload = json_decode(curl_exec($ch), true);
    curl_close($ch);
    @unlink($tmp);

    if (empty($upload['photo'])) return '';

    // 4. Сохраняем фото
    $save = json_decode(vk_curl(
        'https://api.vk.com/method/photos.saveMessagesPhoto'
        . '?server=' . $upload['server']
        . '&photo=' . urlencode($upload['photo'])
        . '&hash=' . $upload['hash']
        . '&access_token=' . _VK_TOKEN . '&v=5.131'
    ), true);

    if (!empty($save['response'][0])) {
        $p = $save['response'][0];
        return 'photo' . $p['owner_id'] . '_' . $p['id'];
    }
    return '';
}

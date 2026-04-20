<?php
// VK Callback API — повар-бот (раскладки по артикулу)
require_once __DIR__ . '/../config.php';

$body = file_get_contents('php://input');
$data = json_decode($body, true);

vk_log('IN', $body);

if (!$data) { echo 'ok'; exit; }

// Подтверждение адреса сервера
if ($data['type'] === 'confirmation' && $data['group_id'] == VK_GROUP_ID) {
    echo VK_CONFIRMATION;
    exit;
}

// Проверка секретного ключа (только если задан)
if (VK_SECRET !== '' && (!isset($data['secret']) || $data['secret'] !== VK_SECRET)) {
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
        $query = preg_replace('/[^a-zA-Z0-9\x{0410}-\x{044F}\x{0451}\x{0401}\-\s]/u', '', trim($text));
        $query = trim($query);
        if ($query) {
            $result = ttk_lookup($query);
            if ($result === null) {
                vk_send_to($peer_id, '❌ «' . $query . '» — не найдено. Попробуй точный артикул (например: 005) или другое слово.');
            } elseif ($result['type'] === 'single') {
                vk_send_to($peer_id, $result['text'], $result['photo']);
            } elseif ($result['type'] === 'multiple') {
                vk_send_to($peer_id, $result['text'], null);
            }
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

    $url = 'https://docs.google.com/spreadsheets/d/' . GS_SHEET_ID . '/export?format=csv';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $csv = curl_exec($ch);
    curl_close($ch);

    if (!$csv) return null;

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
    $exact = null;   // точное совпадение по коду или названию
    $matches = array(); // все частичные совпадения

    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        if (count($row) < 2) continue;

        $code   = trim(isset($row[0]) ? $row[0] : '');
        $name   = trim(isset($row[1]) ? $row[1] : '');
        $photo  = trim(isset($row[2]) ? $row[2] : '');
        $weight = trim(isset($row[3]) ? $row[3] : '');
        $recipe = trim(isset($row[4]) ? $row[4] : '');
        $active = trim(isset($row[5]) ? $row[5] : '');

        $code_lower = mb_strtolower($code, 'UTF-8');
        $name_lower = mb_strtolower($name, 'UTF-8');
        $is_archived = ($active && mb_strtolower($active, 'UTF-8') === 'архив');

        $is_exact   = ($code_lower === $query_lower || $name_lower === $query_lower);
        $is_partial = (mb_strlen($query, 'UTF-8') >= 3 && mb_stripos($name, $query, 0, 'UTF-8') !== false);

        if ($is_exact || $is_partial) {
            $item = array(
                'code' => $code, 'name' => $name, 'photo' => $photo,
                'weight' => $weight, 'recipe' => $recipe, 'archived' => $is_archived
            );
            if ($is_exact) {
                $exact = $item;
                break; // точное совпадение — дальше не ищем
            } else {
                $matches[] = $item;
            }
        }
    }

    // Точное совпадение — вернуть одну карточку
    if ($exact) {
        return ttk_make_card($exact);
    }

    // Одно частичное — тоже карточку
    if (count($matches) === 1) {
        return ttk_make_card($matches[0]);
    }

    // Несколько совпадений — список
    if (count($matches) > 1) {
        $text = '🔍 По запросу «' . $query . '» нашлось ' . count($matches) . ' позиции:' . "\n";
        foreach ($matches as $m) {
            $arch = $m['archived'] ? ' [архив]' : '';
            $text .= "\n• " . $m['name'] . ' — ' . $m['code'] . $arch;
        }
        $text .= "\n\nНапиши точный артикул (например: " . $matches[0]['code'] . "), чтобы получить карточку.";
        return array('type' => 'multiple', 'text' => $text, 'photo' => null);
    }

    return null;
}

function ttk_make_card($item) {
    if ($item['archived']) {
        return array('type' => 'single', 'text' => '📦 ' . $item['name'] . ' [' . $item['code'] . '] — в АРХИВЕ', 'photo' => null);
    }
    $text = '📋 ' . $item['name'] . ' [' . $item['code'] . ']';
    if ($item['weight']) $text .= "\n⚖️ Вес: " . $item['weight'] . ' г';
    if ($item['recipe']) $text .= "\n\n🍽 Состав и приготовление:\n" . $item['recipe'];

    $photo_url = null;
    if ($item['photo'] && strpos($item['photo'], 'http') === 0) {
        $photo_url = gdrive_to_direct($item['photo']);
    }
    return array('type' => 'single', 'text' => $text, 'photo' => $photo_url);
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
        'access_token' => VK_TOKEN,
        'v'            => '5.131'
    );
    if ($attachment) $params['attachment'] = $attachment;

    $url = 'https://api.vk.com/method/messages.send?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $resp = curl_exec($ch);
    curl_close($ch);
    vk_log('SEND peer=' . $peer_id . ' att=' . ($attachment ? 'y' : 'n'), $resp);
}

function vk_log($tag, $payload) {
    $path = __DIR__ . '/vk_bot_log.txt';
    if (file_exists($path) && filesize($path) > 500000) {
        @file_put_contents($path, '');
    }
    @file_put_contents(
        $path,
        date('c') . ' [' . $tag . '] ' . (is_string($payload) ? $payload : json_encode($payload)) . "\n",
        FILE_APPEND
    );
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
        . '&access_token=' . VK_TOKEN . '&v=5.131'
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
        . '&access_token=' . VK_TOKEN . '&v=5.131'
    ), true);

    if (!empty($save['response'][0])) {
        $p = $save['response'][0];
        return 'photo' . $p['owner_id'] . '_' . $p['id'];
    }
    return '';
}

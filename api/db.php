<?php
require_once __DIR__ . '/config.php';

$_loyalty_config = null;

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
        );
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(array('ok' => false, 'error' => 'DB connection failed')));
    }
    return $pdo;
}

function loyalty_config($key) {
    global $_loyalty_config;
    if (!$_loyalty_config) {
        $rows = db()->query('SELECT key_name, value FROM loyalty_config')->fetchAll();
        foreach ($rows as $r) $_loyalty_config[$r['key_name']] = $r['value'];
    }
    return isset($_loyalty_config[$key]) ? $_loyalty_config[$key] : '';
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_required() {
    $token = isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : (isset($_COOKIE['swl_token']) ? $_COOKIE['swl_token'] : '');
    if (!$token) json_out(array('ok' => false, 'error' => 'unauthorized'), 401);
    $stmt = db()->prepare('
        SELECT u.* FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ? AND s.expires_at > NOW()
    ');
    $stmt->execute(array($token));
    $user = $stmt->fetch();
    if (!$user) json_out(array('ok' => false, 'error' => 'unauthorized'), 401);
    return $user;
}

// Создать сессию для пользователя, вернуть токен
function create_session($user_id) {
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SESSION_DAYS * 86400);
    db()->prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)')
       ->execute(array($token, $user_id, $expires));
    return $token;
}

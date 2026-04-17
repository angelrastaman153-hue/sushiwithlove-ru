<?php
// Шаблон конфига — скопируйте в api/config.php и заполните реальными значениями
// api/config.php НЕ коммитится (см. .gitignore)

define('BOT_TOKEN',  'your_telegram_bot_token_here');
define('BOT_NAME',   'your_bot_username');
define('SITE_URL',   'https://xn--90acqmqobo9b7bse.xn--p1ai');

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

define('SESSION_DAYS', 30);

// VK — Callback API
define('VK_CONFIRMATION', 'your_vk_confirmation_code');
define('VK_SECRET',       '');

// Google Sheets — ТТК
define('GS_SHEET_ID', 'your_google_sheet_id');

// VK — кухонный экран
define('VK_TOKEN',    'your_vk_community_token');
define('VK_GROUP_ID', 'your_vk_group_id');
define('VK_PEER_IDS', 'peer_id_1,peer_id_2');

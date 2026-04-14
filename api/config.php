<?php
// Конфиг — не коммитить реальные значения (держать в .env на сервере)
// На сервере: просто define'ы с реальными значениями

define('BOT_TOKEN',  '8687681331:AAEVMn_44tRlyBFRuD-X0fTOtkZHnAzO5YQ');
define('BOT_NAME',   'sushi45_bot');
define('SITE_URL',   'https://xn--90acqmqobo9b7bse.xn--p1ai'); // сушислюбовью.рф

define('DB_HOST', 'localhost');
define('DB_USER', 'angelros_swl');
define('DB_PASS', 'ArastamaN45');
define('DB_NAME', 'angelros_swl');

define('SESSION_DAYS', 30); // срок жизни сессии

// VK — Callback API
define('VK_CONFIRMATION', '813d4204');
define('VK_SECRET',       ''); // оставить пустым если секрет не задан в настройках ВК

// Google Sheets — ТТК
define('GS_SHEET_ID', '10vZ9_4tPf23o3E3ETdIqHxQmgDc4_hm0Jtrpu4i_PnA');

// VK — кухонный экран
define('VK_TOKEN',    'vk1.a.WDNS1oSr7tPId3ZWhRbjLCe-AbRhMojK0IXg1RKXvW8C1ANWkTVyRk4r85nRz0IzgdfffHpYbv2zaCEb5AKT1TjQRNpw85zF2GUiJIfWoKdIWJFrB0fGeoCviFJI8RzqkYSgShCNM0QBYeIzIxKWoZJQvmi0lvtvhDUr8HJiERXw_XA-PhrdhLaT1mpdT9IJuZNXf-NDIg5mQtdeOZ60kQ');
define('VK_GROUP_ID', '237666301');
// peer_id получателей уведомлений (через запятую если несколько)
// Личный диалог: ID пользователя, Беседа: 2000000000 + номер беседы
define('VK_PEER_IDS', '3150260'); // Михаил Богачёв (администратор)

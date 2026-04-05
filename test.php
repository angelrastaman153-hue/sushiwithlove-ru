<?php echo json_encode(['ok' => true, 'php' => phpversion(), 'extensions' => ['curl' => extension_loaded('curl'), 'openssl' => extension_loaded('openssl')]]);

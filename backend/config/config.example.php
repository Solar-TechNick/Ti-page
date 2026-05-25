<?php
// backend/config/config.example.php
// Copy to config.php and fill in. config.php is gitignored.

return [
    'env' => 'production',                             // 'production' | 'test'
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'ti_backend',
        'username' => 'ti_backend_app',
        'password' => 'CHANGE_ME',
    ],
    'mail' => [
        'from_address' => 'anfrage@technik-prignitz.de',
        'from_name'    => 'Technik- & Instandsetzungs GmbH',
        'to_address'   => 'info@technik-prignitz.de',
    ],
    'urls' => [
        'admin_base'  => 'https://admin.technik-prignitz.de',
        'public_site' => 'https://technik-prignitz.de',
    ],
    'cors_origins' => [
        'https://technik-prignitz.de',
        'https://www.technik-prignitz.de',
    ],
    'rate_limit' => [
        'max_per_hour' => 5,
    ],
    'uploads' => [
        'dir'              => __DIR__ . '/../storage/uploads',
        'max_file_bytes'   => 10 * 1024 * 1024,
        'max_total_bytes'  => 50 * 1024 * 1024,
        'max_file_count'   => 10,
        'allowed_mimes'    => ['image/jpeg','image/png','image/heic','image/webp','application/pdf'],
    ],
    'logs_dir' => __DIR__ . '/../storage/logs',
    'session' => [
        'name'              => 'tiadmin',
        'idle_seconds'      => 8 * 3600,
        'lockout_threshold' => 5,
        'lockout_minutes'   => 15,
    ],
];

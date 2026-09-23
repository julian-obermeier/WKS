<?php
declare(strict_types=1);

use WKS\Core\Env;

return [
    'session_name' => Env::get('SESSION_NAME', 'WKSSESSID'),
    'session_secure' => Env::bool('SESSION_SECURE', true),
    'session_samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    'default_inactivity_minutes' => 30,
    'default_login_max_attempts' => 5,
    'default_login_lock_minutes' => 15,
];

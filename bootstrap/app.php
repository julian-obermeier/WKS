<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/Core/Env.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'WKS\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

WKS\Core\Env::load(BASE_PATH . '/.env');

require_once BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string) config('app.timezone', 'Europe/Berlin'));

WKS\Core\Session::start();
WKS\Core\ExceptionHandler::register();

$router = new WKS\Core\Router();
require BASE_PATH . '/routes/web.php';

return $router;

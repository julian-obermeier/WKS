<?php
declare(strict_types=1);

$router = require dirname(__DIR__) . '/bootstrap/app.php';

$request = WKS\Core\Request::capture();
WKS\Core\Csrf::validate($request);

$response = $router->dispatch($request);
$response->send();

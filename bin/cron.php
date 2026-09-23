<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

$result=(new WKS\Services\CronService())->runDue(in_array('--force',$argv??[],true));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;

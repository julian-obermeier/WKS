<?php
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
require_once BASE_PATH.'/app/Core/Env.php';

spl_autoload_register(static function(string $class): void {
    $prefix='WKS\\';
    if(!str_starts_with($class,$prefix))return;
    $path=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});

WKS\Core\Env::load(BASE_PATH.'/.env');
require_once BASE_PATH.'/app/Support/helpers.php';
date_default_timezone_set((string)config('app.timezone','Europe/Berlin'));
WKS\Core\Session::start();

header('Content-Type: application/json; charset=UTF-8');

try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_SERVER['REQUEST_URI']??'/')!=='/upload'){
        http_response_code(404);
        echo json_encode(['error'=>'not_found'],JSON_THROW_ON_ERROR);
        return;
    }
    if(!isset($_FILES['file'])||!is_array($_FILES['file'])){
        throw new WKS\Core\HttpException(422,'Testdatei fehlt.');
    }

    $recordId=(int)WKS\Core\Database::connection()->query(
        'SELECT id FROM house_bans WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if($recordId<=0)throw new RuntimeException('Kein Hausverbot aus dem Integrationslauf vorhanden.');

    $attachmentId=(new WKS\Services\UploadService())->store('house_bans',$recordId,$_FILES['file']);
    $stmt=WKS\Core\Database::connection()->prepare('SELECT * FROM attachments WHERE id=:id');
    $stmt->execute(['id'=>$attachmentId]);
    $attachment=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$attachment)throw new RuntimeException('Upload wurde nicht in der Datenbank gespeichert.');

    $absolute=BASE_PATH.'/storage/uploads/'.$attachment['stored_name'];
    if(!is_file($absolute)||str_starts_with(realpath($absolute)?:'',realpath(BASE_PATH.'/public')?:BASE_PATH.'/public')){
        throw new RuntimeException('Upload liegt nicht im geschützten Storage.');
    }

    echo json_encode([
        'ok'=>true,'attachment_id'=>$attachmentId,'mime'=>$attachment['mime_type'],
        'sha256'=>$attachment['sha256'],'stored_outside_public'=>true
    ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch(WKS\Core\HttpException $e) {
    http_response_code($e->status);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}

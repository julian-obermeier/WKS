<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use RuntimeException;
use WKS\Core\Env;

final class InstallerService
{
    public function isInstalled(): bool
    {
        return is_file(BASE_PATH.'/storage/install.lock');
    }

    public function envConfigured(): bool
    {
        $path=BASE_PATH.'/.env';
        if(!is_file($path))return false;
        foreach(['APP_URL','APP_KEY','DB_HOST','DB_DATABASE','DB_USERNAME'] as $key){
            if(trim((string)Env::get($key,''))==='')return false;
        }
        return true;
    }

    public function requirements(): array
    {
        $items=[];
        $items[]=$this->requirement('PHP-Version',version_compare(PHP_VERSION,'8.1.0','>='),'PHP '.PHP_VERSION,'Mindestens PHP 8.1 erforderlich');
        foreach([
            'pdo_mysql'=>'PDO MySQL',
            'mbstring'=>'mbstring',
            'fileinfo'=>'fileinfo',
            'json'=>'JSON',
            'session'=>'Sessions',
            'zip'=>'ZipArchive / ZIP',
        ] as $ext=>$label){
            $ok=$ext==='zip'?class_exists(\ZipArchive::class):extension_loaded($ext);
            $items[]=$this->requirement($label,$ok,$ok?'verfügbar':'fehlt',$label.' muss auf dem Server aktiviert sein');
        }

        $rootWritable=is_writable(BASE_PATH);
        $items[]=$this->requirement('Projektverzeichnis beschreibbar',$rootWritable,$rootWritable?'beschreibbar':BASE_PATH.' ist nicht beschreibbar','Für .env und Laufzeitverzeichnisse sind Schreibrechte erforderlich');

        $storage=BASE_PATH.'/storage';
        $storageWritable=is_dir($storage)?is_writable($storage):$rootWritable;
        $items[]=$this->requirement('Storage beschreibbar',$storageWritable,$storageWritable?'bereit':'nicht beschreibbar','Uploads, Logs, Exporte und Sessions benötigen Schreibrechte');

        $gd=function_exists('imagecreatefromstring')&&function_exists('imagejpeg');
        $items[]=[
            'name'=>'PHP-GD',
            'status'=>$gd?'ok':'warning',
            'detail'=>$gd?'verfügbar':'nicht verfügbar – JPEG-Logos funktionieren; PNG-Logos für PDF benötigen GD',
        ];

        $overall='ok';
        foreach($items as $item){
            if($item['status']==='error'){$overall='error';break;}
            if($item['status']==='warning')$overall='warning';
        }
        return ['overall'=>$overall,'items'=>$items];
    }

    public function defaults(): array
    {
        return [
            'app_url'=>(string)(Env::get('APP_URL','')?:$this->detectAppUrl()),
            'app_base_path'=>(string)(Env::get('APP_BASE_PATH','')?:$this->detectBasePath()),
            'db_host'=>(string)Env::get('DB_HOST','127.0.0.1'),
            'db_port'=>(string)Env::get('DB_PORT','3306'),
            'db_database'=>(string)Env::get('DB_DATABASE',''),
            'db_username'=>(string)Env::get('DB_USERNAME',''),
        ];
    }

    public function configure(array $input): void
    {
        if($this->isInstalled())throw new RuntimeException('WKS ist bereits installiert.');

        $requirements=$this->requirements();
        if($requirements['overall']==='error')throw new RuntimeException('Die Servervoraussetzungen sind noch nicht erfüllt.');

        $appUrl=rtrim(trim((string)($input['app_url']??'')),'/');
        $basePath=trim((string)($input['app_base_path']??''));
        $dbHost=trim((string)($input['db_host']??''));
        $dbPort=trim((string)($input['db_port']??'3306'));
        $dbName=trim((string)($input['db_database']??''));
        $dbUser=trim((string)($input['db_username']??''));
        $dbPassword=(string)($input['db_password']??'');

        if($dbPassword===''&&is_file(BASE_PATH.'/.env'))$dbPassword=(string)Env::get('DB_PASSWORD','');

        if(!filter_var($appUrl,FILTER_VALIDATE_URL)||!in_array((string)parse_url($appUrl,PHP_URL_SCHEME),['http','https'],true)){
            throw new RuntimeException('Bitte eine gültige http-/https-Anwendungsadresse angeben.');
        }
        if(parse_url($appUrl,PHP_URL_QUERY)!==null||parse_url($appUrl,PHP_URL_FRAGMENT)!==null){
            throw new RuntimeException('Die Anwendungsadresse darf keine Query-Parameter oder Fragmente enthalten.');
        }
        if($basePath!==''&&!preg_match('#^/?[A-Za-z0-9/_-]*$#',$basePath))throw new RuntimeException('Der Base-Pfad enthält ungültige Zeichen.');
        if(str_contains($basePath,'..'))throw new RuntimeException('Der Base-Pfad darf keine relativen Pfadsegmente enthalten.');
        foreach(['Datenbankhost'=>$dbHost,'Datenbankname'=>$dbName,'Datenbankbenutzer'=>$dbUser] as $label=>$value){
            if($value===''||str_contains($value,"\n")||str_contains($value,"\r"))throw new RuntimeException($label.' fehlt oder ist ungültig.');
        }
        if(!ctype_digit($dbPort)||(int)$dbPort<1||(int)$dbPort>65535)throw new RuntimeException('Ungültiger Datenbankport.');

        $this->testDatabase($dbHost,(int)$dbPort,$dbName,$dbUser,$dbPassword);
        $this->prepareStorage();

        $key=bin2hex(random_bytes(32));
        $secure=(string)parse_url($appUrl,PHP_URL_SCHEME)==='https';
        $values=[
            'APP_ENV'=>'production',
            'APP_DEBUG'=>'false',
            'APP_URL'=>$appUrl,
            'APP_BASE_PATH'=>'/'.trim($basePath,'/'),
            'APP_TIMEZONE'=>'Europe/Berlin',
            'APP_KEY'=>$key,
            'DB_HOST'=>$dbHost,
            'DB_PORT'=>(string)(int)$dbPort,
            'DB_DATABASE'=>$dbName,
            'DB_USERNAME'=>$dbUser,
            'DB_PASSWORD'=>$dbPassword,
            'DB_CHARSET'=>'utf8mb4',
            'SESSION_NAME'=>'WKSSESSID',
            'SESSION_SECURE'=>$secure?'true':'false',
            'SESSION_SAMESITE'=>'Lax',
        ];
        if($values['APP_BASE_PATH']==='/')$values['APP_BASE_PATH']='';

        $lines=[];
        foreach($values as $keyName=>$value)$lines[]=$keyName.'='.$this->envValue((string)$value);
        $content=implode(PHP_EOL,$lines).PHP_EOL;

        $target=BASE_PATH.'/.env';$temp=$target.'.tmp-'.bin2hex(random_bytes(6));
        if(file_put_contents($temp,$content,LOCK_EX)===false)throw new RuntimeException('.env konnte nicht geschrieben werden.');
        @chmod($temp,0600);
        if(!rename($temp,$target)){@unlink($temp);throw new RuntimeException('.env konnte nicht aktiviert werden.');}
        @chmod($target,0600);
    }

    public function prepareStorage(): void
    {
        foreach(['storage','storage/uploads','storage/logs','storage/exports','storage/generated','storage/sessions'] as $relative){
            $path=BASE_PATH.'/'.$relative;
            if(!is_dir($path)&&!mkdir($path,0770,true)&&!is_dir($path))throw new RuntimeException('Verzeichnis konnte nicht angelegt werden: '.$relative);
            if(!is_writable($path))throw new RuntimeException('Verzeichnis ist nicht beschreibbar: '.$relative);
        }
    }

    public function markInstalled(int $adminUserId,array $systemCheck): void
    {
        $this->prepareStorage();
        $payload=[
            'installed_at'=>date(DATE_ATOM),
            'version'=>trim((string)@file_get_contents(BASE_PATH.'/VERSION')),
            'admin_user_id'=>$adminUserId,
            'system_check'=>$systemCheck['overall']??'unknown',
        ];
        $path=BASE_PATH.'/storage/install.lock';
        if(file_put_contents($path,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false){
            throw new RuntimeException('Installationssperre konnte nicht geschrieben werden.');
        }
        @chmod($path,0600);
    }

    private function testDatabase(string $host,int $port,string $database,string $username,string $password): void
    {
        $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$host,$port,$database);
        try{
            $pdo=new PDO($dsn,$username,$password,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
            $pdo->query('SELECT 1');
        }catch(\Throwable $e){
            throw new RuntimeException('Datenbankverbindung fehlgeschlagen: '.$e->getMessage(),0,$e);
        }
    }

    private function detectAppUrl(): string
    {
        $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
        $scheme=$https?'https':'http';
        $host=preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';
        return $scheme.'://'.$host;
    }

    private function detectBasePath(): string
    {
        $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/index.php'));
        $dir=rtrim(dirname($script),'/');
        return $dir==='.'||$dir==='/'?'':$dir;
    }

    private function envValue(string $value): string
    {
        return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private function requirement(string $name,bool $ok,string $success,string $error): array
    {
        return ['name'=>$name,'status'=>$ok?'ok':'error','detail'=>$ok?$success:$error];
    }
}

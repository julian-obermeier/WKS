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

use WKS\Core\Database;

$base='http://127.0.0.1:8098';
$password='IntegrationPass!2026';
$assertions=0;

$assert=static function(bool $condition,string $message) use (&$assertions): void {
    $assertions++;
    if(!$condition)throw new RuntimeException('HTTP SMOKE FAILED: '.$message);
};

$client=static function(string $cookieFile) use ($base): Closure {
    return static function(string $path,string $method='GET',array $data=[]) use ($cookieFile,$base): array {
        $ch=curl_init($base.$path);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HEADER=>true,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_COOKIEJAR=>$cookieFile,
            CURLOPT_COOKIEFILE=>$cookieFile,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_USERAGENT=>'WKS-HTTP-Smoke/1.0',
        ]);
        if($method==='POST'){
            curl_setopt($ch,CURLOPT_POST,true);
            curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
        }
        $raw=curl_exec($ch);
        if($raw===false){
            $error=curl_error($ch);curl_close($ch);
            throw new RuntimeException('HTTP request failed: '.$error);
        }
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return ['status'=>$status,'headers'=>substr($raw,0,$headerSize),'body'=>substr($raw,$headerSize)];
    };
};

$token=static function(string $html): string {
    if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing.');
    return html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
};

$pdo=Database::connection();
$gi=(int)$pdo->query('SELECT id FROM locations WHERE code="GI"')->fetchColumn();
$mr=(int)$pdo->query('SELECT id FROM locations WHERE code="MR"')->fetchColumn();
$assert($gi>0&&$mr>0,'test locations exist');

$adminCookie=tempnam(sys_get_temp_dir(),'wks-admin-');
$employeeCookie=tempnam(sys_get_temp_dir(),'wks-employee-');
$admin=$client($adminCookie);
$employee=$client($employeeCookie);

$installBlocked=$admin('/install');
$assert($installBlocked['status']===302&&str_contains($installBlocked['headers'],'/login'),'installed system blocks installer');

$login=$admin('/login');
$assert($login['status']===200&&str_contains($login['body'],'Anmelden'),'login page renders');
$loginPost=$admin('/login','POST',[
    '_token'=>$token($login['body']),
    'username'=>'integration.admin',
    'password'=>$password,
]);
$assert($loginPost['status']===302&&str_contains($loginPost['headers'],'/location/select'),'multi-site admin login redirects to site selection');

$selection=$admin('/location/select');
$assert($selection['status']===200&&str_contains($selection['body'],'Standort auswählen'),'site selection page renders');
$selected=$admin('/location/select','POST',['_token'=>$token($selection['body']),'location_id'=>$gi]);
$assert($selected['status']===302,'admin can select assigned Gießen site');

$dashboard=$admin('/');
$assert($dashboard['status']===200&&str_contains($dashboard['body'],'Dashboard'),'authenticated dashboard renders');
foreach(['/dutybook','/special-reports','/valuables','/house-bans','/search','/statistics','/announcements','/notifications','/admin/system/status','/admin/audit','/admin/updates'] as $path){
    $response=$admin($path);
    $assert($response['status']===200,'admin module responds 200: '.$path);
}

$giBans=$admin('/house-bans');
$assert(str_contains($giBans['body'],'CI Hausverbot geändert'),'Gießen house ban visible in Gießen context');

$selection=$admin('/location/select');
$admin('/location/select','POST',['_token'=>$token($selection['body']),'location_id'=>$mr]);
$mrBans=$admin('/house-bans');
$assert($mrBans['status']===200&&!str_contains($mrBans['body'],'CI Hausverbot geändert'),'Gießen house ban is not visible in Marburg context');

$insert=$pdo->prepare(
    'INSERT INTO house_bans (location_id,ban_date,person_name,reason,created_at,updated_at)
     VALUES (:location_id,"2026-09-23","MR IDOR Test","Isolation",NOW(),NOW())'
);
$insert->execute(['location_id'=>$mr]);
$mrBanId=(int)$pdo->lastInsertId();

$login=$employee('/login');
$employeeLogin=$employee('/login','POST',[
    '_token'=>$token($login['body']),
    'username'=>'integration.employee',
    'password'=>$password,
]);
$assert($employeeLogin['status']===302,'employee login succeeds');
$employeeDashboard=$employee('/');
$assert($employeeDashboard['status']===200&&str_contains($employeeDashboard['body'],'Dashboard'),'single-site employee reaches dashboard');

$forbidden=$employee('/admin/users');
$assert($forbidden['status']===403,'employee receives 403 for user administration');

$idor=$employee('/house-bans/'.$mrBanId);
$assert($idor['status']===404,'Gießen employee cannot access Marburg record by ID');

$selection=$employee('/location/select');
$forbiddenSwitch=$employee('/location/select','POST',[
    '_token'=>$token($selection['body']),
    'location_id'=>$mr,
]);
$assert($forbiddenSwitch['status']===403,'employee cannot switch to unassigned Marburg site');

@unlink($adminCookie);@unlink($employeeCookie);
echo "WKS authenticated HTTP smoke OK: {$assertions} assertions.\n";

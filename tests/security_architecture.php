<?php
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
$errors=[];

$read=static fn(string $path): string=>(string)file_get_contents(BASE_PATH.'/'.$path);
$mustContain=static function(string $path,array $needles) use(&$errors,$read): void {
    $source=$read($path);
    foreach($needles as $needle)if(!str_contains($source,$needle))$errors[]=$path.' missing security invariant: '.$needle;
};

$mustContain('app/Core/Database.php',[
    'PDO::ATTR_EMULATE_PREPARES => false',
    'PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION',
]);
$mustContain('app/Core/Csrf.php',[
    "['GET', 'HEAD', 'OPTIONS']",
    'hash_equals($expected, $provided)',
    'random_bytes(32)',
]);
$mustContain('app/Core/Session.php',[
    "'httponly' => true",
    "ini_set('session.use_strict_mode', '1')",
    "ini_set('session.use_only_cookies', '1')",
    'session_regenerate_id(true)',
]);
$mustContain('app/Services/AuthService.php',[
    'password_verify(',
    'login_attempts',
    'Session::regenerate()',
]);
$mustContain('app/Middleware/LocationMiddleware.php',[
    'userHasLocation($userId, $active)',
    "throw new HttpException(403",
]);
$mustContain('app/Middleware/PermissionMiddleware.php',[
    'Authorization::can($this->permission)',
    "throw new HttpException(403",
]);
$mustContain('public/.htaccess',[
    'Options -Indexes',
    'X-Content-Type-Options',
    'Content-Security-Policy',
    'frame-ancestors',
]);
$mustContain('app/Services/UploadService.php',[
    'is_uploaded_file(',
    'FILEINFO_MIME_TYPE',
    'random_bytes(',
    "/storage/uploads/",
]);
$mustContain('public/wks-installer.php',[
    "WKS_ARCHIVE_URL='https://github.com/julian-obermeier/WKS/archive/refs/heads/main.zip'",
    'hash_equals($csrf,$provided)',
    'wks_safe_relative(',
    '@unlink(__FILE__)',
]);
$mustContain('public/service-worker.js',[
    "if(req.method!=='GET')return",
    "fetch(req,{cache:'no-store'})",
    "./offline.html",
]);
$mustContain('database/migrations/2026_09_23_000009_create_admin_features.php',[
    "['mail.global_enabled','0','bool']",
]);
$mustContain('database/migrations/2026_09_23_000002_seed_core_data.php',[
    "'disabled'",
]);

$routes=$read('routes/web.php');
preg_match_all('/\$router->(get|post)\(\'([^\']+)\',\s*\[[^\]]+\](?:,\s*(\[[^;]+\]))?\);/',$routes,$matches,PREG_SET_ORDER);
$publicPrefixes=['/install','/install/configure','/login'];
$locationExempt=['/logout','/password/change','/location/select','/profile/theme'];
foreach($matches as $route){
    $method=strtoupper($route[1]);$path=$route[2];$middleware=$route[3]??'';
    $isPublic=false;foreach($publicPrefixes as $prefix)if($path===$prefix){$isPublic=true;break;}
    if(!$isPublic&&!str_contains($middleware,"'auth'"))$errors[]="Protected route missing auth middleware: {$method} {$path}";
    if(!$isPublic&&!in_array($path,$locationExempt,true)&&!str_contains($middleware,"'location'"))$errors[]="Protected domain route missing location middleware: {$method} {$path}";
    if($method==='POST'&&!in_array($path,['/install','/install/configure','/login'],true)&&!str_contains($middleware,"'auth'"))$errors[]="State-changing route missing auth middleware: {$method} {$path}";
}

$sensitivePrefixes=[
    '/dutybook'=>'permission:dutybook.',
    '/special-reports'=>'permission:special_reports.',
    '/valuables'=>'permission:valuables.',
    '/house-bans'=>'permission:house_bans.',
];
foreach($matches as $route){
    $path=$route[2];$middleware=$route[3]??'';
    foreach($sensitivePrefixes as $prefix=>$permissionPrefix){
        if(!str_starts_with($path,$prefix))continue;
        if(str_starts_with($path,$prefix.'-export')){}
        if(!str_contains($middleware,$permissionPrefix))$errors[]="Sensitive route missing granular permission: {$path}";
        break;
    }
}

$sw=$read('public/service-worker.js');
foreach(['/dutybook','/special-reports','/valuables','/house-bans','/attachments'] as $sensitive){
    if(preg_match('/STATIC\s*=\s*\[[^\]]*'.preg_quote($sensitive,'/').'/s',$sw))$errors[]='Sensitive path cached by service worker: '.$sensitive;
}

$env=$read('.env.example');
foreach(['password=secret','api_key=','token=ghp_','smtp_password='] as $secretPattern){
    if(str_contains(strtolower($env),$secretPattern))$errors[]='Potential secret in .env.example: '.$secretPattern;
}

if(is_dir(BASE_PATH.'/public/storage')||is_dir(BASE_PATH.'/public/uploads'))$errors[]='Writable sensitive storage must not live under public/.';

if($errors!==[]){
    fwrite(STDERR,"Security architecture check FAILED\n- ".implode("\n- ",array_unique($errors))."\n");
    exit(1);
}
echo "Security architecture check OK.\n";

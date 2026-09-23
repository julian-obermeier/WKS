<?php
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));

spl_autoload_register(static function(string $class): void {
    $prefix='WKS\\';
    if(!str_starts_with($class,$prefix))return;
    $path=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});

$errors=[];
$routesFile=BASE_PATH.'/routes/web.php';
$routes=(string)file_get_contents($routesFile);

$uses=[];
preg_match_all('/^use\s+([^;]+);/m',$routes,$useMatches);
foreach($useMatches[1] as $fqcn){
    $fqcn=trim($fqcn);
    $parts=explode('\\',$fqcn);
    $uses[end($parts)]=$fqcn;
}

preg_match_all('/\[\s*([A-Za-z0-9_]+)::class\s*,\s*\'([A-Za-z0-9_]+)\'\s*\]/',$routes,$handlerMatches,PREG_SET_ORDER);
foreach($handlerMatches as $match){
    [$all,$short,$method]=$match;
    $class=$uses[$short]??null;
    if(!$class){$errors[]="Route controller import missing: {$short}";continue;}
    if(!class_exists($class)){$errors[]="Route controller class missing: {$class}";continue;}
    if(!method_exists($class,$method))$errors[]="Route method missing: {$class}::{$method}";
}

$seen=[];
preg_match_all('/\$router->(get|post)\(\'([^\']+)\'/',$routes,$routeMatches,PREG_SET_ORDER);
foreach($routeMatches as $match){
    $key=strtoupper($match[1]).' '.$match[2];
    if(isset($seen[$key]))$errors[]="Duplicate route: {$key}";
    $seen[$key]=true;
}

$controllerFiles=glob(BASE_PATH.'/app/Controllers/*.php')?:[];
$controllerFiles=array_merge($controllerFiles,glob(BASE_PATH.'/app/Controllers/Admin/*.php')?:[]);
foreach($controllerFiles as $file){
    $source=(string)file_get_contents($file);
    preg_match_all('/View::render\(\s*\'([^\']+)\'/',$source,$viewMatches);
    foreach($viewMatches[1] as $view){
        $path=BASE_PATH.'/resources/views/'.$view.'.php';
        if(!is_file($path))$errors[]='Missing view '.$view.' referenced by '.str_replace(BASE_PATH.'/','',$file);
    }
}

$viewFiles=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH.'/resources/views',FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file)if($file->isFile()&&$file->getExtension()==='php')$viewFiles[]=$file->getPathname();
foreach($viewFiles as $file){
    $source=(string)file_get_contents($file);
    if(preg_match('/Database::|->prepare\s*\(|->query\s*\(/',$source))$errors[]='Direct database access in view: '.str_replace(BASE_PATH.'/','',$file);
}

$sqlRoots=['app/Repositories','app/Services'];
foreach($sqlRoots as $root){
    $path=BASE_PATH.'/'.$root;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
        if(!$file->isFile()||$file->getExtension()!=='php')continue;
        $source=(string)file_get_contents($file->getPathname());
        preg_match_all('/prepare\(\s*([\'"])([\s\S]*?)\1\s*\)/',$source,$prepared,PREG_SET_ORDER);
        foreach($prepared as $match){
            preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/',$match[2],$placeholders);
            $counts=array_count_values($placeholders[1]??[]);
            $duplicates=array_keys(array_filter($counts,static fn(int $count):bool=>$count>1));
            if($duplicates!==[]){
                $errors[]='Repeated native PDO placeholder(s) '.implode(', ',$duplicates).' in '.str_replace(BASE_PATH.'/','',$file->getPathname());
            }
        }
    }
}

$scanRoots=['app','bootstrap','config','database','public','resources','routes','bin'];
foreach($scanRoots as $root){
    $path=BASE_PATH.'/'.$root;if(!is_dir($path))continue;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
        if(!$file->isFile())continue;
        $ext=strtolower($file->getExtension());
        if(!in_array($ext,['php','js','css','json','md'],true))continue;
        $source=(string)file_get_contents($file->getPathname());
        if(preg_match('/\b(?:TODO|FIXME)\b/i',$source))$errors[]='TODO/FIXME found in '.str_replace(BASE_PATH.'/','',$file->getPathname());
    }
}

foreach([
    'public/index.php','public/.htaccess','public/manifest.webmanifest','public/service-worker.js',
    'storage/uploads/.gitkeep','storage/logs/.gitkeep','CHANGELOG.json','VERSION','.env.example'
] as $required){
    if(!is_file(BASE_PATH.'/'.$required))$errors[]='Required file missing: '.$required;
}

if($errors!==[]){
    fwrite(STDERR,"Static quality check FAILED\n- ".implode("\n- ",array_unique($errors))."\n");
    exit(1);
}

echo "Static quality check OK: ".count($seen)." routes, ".count($viewFiles)." views checked.\n";

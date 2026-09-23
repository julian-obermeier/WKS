<?php
declare(strict_types=1);

/**
 * WKS Bootstrap Installer
 *
 * Diese einzelne Datei wird in <WKS>/public/wks-installer.php hochgeladen.
 * Die Domain/Subdomain muss auf <WKS>/public zeigen.
 */

const WKS_ARCHIVE_URL='https://github.com/julian-obermeier/WKS/archive/refs/heads/main.zip';
const WKS_MAX_ARCHIVE_BYTES=62914560; // 60 MB

$root=dirname(__DIR__);
$publicDir=__DIR__;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'none'");

function wks_install_url(): string
{
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/wks-installer.php'));
    $dir=rtrim(dirname($script),'/');
    return ($dir===''||$dir==='.')?'/install':$dir.'/install';
}

function wks_existing_app(string $root): bool
{
    return is_dir($root.'/app')&&is_dir($root.'/bootstrap')&&is_file($root.'/public/index.php')&&is_file($root.'/VERSION');
}

if(wks_existing_app($root)){
    header('Location: '.wks_install_url(),true,302);
    exit;
}

if(session_status()!==PHP_SESSION_ACTIVE){
    session_name('WKSBOOTSTRAP');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off'),'httponly'=>true,'samesite'=>'Strict']);
    ini_set('session.use_strict_mode','1');
    session_start();
}

if(empty($_SESSION['wks_bootstrap_csrf']))$_SESSION['wks_bootstrap_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['wks_bootstrap_csrf'];

function wks_checks(string $root): array
{
    $download=function_exists('curl_init')||(bool)ini_get('allow_url_fopen');
    return [
        ['PHP >= 8.1',version_compare(PHP_VERSION,'8.1.0','>='),PHP_VERSION],
        ['ZipArchive',class_exists('ZipArchive'),'Für Download/Entpacken erforderlich'],
        ['PDO MySQL',extension_loaded('pdo_mysql'),'Für WKS-Datenbank erforderlich'],
        ['mbstring',extension_loaded('mbstring'),'Für sichere Textverarbeitung erforderlich'],
        ['fileinfo',extension_loaded('fileinfo'),'Für Upload-Prüfung erforderlich'],
        ['Downloadfunktion',$download,$download?'cURL oder allow_url_fopen verfügbar':'cURL und allow_url_fopen fehlen'],
        ['Zielverzeichnis beschreibbar',is_writable($root),$root],
    ];
}

function wks_download(string $url,string $target): void
{
    if(function_exists('curl_init')){
        $fp=fopen($target,'wb');
        if(!$fp)throw new RuntimeException('Temporäre ZIP-Datei kann nicht geschrieben werden.');
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_FILE=>$fp,
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>120,
            CURLOPT_CONNECTTIMEOUT=>20,
            CURLOPT_USERAGENT=>'WKS-Bootstrap-Installer/1.0',
            CURLOPT_FAILONERROR=>false,
        ]);
        $ok=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);fclose($fp);
        if(!$ok||$code<200||$code>=300){
            @unlink($target);
            throw new RuntimeException('GitHub-Download fehlgeschlagen: '.($error!==''?$error:'HTTP '.$code));
        }
    }else{
        $ctx=stream_context_create(['http'=>[
            'header'=>"User-Agent: WKS-Bootstrap-Installer/1.0\r\n",
            'timeout'=>120,
            'follow_location'=>1,
        ]]);
        $data=@file_get_contents($url,false,$ctx,0,WKS_MAX_ARCHIVE_BYTES+1);
        if($data===false)throw new RuntimeException('GitHub-Download fehlgeschlagen.');
        if(strlen($data)>WKS_MAX_ARCHIVE_BYTES)throw new RuntimeException('GitHub-Archiv überschreitet das Sicherheitslimit.');
        if(file_put_contents($target,$data,LOCK_EX)===false)throw new RuntimeException('Temporäre ZIP-Datei kann nicht geschrieben werden.');
    }

    $size=@filesize($target);
    if($size===false||$size<1000)throw new RuntimeException('Heruntergeladenes Archiv ist unerwartet klein.');
    if($size>WKS_MAX_ARCHIVE_BYTES)throw new RuntimeException('GitHub-Archiv überschreitet das Sicherheitslimit.');
}

function wks_safe_relative(string $entry): ?string
{
    $entry=str_replace('\\','/',$entry);
    $parts=explode('/',$entry,2);
    if(count($parts)<2)return null; // GitHub-Top-Level-Verzeichnis
    $relative=trim($parts[1],'/');
    if($relative==='')return null;
    if(str_contains($relative,"\0"))throw new RuntimeException('Ungültiger Dateiname im Archiv.');
    foreach(explode('/',$relative) as $segment){
        if($segment===''||$segment==='.'||$segment==='..')throw new RuntimeException('Unsicherer Pfad im GitHub-Archiv.');
    }
    return $relative;
}

function wks_extract(string $zipPath,string $stage): void
{
    $zip=new ZipArchive();
    $opened=$zip->open($zipPath);
    if($opened!==true)throw new RuntimeException('GitHub-Archiv konnte nicht geöffnet werden.');

    try{
        for($i=0;$i<$zip->numFiles;$i++){
            $name=(string)$zip->getNameIndex($i);
            $relative=wks_safe_relative($name);
            if($relative===null)continue;

            $opsys=0;$attr=0;
            if(method_exists($zip,'getExternalAttributesIndex')&&$zip->getExternalAttributesIndex($i,$opsys,$attr)){
                $mode=($attr>>16)&0xFFFF;
                if(($mode&0170000)===0120000)throw new RuntimeException('Symbolische Links sind im Installationsarchiv nicht erlaubt.');
            }

            $target=$stage.'/'.$relative;
            if(str_ends_with($name,'/')){
                if(!is_dir($target)&&!mkdir($target,0770,true)&&!is_dir($target))throw new RuntimeException('Verzeichnis kann nicht angelegt werden: '.$relative);
                continue;
            }

            $dir=dirname($target);
            if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Verzeichnis kann nicht angelegt werden: '.dirname($relative));
            $in=$zip->getStream($name);$out=fopen($target,'wb');
            if(!$in||!$out)throw new RuntimeException('Archivdatei kann nicht entpackt werden: '.$relative);
            stream_copy_to_stream($in,$out);fclose($in);fclose($out);@chmod($target,0640);
        }
    }finally{
        $zip->close();
    }

    foreach(['app','bootstrap','config','database','public','resources','routes'] as $required){
        if(!is_dir($stage.'/'.$required))throw new RuntimeException('GitHub-Archiv ist unvollständig: '.$required.' fehlt.');
    }
    if(!is_file($stage.'/VERSION'))throw new RuntimeException('GitHub-Archiv enthält keine VERSION-Datei.');
}

function wks_copy_tree(string $source,string $destination): void
{
    if(!is_dir($destination)&&!mkdir($destination,0770,true)&&!is_dir($destination))throw new RuntimeException('Zielverzeichnis kann nicht angelegt werden.');
    foreach(scandir($source)?:[] as $name){
        if($name==='.'||$name==='..')continue;
        $src=$source.'/'.$name;$dst=$destination.'/'.$name;
        if($dst===dirname(__DIR__).'/.env'&&is_file($dst))continue;
        if(is_link($src))throw new RuntimeException('Symbolische Links werden nicht installiert.');
        if(is_dir($src))wks_copy_tree($src,$dst);
        else{
            $dir=dirname($dst);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Zielverzeichnis kann nicht angelegt werden.');
            if(!copy($src,$dst))throw new RuntimeException('Datei konnte nicht installiert werden: '.str_replace(dirname(__DIR__).'/', '',$dst));
            @chmod($dst,0640);
        }
    }
}

function wks_remove_tree(string $path): void
{
    if(!is_dir($path)){@unlink($path);return;}
    foreach(scandir($path)?:[] as $name){
        if($name==='.'||$name==='..')continue;
        $child=$path.'/'.$name;
        if(is_dir($child)&&!is_link($child))wks_remove_tree($child);else @unlink($child);
    }
    @rmdir($path);
}

$error=null;$success=false;$checks=wks_checks($root);
$blocked=(bool)array_filter($checks,static fn(array $row):bool=>!$row[1]);

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        $provided=(string)($_POST['_token']??'');
        if($provided===''||!hash_equals($csrf,$provided))throw new RuntimeException('Sicherheitsprüfung abgelaufen. Seite bitte neu laden.');
        if($blocked)throw new RuntimeException('Die Servervoraussetzungen sind noch nicht erfüllt.');
        if(wks_existing_app($root))throw new RuntimeException('WKS-Dateien sind bereits vorhanden.');

        $token=bin2hex(random_bytes(8));
        $zipPath=$root.'/.wks-bootstrap-'.$token.'.zip';
        $stage=$root.'/.wks-bootstrap-'.$token;
        if(!mkdir($stage,0770,true)&&!is_dir($stage))throw new RuntimeException('Temporäres Installationsverzeichnis kann nicht angelegt werden.');

        try{
            wks_download(WKS_ARCHIVE_URL,$zipPath);
            wks_extract($zipPath,$stage);
            wks_copy_tree($stage,$root);
            foreach(['storage/uploads','storage/logs','storage/exports','storage/generated','storage/sessions'] as $relative){
                $path=$root.'/'.$relative;
                if(!is_dir($path)&&!mkdir($path,0770,true)&&!is_dir($path))throw new RuntimeException('Storage-Verzeichnis konnte nicht angelegt werden: '.$relative);
            }
            if(!wks_existing_app($root))throw new RuntimeException('WKS-Dateien konnten nach der Installation nicht verifiziert werden.');
        }finally{
            @unlink($zipPath);
            wks_remove_tree($stage);
        }

        $_SESSION=[];session_destroy();
        @unlink(__FILE__);
        header('Location: '.wks_install_url(),true,302);
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WKS Bootstrap-Installer</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#152638;background:#eef3f7}*{box-sizing:border-box}body{margin:0;padding:32px 18px}.card{max-width:860px;margin:auto;background:#fff;border:1px solid #d9e2ea;border-radius:20px;box-shadow:0 18px 55px rgba(19,43,63,.12);overflow:hidden}.head{padding:30px;background:#123f65;color:#fff}.head h1{margin:0 0 8px;font-size:30px}.head p{margin:0;opacity:.86}.body{padding:28px}.notice{padding:14px 16px;border-radius:12px;margin:0 0 18px}.notice.error{background:#fff0f0;color:#8a1d24;border:1px solid #f0c8cc}.notice.info{background:#edf6ff;color:#214f78;border:1px solid #c9def0}.checks{display:grid;gap:10px;margin:22px 0}.check{display:grid;grid-template-columns:26px 1fr;gap:10px;padding:12px;border:1px solid #e1e8ee;border-radius:12px}.ok{color:#197044}.bad{color:#a62931}.check strong{display:block}.check small{color:#66798a}.button{appearance:none;border:0;border-radius:11px;background:#16669a;color:#fff;padding:13px 18px;font:inherit;font-weight:700;cursor:pointer}.button:disabled{opacity:.45;cursor:not-allowed}code{background:#eef3f7;padding:2px 6px;border-radius:5px}.foot{margin-top:18px;color:#6a7b89;font-size:14px}@media(max-width:600px){body{padding:12px}.head,.body{padding:20px}}
</style>
</head>
<body>
<div class="card">
<div class="head"><h1>WKS Bootstrap-Installer</h1><p>Eine Datei → GitHub herunterladen → WKS-Websetup starten</p></div>
<div class="body">
<?php if($error):?><div class="notice error"><strong>Installation nicht möglich:</strong><br><?= htmlspecialchars($error,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></div><?php endif;?>
<div class="notice info">Diese Datei installiert den aktuellen öffentlichen <strong>main</strong>-Stand von <code>julian-obermeier/WKS</code>. Datenbankzugang und ersten Administrator geben Sie anschließend im normalen WKS-Installer ein.</div>
<div class="checks">
<?php foreach($checks as [$name,$ok,$detail]):?>
<div class="check"><div class="<?= $ok?'ok':'bad' ?>"><?= $ok?'●':'●' ?></div><div><strong><?= htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong><small><?= htmlspecialchars((string)$detail,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></small></div></div>
<?php endforeach;?>
</div>
<form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><button class="button" type="submit" <?= $blocked?'disabled':'' ?>>Aktuellen WKS-Stand herunterladen und installieren</button></form>
<div class="foot">Ziel: <code><?= htmlspecialchars($root,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></code><br>Nach erfolgreichem Download löscht sich der Bootstrap-Installer selbst und öffnet automatisch <code>/install</code>.</div>
</div>
</div>
</body>
</html>

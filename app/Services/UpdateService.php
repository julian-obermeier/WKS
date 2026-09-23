<?php
declare(strict_types=1);

namespace WKS\Services;

use RuntimeException;
use Throwable;
use ZipArchive;
use WKS\Core\Auth;
use WKS\Core\MigrationRunner;
use WKS\Repositories\SettingsRepository;
use WKS\Repositories\UpdateRepository;

final class UpdateService
{
    private const API='https://api.github.com/repos/julian-obermeier/WKS';
    private const RAW='https://raw.githubusercontent.com/julian-obermeier/WKS/main';

    public function check(): array
    {
        $commit=json_decode($this->http(self::API.'/commits/main'),true);
        if(!is_array($commit)||empty($commit['sha']))throw new RuntimeException('GitHub-Commitstand konnte nicht ermittelt werden.');
        $remoteVersion=trim($this->http(self::RAW.'/VERSION'));
        $localVersion=trim((string)@file_get_contents(BASE_PATH.'/VERSION'));
        $settings=new SettingsRepository();$installedCommit=trim((string)$settings->get('system.installed_commit',''));
        $update=$remoteVersion!==$localVersion||($installedCommit!==''&&$installedCommit!==(string)$commit['sha']);
        return [
            'remote_sha'=>(string)$commit['sha'],'remote_version'=>$remoteVersion,'local_version'=>$localVersion,
            'installed_commit'=>$installedCommit,'update_available'=>$update,
            'commit_date'=>$commit['commit']['committer']['date']??null,'commit_message'=>$commit['commit']['message']??null
        ];
    }

    public function install(int $userId): array
    {
        $remote=$this->check();$settings=new SettingsRepository();$updates=new UpdateRepository();
        $settings->set('system.maintenance_mode',true,'bool',$userId);
        $historyId=$updates->start($remote['remote_version'],$remote['remote_sha'],$userId);
        $tempBase=BASE_PATH.'/storage/generated/update-'.bin2hex(random_bytes(8));$zipPath=$tempBase.'.zip';$migrations=[];$changes=[];
        try{
            if(!class_exists(ZipArchive::class))throw new RuntimeException('ZipArchive ist für das Update-System erforderlich.');
            $this->download(self::API.'/zipball/main',$zipPath);
            if(!mkdir($tempBase,0770,true)&&!is_dir($tempBase))throw new RuntimeException('Temporäres Updateverzeichnis konnte nicht angelegt werden.');
            $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Updatearchiv konnte nicht geöffnet werden.');
            $zip->extractTo($tempBase);$zip->close();
            $items=array_values(array_filter(scandir($tempBase)?:[],static fn(string $v):bool=>$v!=='.'&&$v!=='..'));
            if(count($items)!==1||!is_dir($tempBase.'/'.$items[0]))throw new RuntimeException('Unerwartete Archivstruktur.');
            $source=$tempBase.'/'.$items[0];

            $this->mirrorDirectory($source.'/database/migrations',BASE_PATH.'/database/migrations',false);
            $runner=new MigrationRunner();$pending=array_map('basename',$runner->pending());$migrations=$runner->migrate();

            foreach(['app','bootstrap','config','resources','routes','bin'] as $dir){
                if(is_dir($source.'/'.$dir)){$this->mirrorDirectory($source.'/'.$dir,BASE_PATH.'/'.$dir,true);$changes[]=$dir;}
            }
            foreach(['database/migrations','database/seeders','public/assets'] as $dir){
                if(is_dir($source.'/'.$dir)){$this->mirrorDirectory($source.'/'.$dir,BASE_PATH.'/'.$dir,true);$changes[]=$dir;}
            }
            foreach(['public/index.php','public/manifest.webmanifest','public/service-worker.js','public/offline.html','VERSION','README.md','CHANGELOG.json'] as $file){
                if(is_file($source.'/'.$file)){$this->copyFile($source.'/'.$file,BASE_PATH.'/'.$file);$changes[]=$file;}
            }

            (new ReleaseNotesService())->syncLocal();
            $settings->set('system.installed_commit',$remote['remote_sha'],'string',$userId);
            $settings->set('system.installed_version',$remote['remote_version'],'string',$userId);

            $post=(new PostUpdateCheckService())->run();
            $status=$post['overall']==='error'?'partial':'successful';
            $updates->finish($historyId,$status,$migrations,$changes,['pending_before'=>$pending,'post_check'=>$post],$status==='partial'?'Post-Update-Check enthält Fehler.':null);
            if($status==='successful')$settings->set('system.maintenance_mode',false,'bool',$userId);
            (new AuditService())->log('system_update_installed','updates',(string)$historyId,null,['version'=>$remote['remote_version'],'sha'=>$remote['remote_sha'],'status'=>$status,'migrations'=>$migrations]);
            return ['status'=>$status,'version'=>$remote['remote_version'],'sha'=>$remote['remote_sha'],'migrations'=>$migrations,'post_check'=>$post];
        }catch(Throwable $e){
            $updates->finish($historyId,'failed',$migrations,$changes,[],$e->getMessage());
            (new AuditService())->log('system_update_failed','updates',(string)$historyId,null,['version'=>$remote['remote_version'],'error'=>$e->getMessage()]);
            throw $e;
        }finally{
            @unlink($zipPath);$this->removeDirectory($tempBase);
        }
    }

    private function http(string $url): string
    {
        if(function_exists('curl_init')){
            $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'WKS-Update-System/1.0',CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json']]);
            $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
            if($body===false||$code>=400)throw new RuntimeException('GitHub-Abfrage fehlgeschlagen: '.($error?:'HTTP '.$code));
            return (string)$body;
        }
        $ctx=stream_context_create(['http'=>['header'=>"User-Agent: WKS-Update-System/1.0\r\n",'timeout'=>30,'follow_location'=>1]]);
        $body=@file_get_contents($url,false,$ctx);if($body===false)throw new RuntimeException('GitHub-Abfrage fehlgeschlagen.');return $body;
    }

    private function download(string $url,string $target): void
    {
        if(!function_exists('curl_init')){file_put_contents($target,$this->http($url));return;}
        $fp=fopen($target,'wb');if(!$fp)throw new RuntimeException('Updatearchiv kann nicht geschrieben werden.');
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>120,CURLOPT_USERAGENT=>'WKS-Update-System/1.0',CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json']]);
        $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);fclose($fp);
        if(!$ok||$code>=400){@unlink($target);throw new RuntimeException('Update-Download fehlgeschlagen: '.($error?:'HTTP '.$code));}
    }

    private function mirrorDirectory(string $source,string $destination,bool $deleteExtra): void
    {
        if(!is_dir($source))return;if(!is_dir($destination)&&!mkdir($destination,0755,true)&&!is_dir($destination))throw new RuntimeException('Verzeichnis konnte nicht angelegt werden: '.$destination);@chmod($destination,0755);
        $sourceNames=[];
        foreach(scandir($source)?:[] as $name){if($name==='.'||$name==='..')continue;$sourceNames[]=$name;$src=$source.'/'.$name;$dst=$destination.'/'.$name;if(is_dir($src))$this->mirrorDirectory($src,$dst,$deleteExtra);else $this->copyFile($src,$dst);}
        if($deleteExtra)foreach(scandir($destination)?:[] as $name){if($name==='.'||$name==='..'||in_array($name,$sourceNames,true))continue;$path=$destination.'/'.$name;if(is_dir($path))$this->removeDirectory($path);else @unlink($path);}
    }

    private function copyFile(string $source,string $destination): void
    {
        $dir=dirname($destination);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Zielverzeichnis konnte nicht angelegt werden.');@chmod($dir,0755);
        if(!copy($source,$destination))throw new RuntimeException('Datei konnte nicht aktualisiert werden: '.str_replace(BASE_PATH.'/','',$destination));@chmod($destination,0644);
    }

    private function removeDirectory(string $dir): void
    {
        if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $name){if($name==='.'||$name==='..')continue;$path=$dir.'/'.$name;if(is_dir($path))$this->removeDirectory($path);else @unlink($path);}@rmdir($dir);
    }
}

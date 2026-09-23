<?php
declare(strict_types=1);

namespace WKS\Services;

use finfo;
use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\SettingsRepository;

final class UploadService
{
    private const DEFAULT_MIMES = [
        'image/jpeg'=>['jpg','jpeg'],
        'image/png'=>['png'],
        'application/pdf'=>['pdf'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>['docx'],
    ];

    public function storeMany(string $module,int $recordId,array $files,?int $maxBytes=null,array $descriptions=[]): array
    {
        $normalized=$this->normalize($files);
        $stored=[];

        foreach($normalized as $index=>$file){
            if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
            $description=trim((string)($descriptions[$index]??''));
            if(in_array($module,['special_report','house_bans'],true)&&$description===''){
                throw new HttpException(422,'Bitte für jede Anlage eine Beschreibung angeben.');
            }
            $stored[]=$this->store($module,$recordId,$file,$maxBytes,$description!==''?$description:null);
        }

        return $stored;
    }

    public function store(string $module,int $recordId,array $file,?int $maxBytes=null,?string $description=null): int
    {
        if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new HttpException(422,'Ein Anhang konnte nicht hochgeladen werden.');
        if(!isset($file['tmp_name'])||!is_uploaded_file((string)$file['tmp_name'])) throw new HttpException(422,'Ungültiger Datei-Upload.');
        $settings=new SettingsRepository();
        $maxBytes ??= max(1,(int)$settings->get('uploads.max_mb',10))*1024*1024;
        $size=(int)($file['size']??0);
        if($size<=0||$size>$maxBytes) throw new HttpException(422,'Ein Anhang ist leer oder überschreitet die maximal erlaubte Dateigröße.');

        $key=match($module){
            'dutybook'=>'uploads.dutybook_extensions',
            'special_report'=>'uploads.special_report_extensions',
            'valuables'=>'uploads.valuables_extensions',
            'house_bans'=>'uploads.house_bans_extensions',
            'messages'=>'uploads.messages_extensions',
            default=>null
        };
        $configured=$key?(array)$settings->get($key,[]):[];
        $configured=array_map('strtolower',array_map('strval',$configured));
        $allowed=[];
        foreach(self::DEFAULT_MIMES as $mime=>$extensions){
            $matching=array_values(array_intersect($extensions,$configured));
            if($matching!==[])$allowed[$mime]=$matching;
        }
        if($allowed===[])throw new HttpException(422,'Für dieses Modul sind derzeit keine Dateitypen freigegeben.');

        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=(string)$finfo->file((string)$file['tmp_name']);
        if(!isset($allowed[$mime])) throw new HttpException(422,'Dieser Dateityp ist für dieses Modul nicht freigegeben.');

        $original=basename((string)($file['name']??'datei'));
        $extension=strtolower((string)pathinfo($original,PATHINFO_EXTENSION));
        if(!in_array($extension,$allowed[$mime],true)) throw new HttpException(422,'Dateiendung und Dateityp stimmen nicht überein.');

        $subdir=$module.'/'.date('Y').'/'.date('m');
        $dir=BASE_PATH.'/storage/uploads/'.$subdir;
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new \RuntimeException('Uploadverzeichnis konnte nicht angelegt werden.');

        $storedName=bin2hex(random_bytes(24)).'.'.$extension;
        $relative=$subdir.'/'.$storedName;
        $target=BASE_PATH.'/storage/uploads/'.$relative;
        if(!move_uploaded_file((string)$file['tmp_name'],$target)) throw new \RuntimeException('Upload konnte nicht gespeichert werden.');
        @chmod($target,0640);

        $sha=hash_file('sha256',$target);
        $description=$description!==null?trim($description):null;
        if($description==='')$description=null;
        if($description!==null&&mb_strlen($description)>255)throw new HttpException(422,'Die Anlagenbeschreibung darf höchstens 255 Zeichen lang sein.');
        $stmt=Database::connection()->prepare(
            'INSERT INTO attachments (module,record_id,description,original_name,stored_name,mime_type,file_size,sha256,uploaded_by,uploaded_at)
             VALUES (:module,:record_id,:description,:original_name,:stored_name,:mime_type,:file_size,:sha256,:uploaded_by,NOW())'
        );
        $stmt->execute([
            'module'=>$module,'record_id'=>$recordId,'description'=>$description,'original_name'=>$original,'stored_name'=>$relative,
            'mime_type'=>$mime,'file_size'=>$size,'sha256'=>$sha,'uploaded_by'=>Auth::id()
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function find(int $attachmentId): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM attachments WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id'=>$attachmentId]);
        return $stmt->fetch() ?: null;
    }

    private function normalize(array $files): array
    {
        if(!isset($files['name'])) return [];
        if(!is_array($files['name'])) return [$files];
        $result=[];
        foreach(array_keys($files['name']) as $i){
            $result[]=[
                'name'=>$files['name'][$i]??'',
                'type'=>$files['type'][$i]??'',
                'tmp_name'=>$files['tmp_name'][$i]??'',
                'error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,
                'size'=>$files['size'][$i]??0,
            ];
        }
        return $result;
    }
}

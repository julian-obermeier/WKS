<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\TrashRepository;

final class TrashService
{
    private const MODULES=[
        'dutybook'=>['table'=>'dutybook_entries','location'=>'location_id','attachment'=>'dutybook'],
        'special_reports'=>['table'=>'special_reports','location'=>'location_id','attachment'=>'special_report'],
        'valuables'=>['table'=>'valuables_records','location'=>'location_id','attachment'=>'valuables'],
        'house_bans'=>['table'=>'house_bans','location'=>'location_id','attachment'=>'house_bans'],
    ];

    public function move(string $module,int $recordId,int $locationId,int $userId): void
    {
        $cfg=$this->config($module);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $row=$this->fetchRecord($cfg['table'],$recordId,$locationId,true);
            if(!$row)throw new HttpException(404,'Datensatz nicht gefunden.');
            if($module==='valuables'&&($row['status']??null)==='stored')throw new HttpException(422,'Ein aktuell eingelagerter Wertsachenvorgang kann nicht gelöscht werden. Zuerst muss fachlich korrekt ausgelagert werden.');
            $summary=$this->summary($module,$row);
            $sql='UPDATE '.$cfg['table'].' SET deleted_at=NOW(),updated_at=NOW()';
            if($module==='house_bans')$sql.=',deleted_by=:user_id';
            $sql.=' WHERE id=:id AND '.$cfg['location'].'=:location_id AND deleted_at IS NULL';
            $stmt=$pdo->prepare($sql);
            $params=['id'=>$recordId,'location_id'=>$locationId];
            if($module==='house_bans')$params['user_id']=$userId;
            $stmt->execute($params);
            (new TrashRepository())->add($module,$recordId,$locationId,$summary,$userId,['snapshot'=>$row]);
            $pdo->commit();
            (new AuditService())->log('trash_move','trash',(string)$recordId,$row,['module'=>$module,'deleted'=>true],[],null,$userId,$locationId);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function restore(int $trashId,int $userId): void
    {
        $trashRepo=new TrashRepository();$trash=$trashRepo->find($trashId);if(!$trash)throw new HttpException(404,'Papierkorbeintrag nicht gefunden.');
        $cfg=$this->config((string)$trash['module']);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $sql='UPDATE '.$cfg['table'].' SET deleted_at=NULL,updated_at=NOW()';
            if($trash['module']==='house_bans')$sql.=',deleted_by=NULL';
            $sql.=' WHERE id=:id';
            $pdo->prepare($sql)->execute(['id'=>$trash['record_id']]);
            $trashRepo->remove($trashId);$pdo->commit();
            (new AuditService())->log('trash_restore','trash',(string)$trash['record_id'],['deleted'=>true],['deleted'=>false],['module'=>$trash['module']],null,$userId,$trash['location_id']? (int)$trash['location_id']:null);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function hardDelete(int $trashId,int $userId): void
    {
        $trashRepo=new TrashRepository();$trash=$trashRepo->find($trashId);if(!$trash)throw new HttpException(404,'Papierkorbeintrag nicht gefunden.');
        $module=(string)$trash['module'];$cfg=$this->config($module);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $this->deletePhysicalFiles($cfg['attachment'],(int)$trash['record_id']);
            if($module==='special_reports')$this->deleteVersionFiles((int)$trash['record_id']);
            $pdo->prepare('DELETE FROM '.$cfg['table'].' WHERE id=:id')->execute(['id'=>$trash['record_id']]);
            $trashRepo->remove($trashId);$pdo->commit();
            (new AuditService())->log('trash_hard_delete','trash',(string)$trash['record_id'],['module'=>$module,'summary'=>$trash['summary']],['hard_deleted'=>true],[],null,$userId,$trash['location_id']? (int)$trash['location_id']:null);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function config(string $module): array
    {
        if(!isset(self::MODULES[$module]))throw new HttpException(422,'Dieses Modul unterstützt den zentralen Papierkorb nicht.');
        return self::MODULES[$module];
    }

    private function fetchRecord(string $table,int $id,int $locationId,bool $forUpdate=false): ?array
    {
        $sql='SELECT * FROM '.$table.' WHERE id=:id AND location_id=:location_id AND deleted_at IS NULL LIMIT 1'.($forUpdate?' FOR UPDATE':'');
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        return $stmt->fetch()?:null;
    }

    private function summary(string $module,array $row): string
    {
        return match($module){
            'dutybook'=>'Dienstbuch · '.mb_strimwidth((string)($row['facts']??''),0,180,'…'),
            'special_reports'=>'Sonderbericht '.str_pad((string)($row['report_number']??0),4,'0',STR_PAD_LEFT).'/'.($row['report_year']??'').' · '.mb_strimwidth((string)($row['facts']??''),0,140,'…'),
            'valuables'=>'Wertsache Verwahrnr. '.str_pad((string)($row['custody_number']??0),4,'0',STR_PAD_LEFT).' · '.trim(($row['first_name']??'').' '.($row['last_name']??'')),
            'house_bans'=>'Hausverbot · '.($row['person_name']??'').' · '.mb_strimwidth((string)($row['reason']??''),0,140,'…'),
            default=>$module.' #'.($row['id']??'')
        };
    }

    private function deletePhysicalFiles(string $attachmentModule,int $recordId): void
    {
        $stmt=Database::connection()->prepare('SELECT stored_name FROM attachments WHERE module=:module AND record_id=:id');
        $stmt->execute(['module'=>$attachmentModule,'id'=>$recordId]);$root=realpath(BASE_PATH.'/storage/uploads');
        foreach($stmt->fetchAll(\PDO::FETCH_COLUMN) as $relative){
            $path=realpath(BASE_PATH.'/storage/uploads/'.$relative);
            if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);
        }
        Database::connection()->prepare('DELETE FROM attachments WHERE module=:module AND record_id=:id')->execute(['module'=>$attachmentModule,'id'=>$recordId]);
    }

    private function deleteVersionFiles(int $reportId): void
    {
        $stmt=Database::connection()->prepare('SELECT pdf_path,docx_path FROM special_report_versions WHERE report_id=:id');
        $stmt->execute(['id'=>$reportId]);$root=realpath(BASE_PATH.'/storage/generated');
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)foreach(['pdf_path','docx_path'] as $key){
            if(!$row[$key])continue;$path=realpath(BASE_PATH.'/storage/generated/'.$row[$key]);
            if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);
        }
    }
}

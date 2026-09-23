<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\HouseBanRepository;

final class HouseBanService
{
    public function create(int $locationId,array $input,array $files=[]): int
    {
        $data=$this->validate($input);$userId=(int)Auth::id();$repo=new HouseBanRepository();
        $id=$repo->create($data+['location_id'=>$locationId,'created_by'=>$userId,'updated_by'=>$userId]);
        try{
            if(isset($files['attachments']))(new UploadService())->storeMany('house_bans',$id,$files['attachments']);
        }catch(\Throwable $e){
            $repo->hardDelete($id);throw $e;
        }
        (new AuditService())->log('house_ban_created','house_bans',(string)$id,null,$data,[],null,$userId,$locationId);
        return $id;
    }

    public function update(int $locationId,int $id,array $input,array $files=[]): void
    {
        $repo=new HouseBanRepository();$old=$repo->find($id,$locationId);if(!$old)throw new HttpException(404,'Hausverbot nicht gefunden.');
        $data=$this->validate($input);$userId=(int)Auth::id();$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $repo->update($id,$locationId,$data+['updated_by'=>$userId]);
            foreach(['ban_date','person_name','reason'] as $field)if((string)$old[$field]!== (string)$data[$field])$repo->addHistory($id,$field,$old[$field],$data[$field],$userId);
            $pdo->commit();
            if(isset($files['attachments']))(new UploadService())->storeMany('house_bans',$id,$files['attachments']);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        (new AuditService())->log('house_ban_updated','house_bans',(string)$id,['ban_date'=>$old['ban_date'],'person_name'=>$old['person_name'],'reason'=>$old['reason']],$data,[],null,$userId,$locationId);
    }

    public function delete(int $locationId,int $id): void
    {
        $repo=new HouseBanRepository();$record=$repo->find($id,$locationId);if(!$record)throw new HttpException(404,'Hausverbot nicht gefunden.');
        $repo->softDelete($id,$locationId,(int)Auth::id());
        (new AuditService())->log('house_ban_deleted','house_bans',(string)$id,['ban_date'=>$record['ban_date'],'person_name'=>$record['person_name'],'reason'=>$record['reason']],['deleted'=>true],[],null,Auth::id(),$locationId);
    }

    private function validate(array $input): array
    {
        $date=trim((string)($input['ban_date']??''));$name=trim((string)($input['person_name']??''));$reason=trim((string)($input['reason']??''));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new HttpException(422,'Ein gültiges Datum ist erforderlich.');
        if($name==='')throw new HttpException(422,'Der Name ist erforderlich.');
        if($reason==='')throw new HttpException(422,'Der Grund ist erforderlich.');
        return ['ban_date'=>$date,'person_name'=>$name,'reason'=>$reason];
    }
}

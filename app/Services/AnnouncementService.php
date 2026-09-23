<?php
declare(strict_types=1);

namespace WKS\Services;

use DateTimeImmutable;
use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Repositories\AnnouncementRepository;

final class AnnouncementService
{
    public function save(?int $id,array $input,array $files=[]): int
    {
        $title=trim((string)($input['title']??''));$body=trim((string)($input['body']??''));
        if($title===''||$body==='')throw new HttpException(422,'Titel und Text sind erforderlich.');
        $priority=(string)($input['priority']??'info');if(!in_array($priority,['info','important'],true))$priority='info';
        $status=(string)($input['status']??'draft');if(!in_array($status,['draft','scheduled','published','archived'],true))$status='draft';
        $from=$this->dateTime((string)($input['valid_from']??''));$until=$this->dateTime((string)($input['valid_until']??''));
        if($from&&$until&&strtotime($until)<strtotime($from))throw new HttpException(422,'Gültigkeitsende darf nicht vor dem Beginn liegen.');
        if($status==='scheduled'&&!$from)throw new HttpException(422,'Für eine geplante Mitteilung ist ein Gültigkeitsbeginn erforderlich.');
        $targets=$this->targets($input);if($targets===[])throw new HttpException(422,'Mindestens eine Zielgruppe ist erforderlich.');
        $publishedAt=$status==='published'?date('Y-m-d H:i:s'):null;$repo=new AnnouncementRepository();$userId=(int)Auth::id();
        $data=['title'=>$title,'body'=>$body,'priority'=>$priority,'status'=>$status,'valid_from'=>$from,'valid_until'=>$until,
            'require_ack'=>!empty($input['require_ack'])?1:0,'published_at'=>$publishedAt,'updated_by'=>$userId];

        if($id===null){
            $data['created_by']=$userId;$id=$repo->create($data,$targets);
            $action='announcement_created';
        }else{
            $old=$repo->find($id);if(!$old)throw new HttpException(404,'Mitteilung nicht gefunden.');
            if($status==='published'&&$old['published_at'])$data['published_at']=$old['published_at'];
            $publishedChanged=$old['status']==='published' && (
                $old['title']!==$title || $old['body']!==$body || $old['priority']!==$priority ||
                (int)$old['require_ack']!==(int)$data['require_ack']
            );
            $repo->update($id,$data,$targets,$publishedChanged);$action=$publishedChanged?'announcement_published_updated':'announcement_updated';
        }

        if(isset($files['attachments']))(new UploadService())->storeMany('messages',$id,$files['attachments']);
        (new AuditService())->log($action,'messages',(string)$id,null,['title'=>$title,'status'=>$status,'targets'=>$targets],[],null,$userId,active_location_id());
        return $id;
    }

    public function markReadVisible(int $id,int $userId,int $locationId,string $roleCode,bool $confirm=false): void
    {
        $repo=new AnnouncementRepository();$a=$repo->findVisible($id,$userId,$locationId,$roleCode);if(!$a)throw new HttpException(404,'Mitteilung nicht gefunden.');
        if($confirm&&!$a['require_ack'])$confirm=false;
        $repo->markRead($id,$userId,(int)$a['revision'],$confirm);
    }

    private function targets(array $input): array
    {
        if(!empty($input['target_all']))return [['type'=>'all','value'=>'*']];
        $targets=[];
        foreach(array_unique(array_map('intval',(array)($input['target_locations']??[]))) as $id)if($id>0)$targets[]=['type'=>'location','value'=>(string)$id];
        foreach(array_unique(array_map('strval',(array)($input['target_roles']??[]))) as $role)if(in_array($role,['employee','management','admin'],true))$targets[]=['type'=>'role','value'=>$role];
        return $targets;
    }

    private function dateTime(string $v): ?string
    {
        $v=trim($v);if($v==='')return null;$v=str_replace('T',' ',$v);
        $d=DateTimeImmutable::createFromFormat('Y-m-d H:i',$v)?:DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$v);
        if(!$d)throw new HttpException(422,'Ungültiges Datum/Uhrzeit.');return $d->format('Y-m-d H:i:s');
    }
}

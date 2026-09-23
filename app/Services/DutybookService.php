<?php
declare(strict_types=1);

namespace WKS\Services;

use DateTimeImmutable;
use PDO;
use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SettingsRepository;

final class DutybookService
{
    public function create(int $locationId,array $input,array $files=[]): int
    {
        $repo=new DutybookRepository();
        $userId=(int)Auth::id();
        $session=$repo->currentOpenSessionForUser($locationId,$userId);
        $shiftId=(int)($input['shift_id']??($session['shift_id']??0));
        $master=new MasterDataRepository();
        $shift=$shiftId>0?$master->shift($shiftId,$locationId):null;
        if(!$shift) throw new HttpException(422,'Bitte wählen Sie eine gültige Schicht.');

        $eventTypeId=(int)($input['event_type_id']??0);
        $eventType=$master->eventType($eventTypeId,$locationId);
        if(!$eventType) throw new HttpException(422,'Bitte wählen Sie eine gültige Ereignisart.');

        $categoryId=(int)$eventType['category_id'];
        $status=$this->validateStatus((string)($input['status']??'open'));
        $occurredAt=$this->normalizeDateTime((string)($input['occurred_at']??date('Y-m-d H:i:s')),true);
        $facts=trim((string)($input['facts']??''));
        if($facts==='') throw new HttpException(422,'Der Sachverhalt ist erforderlich.');

        $dutyDate=$session ? (string)$session['duty_date'] : (new ShiftService())->dutyDateForShift($shift,new DateTimeImmutable($occurredAt));
        $dayId=$repo->ensureDay($locationId,$dutyDate);
        $dynamic=(new DynamicFormService())->validateDutybookValues($eventTypeId,(array)($input['dynamic']??[]));
        if($dynamic['errors']!==[]) throw new HttpException(422,implode(' ',$dynamic['errors']));

        $editMinutes=(int)(new SettingsRepository())->get('dutybook.edit_window_minutes',120);
        $locked=(new DateTimeImmutable())->modify('+'.max(0,$editMinutes).' minutes')->format('Y-m-d H:i:s');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $entryId=$repo->createEntry([
                'location_id'=>$locationId,'dutybook_day_id'=>$dayId,'duty_date'=>$dutyDate,
                'shift_session_id'=>$session['id']??null,'shift_id'=>$shiftId,'category_id'=>$categoryId,'event_type_id'=>$eventTypeId,
                'status'=>$status,'occurred_at'=>$occurredAt,'event_started_at'=>$this->normalizeDateTime((string)($input['event_started_at']??'')),
                'event_ended_at'=>$this->normalizeDateTime((string)($input['event_ended_at']??'')),
                'place_id'=>($id=(int)($input['place_id']??0))>0?$id:null,'place_free_text'=>$this->nullable($input['place_free_text']??null),
                'facts'=>$facts,'measures_text'=>$this->nullable($input['measures_text']??null),'result_text'=>$this->nullable($input['result_text']??null),
                'is_automatic'=>0,'automatic_type'=>null,'edit_locked_at'=>$locked,'created_by'=>$userId,'updated_by'=>$userId
            ]);
            $repo->syncStaff($entryId,(array)($input['staff_ids']??[]));
            $repo->replacePeople($entryId,$this->people((array)($input['people']??[])));
            $repo->syncMeasures($entryId,(array)($input['measure_ids']??[]));
            $repo->replaceExternal($entryId,$this->external((array)($input['external']??[])));
            $repo->saveDynamicValues($entryId,$dynamic['values']);
            $pdo->commit();

            if(isset($files['attachments'])) (new UploadService())->storeMany('dutybook',$entryId,$files['attachments']);
            (new AuditService())->log('dutybook_entry_created','dutybook',(string)$entryId,null,['status'=>$status,'event_type_id'=>$eventTypeId],[],null,$userId,$locationId);
            return $entryId;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $locationId,int $entryId,array $input,array $files=[]): void
    {
        $repo=new DutybookRepository();
        $entry=$repo->findEntry($entryId,$locationId);
        if(!$entry) throw new HttpException(404,'Dienstbucheintrag nicht gefunden.');
        if((bool)$entry['is_automatic']) throw new HttpException(422,'Automatische Einträge können nicht direkt bearbeitet werden.');
        if($this->isLocked($entry)) throw new HttpException(423,'Die Bearbeitungsfrist ist abgelaufen. Verwenden Sie einen dokumentierten Nachtrag.');

        $master=new MasterDataRepository();
        $eventTypeId=(int)($input['event_type_id']??$entry['event_type_id']);
        $eventType=$master->eventType($eventTypeId,$locationId);
        if(!$eventType) throw new HttpException(422,'Ungültige Ereignisart.');
        $facts=trim((string)($input['facts']??''));
        if($facts==='') throw new HttpException(422,'Der Sachverhalt ist erforderlich.');

        $dynamic=(new DynamicFormService())->validateDutybookValues($eventTypeId,(array)($input['dynamic']??[]));
        if($dynamic['errors']!==[]) throw new HttpException(422,implode(' ',$dynamic['errors']));

        $before=$this->snapshot($entry);
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $repo->updateEntry($entryId,$locationId,[
                'category_id'=>(int)$eventType['category_id'],'event_type_id'=>$eventTypeId,'status'=>$this->validateStatus((string)($input['status']??$entry['status'])),
                'occurred_at'=>$this->normalizeDateTime((string)($input['occurred_at']??$entry['occurred_at']),true),
                'event_started_at'=>$this->normalizeDateTime((string)($input['event_started_at']??'')),
                'event_ended_at'=>$this->normalizeDateTime((string)($input['event_ended_at']??'')),
                'place_id'=>($id=(int)($input['place_id']??0))>0?$id:null,'place_free_text'=>$this->nullable($input['place_free_text']??null),
                'facts'=>$facts,'measures_text'=>$this->nullable($input['measures_text']??null),'result_text'=>$this->nullable($input['result_text']??null),
                'updated_by'=>Auth::id()
            ]);
            $repo->syncStaff($entryId,(array)($input['staff_ids']??[]));
            $repo->replacePeople($entryId,$this->people((array)($input['people']??[])));
            $repo->syncMeasures($entryId,(array)($input['measure_ids']??[]));
            $repo->replaceExternal($entryId,$this->external((array)($input['external']??[])));
            $repo->saveDynamicValues($entryId,$dynamic['values']);
            $pdo->commit();
            if(isset($files['attachments'])) (new UploadService())->storeMany('dutybook',$entryId,$files['attachments']);
            $after=$repo->findEntry($entryId,$locationId);
            (new AuditService())->log('dutybook_entry_updated','dutybook',(string)$entryId,$before,$after?$this->snapshot($after):null,[],null,Auth::id(),$locationId);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function addendum(int $locationId,int $entryId,string $reason,array $input): void
    {
        $repo=new DutybookRepository();
        $entry=$repo->findEntry($entryId,$locationId);
        if(!$entry) throw new HttpException(404,'Dienstbucheintrag nicht gefunden.');
        $reason=trim($reason);
        if($reason==='') throw new HttpException(422,'Ein Änderungsgrund ist erforderlich.');

        $new=[
            'status'=>$this->validateStatus((string)($input['status']??$entry['status'])),
            'facts'=>trim((string)($input['facts']??$entry['facts'])),
            'measures_text'=>$this->nullable($input['measures_text']??$entry['measures_text']),
            'result_text'=>$this->nullable($input['result_text']??$entry['result_text'])
        ];
        if($new['facts']==='') throw new HttpException(422,'Der Sachverhalt darf nicht leer sein.');

        $old=['status'=>$entry['status'],'facts'=>$entry['facts'],'measures_text'=>$entry['measures_text'],'result_text'=>$entry['result_text']];
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $repo->updateEntry($entryId,$locationId,[
                'category_id'=>$entry['category_id'],'event_type_id'=>$entry['event_type_id'],'status'=>$new['status'],
                'occurred_at'=>$entry['occurred_at'],'event_started_at'=>$entry['event_started_at'],'event_ended_at'=>$entry['event_ended_at'],
                'place_id'=>$entry['place_id'],'place_free_text'=>$entry['place_free_text'],'facts'=>$new['facts'],
                'measures_text'=>$new['measures_text'],'result_text'=>$new['result_text'],'updated_by'=>Auth::id()
            ]);
            $repo->addAddendum($entryId,$reason,$old,$new,(int)Auth::id());
            $pdo->commit();
            (new AuditService())->log('dutybook_addendum_created','dutybook',(string)$entryId,$old,$new,['reason'=>$reason],null,Auth::id(),$locationId);
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public function isLocked(array $entry): bool
    {
        return !empty($entry['edit_locked_at']) && strtotime((string)$entry['edit_locked_at']) <= time();
    }

    private function validateStatus(string $status): string
    {
        if(!in_array($status,['open','in_progress','done','handover'],true)) throw new HttpException(422,'Ungültiger Vorgangsstatus.');
        return $status;
    }

    private function normalizeDateTime(string $value,bool $required=false): ?string
    {
        $value=trim($value);
        if($value===''){if($required)throw new HttpException(422,'Datum/Uhrzeit ist erforderlich.');return null;}
        $value=str_replace('T',' ',$value);
        $dt=DateTimeImmutable::createFromFormat('Y-m-d H:i',$value) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value);
        if(!$dt) throw new HttpException(422,'Ungültiges Datum/Uhrzeit.');
        return $dt->format('Y-m-d H:i:s');
    }

    private function nullable(mixed $value): ?string
    {
        $value=trim((string)($value??''));
        return $value===''?null:$value;
    }

    private function people(array $rows): array
    {
        $clean=[];
        foreach($rows as $row){
            if(!is_array($row))continue;
            $person=[
                'role_id'=>(int)($row['role_id']??0),'person_type'=>trim((string)($row['person_type']??'')),
                'first_name'=>trim((string)($row['first_name']??'')),'last_name'=>trim((string)($row['last_name']??'')),
                'birth_date'=>trim((string)($row['birth_date']??'')),'area'=>trim((string)($row['area']??'')),
                'internal_identifier'=>trim((string)($row['internal_identifier']??''))
            ];
            if(array_filter($person,static fn($v)=>$v!==''&&$v!==0))$clean[]=$person;
        }
        return $clean;
    }

    private function external(array $rows): array
    {
        $clean=[];
        foreach($rows as $row){
            if(!is_array($row))continue;
            $item=[
                'organization_id'=>(int)($row['organization_id']??0),'organization_name'=>trim((string)($row['organization_name']??'')),
                'contact_name'=>trim((string)($row['contact_name']??'')),
                'notified_at'=>$this->normalizeDateTime((string)($row['notified_at']??'')),
                'feedback'=>trim((string)($row['feedback']??'')),'reference_number'=>trim((string)($row['reference_number']??''))
            ];
            if(array_filter($item,static fn($v)=>$v!==''&&$v!==null&&$v!==0))$clean[]=$item;
        }
        return $clean;
    }

    private function snapshot(array $entry): array
    {
        unset($entry['attachments'],$entry['addenda']);
        return $entry;
    }
}

<?php
declare(strict_types=1);

namespace WKS\Services;

use DateTimeImmutable;
use PDO;
use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\DutybookAutomaticRuleRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SettingsRepository;
use WKS\Repositories\SpecialReportRepository;

final class SpecialReportService
{
    public function create(int $locationId,array $input,array $files=[]): int
    {
        $repo=new SpecialReportRepository();$typeId=(int)($input['report_type_id']??0);
        $type=$repo->type($typeId,$locationId);if(!$type)throw new HttpException(422,'Ungültige Einsatzart.');
        $core=$this->validatedCore($input,null);
        $dynamic=(new DynamicFormService())->validateSpecialReportValues($typeId,(array)($input['dynamic']??[]));
        if($dynamic['errors']!==[])throw new HttpException(422,implode(' ',$dynamic['errors']));
        $year=(int)substr($core['incident_date'],0,4);
        $userId=(int)Auth::id();$editMinutes=(int)(new SettingsRepository())->get('special_reports.edit_window_minutes',120);
        $status=in_array((string)($input['status']??'draft'),['draft','in_progress'],true)?(string)$input['status']:'draft';
        $sourceId=(int)($input['source_dutybook_entry_id']??0);
        if($sourceId>0 && !(new DutybookRepository())->findEntry($sourceId,$locationId))throw new HttpException(422,'Der verknüpfte Dienstbucheintrag ist ungültig.');

        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $number=$repo->allocateNumber($year);
            $id=$repo->create([
                'location_id'=>$locationId,'report_type_id'=>$typeId,'report_year'=>$year,'report_number'=>$number,
                'incident_date'=>$core['incident_date'],'incident_started_at'=>$core['incident_started_at'],'incident_ended_at'=>$core['incident_ended_at'],
                'place_id'=>$core['place_id'],'place_free_text'=>$core['place_free_text'],'facts'=>$core['facts'],
                'measures_text'=>$core['measures_text'],'result_text'=>$core['result_text'],'status'=>$status,
                'source_dutybook_entry_id'=>$sourceId>0?$sourceId:null,
                'edit_locked_at'=>(new DateTimeImmutable())->modify('+'.max(0,$editMinutes).' minutes')->format('Y-m-d H:i:s'),
                'created_by'=>$userId,'updated_by'=>$userId
            ]);
            $witnessIds=$this->replaceRelations($repo,$id,$input,$dynamic['values'],(bool)$type['force_section_enabled'],(array)($type['force_requirements']??[]));
            $pdo->commit();

            if(isset($files['attachments']))(new UploadService())->storeMany('special_report',$id,$files['attachments'],null,(array)($input['attachment_descriptions']??[]));
            $this->storeWitnessAttachments($id,$witnessIds,$files['witness_attachments']??[]);
            $dutybookId=$this->createDutybookLink($locationId,$id,$sourceId,$type['name'],$year,$number,$core['incident_started_at']);
            $this->link($sourceId>0?$sourceId:$dutybookId,'dutybook',$id,'special_report','special_report',(int)Auth::id());
            (new AuditService())->log('special_report_created','special_reports',(string)$id,null,['year'=>$year,'number'=>$number,'type'=>$type['name'],'status'=>$status],[] ,null,$userId,$locationId);
            return $id;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function update(int $locationId,int $id,array $input,array $files=[]): void
    {
        $repo=new SpecialReportRepository();$report=$repo->find($id,$locationId);
        if(!$report)throw new HttpException(404,'Sonderbericht nicht gefunden.');
        $this->assertEditable($report);
        $typeId=(int)($input['report_type_id']??$report['report_type_id']);$type=$repo->type($typeId,$locationId);
        if(!$type)throw new HttpException(422,'Ungültige Einsatzart.');
        $core=$this->validatedCore($input,$report);
        if((int)substr($core['incident_date'],0,4)!==(int)$report['report_year'])throw new HttpException(422,'Das Kalenderjahr eines bereits nummerierten Sonderberichts kann nicht geändert werden.');
        $dynamic=(new DynamicFormService())->validateSpecialReportValues($typeId,(array)($input['dynamic']??[]));
        if($dynamic['errors']!==[])throw new HttpException(422,implode(' ',$dynamic['errors']));

        $before=$this->snapshot($report);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $status=$report['status']==='revision_required'?'revision_required':(in_array((string)($input['status']??$report['status']),['draft','in_progress'],true)?(string)$input['status']:$report['status']);
            $repo->update($id,$locationId,[
                'report_type_id'=>$typeId,'incident_date'=>$core['incident_date'],'incident_started_at'=>$core['incident_started_at'],
                'incident_ended_at'=>$core['incident_ended_at'],'place_id'=>$core['place_id'],'place_free_text'=>$core['place_free_text'],
                'facts'=>$core['facts'],'measures_text'=>$core['measures_text'],'result_text'=>$core['result_text'],'status'=>$status,'updated_by'=>Auth::id()
            ]);
            $witnessIds=$this->replaceRelations($repo,$id,$input,$dynamic['values'],(bool)$type['force_section_enabled'],(array)($type['force_requirements']??[]));
            $pdo->commit();
            if(isset($files['attachments']))(new UploadService())->storeMany('special_report',$id,$files['attachments'],null,(array)($input['attachment_descriptions']??[]));
            $this->storeWitnessAttachments($id,$witnessIds,$files['witness_attachments']??[]);
            $after=$repo->find($id,$locationId);
            (new AuditService())->log('special_report_updated','special_reports',(string)$id,$before,$after?$this->snapshot($after):null,[],null,Auth::id(),$locationId);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function complete(int $locationId,int $id): void
    {
        $repo=new SpecialReportRepository();$r=$repo->find($id,$locationId);
        if(!$r)throw new HttpException(404,'Sonderbericht nicht gefunden.');
        if((int)$r['created_by']!==(int)Auth::id()&&!Authorization::can('special_reports.review'))throw new HttpException(403,'Nur der Ersteller kann diesen Bericht abschließen.');
        if(!in_array($r['status'],['draft','in_progress','revision_required'],true))throw new HttpException(422,'Dieser Bericht kann in seinem aktuellen Status nicht abgeschlossen werden.');
        if($repo->openRevisionCount($id)>0)throw new HttpException(422,'Alle Nachforderungen müssen zuerst als erledigt markiert werden.');

        Database::connection()->prepare(
            'UPDATE special_reports SET status="completed",author_confirmed_at=NOW(),completed_by=:completed_by,completed_at=NOW(),
             edit_locked_at=NOW(),updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND location_id=:location_id'
        )->execute(['completed_by'=>Auth::id(),'updated_by'=>Auth::id(),'id'=>$id,'location_id'=>$locationId]);
        (new AuditService())->log('special_report_completed','special_reports',(string)$id,['status'=>$r['status']],['status'=>'completed'],[],null,Auth::id(),$locationId);
        (new NotificationService())->notifyRole('special_report_completed',$locationId,'management','Sonderbericht zur Prüfung','Sonderbericht '.$this->displayNumber($r).' wurde abgeschlossen und wartet auf Leitungsprüfung.',url('special-reports/'.$id),'important');
    }

    public function requestRevision(int $locationId,int $id,array $texts,string $note=''): void
    {
        $repo=new SpecialReportRepository();$r=$repo->find($id,$locationId);
        if(!$r||$r['status']!=='completed')throw new HttpException(422,'Nur abgeschlossene, noch nicht geprüfte Berichte können zur Nachbearbeitung zurückgegeben werden.');
        $texts=array_values(array_filter(array_map('trim',$texts)));
        if($texts===[])throw new HttpException(422,'Mindestens eine konkrete Nachforderung ist erforderlich.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            foreach($texts as $text)$repo->addRevisionRequest($id,$text,(int)Auth::id());
            $pdo->prepare(
                'UPDATE special_reports SET status="revision_required",review_note=:note,updated_at=NOW(),updated_by=:user_id WHERE id=:id'
            )->execute(['note'=>trim($note)?:null,'user_id'=>Auth::id(),'id'=>$id]);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
        (new AuditService())->log('special_report_revision_requested','special_reports',(string)$id,['status'=>'completed'],['status'=>'revision_required','requests'=>$texts],[],null,Auth::id(),$locationId);
        (new NotificationService())->notifyUser((int)$r['created_by'],'special_report_revision_required',$locationId,'Nachbearbeitung erforderlich','Für Sonderbericht '.$this->displayNumber($r).' wurden Nachforderungen erstellt.',url('special-reports/'.$id),'important');
    }

    public function completeRevisionRequest(int $locationId,int $id,int $requestId): void
    {
        $repo=new SpecialReportRepository();$r=$repo->find($id,$locationId);
        if(!$r)throw new HttpException(404,'Sonderbericht nicht gefunden.');
        if((int)$r['created_by']!==(int)Auth::id())throw new HttpException(403,'Nur der Ersteller kann Nachforderungen als erledigt markieren.');
        $repo->completeRevisionRequest($id,$requestId,(int)Auth::id());
        (new AuditService())->log('special_report_revision_item_done','special_reports',(string)$id,null,['request_id'=>$requestId],[],null,Auth::id(),$locationId);
    }

    public function approve(int $locationId,int $id,string $note=''): void
    {
        $repo=new SpecialReportRepository();$r=$repo->find($id,$locationId);
        if(!$r||$r['status']!=='completed')throw new HttpException(422,'Nur ein abgeschlossener Bericht kann geprüft werden.');
        if($repo->openRevisionCount($id)>0)throw new HttpException(422,'Es existieren noch offene Nachforderungen.');

        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $pdo->prepare(
                'UPDATE special_reports SET status="reviewed",reviewed_by=:reviewed_by,reviewed_at=NOW(),review_note=:note,
                 edit_locked_at=NOW(),updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND location_id=:location_id'
            )->execute(['reviewed_by'=>Auth::id(),'updated_by'=>Auth::id(),'note'=>trim($note)?:null,'id'=>$id,'location_id'=>$locationId]);
            $fresh=$repo->find($id,$locationId)??$r;$version=max(1,(int)$fresh['current_version']+1);
            [$pdfPath,$docxPath]=$this->persistVersionDocuments($fresh,$version);
            $repo->saveVersion($id,$version,$this->snapshot($fresh),(int)Auth::id(),$pdfPath,$docxPath);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        (new AuditService())->log('special_report_reviewed','special_reports',(string)$id,['status'=>'completed'],['status'=>'reviewed'],[],null,Auth::id(),$locationId);
    }

    public function addendum(int $locationId,int $id,string $reason,array $changes): void
    {
        $repo=new SpecialReportRepository();$r=$repo->find($id,$locationId);
        if(!$r||$r['status']!=='reviewed')throw new HttpException(422,'Nachträge sind erst nach abgeschlossener Leitungsprüfung möglich.');
        if((int)$r['created_by']!==(int)Auth::id()&&!Authorization::can('special_reports.review'))throw new HttpException(403,'Keine Berechtigung für diesen Nachtrag.');
        $reason=trim($reason);if($reason==='')throw new HttpException(422,'Ein Änderungsgrund ist erforderlich.');
        $new=[
            'facts'=>trim((string)($changes['facts']??$r['facts'])),
            'measures_text'=>$this->nullable($changes['measures_text']??$r['measures_text']),
            'result_text'=>$this->nullable($changes['result_text']??$r['result_text'])
        ];
        if($new['facts']==='')throw new HttpException(422,'Der Sachverhalt darf nicht leer sein.');
        $old=['facts'=>$r['facts'],'measures_text'=>$r['measures_text'],'result_text'=>$r['result_text']];
        $version=(int)$r['current_version']+1;$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $pdo->prepare(
                'UPDATE special_reports SET facts=:facts,measures_text=:measures_text,result_text=:result_text,updated_at=NOW(),updated_by=:user_id WHERE id=:id'
            )->execute($new+['user_id'=>Auth::id(),'id'=>$id]);
            $pdo->prepare(
                'INSERT INTO special_report_addenda (report_id,reason,changes_json,version_number,created_by,created_at)
                 VALUES (:report_id,:reason,:changes,:version,:user_id,NOW())'
            )->execute(['report_id'=>$id,'reason'=>$reason,'changes'=>json_encode(['old'=>$old,'new'=>$new],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'version'=>$version,'user_id'=>Auth::id()]);
            $fresh=$repo->find($id,$locationId)??$r;[$pdfPath,$docxPath]=$this->persistVersionDocuments($fresh,$version);
            $repo->saveVersion($id,$version,$this->snapshot($fresh),(int)Auth::id(),$pdfPath,$docxPath);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
        (new AuditService())->log('special_report_addendum_created','special_reports',(string)$id,$old,$new,['reason'=>$reason,'version'=>$version],null,Auth::id(),$locationId);
    }

    public function exportSections(array $r): array
    {
        $people=array_map(static fn(array $p):string=>trim(($p['role_name']??$p['person_type']??'Person').': '.($p['first_name']??'').' '.($p['last_name']??'')),(array)$r['people']);
        $staff=array_map(static fn(array $u):string=>$u['first_name'].' '.$u['last_name'],(array)$r['staff']);
        $external=array_map(static fn(array $x):string=>trim(($x['organization_master_name']??$x['organization_name']??'').'; '.($x['contact_name']??'').'; '.($x['reference_number']??'')),(array)$r['external']);
        $dynamic=[];foreach((array)$r['dynamic_values'] as $v){$section=trim((string)($v['section_name']??''))?:'Zusatzangaben';$dynamic[$section][]=$v['label'].': '.(is_array($v['value'])?implode(', ',$v['value']):(is_bool($v['value'])?($v['value']?'Ja':'Nein'):(string)$v['value']));}
        $sections=[
            'Bericht'=>sprintf('SB %04d/%d · %s',(int)$r['report_number'],(int)$r['report_year'],$r['report_type_name']),
            'Einsatz'=>sprintf('%s bis %s · %s',format_datetime($r['incident_started_at']),format_datetime($r['incident_ended_at']),trim(($r['place_name']??'').' '.($r['place_free_text']??''))),
            'Beteiligte Personen'=>$people,'Beteiligte Mitarbeiter'=>$staff,'Sachverhalt'=>$r['facts'],
            'Maßnahmen'=>$r['measures_text']??'','Ergebnis'=>$r['result_text']??'','Externe Stellen'=>$external
        ];
        foreach($dynamic as $section=>$rows)$sections[$section]=$rows;
        if($r['injury'])$sections['Verletzungen / medizinische Maßnahmen']=[
            'Verletzung vorhanden: '.($r['injury']['injury_present']?'Ja':'Nein'),
            'Beschreibung: '.($r['injury']['description']??''),'Versorgung: '.($r['injury']['medical_care']??''),
            'Behandelnde Stelle: '.($r['injury']['treating_entity']??'')
        ];
        if($r['force_actions'])$sections['Zwangsmaßnahmen']=array_map(static fn(array $x):string=>$x['action_type'].' · '.($x['justification']??'').' · '.($x['result_text']??''),$r['force_actions']);
        $sections['Digitale Bestätigungen']=[
            'Ersteller: '.($r['creator_name']??'').' · '.format_datetime($r['author_confirmed_at']),
            'Leitungsprüfung: '.($r['reviewer_name']??'–').' · '.format_datetime($r['reviewed_at'])
        ];
        return $sections;
    }

    public function filename(array $r,string $extension): string
    {
        $person=$r['people'][0]??null;
        $name='Ohne_Person';
        if(is_array($person)){
            $last=(string)($person['last_name']??'');
            $first=(string)($person['first_name']??'');
            $candidate=trim($last.'_'.$first,'_ ');
            if($candidate!=='')$name=$candidate;
        }
        $name=$this->filePart($name);$type=$this->filePart((string)$r['report_type_name']);
        return date('Y.m.d',strtotime((string)$r['incident_date'])).'_SB_'.str_pad((string)$r['report_number'],4,'0',STR_PAD_LEFT).'_'.$type.'_'.$name.'.'.$extension;
    }

    private function assertEditable(array $r): void
    {
        if((int)$r['created_by']!==(int)Auth::id()&&!Authorization::can('special_reports.review'))throw new HttpException(403,'Keine Bearbeitungsberechtigung.');
        if(in_array($r['status'],['completed','reviewed'],true))throw new HttpException(423,'Der Bericht ist abgeschlossen und gesperrt.');
        if($r['status']!=='revision_required'&&!empty($r['edit_locked_at'])&&strtotime((string)$r['edit_locked_at'])<=time())throw new HttpException(423,'Die Bearbeitungsfrist ist abgelaufen.');
    }

    private function validatedCore(array $input,?array $existing): array
    {
        $date=trim((string)($input['incident_date']??$existing['incident_date']??date('Y-m-d')));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new HttpException(422,'Ungültiges Einsatzdatum.');
        $start=$this->dateTime((string)($input['incident_started_at']??$existing['incident_started_at']??''),true);
        $end=$this->dateTime((string)($input['incident_ended_at']??$existing['incident_ended_at']??''));
        if($end&&$start&&strtotime($end)<strtotime($start))throw new HttpException(422,'Das Einsatzende darf nicht vor dem Beginn liegen.');
        $facts=trim((string)($input['facts']??$existing['facts']??''));if($facts==='')throw new HttpException(422,'Der Sachverhalt ist erforderlich.');
        return [
            'incident_date'=>$date,'incident_started_at'=>$start,'incident_ended_at'=>$end,
            'place_id'=>($p=(int)($input['place_id']??$existing['place_id']??0))>0?$p:null,
            'place_free_text'=>$this->nullable($input['place_free_text']??$existing['place_free_text']??null),
            'facts'=>$facts,'measures_text'=>$this->nullable($input['measures_text']??$existing['measures_text']??null),
            'result_text'=>$this->nullable($input['result_text']??$existing['result_text']??null)
        ];
    }

    private function replaceRelations(SpecialReportRepository $repo,int $id,array $input,array $dynamic,bool $forceEnabled,array $forceRequirements=[]): array
    {
        $repo->syncStaff($id,(array)($input['staff_ids']??[]));$repo->replacePeople($id,$this->people((array)($input['people']??[])));
        $witnessIds=$repo->replaceWitnesses($id,$this->witnesses((array)($input['witnesses']??[])));$repo->replaceExternal($id,$this->external((array)($input['external']??[])));
        $inj=(array)($input['injury']??[]);$repo->saveInjury($id,[
            'injury_present'=>!empty($inj['injury_present'])?1:0,'description'=>$this->nullable($inj['description']??null),
            'medical_care'=>$this->nullable($inj['medical_care']??null),'treating_entity'=>$this->nullable($inj['treating_entity']??null),
            'treated_at'=>$this->dateTime((string)($inj['treated_at']??''))
        ]);
        $repo->replaceForceActions($id,$forceEnabled?$this->forceActions((array)($input['force_actions']??[]),$forceRequirements):[]);
        $repo->saveDynamicValues($id,$dynamic);
        return $witnessIds;
    }

    private function people(array $rows): array
    {
        $out=[];foreach($rows as $x){if(!is_array($x))continue;$row=[
            'role_id'=>($r=(int)($x['role_id']??0))>0?$r:null,'person_type'=>$this->nullable($x['person_type']??null),
            'first_name'=>$this->nullable($x['first_name']??null),'last_name'=>$this->nullable($x['last_name']??null),
            'birth_date'=>$this->nullable($x['birth_date']??null),'area'=>$this->nullable($x['area']??null),
            'internal_identifier'=>$this->nullable($x['internal_identifier']??null)
        ];if(array_filter($row,static fn($v)=>$v!==null))$out[]=$row;}return $out;
    }
    private function witnesses(array $rows): array
    {
        $out=[];foreach($rows as $x){if(!is_array($x))continue;$name=trim((string)($x['name']??''));if($name==='')continue;$out[]=['name'=>$name,'contact_details'=>$this->nullable($x['contact_details']??null),'statement_summary'=>$this->nullable($x['statement_summary']??null),'written_statement'=>!empty($x['written_statement'])?1:0];}return $out;
    }
    private function external(array $rows): array
    {
        $out=[];foreach($rows as $x){if(!is_array($x))continue;$row=[
            'organization_id'=>($o=(int)($x['organization_id']??0))>0?$o:null,'organization_name'=>$this->nullable($x['organization_name']??null),
            'contact_name'=>$this->nullable($x['contact_name']??null),'notified_at'=>$this->dateTime((string)($x['notified_at']??'')),
            'feedback'=>$this->nullable($x['feedback']??null),'reference_number'=>$this->nullable($x['reference_number']??null)
        ];if(array_filter($row,static fn($v)=>$v!==null))$out[]=$row;}return $out;
    }
    private function forceActions(array $rows,array $requirements=[]): array
    {
        $labels=['action_type'=>'Art der Maßnahme','started_at'=>'Beginn','ended_at'=>'Ende','staff_ids'=>'beteiligte Mitarbeiter','justification'=>'Begründung','result_text'=>'Ergebnis'];
        $required=array_keys(array_filter($requirements,static fn($value):bool=>(bool)$value));$out=[];
        foreach($rows as $x){
            if(!is_array($x))continue;
            $type=trim((string)($x['action_type']??''));$start=$this->dateTime((string)($x['started_at']??''));$end=$this->dateTime((string)($x['ended_at']??''));
            $justification=$this->nullable($x['justification']??null);$result=$this->nullable($x['result_text']??null);
            $staffIds=array_values(array_unique(array_filter(array_map('intval',(array)($x['staff_ids']??[])),static fn(int $id):bool=>$id>0)));
            $values=['action_type'=>$type,'started_at'=>$start,'ended_at'=>$end,'staff_ids'=>$staffIds,'justification'=>$justification,'result_text'=>$result];
            $hasData=$type!==''||$start!==null||$end!==null||$staffIds!==[]||$justification!==null||$result!==null;
            if(!$hasData)continue;
            foreach($required as $field){
                $value=$values[$field]??null;
                if($value===null||$value===''||$value===[])throw new HttpException(422,'Im Abschnitt Zwangsmaßnahmen ist „'.($labels[$field]??$field).'“ erforderlich.');
            }
            if($start&&$end&&strtotime($end)<strtotime($start))throw new HttpException(422,'Bei Zwangsmaßnahmen darf das Ende nicht vor dem Beginn liegen.');
            $out[]=['action_type'=>$type,'started_at'=>$start,'ended_at'=>$end,'justification'=>$justification,'result_text'=>$result,'staff_ids'=>$staffIds];
        }
        if($out===[]&&$required!==[])throw new HttpException(422,'Für diese Einsatzart muss mindestens eine Zwangsmaßnahme vollständig erfasst werden.');
        return $out;
    }

    private function createDutybookLink(int $locationId,int $reportId,int $sourceId,string $typeName,int $year,int $number,string $occurredAt): int
    {
        $dutyRepo=new DutybookRepository();$source=$sourceId>0?$dutyRepo->findEntry($sourceId,$locationId):null;
        if($source && !(new DutybookAutomaticRuleRepository())->enabled($locationId,'special_report_created'))return $sourceId;
        if($source){$dayId=(int)$source['dutybook_day_id'];$dutyDate=$source['duty_date'];$sessionId=$source['shift_session_id'];$shiftId=$source['shift_id'];}
        else{
            $session=$dutyRepo->currentOpenSessionForUser($locationId,(int)Auth::id());
            if($session){$dayId=(int)$session['dutybook_day_id'];$dutyDate=$session['duty_date'];$sessionId=$session['id'];$shiftId=$session['shift_id'];}
            else{
                $detected=(new ShiftService())->detect($locationId,new DateTimeImmutable($occurredAt));
                $shiftId=$detected['id']??null;$dutyDate=$detected['detected_duty_date']??date('Y-m-d',strtotime($occurredAt));$dayId=$dutyRepo->ensureDay($locationId,$dutyDate);$sessionId=null;
            }
        }
        return $dutyRepo->createEntry([
            'location_id'=>$locationId,'dutybook_day_id'=>$dayId,'duty_date'=>$dutyDate,'shift_session_id'=>$sessionId,'shift_id'=>$shiftId,
            'category_id'=>null,'event_type_id'=>null,'status'=>'done','occurred_at'=>$occurredAt,'event_started_at'=>null,'event_ended_at'=>null,
            'place_id'=>null,'place_free_text'=>null,'facts'=>'Sonderbericht erstellt: '.$typeName.' · SB '.str_pad((string)$number,4,'0',STR_PAD_LEFT).'/'.$year,
            'measures_text'=>null,'result_text'=>null,'is_automatic'=>1,'automatic_type'=>'special_report_created','edit_locked_at'=>date('Y-m-d H:i:s'),
            'created_by'=>Auth::id(),'updated_by'=>Auth::id()
        ]);
    }

    private function link(int $leftId,string $leftModule,int $rightId,string $rightModule,string $relation,int $userId): void
    {
        if($leftId<=0)return;
        Database::connection()->prepare(
            'INSERT IGNORE INTO record_links (left_module,left_record_id,right_module,right_record_id,relation,created_by,created_at)
             VALUES (:lm,:li,:rm,:ri,:relation,:user,NOW())'
        )->execute(['lm'=>$leftModule,'li'=>$leftId,'rm'=>$rightModule,'ri'=>$rightId,'relation'=>$relation,'user'=>$userId]);
    }

    private function persistVersionDocuments(array $report,int $version): array
    {
        $generator=new DocumentGeneratorService();$title='Sonderbericht '.$this->displayNumber($report).' · Version '.$version;$sections=$this->exportSections($report);
        $dir=BASE_PATH.'/storage/generated/special-reports/'.$report['report_year'];
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Versionsverzeichnis kann nicht angelegt werden.');
        $base=pathinfo($this->filename($report,'pdf'),PATHINFO_FILENAME).'_v'.$version;
        $pdfRel='special-reports/'.$report['report_year'].'/'.$base.'.pdf';$pdfAbs=BASE_PATH.'/storage/generated/'.$pdfRel;
        file_put_contents($pdfAbs,$generator->pdfForTemplate('special_report',$title,$sections));
        $tmp=$generator->docxForTemplate('special_report',$title,$sections);$docxRel='special-reports/'.$report['report_year'].'/'.$base.'.docx';$docxAbs=BASE_PATH.'/storage/generated/'.$docxRel;
        if(!rename($tmp,$docxAbs)){@unlink($tmp);throw new \RuntimeException('DOCX-Version konnte nicht archiviert werden.');}
        return [$pdfRel,$docxRel];
    }

    public function displayNumber(array $r): string{return str_pad((string)$r['report_number'],4,'0',STR_PAD_LEFT).'/'.(int)$r['report_year'];}
    private function snapshot(array $r): array{unset($r['versions']);return $r;}
    private function nullable(mixed $v): ?string{$v=trim((string)($v??''));return $v===''?null:$v;}
    private function dateTime(string $v,bool $required=false): ?string{$v=trim($v);if($v===''){if($required)throw new HttpException(422,'Datum/Uhrzeit fehlt.');return null;}$v=str_replace('T',' ',$v);$d=DateTimeImmutable::createFromFormat('Y-m-d H:i',$v)?:DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$v);if(!$d)throw new HttpException(422,'Ungültiges Datum/Uhrzeit.');return $d->format('Y-m-d H:i:s');}
    private function filePart(string $v): string{$v=trim($v);$v=preg_replace('/[\\\\\/:*?"<>|]+/u','_',$v)??$v;$v=preg_replace('/\s+/u','_',$v)??$v;return trim($v,'_.')?:'Unbenannt';}

    private function storeWitnessAttachments(int $reportId,array $witnessIds,array $files): void
    {
        if(!isset($files['name']))return;
        $uploader=new UploadService();
        foreach($witnessIds as $index=>$witnessId){
            if(!isset($files['name'][$index]))continue;
            $file=[
                'name'=>$files['name'][$index]??'','type'=>$files['type'][$index]??'',
                'tmp_name'=>$files['tmp_name'][$index]??'','error'=>$files['error'][$index]??UPLOAD_ERR_NO_FILE,
                'size'=>$files['size'][$index]??0
            ];
            if($file['error']===UPLOAD_ERR_NO_FILE)continue;
            $attachmentId=$uploader->store('special_report',$reportId,$file);
            \WKS\Core\Database::connection()->prepare(
                'UPDATE special_report_witnesses SET attachment_id=:attachment_id WHERE id=:id AND report_id=:report_id'
            )->execute(['attachment_id'=>$attachmentId,'id'=>$witnessId,'report_id'=>$reportId]);
        }
    }

}

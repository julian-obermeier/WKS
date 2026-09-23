<?php
declare(strict_types=1);

namespace WKS\Services;

use DateTimeImmutable;
use PDOException;
use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\DutybookAutomaticRuleRepository;
use WKS\Repositories\SettingsRepository;
use WKS\Repositories\ValuablesRepository;

final class ValuablesService
{
    private const CONTAINER_TYPES=['cassette','bag','sack','case','pocket','other'];
    private const HANDOVER_TYPES=['patient','relative','care','police','other'];
    private const RECEIVER_TYPES=['patient','relative','care','police','authorized','other'];
    private const SEAL_CONDITIONS=['intact','damaged','missing'];

    public function store(int $locationId,array $input,array $files=[]): int
    {
        $repo=new ValuablesRepository();$userId=(int)Auth::id();
        $person=$this->validatePerson($input);
        $handoverType=(string)($input['handed_over_by_type']??'');
        if(!in_array($handoverType,self::HANDOVER_TYPES,true))throw new HttpException(422,'Ungültiger Übergabetyp.');
        $handoverName=trim((string)($input['handed_over_by_name']??''));
        if($handoverName==='')throw new HttpException(422,'„Übergeben durch – Name“ ist erforderlich.');
        $containers=$this->validateContainers($locationId,(array)($input['containers']??[]));
        if($containers===[])throw new HttpException(422,'Mindestens ein Behältnis ist erforderlich.');
        $storedAt=$this->dateTime((string)($input['stored_at']??date('Y-m-d H:i:s')),true);
        $correctionParent=($p=(int)($input['correction_parent_id']??0))>0?$p:null;
        if($correctionParent){
            $parent=$repo->find($correctionParent,$locationId);
            if(!$parent||$parent['status']!=='released')throw new HttpException(422,'Korrekturfolge ist nur zu einem vollständig ausgelagerten Vorgang möglich.');
        }

        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $number=$repo->allocateCustodyNumber();
            $recordId=$repo->createRecord([
                'custody_number'=>$number,'location_id'=>$locationId,'first_name'=>$person['first_name'],'last_name'=>$person['last_name'],
                'birth_date'=>$person['birth_date'],'internal_identifier'=>$person['internal_identifier'],
                'handed_over_by_type'=>$handoverType,'handed_over_by_name'=>$handoverName,
                'handed_over_by_organization'=>$this->nullable($input['handed_over_by_organization']??null),
                'handed_over_by_note'=>$this->nullable($input['handed_over_by_note']??null),
                'storage_note'=>$this->nullable($input['storage_note']??null),'stored_by'=>$userId,'stored_at'=>$storedAt,
                'correction_parent_id'=>$correctionParent,'created_by'=>$userId,'updated_by'=>$userId
            ]);

            foreach($containers as $i=>$container){
                $cassetteId=$container['cassette_id'];
                if($cassetteId){
                    $cassette=$repo->cassette($cassetteId,$locationId,true);
                    if(!$cassette)throw new HttpException(422,'Eine ausgewählte Kassette existiert nicht.');
                    if($cassette['valuables_record_id']!==null)throw new HttpException(409,'Kassette '.$cassette['cassette_number'].' ist bereits belegt.');
                    foreach(['seal_left','seal_right'] as $sealKey){
                        $seal=$container[$sealKey];
                        if($repo->sealUsage($seal))throw new HttpException(409,'Siegelnummer '.$seal.' wurde bereits verwendet.');
                    }
                }

                $containerId=$repo->addContainer($recordId,[
                    'position_number'=>$i+1,'container_type'=>$container['container_type'],'description'=>$container['description'],
                    'storage_location_id'=>$container['storage_location_id'],'cassette_id'=>$cassetteId,
                    'seal_left'=>$container['seal_left'],'seal_right'=>$container['seal_right']
                ]);

                if($cassetteId){
                    $repo->assignCassette($locationId,$cassetteId,$recordId,$containerId);
                    $repo->recordSeal($container['seal_left'],$number,$recordId,$containerId,'left',$userId,$storedAt);
                    $repo->recordSeal($container['seal_right'],$number,$recordId,$containerId,'right',$userId,$storedAt);
                }
            }

            if($correctionParent)$repo->addHistory($recordId,'correction_parent_id',null,$correctionParent,$userId,'Neuer Vorgang als Korrekturfolge');
            $this->autoDutybook($locationId,$recordId,$number,'stored',$storedAt,$person);
            $pdo->commit();

            if(isset($files['attachments']))(new UploadService())->storeMany('valuables',$recordId,$files['attachments']);
            (new AuditService())->log('valuables_stored','valuables',(string)$recordId,null,['custody_number'=>$number,'container_count'=>count($containers)],[],null,$userId,$locationId);
            return $recordId;
        }catch(PDOException $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if((string)$e->getCode()==='23000')throw new HttpException(409,'Kassette oder Siegel wurde gleichzeitig bereits anderweitig vergeben.');
            throw $e;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();throw $e;
        }
    }

    public function release(int $locationId,int $recordId,array $input): void
    {
        $repo=new ValuablesRepository();$record=$repo->find($recordId,$locationId);
        if(!$record)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($record['status']!=='stored')throw new HttpException(422,'Dieser Vorgang wurde bereits vollständig ausgelagert.');
        if(empty($input['release_confirmed']))throw new HttpException(422,'Die vollständige Übergabe aller Wertsachen muss bestätigt werden.');

        $isSubject=!empty($input['receiver_is_subject']);
        if($isSubject){
            $receiverType='patient';$receiverName=trim($record['first_name'].' '.$record['last_name']);$reason=null;$organization=null;
        }else{
            $receiverType=(string)($input['receiver_type']??'');
            if(!in_array($receiverType,self::RECEIVER_TYPES,true))throw new HttpException(422,'Bitte wählen Sie einen gültigen Empfängertyp.');
            $receiverName=trim((string)($input['receiver_name']??''));if($receiverName==='')throw new HttpException(422,'Der Empfängername ist erforderlich.');
            $reason=trim((string)($input['receiver_reason']??''));if($reason==='')throw new HttpException(422,'Der Grund der Fremdausgabe ist erforderlich.');
            $organization=$this->nullable($input['receiver_organization']??null);
        }

        $checks=(array)($input['seal_checks']??[]);
        foreach($record['containers'] as $container){
            if(!$container['cassette_id'])continue;
            $row=$checks[$container['id']]??null;
            if(!is_array($row))throw new HttpException(422,'Die Siegelprüfung ist für jede Kassette vollständig erforderlich.');
            $this->validateSealSide($row,'left');
            $this->validateSealSide($row,'right');
        }

        $releasedAt=$this->dateTime((string)($input['released_at']??date('Y-m-d H:i:s')),true);$userId=(int)Auth::id();
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $locked=$pdo->prepare('SELECT status FROM valuables_records WHERE id=:id AND location_id=:location_id FOR UPDATE');
            $locked->execute(['id'=>$recordId,'location_id'=>$locationId]);
            if($locked->fetchColumn()!=='stored')throw new HttpException(409,'Der Vorgang wurde zwischenzeitlich bereits ausgelagert.');

            foreach($record['containers'] as $container){
                if(!$container['cassette_id'])continue;
                $row=$checks[$container['id']];
                $repo->saveReleaseCheck((int)$container['id'],[
                    'seal_left_matches'=>(int)$row['left_matches'],'seal_left_condition'=>(string)$row['left_condition'],
                    'seal_left_actual'=>$this->nullable($row['left_actual']??null),'seal_left_reason'=>$this->nullable($row['left_reason']??null),
                    'seal_right_matches'=>(int)$row['right_matches'],'seal_right_condition'=>(string)$row['right_condition'],
                    'seal_right_actual'=>$this->nullable($row['right_actual']??null),'seal_right_reason'=>$this->nullable($row['right_reason']??null)
                ],$userId,$releasedAt);
            }

            $repo->releaseRecord($recordId,$locationId,[
                'released_by'=>$userId,'released_at'=>$releasedAt,'receiver_is_subject'=>$isSubject?1:0,
                'receiver_type'=>$receiverType,'receiver_name'=>$receiverName,'receiver_reason'=>$reason,
                'receiver_organization'=>$organization,'release_note'=>$this->nullable($input['release_note']??null),'updated_by'=>$userId
            ]);
            $repo->freeCassettesForRecord($recordId);
            $repo->addHistory($recordId,'status','stored','released',$userId,'Vollständige Auslagerung');
            $this->autoDutybook($locationId,$recordId,(int)$record['custody_number'],'released',$releasedAt,[
                'first_name'=>$record['first_name'],'last_name'=>$record['last_name'],'birth_date'=>$record['birth_date']
            ]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

        (new AuditService())->log('valuables_released','valuables',(string)$recordId,['status'=>'stored'],['status'=>'released','receiver_type'=>$receiverType,'receiver_name'=>$receiverName],[],null,$userId,$locationId);
    }

    public function addNote(int $locationId,int $recordId,string $text): void
    {
        $repo=new ValuablesRepository();$record=$repo->find($recordId,$locationId);if(!$record)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        $text=trim($text);if($text==='')throw new HttpException(422,'Die Notiz darf nicht leer sein.');
        $id=$repo->addNote($recordId,$text,(int)Auth::id());
        (new AuditService())->log('valuables_note_added','valuables',(string)$recordId,null,['note_id'=>$id],[],null,Auth::id(),$locationId);
    }

    public function longTermDays(): int
    {
        return max(1,(int)(new SettingsRepository())->get('valuables.long_term_days',14));
    }

    private function validatePerson(array $input): array
    {
        $first=trim((string)($input['first_name']??''));$last=trim((string)($input['last_name']??''));$birth=trim((string)($input['birth_date']??''));
        if($first===''||$last===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$birth))throw new HttpException(422,'Vorname, Nachname und gültiges Geburtsdatum sind Pflicht.');
        return ['first_name'=>$first,'last_name'=>$last,'birth_date'=>$birth,'internal_identifier'=>$this->nullable($input['internal_identifier']??null)];
    }

    private function validateContainers(int $locationId,array $rows): array
    {
        $repo=new ValuablesRepository();$out=[];$seenCassettes=[];$seenSeals=[];
        foreach($rows as $row){
            if(!is_array($row))continue;
            $type=(string)($row['container_type']??'');if(!in_array($type,self::CONTAINER_TYPES,true))continue;
            $storage=(int)($row['storage_location_id']??0);if(!$repo->storageLocation($storage,$locationId))throw new HttpException(422,'Ungültiger Lagerort.');
            $cassetteId=($c=(int)($row['cassette_id']??0))>0?$c:null;$left=null;$right=null;
            if($type==='cassette'){
                if(!$cassetteId)throw new HttpException(422,'Bei Behältnistyp Kassette muss eine Kassette ausgewählt werden.');
                if(in_array($cassetteId,$seenCassettes,true))throw new HttpException(422,'Eine Kassette darf im Vorgang nur einmal verwendet werden.');
                $seenCassettes[]=$cassetteId;
                $left=trim((string)($row['seal_left']??''));$right=trim((string)($row['seal_right']??''));
                if(!preg_match('/^\d+$/',$left)||!preg_match('/^\d+$/',$right))throw new HttpException(422,'Beide Siegelnummern müssen ausschließlich numerisch sein.');
                if($left===$right)throw new HttpException(422,'Siegel links und rechts dürfen nicht identisch sein.');
                foreach([$left,$right] as $seal){
                    if(in_array($seal,$seenSeals,true))throw new HttpException(422,'Eine Siegelnummer darf im Vorgang nur einmal vorkommen.');
                    $seenSeals[]=$seal;
                }
            }else{
                $cassetteId=null;
            }
            $out[]=['container_type'=>$type,'description'=>$this->nullable($row['description']??null),'storage_location_id'=>$storage,'cassette_id'=>$cassetteId,'seal_left'=>$left,'seal_right'=>$right];
        }
        return $out;
    }

    private function validateSealSide(array $row,string $side): void
    {
        if(!array_key_exists($side.'_matches',$row)||!in_array((string)$row[$side.'_matches'],['0','1'],true))throw new HttpException(422,'Für jedes Siegel muss angegeben werden, ob die Nummer übereinstimmt.');
        $condition=(string)($row[$side.'_condition']??'');if(!in_array($condition,self::SEAL_CONDITIONS,true))throw new HttpException(422,'Für jedes Siegel muss der Zustand erfasst werden.');
        if((string)$row[$side.'_matches']==='0'){
            $reason=trim((string)($row[$side.'_reason']??''));if($reason==='')throw new HttpException(422,'Bei Siegelabweichung ist eine Begründung erforderlich.');
            if($condition!=='missing'){
                $actual=trim((string)($row[$side.'_actual']??''));
                if(!preg_match('/^\d+$/',$actual))throw new HttpException(422,'Bei Siegelabweichung muss die tatsächlich vorgefundene Siegelnummer numerisch erfasst werden.');
            }
        }
    }

    private function autoDutybook(int $locationId,int $recordId,int $custodyNumber,string $type,string $occurredAt,array $person): void
    {
        $eventCode=$type==='stored'?'valuables_stored':'valuables_released';
        if(!(new DutybookAutomaticRuleRepository())->enabled($locationId,$eventCode))return;
        $repo=new DutybookRepository();$session=$repo->currentOpenSessionForUser($locationId,(int)Auth::id());
        if($session){
            $dayId=(int)$session['dutybook_day_id'];$dutyDate=(string)$session['duty_date'];$sessionId=(int)$session['id'];$shiftId=(int)$session['shift_id'];
        }else{
            $detected=(new ShiftService())->detect($locationId,new DateTimeImmutable($occurredAt));
            $shiftId=$detected['id']??null;$dutyDate=$detected['detected_duty_date']??date('Y-m-d',strtotime($occurredAt));
            $dayId=$repo->ensureDay($locationId,$dutyDate);$sessionId=null;
        }
        $facts=($type==='stored'?'Wertsache eingelagert':'Wertsache vollständig ausgelagert').' · Verwahrnummer '.str_pad((string)$custodyNumber,4,'0',STR_PAD_LEFT).' · '.trim($person['first_name'].' '.$person['last_name']);
        $entryId=$repo->createEntry([
            'location_id'=>$locationId,'dutybook_day_id'=>$dayId,'duty_date'=>$dutyDate,'shift_session_id'=>$sessionId,'shift_id'=>$shiftId,
            'category_id'=>null,'event_type_id'=>null,'status'=>'done','occurred_at'=>$occurredAt,'event_started_at'=>null,'event_ended_at'=>null,
            'place_id'=>null,'place_free_text'=>null,'facts'=>$facts,'measures_text'=>null,'result_text'=>null,'is_automatic'=>1,
            'automatic_type'=>$type==='stored'?'valuables_stored':'valuables_released','edit_locked_at'=>date('Y-m-d H:i:s'),
            'created_by'=>Auth::id(),'updated_by'=>Auth::id()
        ]);
        Database::connection()->prepare(
            'INSERT IGNORE INTO record_links (left_module,left_record_id,right_module,right_record_id,relation,created_by,created_at)
             VALUES ("dutybook",:entry_id,"valuables",:record_id,"valuables",:user_id,NOW())'
        )->execute(['entry_id'=>$entryId,'record_id'=>$recordId,'user_id'=>Auth::id()]);
    }

    private function dateTime(string $value,bool $required=false): ?string
    {
        $value=trim($value);if($value===''){if($required)throw new HttpException(422,'Datum/Uhrzeit ist erforderlich.');return null;}
        $value=str_replace('T',' ',$value);$dt=DateTimeImmutable::createFromFormat('Y-m-d H:i',$value)?:DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value);
        if(!$dt)throw new HttpException(422,'Ungültiges Datum/Uhrzeit.');return $dt->format('Y-m-d H:i:s');
    }

    private function nullable(mixed $value): ?string
    {
        $value=trim((string)($value??''));return $value===''?null:$value;
    }
}

<?php
declare(strict_types=1);

namespace WKS\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\DutybookAutomaticRuleRepository;
use WKS\Repositories\MasterDataRepository;

final class ShiftService
{
    public function detect(int $locationId,?DateTimeImmutable $now=null): ?array
    {
        $now ??= new DateTimeImmutable('now',new DateTimeZone((string)config('app.timezone','Europe/Berlin')));
        $candidates=[];

        foreach((new MasterDataRepository())->shifts($locationId) as $shift){
            [$start,$end,$dutyDate]=$this->intervalForShift($shift,$now);
            if($now >= $start && $now <= $end){
                $shift['detected_duty_date']=$dutyDate;
                $shift['_start_ts']=$start->getTimestamp();
                $candidates[]=$shift;
            }
        }

        if($candidates===[]) return null;
        usort($candidates,static fn(array $a,array $b):int=>$b['_start_ts']<=>$a['_start_ts']);
        unset($candidates[0]['_start_ts']);
        return $candidates[0];
    }

    public function dutyDateForShift(array $shift,?DateTimeImmutable $now=null): string
    {
        $now ??= new DateTimeImmutable('now',new DateTimeZone((string)config('app.timezone','Europe/Berlin')));
        [,,$date]=$this->intervalForShift($shift,$now);
        return $date;
    }

    public function acceptDuty(int $locationId,int $shiftId,int $userId,?DateTimeImmutable $now=null): array
    {
        $now ??= new DateTimeImmutable('now',new DateTimeZone((string)config('app.timezone','Europe/Berlin')));
        $master=new MasterDataRepository();
        $shift=$master->shift($shiftId,$locationId);
        if(!$shift||!(bool)$shift['active']) throw new HttpException(422,'Die ausgewählte Schicht ist nicht verfügbar.');

        $dutyDate=$this->dutyDateForShift($shift,$now);
        $repo=new DutybookRepository();
        $dayId=$repo->ensureDay($locationId,$dutyDate);
        $pdo=Database::connection();
        $pdo->beginTransaction();

        try{
            $sessionStmt=$pdo->prepare(
                'INSERT INTO shift_sessions (location_id,dutybook_day_id,duty_date,shift_id,status,started_at,started_by,created_at,updated_at)
                 VALUES (:location_id,:day_id,:duty_date,:shift_id,"open",NOW(),:user_id,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),updated_at=NOW()'
            );
            $sessionStmt->execute(['location_id'=>$locationId,'day_id'=>$dayId,'duty_date'=>$dutyDate,'shift_id'=>$shiftId,'user_id'=>$userId]);
            $sessionId=(int)$pdo->lastInsertId();

            $attendance=$pdo->prepare(
                'INSERT INTO shift_attendance (shift_session_id,user_id,checked_in_at,duty_accepted_at,created_at,updated_at)
                 VALUES (:session_id,:user_id,NOW(),NOW(),NOW(),NOW())
                 ON DUPLICATE KEY UPDATE checked_out_at=NULL,duty_accepted_at=COALESCE(duty_accepted_at,NOW()),updated_at=NOW()'
            );
            $attendance->execute(['session_id'=>$sessionId,'user_id'=>$userId]);
            $pdo->commit();

            $this->createAutomaticEntry($locationId,$dayId,$dutyDate,$sessionId,$shiftId,'shift_start','Schichtbeginn / Dienst übernommen',$userId);
            return $repo->session($sessionId,$locationId) ?? [];
        }catch(\Throwable $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    public function endShift(int $locationId,int $sessionId,int $userId): void
    {
        $repo=new DutybookRepository();
        $session=$repo->session($sessionId,$locationId);
        if(!$session||$session['status']!=='open') throw new HttpException(422,'Diese Schicht ist nicht mehr offen.');

        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM shift_attendance WHERE shift_session_id=:session_id AND user_id=:user_id AND duty_accepted_at IS NOT NULL'
        );
        $stmt->execute(['session_id'=>$sessionId,'user_id'=>$userId]);
        if(!(bool)$stmt->fetchColumn()) throw new HttpException(403,'Nur ein anwesender Mitarbeiter darf diese Schicht beenden.');

        $handover=$repo->handoverForSession($sessionId);
        if(!$handover||$handover['status']!=='completed') throw new HttpException(422,'Die Schicht kann erst nach vollständig bestätigter Übergabe beendet werden.');

        $open=$repo->openEntriesForSession($locationId,$sessionId);
        $handed=array_map('intval',array_column($handover['entries'],'entry_id'));
        foreach($open as $entry){
            if(!in_array((int)$entry['id'],$handed,true)){
                throw new HttpException(422,'Mindestens ein offener Vorgang wurde noch nicht ausdrücklich übergeben.');
            }
        }

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $pdo->prepare(
                'UPDATE shift_sessions SET status="closed",ended_at=NOW(),ended_by=:user_id,updated_at=NOW()
                 WHERE id=:session_id AND location_id=:location_id'
            )->execute(['user_id'=>$userId,'session_id'=>$sessionId,'location_id'=>$locationId]);
            $pdo->prepare('UPDATE shift_attendance SET checked_out_at=COALESCE(checked_out_at,NOW()),updated_at=NOW() WHERE shift_session_id=:session_id')
                ->execute(['session_id'=>$sessionId]);
            $pdo->commit();
            $this->createAutomaticEntry($locationId,(int)$session['dutybook_day_id'],(string)$session['duty_date'],$sessionId,(int)$session['shift_id'],'shift_end','Schicht beendet',$userId);
        }catch(\Throwable $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    private function intervalForShift(array $shift,DateTimeImmutable $now): array
    {
        $tz=$now->getTimezone();
        $today=$now->format('Y-m-d');
        $start=new DateTimeImmutable($today.' '.$shift['start_time'],$tz);
        $end=new DateTimeImmutable($today.' '.$shift['end_time'],$tz);

        if((bool)$shift['crosses_midnight']){
            if($now->format('H:i:s') <= (string)$shift['end_time']){
                $start=$start->modify('-1 day');
            }else{
                $end=$end->modify('+1 day');
            }
            if($end <= $start) $end=$start->modify('+1 day')->setTime((int)substr((string)$shift['end_time'],0,2),(int)substr((string)$shift['end_time'],3,2),(int)substr((string)$shift['end_time'],6,2));
        }

        return [$start,$end,$start->format('Y-m-d')];
    }

    private function createAutomaticEntry(int $locationId,int $dayId,string $dutyDate,int $sessionId,int $shiftId,string $type,string $facts,int $userId): void
    {
        if(!(new DutybookAutomaticRuleRepository())->enabled($locationId,$type))return;
        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM dutybook_entries
             WHERE location_id=:location_id AND shift_session_id=:session_id AND automatic_type=:type AND deleted_at IS NULL'
        );
        $stmt->execute(['location_id'=>$locationId,'session_id'=>$sessionId,'type'=>$type]);
        if((int)$stmt->fetchColumn()>0) return;

        (new DutybookRepository())->createEntry([
            'location_id'=>$locationId,'dutybook_day_id'=>$dayId,'duty_date'=>$dutyDate,'shift_session_id'=>$sessionId,'shift_id'=>$shiftId,
            'category_id'=>null,'event_type_id'=>null,'status'=>'done','occurred_at'=>date('Y-m-d H:i:s'),
            'event_started_at'=>null,'event_ended_at'=>null,'place_id'=>null,'place_free_text'=>null,
            'facts'=>$facts,'measures_text'=>null,'result_text'=>null,'is_automatic'=>1,'automatic_type'=>$type,
            'edit_locked_at'=>date('Y-m-d H:i:s'),'created_by'=>$userId,'updated_by'=>$userId
        ]);
    }
}

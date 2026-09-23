<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\MasterDataRepository;

final class HandoverService
{
    public function save(int $locationId,int $sessionId,int $toShiftId,string $notes,array $entryAssignments): int
    {
        $repo=new DutybookRepository();
        $session=$repo->session($sessionId,$locationId);
        if(!$session||$session['status']!=='open')throw new HttpException(422,'Die abgebende Schicht ist nicht offen.');
        if(!(new MasterDataRepository())->shift($toShiftId,$locationId))throw new HttpException(422,'Ungültige Folgeschicht.');
        $this->requireAttendance($sessionId,(int)Auth::id());

        $open=$repo->openEntriesForSession($locationId,$sessionId);
        $required=array_map('intval',array_column($open,'id'));
        $provided=array_values(array_unique(array_map('intval',array_keys($entryAssignments))));
        sort($required);sort($provided);
        if($required!==$provided)throw new HttpException(422,'Alle offenen Vorgänge müssen ausdrücklich in die Übergabe aufgenommen werden.');

        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $existing=$repo->handoverForSession($sessionId);
            if($existing&&$existing['status']==='completed')throw new HttpException(422,'Diese Übergabe ist bereits abgeschlossen.');

            if($existing){
                $handoverId=(int)$existing['id'];
                $pdo->prepare(
                    'UPDATE shift_handovers SET to_shift_id=:to_shift_id,notes=:notes,status="pending",
                     outgoing_confirmed_by=NULL,outgoing_confirmed_at=NULL,incoming_confirmed_by=NULL,incoming_confirmed_at=NULL,updated_at=NOW()
                     WHERE id=:id'
                )->execute(['to_shift_id'=>$toShiftId,'notes'=>trim($notes)?:null,'id'=>$handoverId]);
                $pdo->prepare('DELETE FROM shift_handover_entries WHERE handover_id=:id')->execute(['id'=>$handoverId]);
            }else{
                $stmt=$pdo->prepare(
                    'INSERT INTO shift_handovers (location_id,from_shift_session_id,to_shift_id,notes,status,created_at,updated_at)
                     VALUES (:location_id,:session_id,:to_shift_id,:notes,"pending",NOW(),NOW())'
                );
                $stmt->execute(['location_id'=>$locationId,'session_id'=>$sessionId,'to_shift_id'=>$toShiftId,'notes'=>trim($notes)?:null]);
                $handoverId=(int)$pdo->lastInsertId();
            }

            $insert=$pdo->prepare(
                'INSERT INTO shift_handover_entries (handover_id,entry_id,assigned_to_user_id,assigned_to_next_shift)
                 VALUES (:handover_id,:entry_id,:assigned_to_user_id,:assigned_to_next_shift)'
            );
            foreach($entryAssignments as $entryId=>$assignment){
                $userId=(int)($assignment['user_id']??0);
                $toNext=$userId>0?0:1;
                $insert->execute([
                    'handover_id'=>$handoverId,'entry_id'=>(int)$entryId,
                    'assigned_to_user_id'=>$userId>0?$userId:null,'assigned_to_next_shift'=>$toNext
                ]);
                $pdo->prepare('UPDATE dutybook_entries SET status="handover",updated_at=NOW() WHERE id=:id AND location_id=:location_id')
                    ->execute(['id'=>(int)$entryId,'location_id'=>$locationId]);
            }
            $pdo->commit();
            (new AuditService())->log('handover_saved','dutybook',(string)$handoverId,null,['from_session'=>$sessionId,'to_shift'=>$toShiftId,'entry_ids'=>$required],[],null,Auth::id(),$locationId);
            return $handoverId;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function confirmOutgoing(int $locationId,int $sessionId): void
    {
        $repo=new DutybookRepository();$handover=$repo->handoverForSession($sessionId);
        if(!$handover||$handover['location_id']!=$locationId)throw new HttpException(404,'Übergabe nicht gefunden.');
        if($handover['status']==='completed')throw new HttpException(422,'Übergabe bereits abgeschlossen.');
        $this->requireAttendance($sessionId,(int)Auth::id());

        Database::connection()->prepare(
            'UPDATE shift_handovers SET outgoing_confirmed_by=:user_id,outgoing_confirmed_at=NOW(),updated_at=NOW() WHERE id=:id'
        )->execute(['user_id'=>Auth::id(),'id'=>$handover['id']]);
        (new AuditService())->log('handover_outgoing_confirmed','dutybook',(string)$handover['id'],null,null,[],null,Auth::id(),$locationId);
    }

    public function confirmIncoming(int $locationId,int $sessionId): void
    {
        $repo=new DutybookRepository();$handover=$repo->handoverForSession($sessionId);
        if(!$handover||$handover['location_id']!=$locationId)throw new HttpException(404,'Übergabe nicht gefunden.');
        if(!$handover['outgoing_confirmed_at'])throw new HttpException(422,'Die abgebende Schicht muss zuerst bestätigen.');
        if($handover['status']==='completed')return;

        $current=$repo->currentOpenSessionForUser($locationId,(int)Auth::id());
        if(!$current||(int)$current['shift_id']!==(int)$handover['to_shift_id']){
            throw new HttpException(403,'Die Übergabe kann nur durch einen Mitarbeiter der übernehmenden, bereits übernommenen Schicht bestätigt werden.');
        }

        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $pdo->prepare(
                'UPDATE shift_handovers SET incoming_confirmed_by=:user_id,incoming_confirmed_at=NOW(),status="completed",updated_at=NOW() WHERE id=:id'
            )->execute(['user_id'=>Auth::id(),'id'=>$handover['id']]);
            $pdo->prepare(
                'UPDATE dutybook_entries e JOIN shift_handover_entries he ON he.entry_id=e.id
                 SET e.status="open",e.updated_at=NOW() WHERE he.handover_id=:handover_id AND e.status="handover"'
            )->execute(['handover_id'=>$handover['id']]);

            (new DutybookRepository())->createEntry([
                'location_id'=>$locationId,'dutybook_day_id'=>$current['dutybook_day_id'],'duty_date'=>$current['duty_date'],
                'shift_session_id'=>$current['id'],'shift_id'=>$current['shift_id'],'category_id'=>null,'event_type_id'=>null,
                'status'=>'done','occurred_at'=>date('Y-m-d H:i:s'),'event_started_at'=>null,'event_ended_at'=>null,'place_id'=>null,
                'place_free_text'=>null,'facts'=>'Schichtübergabe übernommen','measures_text'=>null,'result_text'=>null,
                'is_automatic'=>1,'automatic_type'=>'shift_handover','edit_locked_at'=>date('Y-m-d H:i:s'),
                'created_by'=>Auth::id(),'updated_by'=>Auth::id()
            ]);
            $pdo->commit();
            (new AuditService())->log('handover_completed','dutybook',(string)$handover['id'],null,['incoming_session_id'=>$current['id']],[],null,Auth::id(),$locationId);
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    private function requireAttendance(int $sessionId,int $userId): void
    {
        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM shift_attendance WHERE shift_session_id=:session_id AND user_id=:user_id AND duty_accepted_at IS NOT NULL'
        );
        $stmt->execute(['session_id'=>$sessionId,'user_id'=>$userId]);
        if(!(bool)$stmt->fetchColumn())throw new HttpException(403,'Nur ein anwesender Mitarbeiter dieser Schicht darf die Übergabe bearbeiten.');
    }
}

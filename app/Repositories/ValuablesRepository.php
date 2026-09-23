<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class ValuablesRepository
{
    public function allocateCustodyNumber(): int
    {
        $pdo=Database::connection();
        $pdo->exec('INSERT INTO valuables_sequence (id,next_number,updated_at) VALUES (1,1,NOW()) ON DUPLICATE KEY UPDATE id=id');
        $stmt=$pdo->query('SELECT next_number FROM valuables_sequence WHERE id=1 FOR UPDATE');
        $number=(int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE valuables_sequence SET next_number=:next,updated_at=NOW() WHERE id=1')->execute(['next'=>$number+1]);
        return $number;
    }

    public function storageLocations(int $locationId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT * FROM storage_locations WHERE location_id=:location_id AND active=1 ORDER BY sort_order,label'
        );
        $stmt->execute(['location_id'=>$locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function storageLocation(int $id,int $locationId): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT * FROM storage_locations WHERE id=:id AND location_id=:location_id AND active=1 LIMIT 1'
        );
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function cassettes(int $locationId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT c.*,ca.valuables_record_id,vr.custody_number,vr.stored_at,sl.label AS storage_label
             FROM cassettes c
             LEFT JOIN cassette_assignments ca ON ca.cassette_id=c.id AND ca.location_id=c.location_id
             LEFT JOIN valuables_records vr ON vr.id=ca.valuables_record_id
             LEFT JOIN valuables_containers vc ON vc.id=ca.container_id
             LEFT JOIN storage_locations sl ON sl.id=vc.storage_location_id
             WHERE c.location_id=:location_id AND c.active=1
             ORDER BY c.cassette_number'
        );
        $stmt->execute(['location_id'=>$locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cassette(int $id,int $locationId,bool $lock=false): ?array
    {
        $sql='SELECT c.*,ca.valuables_record_id FROM cassettes c
              LEFT JOIN cassette_assignments ca ON ca.cassette_id=c.id AND ca.location_id=c.location_id
              WHERE c.id=:id AND c.location_id=:location_id AND c.active=1 LIMIT 1';
        if($lock)$sql.=' FOR UPDATE';
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function sealUsage(string $sealNumber): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT s.*,r.custody_number FROM seal_usages s
             JOIN valuables_records r ON r.id=s.valuables_record_id
             WHERE s.seal_number=:seal LIMIT 1'
        );
        $stmt->execute(['seal'=>$sealNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function createRecord(array $data): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO valuables_records
             (custody_number,location_id,first_name,last_name,birth_date,internal_identifier,status,
              handed_over_by_type,handed_over_by_name,handed_over_by_organization,handed_over_by_note,
              storage_note,stored_by,stored_at,correction_parent_id,created_at,updated_at,created_by,updated_by)
             VALUES
             (:custody_number,:location_id,:first_name,:last_name,:birth_date,:internal_identifier,"stored",
              :handed_over_by_type,:handed_over_by_name,:handed_over_by_organization,:handed_over_by_note,
              :storage_note,:stored_by,:stored_at,:correction_parent_id,NOW(),NOW(),:created_by,:updated_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function addContainer(int $recordId,array $data): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO valuables_containers
             (valuables_record_id,position_number,container_type,description,storage_location_id,cassette_id,seal_left,seal_right,created_at)
             VALUES (:record_id,:position_number,:container_type,:description,:storage_location_id,:cassette_id,:seal_left,:seal_right,NOW())'
        );
        $stmt->execute($data+['record_id'=>$recordId]);
        return (int)Database::connection()->lastInsertId();
    }

    public function assignCassette(int $locationId,int $cassetteId,int $recordId,int $containerId): void
    {
        Database::connection()->prepare(
            'INSERT INTO cassette_assignments (location_id,cassette_id,valuables_record_id,container_id,assigned_at)
             VALUES (:location_id,:cassette_id,:record_id,:container_id,NOW())'
        )->execute(['location_id'=>$locationId,'cassette_id'=>$cassetteId,'record_id'=>$recordId,'container_id'=>$containerId]);
    }

    public function recordSeal(string $seal,int $custodyNumber,int $recordId,int $containerId,string $side,int $userId,string $usedAt): void
    {
        Database::connection()->prepare(
            'INSERT INTO seal_usages (seal_number,custody_number_snapshot,valuables_record_id,container_id,seal_side,used_at,created_by)
             VALUES (:seal,:custody_number,:record_id,:container_id,:side,:used_at,:user_id)'
        )->execute(['seal'=>$seal,'custody_number'=>$custodyNumber,'record_id'=>$recordId,'container_id'=>$containerId,'side'=>$side,'used_at'=>$usedAt,'user_id'=>$userId]);
    }

    public function find(int $id,int $locationId): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT r.*,CONCAT(s.first_name," ",s.last_name) AS stored_by_name,
                    CONCAT(rel.first_name," ",rel.last_name) AS released_by_name,
                    parent.custody_number AS correction_parent_number
             FROM valuables_records r
             LEFT JOIN users s ON s.id=r.stored_by
             LEFT JOIN users rel ON rel.id=r.released_by
             LEFT JOIN valuables_records parent ON parent.id=r.correction_parent_id
             WHERE r.id=:id AND r.location_id=:location_id AND r.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);$record=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$record)return null;
        $record['containers']=$this->containers($id);
        $record['notes']=$this->notes($id);
        $record['history']=$this->history($id);
        $record['attachments']=$this->attachments($id);
        return $record;
    }

    public function containers(int $recordId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT vc.*,sl.label AS storage_label,c.cassette_number,rc.seal_left_matches,rc.seal_left_condition,
                    rc.seal_left_actual,rc.seal_left_reason,rc.seal_right_matches,rc.seal_right_condition,
                    rc.seal_right_actual,rc.seal_right_reason,rc.checked_at
             FROM valuables_containers vc
             JOIN storage_locations sl ON sl.id=vc.storage_location_id
             LEFT JOIN cassettes c ON c.id=vc.cassette_id
             LEFT JOIN valuables_release_checks rc ON rc.container_id=vc.id
             WHERE vc.valuables_record_id=:record_id ORDER BY vc.position_number'
        );
        $stmt->execute(['record_id'=>$recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function notes(int $recordId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT n.*,CONCAT(u.first_name," ",u.last_name) AS user_name FROM valuables_notes n
             LEFT JOIN users u ON u.id=n.created_by WHERE n.valuables_record_id=:record_id ORDER BY n.created_at,n.id'
        );
        $stmt->execute(['record_id'=>$recordId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(int $recordId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT h.*,CONCAT(u.first_name," ",u.last_name) AS user_name FROM valuables_history h
             LEFT JOIN users u ON u.id=h.changed_by WHERE h.valuables_record_id=:record_id ORDER BY h.changed_at,h.id'
        );
        $stmt->execute(['record_id'=>$recordId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachments(int $recordId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS uploader_name FROM attachments a
             LEFT JOIN users u ON u.id=a.uploaded_by
             WHERE a.module="valuables" AND a.record_id=:record_id AND a.deleted_at IS NULL
             ORDER BY a.uploaded_at,a.id'
        );
        $stmt->execute(['record_id'=>$recordId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addNote(int $recordId,string $text,int $userId): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO valuables_notes (valuables_record_id,note_text,created_by,created_at)
             VALUES (:record_id,:text,:user_id,NOW())'
        );
        $stmt->execute(['record_id'=>$recordId,'text'=>$text,'user_id'=>$userId]);
        return (int)Database::connection()->lastInsertId();
    }

    public function addHistory(int $recordId,string $field,mixed $old,mixed $new,int $userId,?string $reason=null): void
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO valuables_history (valuables_record_id,field_name,old_value,new_value,changed_by,changed_at,change_reason)
             VALUES (:record_id,:field,:old_value,:new_value,:user_id,NOW(),:reason)'
        );
        $encode=static fn(mixed $v): ?string => $v===null?null:json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $stmt->execute(['record_id'=>$recordId,'field'=>$field,'old_value'=>$encode($old),'new_value'=>$encode($new),'user_id'=>$userId,'reason'=>$reason]);
    }

    public function releaseRecord(int $id,int $locationId,array $data): void
    {
        $stmt=Database::connection()->prepare(
            'UPDATE valuables_records SET status="released",released_by=:released_by,released_at=:released_at,
             receiver_is_subject=:receiver_is_subject,receiver_type=:receiver_type,receiver_name=:receiver_name,
             receiver_reason=:receiver_reason,receiver_organization=:receiver_organization,release_note=:release_note,
             release_confirmed=1,updated_at=NOW(),updated_by=:updated_by
             WHERE id=:id AND location_id=:location_id AND status="stored" AND deleted_at IS NULL'
        );
        $stmt->execute($data+['id'=>$id,'location_id'=>$locationId]);
    }

    public function saveReleaseCheck(int $containerId,array $data,int $userId,string $checkedAt): void
    {
        Database::connection()->prepare(
            'INSERT INTO valuables_release_checks
             (container_id,seal_left_matches,seal_left_condition,seal_left_actual,seal_left_reason,
              seal_right_matches,seal_right_condition,seal_right_actual,seal_right_reason,checked_by,checked_at)
             VALUES
             (:container_id,:seal_left_matches,:seal_left_condition,:seal_left_actual,:seal_left_reason,
              :seal_right_matches,:seal_right_condition,:seal_right_actual,:seal_right_reason,:user_id,:checked_at)'
        )->execute($data+['container_id'=>$containerId,'user_id'=>$userId,'checked_at'=>$checkedAt]);
    }

    public function freeCassettesForRecord(int $recordId): void
    {
        Database::connection()->prepare('DELETE FROM cassette_assignments WHERE valuables_record_id=:record_id')->execute(['record_id'=>$recordId]);
    }

    public function active(int $locationId,array $filters=[]): array
    {
        $where=['r.location_id=:location_id','r.status="stored"','r.deleted_at IS NULL'];$params=['location_id'=>$locationId];
        if(!empty($filters['container_type'])){
            $where[]='EXISTS (SELECT 1 FROM valuables_containers vc WHERE vc.valuables_record_id=r.id AND vc.container_type=:container_type)';
            $params['container_type']=$filters['container_type'];
        }
        if(!empty($filters['storage_location_id'])){
            $where[]='EXISTS (SELECT 1 FROM valuables_containers vc WHERE vc.valuables_record_id=r.id AND vc.storage_location_id=:storage_location_id)';
            $params['storage_location_id']=(int)$filters['storage_location_id'];
        }
        $stmt=Database::connection()->prepare(
            'SELECT r.*,(SELECT COUNT(*) FROM valuables_containers vc WHERE vc.valuables_record_id=r.id) AS container_count
             FROM valuables_records r WHERE '.implode(' AND ',$where).' ORDER BY r.stored_at,r.custody_number'
        );
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function longTerm(int $locationId,int $days): array
    {
        $days=max(0,$days);
        $stmt=Database::connection()->prepare(
            'SELECT r.*,TIMESTAMPDIFF(DAY,r.stored_at,NOW()) AS storage_days,
                    (SELECT COUNT(*) FROM valuables_containers vc WHERE vc.valuables_record_id=r.id) AS container_count
             FROM valuables_records r
             WHERE r.location_id=:location_id AND r.status="stored" AND r.deleted_at IS NULL
               AND r.stored_at <= DATE_SUB(NOW(),INTERVAL '.$days.' DAY)
             ORDER BY r.stored_at,r.custody_number'
        );
        $stmt->execute(['location_id'=>$locationId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function counts(int $locationId,int $longTermDays): array
    {
        $days=max(0,$longTermDays);
        $stmt=Database::connection()->prepare(
            'SELECT
                SUM(CASE WHEN status="stored" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS stored_count,
                SUM(CASE WHEN status="stored" AND deleted_at IS NULL AND stored_at<=DATE_SUB(NOW(),INTERVAL '.$days.' DAY) THEN 1 ELSE 0 END) AS long_term_count
             FROM valuables_records WHERE location_id=:location_id'
        );
        $stmt->execute(['location_id'=>$locationId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC)?:['stored_count'=>0,'long_term_count'=>0];
        $c=Database::connection()->prepare('SELECT COUNT(*) FROM cassette_assignments WHERE location_id=:location_id');$c->execute(['location_id'=>$locationId]);
        $row['occupied_cassettes']=(int)$c->fetchColumn();return $row;
    }

    public function search(int $locationId,array $filters,int $page=1,int $perPage=30): array
    {
        $where=['r.location_id=:location_id','r.deleted_at IS NULL'];$params=['location_id'=>$locationId];
        foreach([
            'custody_number'=>'r.custody_number=:custody_number','first_name'=>'r.first_name LIKE :first_name',
            'last_name'=>'r.last_name LIKE :last_name','birth_date'=>'r.birth_date=:birth_date',
            'internal_identifier'=>'r.internal_identifier LIKE :internal_identifier','status'=>'r.status=:status',
            'stored_from'=>'r.stored_at>=:stored_from','stored_to'=>'r.stored_at<=:stored_to',
            'released_from'=>'r.released_at>=:released_from','released_to'=>'r.released_at<=:released_to'
        ] as $key=>$condition){
            if(($filters[$key]??'')!==''){
                $where[]=$condition;
                $v=$filters[$key];
                if(in_array($key,['first_name','last_name','internal_identifier'],true))$v='%'.$v.'%';
                if(in_array($key,['stored_from','released_from'],true))$v.=' 00:00:00';
                if(in_array($key,['stored_to','released_to'],true))$v.=' 23:59:59';
                $params[$key]=$v;
            }
        }
        if(!empty($filters['cassette_number'])){
            $where[]='EXISTS (SELECT 1 FROM valuables_containers vc JOIN cassettes c ON c.id=vc.cassette_id WHERE vc.valuables_record_id=r.id AND c.cassette_number=:cassette_number)';
            $params['cassette_number']=(int)$filters['cassette_number'];
        }
        if(!empty($filters['seal'])){
            $where[]='EXISTS (SELECT 1 FROM seal_usages s WHERE s.valuables_record_id=r.id AND s.seal_number=:seal)';
            $params['seal']=(string)$filters['seal'];
        }
        if(!empty($filters['storage_location_id'])){
            $where[]='EXISTS (SELECT 1 FROM valuables_containers vc WHERE vc.valuables_record_id=r.id AND vc.storage_location_id=:storage_location_id)';
            $params['storage_location_id']=(int)$filters['storage_location_id'];
        }
        $clause=implode(' AND ',$where);$pdo=Database::connection();
        $count=$pdo->prepare("SELECT COUNT(*) FROM valuables_records r WHERE {$clause}");$count->execute($params);$total=(int)$count->fetchColumn();
        $page=max(1,$page);$offset=($page-1)*$perPage;
        $stmt=$pdo->prepare(
            "SELECT r.*,(SELECT COUNT(*) FROM valuables_containers vc WHERE vc.valuables_record_id=r.id) AS container_count
             FROM valuables_records r WHERE {$clause}
             ORDER BY r.stored_at DESC,r.custody_number DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }
}

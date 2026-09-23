<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class DutybookRepository
{
    public function ensureDay(int $locationId, string $dutyDate): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO dutybook_days (location_id,duty_date,created_at,updated_at)
             VALUES (:location_id,:duty_date,NOW(),NOW())
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),updated_at=updated_at'
        );
        $stmt->execute(['location_id'=>$locationId,'duty_date'=>$dutyDate]);
        return (int) Database::connection()->lastInsertId();
    }

    public function day(int $locationId, string $dutyDate): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.*, l.name AS location_name, l.code AS location_code
             FROM dutybook_days d JOIN locations l ON l.id=d.location_id
             WHERE d.location_id=:location_id AND d.duty_date=:duty_date LIMIT 1'
        );
        $stmt->execute(['location_id'=>$locationId,'duty_date'=>$dutyDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function currentOpenSessionForUser(int $locationId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ss.*, s.code AS shift_code,s.name AS shift_name,s.start_time,s.end_time,s.crosses_midnight,
                    a.checked_in_at,a.duty_accepted_at,a.checked_out_at
             FROM shift_sessions ss
             JOIN shifts s ON s.id=ss.shift_id
             JOIN shift_attendance a ON a.shift_session_id=ss.id
             WHERE ss.location_id=:location_id AND a.user_id=:user_id AND ss.status="open" AND a.checked_out_at IS NULL
             ORDER BY ss.started_at DESC, ss.id DESC LIMIT 1'
        );
        $stmt->execute(['location_id'=>$locationId,'user_id'=>$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function session(int $id, int $locationId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ss.*,s.code AS shift_code,s.name AS shift_name,s.start_time,s.end_time,s.crosses_midnight
             FROM shift_sessions ss JOIN shifts s ON s.id=ss.shift_id
             WHERE ss.id=:id AND ss.location_id=:location_id LIMIT 1'
        );
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function attendance(int $sessionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*,u.first_name,u.last_name,u.personnel_number
             FROM shift_attendance a JOIN users u ON u.id=a.user_id
             WHERE a.shift_session_id=:session_id
             ORDER BY a.checked_in_at,u.last_name,u.first_name'
        );
        $stmt->execute(['session_id'=>$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entriesForDay(int $locationId, string $dutyDate): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*,c.name AS category_name,t.name AS event_type_name,t.offer_special_report,t.offer_valuables,s.name AS shift_name,
                    CONCAT(u.first_name," ",u.last_name) AS creator_name,p.name AS place_name,
                    (SELECT COUNT(*) FROM attachments a WHERE a.module="dutybook" AND a.record_id=e.id AND a.deleted_at IS NULL) AS attachment_count
             FROM dutybook_entries e
             LEFT JOIN dutybook_categories c ON c.id=e.category_id
             LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             LEFT JOIN shifts s ON s.id=e.shift_id
             LEFT JOIN users u ON u.id=e.created_by
             LEFT JOIN places p ON p.id=e.place_id
             WHERE e.location_id=:location_id AND e.duty_date=:duty_date AND e.deleted_at IS NULL
             ORDER BY e.occurred_at,e.id'
        );
        $stmt->execute(['location_id'=>$locationId,'duty_date'=>$dutyDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEntry(int $id, int $locationId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*,c.name AS category_name,t.name AS event_type_name,s.name AS shift_name,
                    CONCAT(u.first_name," ",u.last_name) AS creator_name,p.name AS place_name
             FROM dutybook_entries e
             LEFT JOIN dutybook_categories c ON c.id=e.category_id
             LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             LEFT JOIN shifts s ON s.id=e.shift_id
             LEFT JOIN users u ON u.id=e.created_by
             LEFT JOIN places p ON p.id=e.place_id
             WHERE e.id=:id AND e.location_id=:location_id AND e.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) return null;

        $entry['staff'] = $this->entryStaff($id);
        $entry['people'] = $this->entryPeople($id);
        $entry['measures'] = $this->entryMeasures($id);
        $entry['external'] = $this->entryExternal($id);
        $entry['dynamic_values'] = $this->dynamicValues($id);
        $entry['attachments'] = $this->attachments($id);
        $entry['addenda'] = $this->addenda($id);
        return $entry;
    }

    public function createEntry(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO dutybook_entries
             (location_id,dutybook_day_id,duty_date,shift_session_id,shift_id,category_id,event_type_id,status,occurred_at,
              event_started_at,event_ended_at,place_id,place_free_text,facts,measures_text,result_text,is_automatic,automatic_type,
              edit_locked_at,created_at,updated_at,created_by,updated_by)
             VALUES
             (:location_id,:dutybook_day_id,:duty_date,:shift_session_id,:shift_id,:category_id,:event_type_id,:status,:occurred_at,
              :event_started_at,:event_ended_at,:place_id,:place_free_text,:facts,:measures_text,:result_text,:is_automatic,:automatic_type,
              :edit_locked_at,NOW(),NOW(),:created_by,:updated_by)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function updateEntry(int $id, int $locationId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE dutybook_entries SET category_id=:category_id,event_type_id=:event_type_id,status=:status,occurred_at=:occurred_at,
             event_started_at=:event_started_at,event_ended_at=:event_ended_at,place_id=:place_id,place_free_text=:place_free_text,
             facts=:facts,measures_text=:measures_text,result_text=:result_text,updated_at=NOW(),updated_by=:updated_by
             WHERE id=:id AND location_id=:location_id AND deleted_at IS NULL'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId]);
    }

    public function syncStaff(int $entryId, array $userIds): void
    {
        $pdo=Database::connection();
        $pdo->prepare('DELETE FROM dutybook_entry_staff WHERE entry_id=:entry_id')->execute(['entry_id'=>$entryId]);
        $stmt=$pdo->prepare('INSERT IGNORE INTO dutybook_entry_staff (entry_id,user_id) VALUES (:entry_id,:user_id)');
        foreach (array_unique(array_map('intval',$userIds)) as $userId) if($userId>0) $stmt->execute(['entry_id'=>$entryId,'user_id'=>$userId]);
    }

    public function replacePeople(int $entryId, array $people): void
    {
        $pdo=Database::connection();
        $pdo->prepare('DELETE FROM dutybook_entry_people WHERE entry_id=:entry_id')->execute(['entry_id'=>$entryId]);
        $stmt=$pdo->prepare(
            'INSERT INTO dutybook_entry_people (entry_id,role_id,person_type,first_name,last_name,birth_date,area,internal_identifier,created_at)
             VALUES (:entry_id,:role_id,:person_type,:first_name,:last_name,:birth_date,:area,:internal_identifier,NOW())'
        );
        foreach ($people as $person) {
            $stmt->execute([
                'entry_id'=>$entryId,'role_id'=>$person['role_id'] ?: null,'person_type'=>$person['person_type'] ?: null,
                'first_name'=>$person['first_name'] ?: null,'last_name'=>$person['last_name'] ?: null,
                'birth_date'=>$person['birth_date'] ?: null,'area'=>$person['area'] ?: null,
                'internal_identifier'=>$person['internal_identifier'] ?: null,
            ]);
        }
    }

    public function syncMeasures(int $entryId, array $measureIds): void
    {
        $pdo=Database::connection();
        $pdo->prepare('DELETE FROM dutybook_entry_measures WHERE entry_id=:entry_id')->execute(['entry_id'=>$entryId]);
        $stmt=$pdo->prepare('INSERT IGNORE INTO dutybook_entry_measures (entry_id,measure_id) VALUES (:entry_id,:measure_id)');
        foreach (array_unique(array_map('intval',$measureIds)) as $id) if($id>0) $stmt->execute(['entry_id'=>$entryId,'measure_id'=>$id]);
    }

    public function replaceExternal(int $entryId, array $rows): void
    {
        $pdo=Database::connection();
        $pdo->prepare('DELETE FROM dutybook_entry_external WHERE entry_id=:entry_id')->execute(['entry_id'=>$entryId]);
        $stmt=$pdo->prepare(
            'INSERT INTO dutybook_entry_external
             (entry_id,organization_id,organization_name,contact_name,notified_at,feedback,reference_number,created_at)
             VALUES (:entry_id,:organization_id,:organization_name,:contact_name,:notified_at,:feedback,:reference_number,NOW())'
        );
        foreach ($rows as $row) {
            $stmt->execute([
                'entry_id'=>$entryId,'organization_id'=>$row['organization_id'] ?: null,
                'organization_name'=>$row['organization_name'] ?: null,'contact_name'=>$row['contact_name'] ?: null,
                'notified_at'=>$row['notified_at'] ?: null,'feedback'=>$row['feedback'] ?: null,
                'reference_number'=>$row['reference_number'] ?: null,
            ]);
        }
    }

    public function saveDynamicValues(int $entryId, array $values): void
    {
        $pdo=Database::connection();
        $pdo->prepare('DELETE FROM dynamic_values WHERE module="dutybook" AND record_id=:record_id')->execute(['record_id'=>$entryId]);
        $stmt=$pdo->prepare(
            'INSERT INTO dynamic_values (module,record_id,field_id,value_json,created_at,updated_at)
             VALUES ("dutybook",:record_id,:field_id,:value_json,NOW(),NOW())'
        );
        foreach($values as $fieldId=>$value){
            $stmt->execute(['record_id'=>$entryId,'field_id'=>(int)$fieldId,'value_json'=>json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function entryStaff(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT u.id,u.first_name,u.last_name,u.personnel_number FROM dutybook_entry_staff es
             JOIN users u ON u.id=es.user_id WHERE es.entry_id=:entry_id ORDER BY u.last_name,u.first_name'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entryPeople(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT p.*,r.name AS role_name FROM dutybook_entry_people p
             LEFT JOIN person_roles r ON r.id=p.role_id WHERE p.entry_id=:entry_id ORDER BY p.id'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entryMeasures(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT m.* FROM dutybook_entry_measures em JOIN measures m ON m.id=em.measure_id
             WHERE em.entry_id=:entry_id ORDER BY m.sort_order,m.name'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entryExternal(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT x.*,o.name AS organization_master_name,o.organization_type
             FROM dutybook_entry_external x LEFT JOIN external_organizations o ON o.id=x.organization_id
             WHERE x.entry_id=:entry_id ORDER BY x.id'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dynamicValues(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT v.field_id,v.value_json,f.label,f.field_type FROM dynamic_values v
             JOIN dynamic_fields f ON f.id=v.field_id
             WHERE v.module="dutybook" AND v.record_id=:entry_id ORDER BY f.sort_order,f.id'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        $result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $row['value']=json_decode((string)$row['value_json'],true);
            $result[(int)$row['field_id']]=$row;
        }
        return $result;
    }

    public function attachments(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS uploader_name
             FROM attachments a LEFT JOIN users u ON u.id=a.uploaded_by
             WHERE a.module="dutybook" AND a.record_id=:record_id AND a.deleted_at IS NULL
             ORDER BY a.uploaded_at,a.id'
        );
        $stmt->execute(['record_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addenda(int $entryId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS user_name
             FROM dutybook_addenda a LEFT JOIN users u ON u.id=a.created_by
             WHERE a.entry_id=:entry_id ORDER BY a.created_at,a.id'
        );
        $stmt->execute(['entry_id'=>$entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAddendum(int $entryId,string $reason,array $old,array $new,int $userId): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO dutybook_addenda (entry_id,reason,original_content,new_content,created_by,created_at)
             VALUES (:entry_id,:reason,:old_content,:new_content,:created_by,NOW())'
        );
        $stmt->execute([
            'entry_id'=>$entryId,'reason'=>$reason,
            'old_content'=>json_encode($old,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'new_content'=>json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'created_by'=>$userId
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function openEntriesForSession(int $locationId,int $sessionId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT e.id,e.occurred_at,e.status,e.facts,t.name AS event_type_name
             FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             WHERE e.location_id=:location_id AND e.deleted_at IS NULL
               AND e.status IN ("open","in_progress","handover")
               AND (e.shift_session_id=:session_id OR EXISTS (
                   SELECT 1 FROM shift_handover_entries he
                   JOIN shift_handovers h ON h.id=he.handover_id
                   WHERE he.entry_id=e.id AND h.status="completed"
               ))
             ORDER BY e.occurred_at,e.id'
        );
        $stmt->execute(['location_id'=>$locationId,'session_id'=>$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function handoverForSession(int $sessionId): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT h.*,s.name AS to_shift_name,
                    CONCAT(out_u.first_name," ",out_u.last_name) AS outgoing_name,
                    CONCAT(in_u.first_name," ",in_u.last_name) AS incoming_name
             FROM shift_handovers h
             JOIN shifts s ON s.id=h.to_shift_id
             LEFT JOIN users out_u ON out_u.id=h.outgoing_confirmed_by
             LEFT JOIN users in_u ON in_u.id=h.incoming_confirmed_by
             WHERE h.from_shift_session_id=:session_id ORDER BY h.id DESC LIMIT 1'
        );
        $stmt->execute(['session_id'=>$sessionId]);
        $handover=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$handover) return null;
        $handover['entries']=$this->handoverEntries((int)$handover['id']);
        return $handover;
    }

    public function handoverEntries(int $handoverId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT he.*,e.occurred_at,e.facts,e.status,t.name AS event_type_name,
                    CONCAT(u.first_name," ",u.last_name) AS assigned_user_name
             FROM shift_handover_entries he
             JOIN dutybook_entries e ON e.id=he.entry_id
             LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             LEFT JOIN users u ON u.id=he.assigned_to_user_id
             WHERE he.handover_id=:handover_id ORDER BY e.occurred_at,e.id'
        );
        $stmt->execute(['handover_id'=>$handoverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(int $locationId,array $filters,int $page=1,int $perPage=30): array
    {
        $where=['e.location_id=:location_id','e.deleted_at IS NULL'];
        $params=['location_id'=>$locationId];

        foreach(['from'=>'e.duty_date >= :from','to'=>'e.duty_date <= :to','status'=>'e.status = :status','shift_id'=>'e.shift_id = :shift_id','category_id'=>'e.category_id = :category_id','event_type_id'=>'e.event_type_id = :event_type_id','creator_id'=>'e.created_by = :creator_id'] as $key=>$condition){
            if(!empty($filters[$key])){$where[]=$condition;$params[$key]=$filters[$key];}
        }
        if(!empty($filters['staff_id'])){$where[]='EXISTS (SELECT 1 FROM dutybook_entry_staff es WHERE es.entry_id=e.id AND es.user_id=:staff_id)';$params['staff_id']=(int)$filters['staff_id'];}
        if(!empty($filters['attachments']))$where[]='EXISTS (SELECT 1 FROM attachments a WHERE a.module="dutybook" AND a.record_id=e.id AND a.deleted_at IS NULL)';
        if(!empty($filters['q'])){$where[]='(e.facts LIKE :q OR e.measures_text LIKE :q OR e.result_text LIKE :q)';$params['q']='%'.$filters['q'].'%';}

        $clause=implode(' AND ',$where);
        $count=Database::connection()->prepare("SELECT COUNT(*) FROM dutybook_entries e WHERE {$clause}");
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $page=max(1,$page);$offset=($page-1)*$perPage;

        $stmt=Database::connection()->prepare(
            "SELECT e.*,t.name AS event_type_name,c.name AS category_name,s.name AS shift_name,
                    CONCAT(u.first_name,' ',u.last_name) AS creator_name
             FROM dutybook_entries e
             LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             LEFT JOIN dutybook_categories c ON c.id=e.category_id
             LEFT JOIN shifts s ON s.id=e.shift_id
             LEFT JOIN users u ON u.id=e.created_by
             WHERE {$clause}
             ORDER BY e.occurred_at DESC,e.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }
}

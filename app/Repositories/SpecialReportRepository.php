<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;
use WKS\Core\HttpException;

final class SpecialReportRepository
{
    public function types(int $locationId,bool $activeOnly=true): array
    {
        $sql='SELECT * FROM special_report_types WHERE location_id=:location_id';
        if($activeOnly)$sql.=' AND active=1';
        $sql.=' ORDER BY sort_order,name';
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['location_id'=>$locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function type(int $id,int $locationId): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM special_report_types WHERE id=:id AND location_id=:location_id LIMIT 1');
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function saveType(?int $id,int $locationId,array $data,int $userId): int
    {
        if($id===null){
            $stmt=Database::connection()->prepare(
                'INSERT INTO special_report_types (location_id,name,code,sort_order,active,force_section_enabled,created_at,updated_at,created_by,updated_by)
                 VALUES (:location_id,:name,:code,:sort_order,:active,:force_section_enabled,NOW(),NOW(),:created_by,:updated_by)'
            );
            $stmt->execute($data+['location_id'=>$locationId,'created_by'=>$userId,'updated_by'=>$userId]);
            return (int)Database::connection()->lastInsertId();
        }
        $stmt=Database::connection()->prepare(
            'UPDATE special_report_types SET name=:name,code=:code,sort_order=:sort_order,active=:active,
             force_section_enabled=:force_section_enabled,updated_at=NOW(),updated_by=:user_id
             WHERE id=:id AND location_id=:location_id'
        );
        $stmt->execute($data+['id'=>$id,'location_id'=>$locationId,'user_id'=>$userId]);
        return $id;
    }

    public function allocateNumber(int $year): int
    {
        $pdo=Database::connection();
        $pdo->prepare(
            'INSERT INTO special_report_sequences (report_year,next_number,updated_at)
             VALUES (:year,1,NOW()) ON DUPLICATE KEY UPDATE report_year=report_year'
        )->execute(['year'=>$year]);
        $stmt=$pdo->prepare('SELECT next_number FROM special_report_sequences WHERE report_year=:year FOR UPDATE');
        $stmt->execute(['year'=>$year]);$number=(int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE special_report_sequences SET next_number=:next,updated_at=NOW() WHERE report_year=:year')
            ->execute(['next'=>$number+1,'year'=>$year]);
        return $number;
    }

    public function create(array $data): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO special_reports
             (location_id,report_type_id,report_year,report_number,incident_date,incident_started_at,incident_ended_at,
              place_id,place_free_text,facts,measures_text,result_text,status,source_dutybook_entry_id,current_version,
              edit_locked_at,created_at,updated_at,created_by,updated_by)
             VALUES
             (:location_id,:report_type_id,:report_year,:report_number,:incident_date,:incident_started_at,:incident_ended_at,
              :place_id,:place_free_text,:facts,:measures_text,:result_text,:status,:source_dutybook_entry_id,0,
              :edit_locked_at,NOW(),NOW(),:created_by,:updated_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function update(int $id,int $locationId,array $data): void
    {
        $stmt=Database::connection()->prepare(
            'UPDATE special_reports SET report_type_id=:report_type_id,incident_date=:incident_date,
             incident_started_at=:incident_started_at,incident_ended_at=:incident_ended_at,place_id=:place_id,
             place_free_text=:place_free_text,facts=:facts,measures_text=:measures_text,result_text=:result_text,
             status=:status,updated_at=NOW(),updated_by=:updated_by
             WHERE id=:id AND location_id=:location_id AND deleted_at IS NULL'
        );
        $stmt->execute($data+['id'=>$id,'location_id'=>$locationId]);
    }

    public function find(int $id,int $locationId): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT r.*,t.name AS report_type_name,t.code AS report_type_code,t.force_section_enabled,
                    p.name AS place_name,CONCAT(u.first_name," ",u.last_name) AS creator_name,
                    CONCAT(rv.first_name," ",rv.last_name) AS reviewer_name
             FROM special_reports r
             JOIN special_report_types t ON t.id=r.report_type_id
             LEFT JOIN places p ON p.id=r.place_id
             LEFT JOIN users u ON u.id=r.created_by
             LEFT JOIN users rv ON rv.id=r.reviewed_by
             WHERE r.id=:id AND r.location_id=:location_id AND r.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id'=>$id,'location_id'=>$locationId]);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;
        $r['staff']=$this->staff($id);$r['people']=$this->people($id);$r['witnesses']=$this->witnesses($id);
        $r['external']=$this->external($id);$r['injury']=$this->injury($id);$r['force_actions']=$this->forceActions($id);
        $r['dynamic_values']=$this->dynamicValues($id);$r['attachments']=$this->attachments($id);
        $r['revision_requests']=$this->revisionRequests($id);$r['versions']=$this->versions($id);$r['addenda']=$this->addenda($id);
        return $r;
    }

    public function syncStaff(int $reportId,array $userIds): void
    {
        $pdo=Database::connection();$pdo->prepare('DELETE FROM special_report_staff WHERE report_id=:id')->execute(['id'=>$reportId]);
        $stmt=$pdo->prepare('INSERT IGNORE INTO special_report_staff (report_id,user_id) VALUES (:report_id,:user_id)');
        foreach(array_unique(array_map('intval',$userIds)) as $id)if($id>0)$stmt->execute(['report_id'=>$reportId,'user_id'=>$id]);
    }

    public function replacePeople(int $reportId,array $rows): void
    {
        $pdo=Database::connection();$pdo->prepare('DELETE FROM special_report_people WHERE report_id=:id')->execute(['id'=>$reportId]);
        $stmt=$pdo->prepare(
            'INSERT INTO special_report_people (report_id,role_id,person_type,first_name,last_name,birth_date,area,internal_identifier,created_at)
             VALUES (:report_id,:role_id,:person_type,:first_name,:last_name,:birth_date,:area,:internal_identifier,NOW())'
        );
        foreach($rows as $x)$stmt->execute($x+['report_id'=>$reportId]);
    }

    public function replaceWitnesses(int $reportId,array $rows): array
    {
        $pdo=Database::connection();$pdo->prepare('DELETE FROM special_report_witnesses WHERE report_id=:id')->execute(['id'=>$reportId]);
        $stmt=$pdo->prepare(
            'INSERT INTO special_report_witnesses (report_id,name,contact_details,statement_summary,written_statement,created_at)
             VALUES (:report_id,:name,:contact_details,:statement_summary,:written_statement,NOW())'
        );
        $ids=[];
        foreach($rows as $x){$stmt->execute($x+['report_id'=>$reportId]);$ids[]=(int)$pdo->lastInsertId();}
        return $ids;
    }

    public function replaceExternal(int $reportId,array $rows): void
    {
        $pdo=Database::connection();$pdo->prepare('DELETE FROM special_report_external WHERE report_id=:id')->execute(['id'=>$reportId]);
        $stmt=$pdo->prepare(
            'INSERT INTO special_report_external (report_id,organization_id,organization_name,contact_name,notified_at,feedback,reference_number,created_at)
             VALUES (:report_id,:organization_id,:organization_name,:contact_name,:notified_at,:feedback,:reference_number,NOW())'
        );
        foreach($rows as $x)$stmt->execute($x+['report_id'=>$reportId]);
    }

    public function saveInjury(int $reportId,array $data): void
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO special_report_injuries (report_id,injury_present,description,medical_care,treating_entity,treated_at,updated_at)
             VALUES (:report_id,:injury_present,:description,:medical_care,:treating_entity,:treated_at,NOW())
             ON DUPLICATE KEY UPDATE injury_present=VALUES(injury_present),description=VALUES(description),medical_care=VALUES(medical_care),
             treating_entity=VALUES(treating_entity),treated_at=VALUES(treated_at),updated_at=NOW()'
        );$stmt->execute($data+['report_id'=>$reportId]);
    }

    public function replaceForceActions(int $reportId,array $rows): void
    {
        $pdo=Database::connection();$ids=$pdo->prepare('SELECT id FROM special_report_force_actions WHERE report_id=:id');
        $ids->execute(['id'=>$reportId]);
        foreach($ids->fetchAll(PDO::FETCH_COLUMN) as $id)$pdo->prepare('DELETE FROM special_report_force_staff WHERE force_action_id=:id')->execute(['id'=>$id]);
        $pdo->prepare('DELETE FROM special_report_force_actions WHERE report_id=:id')->execute(['id'=>$reportId]);
        $action=$pdo->prepare(
            'INSERT INTO special_report_force_actions (report_id,action_type,started_at,ended_at,justification,result_text,created_at)
             VALUES (:report_id,:action_type,:started_at,:ended_at,:justification,:result_text,NOW())'
        );
        $staff=$pdo->prepare('INSERT IGNORE INTO special_report_force_staff (force_action_id,user_id) VALUES (:action_id,:user_id)');
        foreach($rows as $x){
            $staffIds=$x['staff_ids']??[];unset($x['staff_ids']);$action->execute($x+['report_id'=>$reportId]);
            $actionId=(int)$pdo->lastInsertId();
            foreach(array_unique(array_map('intval',$staffIds)) as $uid)if($uid>0)$staff->execute(['action_id'=>$actionId,'user_id'=>$uid]);
        }
    }

    public function saveDynamicValues(int $reportId,array $values): void
    {
        $pdo=Database::connection();$pdo->prepare('DELETE FROM dynamic_values WHERE module="special_report" AND record_id=:id')->execute(['id'=>$reportId]);
        $stmt=$pdo->prepare(
            'INSERT INTO dynamic_values (module,record_id,field_id,value_json,created_at,updated_at)
             VALUES ("special_report",:record_id,:field_id,:value_json,NOW(),NOW())'
        );
        foreach($values as $fid=>$value)$stmt->execute(['record_id'=>$reportId,'field_id'=>(int)$fid,'value_json'=>json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }

    public function staff(int $id): array
    {
        $s=Database::connection()->prepare('SELECT u.id,u.first_name,u.last_name,u.personnel_number FROM special_report_staff x JOIN users u ON u.id=x.user_id WHERE x.report_id=:id ORDER BY u.last_name,u.first_name');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function people(int $id): array
    {
        $s=Database::connection()->prepare('SELECT x.*,r.name AS role_name FROM special_report_people x LEFT JOIN person_roles r ON r.id=x.role_id WHERE x.report_id=:id ORDER BY x.id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function witnesses(int $id): array
    {
        $s=Database::connection()->prepare('SELECT * FROM special_report_witnesses WHERE report_id=:id ORDER BY id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function external(int $id): array
    {
        $s=Database::connection()->prepare('SELECT x.*,o.name AS organization_master_name,o.organization_type FROM special_report_external x LEFT JOIN external_organizations o ON o.id=x.organization_id WHERE x.report_id=:id ORDER BY x.id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function injury(int $id): ?array
    {
        $s=Database::connection()->prepare('SELECT * FROM special_report_injuries WHERE report_id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function forceActions(int $id): array
    {
        $s=Database::connection()->prepare('SELECT * FROM special_report_force_actions WHERE report_id=:id ORDER BY id');$s->execute(['id'=>$id]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $staff=Database::connection()->prepare('SELECT u.id,u.first_name,u.last_name FROM special_report_force_staff x JOIN users u ON u.id=x.user_id WHERE x.force_action_id=:id ORDER BY u.last_name,u.first_name');
        foreach($rows as &$row){$staff->execute(['id'=>$row['id']]);$row['staff']=$staff->fetchAll(PDO::FETCH_ASSOC);}
        return $rows;
    }
    public function dynamicValues(int $id): array
    {
        $s=Database::connection()->prepare('SELECT v.field_id,v.value_json,f.label,f.field_type FROM dynamic_values v JOIN dynamic_fields f ON f.id=v.field_id WHERE v.module="special_report" AND v.record_id=:id ORDER BY f.sort_order,f.id');$s->execute(['id'=>$id]);$out=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $x){$x['value']=json_decode((string)$x['value_json'],true);$out[(int)$x['field_id']]=$x;}return $out;
    }
    public function attachments(int $id): array
    {
        $s=Database::connection()->prepare('SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS uploader_name FROM attachments a LEFT JOIN users u ON u.id=a.uploaded_by WHERE a.module="special_report" AND a.record_id=:id AND a.deleted_at IS NULL ORDER BY a.uploaded_at,a.id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function revisionRequests(int $id): array
    {
        $s=Database::connection()->prepare('SELECT x.*,CONCAT(c.first_name," ",c.last_name) AS creator_name,CONCAT(d.first_name," ",d.last_name) AS completed_name FROM special_report_revision_requests x LEFT JOIN users c ON c.id=x.created_by LEFT JOIN users d ON d.id=x.completed_by WHERE x.report_id=:id ORDER BY x.created_at,x.id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function versions(int $id): array
    {
        $s=Database::connection()->prepare('SELECT v.*,CONCAT(u.first_name," ",u.last_name) AS user_name FROM special_report_versions v LEFT JOIN users u ON u.id=v.created_by WHERE v.report_id=:id ORDER BY v.version_number DESC');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addenda(int $id): array
    {
        $s=Database::connection()->prepare('SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS user_name FROM special_report_addenda a LEFT JOIN users u ON u.id=a.created_by WHERE a.report_id=:id ORDER BY a.version_number,a.id');$s->execute(['id'=>$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addRevisionRequest(int $reportId,string $text,int $userId): int
    {
        $s=Database::connection()->prepare('INSERT INTO special_report_revision_requests (report_id,request_text,status,created_by,created_at) VALUES (:report_id,:text,"open",:user_id,NOW())');
        $s->execute(['report_id'=>$reportId,'text'=>$text,'user_id'=>$userId]);return (int)Database::connection()->lastInsertId();
    }

    public function completeRevisionRequest(int $reportId,int $requestId,int $userId): void
    {
        $s=Database::connection()->prepare('UPDATE special_report_revision_requests SET status="done",completed_by=:user_id,completed_at=NOW() WHERE id=:id AND report_id=:report_id AND status="open"');
        $s->execute(['user_id'=>$userId,'id'=>$requestId,'report_id'=>$reportId]);
    }

    public function openRevisionCount(int $id): int
    {
        $s=Database::connection()->prepare('SELECT COUNT(*) FROM special_report_revision_requests WHERE report_id=:id AND status="open"');$s->execute(['id'=>$id]);return (int)$s->fetchColumn();
    }

    public function saveVersion(int $reportId,int $version,array $snapshot,int $userId,?string $pdfPath=null,?string $docxPath=null): void
    {
        $s=Database::connection()->prepare(
            'INSERT INTO special_report_versions (report_id,version_number,snapshot_json,pdf_path,docx_path,created_by,created_at)
             VALUES (:report_id,:version,:snapshot,:pdf,:docx,:user_id,NOW())'
        );$s->execute(['report_id'=>$reportId,'version'=>$version,'snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'pdf'=>$pdfPath,'docx'=>$docxPath,'user_id'=>$userId]);
        Database::connection()->prepare('UPDATE special_reports SET current_version=:version WHERE id=:id')->execute(['version'=>$version,'id'=>$reportId]);
    }

    public function search(int $locationId,array $filters,int $page=1,int $perPage=30): array
    {
        $where=['r.location_id=:location_id','r.deleted_at IS NULL'];$params=['location_id'=>$locationId];
        foreach(['from'=>'r.incident_date>=:from','to'=>'r.incident_date<=:to','status'=>'r.status=:status','type_id'=>'r.report_type_id=:type_id','creator_id'=>'r.created_by=:creator_id'] as $key=>$condition){
            if(!empty($filters[$key])){$where[]=$condition;$params[$key]=$filters[$key];}
        }
        if(!empty($filters['staff_id'])){$where[]='EXISTS (SELECT 1 FROM special_report_staff s WHERE s.report_id=r.id AND s.user_id=:staff_id)';$params['staff_id']=(int)$filters['staff_id'];}
        if(!empty($filters['person'])){$where[]='EXISTS (SELECT 1 FROM special_report_people p WHERE p.report_id=r.id AND CONCAT_WS(" ",p.first_name,p.last_name) LIKE :person)';$params['person']='%'.$filters['person'].'%';}
        if(!empty($filters['attachments']))$where[]='EXISTS (SELECT 1 FROM attachments a WHERE a.module="special_report" AND a.record_id=r.id AND a.deleted_at IS NULL)';
        if(!empty($filters['q'])){$where[]='(r.facts LIKE :q_facts OR r.measures_text LIKE :q_measures OR r.result_text LIKE :q_result)';$like='%'.$filters['q'].'%';$params['q_facts']=$like;$params['q_measures']=$like;$params['q_result']=$like;}
        $clause=implode(' AND ',$where);
        $c=Database::connection()->prepare("SELECT COUNT(*) FROM special_reports r WHERE {$clause}");$c->execute($params);$total=(int)$c->fetchColumn();
        $page=max(1,$page);$offset=($page-1)*$perPage;
        $s=Database::connection()->prepare(
            "SELECT r.*,t.name AS report_type_name,CONCAT(u.first_name,' ',u.last_name) AS creator_name
             FROM special_reports r JOIN special_report_types t ON t.id=r.report_type_id LEFT JOIN users u ON u.id=r.created_by
             WHERE {$clause} ORDER BY r.incident_date DESC,r.report_number DESC LIMIT {$perPage} OFFSET {$offset}"
        );$s->execute($params);
        return ['items'=>$s->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }
}

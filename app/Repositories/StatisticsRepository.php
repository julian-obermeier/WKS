<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class StatisticsRepository
{
    public function summary(int $locationId,string $from,string $to,?int $eventTypeId=null,?int $reportTypeId=null): array
    {
        $pdo=Database::connection();$params=['location_id'=>$locationId,'from'=>$from,'to'=>$to];
        $dutyFilter=$eventTypeId?' AND event_type_id=:event_type_id':'';$reportFilter=$reportTypeId?' AND report_type_id=:report_type_id':'';
        if($eventTypeId)$params['event_type_id']=$eventTypeId;if($reportTypeId)$params['report_type_id']=$reportTypeId;
        $scalar=static function(string $sql,array $needed=[]) use($pdo,$params): float {
            $bound=[];foreach($params as $key=>$value)if(str_contains($sql,':'.$key))$bound[$key]=$value;
            $s=$pdo->prepare($sql);$s->execute($bound);return (float)$s->fetchColumn();
        };
        return [
            'dutybook'=>(int)$scalar('SELECT COUNT(*) FROM dutybook_entries WHERE location_id=:location_id AND deleted_at IS NULL AND duty_date BETWEEN :from AND :to'.$dutyFilter),
            'special_reports'=>(int)$scalar('SELECT COUNT(*) FROM special_reports WHERE location_id=:location_id AND deleted_at IS NULL AND incident_date BETWEEN :from AND :to'.$reportFilter),
            'unreviewed'=>(int)$scalar('SELECT COUNT(*) FROM special_reports WHERE location_id=:location_id AND deleted_at IS NULL AND status="completed" AND incident_date BETWEEN :from AND :to'.$reportFilter),
            'valuables_stored'=>(int)$scalar('SELECT COUNT(*) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND DATE(stored_at) BETWEEN :from AND :to'),
            'valuables_released'=>(int)$scalar('SELECT COUNT(*) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND released_at IS NOT NULL AND DATE(released_at) BETWEEN :from AND :to'),
            'house_bans'=>(int)$scalar('SELECT COUNT(*) FROM house_bans WHERE location_id=:location_id AND deleted_at IS NULL AND ban_date BETWEEN :from AND :to'),
            'avg_storage_days'=>$scalar('SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR,stored_at,released_at))/24,0) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND released_at IS NOT NULL AND DATE(released_at) BETWEEN :from AND :to'),
        ];
    }

    public function dutybookByEvent(int $locationId,string $from,string $to,?int $eventTypeId=null): array
    {
        $filter=$eventTypeId?' AND e.event_type_id=:event_type_id':'';
        $stmt=Database::connection()->prepare(
            'SELECT COALESCE(t.name,"Automatisch") AS label,COUNT(*) AS value,t.id AS event_type_id
             FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             WHERE e.location_id=:location_id AND e.deleted_at IS NULL AND e.duty_date BETWEEN :from AND :to'.$filter.'
             GROUP BY t.id,t.name ORDER BY value DESC,label LIMIT 15'
        );
        $params=['location_id'=>$locationId,'from'=>$from,'to'=>$to];if($eventTypeId)$params['event_type_id']=$eventTypeId;
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function specialReportsByType(int $locationId,string $from,string $to,?int $reportTypeId=null): array
    {
        $filter=$reportTypeId?' AND r.report_type_id=:report_type_id':'';
        $stmt=Database::connection()->prepare(
            'SELECT t.name AS label,COUNT(*) AS value,t.id AS type_id
             FROM special_reports r JOIN special_report_types t ON t.id=r.report_type_id
             WHERE r.location_id=:location_id AND r.deleted_at IS NULL AND r.incident_date BETWEEN :from AND :to'.$filter.'
             GROUP BY t.id,t.name ORDER BY value DESC,label LIMIT 15'
        );
        $params=['location_id'=>$locationId,'from'=>$from,'to'=>$to];if($reportTypeId)$params['report_type_id']=$reportTypeId;
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function timeline(int $locationId,string $from,string $to,?int $eventTypeId=null,?int $reportTypeId=null): array
    {
        $dutyFilter=$eventTypeId?' AND event_type_id=:event_type_id':'';
        $reportFilter=$reportTypeId?' AND report_type_id=:report_type_id':'';
        $stmt=Database::connection()->prepare(
            'SELECT d.day,SUM(d.value) AS value FROM (
                SELECT duty_date AS day,COUNT(*) AS value FROM dutybook_entries WHERE location_id=:l1 AND deleted_at IS NULL AND duty_date BETWEEN :f1 AND :t1'.$dutyFilter.' GROUP BY duty_date
                UNION ALL
                SELECT incident_date AS day,COUNT(*) AS value FROM special_reports WHERE location_id=:l2 AND deleted_at IS NULL AND incident_date BETWEEN :f2 AND :t2'.$reportFilter.' GROUP BY incident_date
                UNION ALL
                SELECT DATE(stored_at) AS day,COUNT(*) AS value FROM valuables_records WHERE location_id=:l3 AND deleted_at IS NULL AND DATE(stored_at) BETWEEN :f3 AND :t3 GROUP BY DATE(stored_at)
                UNION ALL
                SELECT ban_date AS day,COUNT(*) AS value FROM house_bans WHERE location_id=:l4 AND deleted_at IS NULL AND ban_date BETWEEN :f4 AND :t4 GROUP BY ban_date
             ) d GROUP BY d.day ORDER BY d.day'
        );
        $params=[
            'l1'=>$locationId,'f1'=>$from,'t1'=>$to,'l2'=>$locationId,'f2'=>$from,'t2'=>$to,
            'l3'=>$locationId,'f3'=>$from,'t3'=>$to,'l4'=>$locationId,'f4'=>$from,'t4'=>$to
        ];
        if($eventTypeId)$params['event_type_id']=$eventTypeId;
        if($reportTypeId)$params['report_type_id']=$reportTypeId;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function drilldown(int $locationId,string $day,?int $eventTypeId=null,?int $reportTypeId=null,array $modules=[],bool $includeReleasedValuables=false): array
    {
        $modules=$modules?:['dutybook','special_reports','valuables','house_bans'];$pdo=Database::connection();$items=[];

        if(in_array('dutybook',$modules,true)){
            $filter=$eventTypeId?' AND e.event_type_id=:event_type_id':'';
            $stmt=$pdo->prepare(
                'SELECT e.id,e.occurred_at,t.name AS type_name,e.facts,e.is_automatic
                 FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
                 WHERE e.location_id=:location_id AND e.deleted_at IS NULL AND e.duty_date=:day'.$filter.'
                 ORDER BY e.occurred_at DESC'
            );
            $params=['location_id'=>$locationId,'day'=>$day];if($eventTypeId)$params['event_type_id']=$eventTypeId;$stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$items[]=[
                'module'=>'Dienstbuch','date'=>$row['occurred_at'],'title'=>$row['type_name']?:'Automatischer Dienstbucheintrag',
                'subtitle'=>mb_strimwidth((string)$row['facts'],0,180,'…'),'url'=>url('dutybook/'.$row['id'])
            ];
        }

        if(in_array('special_reports',$modules,true)){
            $filter=$reportTypeId?' AND r.report_type_id=:report_type_id':'';
            $stmt=$pdo->prepare(
                'SELECT r.id,r.incident_started_at,r.report_year,r.report_number,t.name AS type_name,r.facts
                 FROM special_reports r JOIN special_report_types t ON t.id=r.report_type_id
                 WHERE r.location_id=:location_id AND r.deleted_at IS NULL AND r.incident_date=:day'.$filter.'
                 ORDER BY r.incident_started_at DESC'
            );
            $params=['location_id'=>$locationId,'day'=>$day];if($reportTypeId)$params['report_type_id']=$reportTypeId;$stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$items[]=[
                'module'=>'Sonderbericht','date'=>$row['incident_started_at'],
                'title'=>'SB '.str_pad((string)$row['report_number'],4,'0',STR_PAD_LEFT).'/'.$row['report_year'].' · '.$row['type_name'],
                'subtitle'=>mb_strimwidth((string)$row['facts'],0,180,'…'),'url'=>url('special-reports/'.$row['id'])
            ];
        }

        if(in_array('valuables',$modules,true)){
            $statusFilter=$includeReleasedValuables?'':' AND status="stored"';
            $stmt=$pdo->prepare(
                'SELECT id,stored_at,custody_number,first_name,last_name,status FROM valuables_records
                 WHERE location_id=:location_id AND deleted_at IS NULL AND DATE(stored_at)=:day'.$statusFilter.'
                 ORDER BY stored_at DESC'
            );
            $stmt->execute(['location_id'=>$locationId,'day'=>$day]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$items[]=[
                'module'=>'Wertsachen','date'=>$row['stored_at'],
                'title'=>'Verwahrnr. '.str_pad((string)$row['custody_number'],4,'0',STR_PAD_LEFT).' · '.$row['first_name'].' '.$row['last_name'],
                'subtitle'=>$row['status']==='stored'?'Aktuell eingelagert':'Vollständig ausgelagert','url'=>url('valuables/'.$row['id'])
            ];
        }

        if(in_array('house_bans',$modules,true)){
            $stmt=$pdo->prepare(
                'SELECT id,ban_date,person_name,reason FROM house_bans
                 WHERE location_id=:location_id AND deleted_at IS NULL AND ban_date=:day
                 ORDER BY id DESC'
            );
            $stmt->execute(['location_id'=>$locationId,'day'=>$day]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$items[]=[
                'module'=>'Hausverbot','date'=>$row['ban_date'].' 00:00:00','title'=>$row['person_name'],
                'subtitle'=>mb_strimwidth((string)$row['reason'],0,180,'…'),'url'=>url('house-bans/'.$row['id'])
            ];
        }

        usort($items,static fn(array $a,array $b):int=>strcmp((string)$b['date'],(string)$a['date']));
        return $items;
    }
}

<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class GlobalSearchRepository
{
    public function search(string $q,int $locationId,array $modules): array
    {
        $q=trim($q);if($q==='')return [];$like='%'.$q.'%';$results=[];$pdo=Database::connection();

        if(in_array('dutybook',$modules,true)){
            $stmt=$pdo->prepare(
                'SELECT e.id,e.occurred_at AS event_date,t.name AS type_name,e.facts,
                    (SELECT GROUP_CONCAT(TRIM(CONCAT_WS(" ",p.first_name,p.last_name)) SEPARATOR ", ")
                     FROM dutybook_entry_people p WHERE p.entry_id=e.id) AS people
                 FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
                 WHERE e.location_id=:location_id AND e.deleted_at IS NULL AND
                    (e.facts LIKE :q OR e.measures_text LIKE :q OR e.result_text LIKE :q OR
                     EXISTS (SELECT 1 FROM dutybook_entry_people p WHERE p.entry_id=e.id AND CONCAT_WS(" ",p.first_name,p.last_name) LIKE :q2))
                 ORDER BY e.occurred_at DESC LIMIT 50'
            );
            $stmt->execute(['location_id'=>$locationId,'q'=>$like,'q2'=>$like]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Dienstbuch','module_key'=>'dutybook','date'=>$r['event_date'],
                'title'=>$r['type_name']?:'Dienstbucheintrag','subtitle'=>mb_strimwidth((string)$r['facts'],0,180,'…').($r['people']?' · '.$r['people']:''),
                'url'=>url('dutybook/'.$r['id'])
            ];
        }

        if(in_array('special_reports',$modules,true)){
            $number=preg_replace('/\D+/','',$q);
            $stmt=$pdo->prepare(
                'SELECT r.id,r.incident_started_at AS event_date,r.report_year,r.report_number,t.name AS type_name,r.facts
                 FROM special_reports r JOIN special_report_types t ON t.id=r.report_type_id
                 WHERE r.location_id=:location_id AND r.deleted_at IS NULL AND
                    (r.facts LIKE :q OR r.measures_text LIKE :q OR r.result_text LIKE :q OR t.name LIKE :q
                     OR (:number<>"" AND r.report_number=:number_int)
                     OR EXISTS (SELECT 1 FROM special_report_people p WHERE p.report_id=r.id AND CONCAT_WS(" ",p.first_name,p.last_name) LIKE :q2))
                 ORDER BY r.incident_started_at DESC LIMIT 50'
            );
            $stmt->execute(['location_id'=>$locationId,'q'=>$like,'q2'=>$like,'number'=>$number,'number_int'=>$number===''?0:(int)$number]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Sonderberichte','module_key'=>'special_reports','date'=>$r['event_date'],
                'title'=>'SB '.str_pad((string)$r['report_number'],4,'0',STR_PAD_LEFT).'/'.$r['report_year'].' · '.$r['type_name'],
                'subtitle'=>mb_strimwidth((string)$r['facts'],0,180,'…'),'url'=>url('special-reports/'.$r['id'])
            ];
        }

        if(in_array('valuables',$modules,true)){
            $number=preg_replace('/\D+/','',$q);
            $stmt=$pdo->prepare(
                'SELECT DISTINCT r.id,r.stored_at AS event_date,r.custody_number,r.first_name,r.last_name,r.status
                 FROM valuables_records r
                 LEFT JOIN valuables_containers vc ON vc.valuables_record_id=r.id
                 LEFT JOIN cassettes c ON c.id=vc.cassette_id
                 LEFT JOIN seal_usages s ON s.valuables_record_id=r.id
                 WHERE r.location_id=:location_id AND r.deleted_at IS NULL AND
                    (r.first_name LIKE :q OR r.last_name LIKE :q OR r.internal_identifier LIKE :q
                     OR (:number<>"" AND r.custody_number=:number_int)
                     OR CAST(c.cassette_number AS CHAR)=:exact OR s.seal_number=:exact)
                 ORDER BY r.stored_at DESC LIMIT 50'
            );
            $stmt->execute(['location_id'=>$locationId,'q'=>$like,'number'=>$number,'number_int'=>$number===''?0:(int)$number,'exact'=>$q]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Wertsachen','module_key'=>'valuables','date'=>$r['event_date'],
                'title'=>'Verwahrnr. '.str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT).' · '.$r['first_name'].' '.$r['last_name'],
                'subtitle'=>$r['status']==='stored'?'Aktuell eingelagert':'Vollständig ausgelagert','url'=>url('valuables/'.$r['id'])
            ];
        }

        if(in_array('house_bans',$modules,true)){
            $stmt=$pdo->prepare(
                'SELECT id,ban_date AS event_date,person_name,reason FROM house_bans
                 WHERE location_id=:location_id AND deleted_at IS NULL AND (person_name LIKE :q OR reason LIKE :q)
                 ORDER BY ban_date DESC,id DESC LIMIT 50'
            );
            $stmt->execute(['location_id'=>$locationId,'q'=>$like]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Hausverbote','module_key'=>'house_bans','date'=>$r['event_date'].' 00:00:00',
                'title'=>$r['person_name'],'subtitle'=>mb_strimwidth((string)$r['reason'],0,180,'…'),'url'=>url('house-bans/'.$r['id'])
            ];
        }

        usort($results,static fn(array $a,array $b):int=>strcmp((string)$b['date'],(string)$a['date']));
        return array_slice($results,0,150);
    }
}

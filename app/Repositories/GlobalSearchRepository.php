<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class GlobalSearchRepository
{
    public function search(string $q,int $locationId,array $modules,?string $from=null,?string $to=null): array
    {
        $q=trim($q);if($q==='')return [];$like='%'.$q.'%';$results=[];$pdo=Database::connection();

        if(in_array('dutybook',$modules,true)){
            $stmt=$pdo->prepare(
                'SELECT e.id,e.occurred_at AS event_date,t.name AS type_name,e.facts,
                    (SELECT GROUP_CONCAT(TRIM(CONCAT_WS(" ",p.first_name,p.last_name)) SEPARATOR ", ")
                     FROM dutybook_entry_people p WHERE p.entry_id=e.id) AS people
                 FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
                 WHERE e.location_id=:location_id AND e.deleted_at IS NULL
                    '.($from?' AND e.occurred_at>=:d_from':'').'
                    '.($to?' AND e.occurred_at<=:d_to':'').' AND
                    (e.facts LIKE :dq1 OR e.measures_text LIKE :dq2 OR e.result_text LIKE :dq3 OR
                     EXISTS (SELECT 1 FROM dutybook_entry_people p WHERE p.entry_id=e.id AND CONCAT_WS(" ",p.first_name,p.last_name) LIKE :dq4))
                 ORDER BY e.occurred_at DESC LIMIT 50'
            );
            $params=['location_id'=>$locationId,'dq1'=>$like,'dq2'=>$like,'dq3'=>$like,'dq4'=>$like];
            if($from)$params['d_from']=$from.' 00:00:00';if($to)$params['d_to']=$to.' 23:59:59';
            $stmt->execute($params);
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
                 WHERE r.location_id=:location_id AND r.deleted_at IS NULL
                    '.($from?' AND r.incident_date>=:s_from':'').'
                    '.($to?' AND r.incident_date<=:s_to':'').' AND
                    (r.facts LIKE :sq1 OR r.measures_text LIKE :sq2 OR r.result_text LIKE :sq3 OR t.name LIKE :sq4
                     OR (:number<>"" AND r.report_number=:number_int)
                     OR EXISTS (SELECT 1 FROM special_report_people p WHERE p.report_id=r.id AND CONCAT_WS(" ",p.first_name,p.last_name) LIKE :sq5))
                 ORDER BY r.incident_started_at DESC LIMIT 50'
            );
            $params=['location_id'=>$locationId,'sq1'=>$like,'sq2'=>$like,'sq3'=>$like,'sq4'=>$like,'sq5'=>$like,'number'=>$number,'number_int'=>$number===''?0:(int)$number];
            if($from)$params['s_from']=$from;if($to)$params['s_to']=$to;
            $stmt->execute($params);
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
                 WHERE r.location_id=:location_id AND r.deleted_at IS NULL
                    '.($from?' AND r.stored_at>=:v_from':'').'
                    '.($to?' AND r.stored_at<=:v_to':'').' AND
                    (r.first_name LIKE :vq1 OR r.last_name LIKE :vq2 OR r.internal_identifier LIKE :vq3
                     OR (:number<>"" AND r.custody_number=:number_int)
                     OR CAST(c.cassette_number AS CHAR)=:exact1 OR s.seal_number=:exact2)
                 ORDER BY r.stored_at DESC LIMIT 50'
            );
            $params=['location_id'=>$locationId,'vq1'=>$like,'vq2'=>$like,'vq3'=>$like,'number'=>$number,'number_int'=>$number===''?0:(int)$number,'exact1'=>$q,'exact2'=>$q];
            if($from)$params['v_from']=$from.' 00:00:00';if($to)$params['v_to']=$to.' 23:59:59';
            $stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Wertsachen','module_key'=>'valuables','date'=>$r['event_date'],
                'title'=>'Verwahrnr. '.str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT).' · '.$r['first_name'].' '.$r['last_name'],
                'subtitle'=>$r['status']==='stored'?'Aktuell eingelagert':'Vollständig ausgelagert','url'=>url('valuables/'.$r['id'])
            ];
        }

        if(in_array('house_bans',$modules,true)){
            $stmt=$pdo->prepare(
                'SELECT id,ban_date AS event_date,person_name,reason FROM house_bans
                 WHERE location_id=:location_id AND deleted_at IS NULL
                    '.($from?' AND ban_date>=:h_from':'').'
                    '.($to?' AND ban_date<=:h_to':'').'
                    AND (person_name LIKE :hq1 OR reason LIKE :hq2)
                 ORDER BY ban_date DESC,id DESC LIMIT 50'
            );
            $params=['location_id'=>$locationId,'hq1'=>$like,'hq2'=>$like];
            if($from)$params['h_from']=$from;if($to)$params['h_to']=$to;
            $stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$results[]=[
                'module'=>'Hausverbote','module_key'=>'house_bans','date'=>$r['event_date'].' 00:00:00',
                'title'=>$r['person_name'],'subtitle'=>mb_strimwidth((string)$r['reason'],0,180,'…'),'url'=>url('house-bans/'.$r['id'])
            ];
        }

        usort($results,static fn(array $a,array $b):int=>strcmp((string)$b['date'],(string)$a['date']));
        return array_slice($results,0,150);
    }
}

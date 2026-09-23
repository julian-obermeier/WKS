<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use WKS\Core\Database;
use WKS\Core\HttpException;

final class LocationProvisioningService
{
    public function provision(int $locationId,?int $userId=null): array
    {
        if($locationId<1)throw new HttpException(422,'Ungültiger Standort.');
        $pdo=Database::connection();
        $exists=$pdo->prepare('SELECT 1 FROM locations WHERE id=:id LIMIT 1');
        $exists->execute(['id'=>$locationId]);
        if(!$exists->fetchColumn())throw new HttpException(404,'Standort nicht gefunden.');

        $result=[
            'shifts'=>$this->shifts($pdo,$locationId,$userId),
            'dutybook_event'=>$this->defaultDutybookEvent($pdo,$locationId,$userId),
            'special_report_types'=>$this->specialReportTypes($pdo,$locationId,$userId),
            'cassettes'=>$this->cassettes($pdo,$locationId),
            'storage_locations'=>$this->storageLocations($pdo,$locationId),
            'automatic_rules'=>$this->automaticRules($pdo,$locationId,$userId),
        ];
        return $result;
    }

    private function shifts(PDO $pdo,int $locationId,?int $userId): int
    {
        $before=$this->count($pdo,'shifts',$locationId);
        $stmt=$pdo->prepare(
            'INSERT IGNORE INTO shifts
             (location_id,code,name,start_time,end_time,crosses_midnight,sort_order,active,created_at,updated_at,created_by,updated_by)
             VALUES (:location_id,:code,:name,:start_time,:end_time,:crosses_midnight,:sort_order,1,NOW(),NOW(),:created_by,:updated_by)'
        );
        foreach([
            ['F','Früh','05:40:00','13:58:00',0,10],
            ['S','Spät','13:40:00','21:58:00',0,20],
            ['N','Nacht','21:40:00','05:58:00',1,30],
            ['T','Tag','08:00:00','16:18:00',0,40],
        ] as [$code,$name,$start,$end,$crosses,$sort]){
            $stmt->execute([
                'location_id'=>$locationId,'code'=>$code,'name'=>$name,'start_time'=>$start,'end_time'=>$end,
                'crosses_midnight'=>$crosses,'sort_order'=>$sort,'created_by'=>$userId,'updated_by'=>$userId,
            ]);
        }
        return $this->count($pdo,'shifts',$locationId)-$before;
    }

    private function defaultDutybookEvent(PDO $pdo,int $locationId,?int $userId): int
    {
        $count=$pdo->prepare('SELECT COUNT(*) FROM dutybook_event_types WHERE location_id=:location_id');
        $count->execute(['location_id'=>$locationId]);
        if((int)$count->fetchColumn()>0)return 0;

        $category=(int)$pdo->query(
            "SELECT id FROM dutybook_categories WHERE name='Sonstiges' ORDER BY location_id IS NULL DESC,id LIMIT 1"
        )->fetchColumn();
        if($category<1)throw new HttpException(500,'Globale Dienstbuchkategorie „Sonstiges“ fehlt.');

        $stmt=$pdo->prepare(
            'INSERT INTO dutybook_event_types
             (location_id,category_id,name,sort_order,active,offer_special_report,offer_valuables,auto_entry_enabled,created_at,updated_at,created_by,updated_by)
             VALUES (:location_id,:category_id,"Allgemeiner Vorgang",10,1,1,1,1,NOW(),NOW(),:created_by,:updated_by)'
        );
        $stmt->execute(['location_id'=>$locationId,'category_id'=>$category,'created_by'=>$userId,'updated_by'=>$userId]);
        return 1;
    }

    private function specialReportTypes(PDO $pdo,int $locationId,?int $userId): int
    {
        $before=$this->count($pdo,'special_report_types',$locationId);
        $stmt=$pdo->prepare(
            'INSERT IGNORE INTO special_report_types
             (location_id,name,code,sort_order,active,force_section_enabled,force_requirements_json,created_at,updated_at,created_by,updated_by)
             VALUES (:location_id,:name,:code,:sort_order,1,:force,"{}",NOW(),NOW(),:created_by,:updated_by)'
        );
        $types=[
            ['Verbal aggressive Person','verbal_aggressive_person',0],
            ['Körperlich aggressive Person','koerperlich_aggressive_person',1],
            ['Alarmverfolgung','alarmverfolgung',0],
            ['Diebstahl','diebstahl',0],
            ['Sachbeschädigung','sachbeschaedigung',0],
            ['Feuerwehr','feuerwehr',0],
            ['Fixierung','fixierung',1],
            ['GT/GS','gt_gs',0],
            ['Sonstiges','sonstiges',0],
            ['Automaten','automaten',0],
            ['Vorgänge mit Patienten/Besucher','vorgaenge_patienten_besucher',0],
            ['Hilfeleistung','hilfeleistung',0],
            ['HLP','hlp',0],
            ['Parken/Verkehr','parken_verkehr',0],
            ['Balkontüren/Fenster','balkontueren_fenster',0],
            ['Obdachlose Personen','obdachlose_personen',0],
            ['Patientensuche','patientensuche',0],
            ['Wertsachen','wertsachen',0],
            ['Bewachung','bewachung',0],
        ];
        foreach($types as $i=>[$name,$code,$force]){
            $stmt->execute([
                'location_id'=>$locationId,'name'=>$name,'code'=>$code,'sort_order'=>($i+1)*10,'force'=>$force,
                'created_by'=>$userId,'updated_by'=>$userId,
            ]);
        }
        return $this->count($pdo,'special_report_types',$locationId)-$before;
    }

    private function cassettes(PDO $pdo,int $locationId): int
    {
        $before=$this->count($pdo,'cassettes',$locationId);
        $stmt=$pdo->prepare(
            'INSERT IGNORE INTO cassettes (location_id,cassette_number,active,created_at,updated_at)
             VALUES (:location_id,:number,1,NOW(),NOW())'
        );
        for($i=1;$i<=100;$i++)$stmt->execute(['location_id'=>$locationId,'number'=>$i]);
        return $this->count($pdo,'cassettes',$locationId)-$before;
    }

    private function storageLocations(PDO $pdo,int $locationId): int
    {
        $before=$this->count($pdo,'storage_locations',$locationId);
        $stmt=$pdo->prepare(
            'INSERT IGNORE INTO storage_locations
             (location_id,location_type,location_number,label,active,sort_order,created_at,updated_at)
             VALUES (:location_id,:type,:number,:label,1,:sort_order,NOW(),NOW())'
        );
        for($i=1;$i<=50;$i++){
            $stmt->execute(['location_id'=>$locationId,'type'=>'rack','number'=>$i,'label'=>'Regal '.$i,'sort_order'=>$i]);
        }
        $stmt->execute(['location_id'=>$locationId,'type'=>'floor','number'=>null,'label'=>'Boden','sort_order'=>1000]);
        return $this->count($pdo,'storage_locations',$locationId)-$before;
    }

    private function automaticRules(PDO $pdo,int $locationId,?int $userId): int
    {
        $before=$this->count($pdo,'dutybook_automatic_rules',$locationId);
        $stmt=$pdo->prepare(
            'INSERT IGNORE INTO dutybook_automatic_rules (location_id,event_code,enabled,updated_at,updated_by)
             VALUES (:location_id,:event_code,1,NOW(),:updated_by)'
        );
        foreach(['shift_start','shift_handover','shift_end','special_report_created','valuables_stored','valuables_released'] as $eventCode){
            $stmt->execute(['location_id'=>$locationId,'event_code'=>$eventCode,'updated_by'=>$userId]);
        }
        return $this->count($pdo,'dutybook_automatic_rules',$locationId)-$before;
    }

    private function count(PDO $pdo,string $table,int $locationId): int
    {
        $allowed=['shifts','dutybook_event_types','special_report_types','cassettes','storage_locations','dutybook_automatic_rules'];
        if(!in_array($table,$allowed,true))throw new \LogicException('Ungültige Provisionierungstabelle.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE location_id=:location_id');
        $stmt->execute(['location_id'=>$locationId]);
        return (int)$stmt->fetchColumn();
    }
}

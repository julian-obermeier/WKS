<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;
use WKS\Core\HttpException;

final class DutybookAutomaticRuleRepository
{
    private const EVENTS=[
        'shift_start'=>'Schichtbeginn',
        'shift_handover'=>'Schichtübergabe',
        'shift_end'=>'Schichtende',
        'special_report_created'=>'Sonderbericht erstellt',
        'valuables_stored'=>'Wertsache eingelagert',
        'valuables_released'=>'Wertsache ausgelagert',
    ];

    public function events(): array
    {
        return self::EVENTS;
    }

    public function all(int $locationId): array
    {
        $out=[];
        foreach(self::EVENTS as $code=>$label){
            $out[$code]=['event_code'=>$code,'label'=>$label,'enabled'=>true];
        }

        $stmt=Database::connection()->prepare(
            'SELECT event_code,enabled FROM dutybook_automatic_rules WHERE location_id=:location_id'
        );
        $stmt->execute(['location_id'=>$locationId]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            if(!isset($out[$row['event_code']]))continue;
            $out[$row['event_code']]['enabled']=(bool)$row['enabled'];
        }
        return $out;
    }

    public function enabled(int $locationId,string $eventCode): bool
    {
        $this->assertEvent($eventCode);
        $stmt=Database::connection()->prepare(
            'SELECT enabled FROM dutybook_automatic_rules
             WHERE location_id=:location_id AND event_code=:event_code LIMIT 1'
        );
        $stmt->execute(['location_id'=>$locationId,'event_code'=>$eventCode]);
        $value=$stmt->fetchColumn();
        return $value===false ? true : (bool)$value;
    }

    public function save(int $locationId,string $eventCode,bool $enabled,int $userId): void
    {
        $this->assertEvent($eventCode);
        Database::connection()->prepare(
            'INSERT INTO dutybook_automatic_rules (location_id,event_code,enabled,updated_at,updated_by)
             VALUES (:location_id,:event_code,:enabled,NOW(),:updated_by)
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=NOW(),updated_by=VALUES(updated_by)'
        )->execute([
            'location_id'=>$locationId,
            'event_code'=>$eventCode,
            'enabled'=>$enabled?1:0,
            'updated_by'=>$userId,
        ]);
    }

    private function assertEvent(string $eventCode): void
    {
        if(!array_key_exists($eventCode,self::EVENTS)){
            throw new HttpException(422,'Unbekannter Typ für automatische Dienstbucheinträge.');
        }
    }
}

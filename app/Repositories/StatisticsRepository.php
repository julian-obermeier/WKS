<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class StatisticsRepository
{
    public function summary(int $locationId,string $from,string $to): array
    {
        $pdo=Database::connection();$params=['location_id'=>$locationId,'from'=>$from,'to'=>$to];
        $scalar=static function(string $sql) use($pdo,$params): float {
            $s=$pdo->prepare($sql);$s->execute($params);return (float)$s->fetchColumn();
        };
        return [
            'dutybook'=>(int)$scalar('SELECT COUNT(*) FROM dutybook_entries WHERE location_id=:location_id AND deleted_at IS NULL AND duty_date BETWEEN :from AND :to'),
            'special_reports'=>(int)$scalar('SELECT COUNT(*) FROM special_reports WHERE location_id=:location_id AND deleted_at IS NULL AND incident_date BETWEEN :from AND :to'),
            'unreviewed'=>(int)$scalar('SELECT COUNT(*) FROM special_reports WHERE location_id=:location_id AND deleted_at IS NULL AND status="completed" AND incident_date BETWEEN :from AND :to'),
            'valuables_stored'=>(int)$scalar('SELECT COUNT(*) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND DATE(stored_at) BETWEEN :from AND :to'),
            'valuables_released'=>(int)$scalar('SELECT COUNT(*) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND released_at IS NOT NULL AND DATE(released_at) BETWEEN :from AND :to'),
            'house_bans'=>(int)$scalar('SELECT COUNT(*) FROM house_bans WHERE location_id=:location_id AND deleted_at IS NULL AND ban_date BETWEEN :from AND :to'),
            'avg_storage_days'=>$scalar('SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR,stored_at,released_at))/24,0) FROM valuables_records WHERE location_id=:location_id AND deleted_at IS NULL AND released_at IS NOT NULL AND DATE(released_at) BETWEEN :from AND :to'),
        ];
    }

    public function dutybookByEvent(int $locationId,string $from,string $to): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT COALESCE(t.name,"Automatisch") AS label,COUNT(*) AS value,t.id AS event_type_id
             FROM dutybook_entries e LEFT JOIN dutybook_event_types t ON t.id=e.event_type_id
             WHERE e.location_id=:location_id AND e.deleted_at IS NULL AND e.duty_date BETWEEN :from AND :to
             GROUP BY t.id,t.name ORDER BY value DESC,label LIMIT 15'
        );
        $stmt->execute(['location_id'=>$locationId,'from'=>$from,'to'=>$to]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function specialReportsByType(int $locationId,string $from,string $to): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT t.name AS label,COUNT(*) AS value,t.id AS type_id
             FROM special_reports r JOIN special_report_types t ON t.id=r.report_type_id
             WHERE r.location_id=:location_id AND r.deleted_at IS NULL AND r.incident_date BETWEEN :from AND :to
             GROUP BY t.id,t.name ORDER BY value DESC,label LIMIT 15'
        );
        $stmt->execute(['location_id'=>$locationId,'from'=>$from,'to'=>$to]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function timeline(int $locationId,string $from,string $to): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT d.day,SUM(d.value) AS value FROM (
                SELECT duty_date AS day,COUNT(*) AS value FROM dutybook_entries WHERE location_id=:l1 AND deleted_at IS NULL AND duty_date BETWEEN :f1 AND :t1 GROUP BY duty_date
                UNION ALL
                SELECT incident_date AS day,COUNT(*) AS value FROM special_reports WHERE location_id=:l2 AND deleted_at IS NULL AND incident_date BETWEEN :f2 AND :t2 GROUP BY incident_date
                UNION ALL
                SELECT DATE(stored_at) AS day,COUNT(*) AS value FROM valuables_records WHERE location_id=:l3 AND deleted_at IS NULL AND DATE(stored_at) BETWEEN :f3 AND :t3 GROUP BY DATE(stored_at)
                UNION ALL
                SELECT ban_date AS day,COUNT(*) AS value FROM house_bans WHERE location_id=:l4 AND deleted_at IS NULL AND ban_date BETWEEN :f4 AND :t4 GROUP BY ban_date
             ) d GROUP BY d.day ORDER BY d.day'
        );
        $stmt->execute([
            'l1'=>$locationId,'f1'=>$from,'t1'=>$to,'l2'=>$locationId,'f2'=>$from,'t2'=>$to,
            'l3'=>$locationId,'f3'=>$from,'t3'=>$to,'l4'=>$locationId,'f4'=>$from,'t4'=>$to
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

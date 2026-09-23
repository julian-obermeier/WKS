<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS dutybook_automatic_rules (
            location_id BIGINT UNSIGNED NOT NULL,
            event_code VARCHAR(80) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (location_id,event_code),
            CONSTRAINT fk_dutybook_auto_rule_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_auto_rule_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_auto_rule_enabled (location_id,enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $events=[
        'shift_start',
        'shift_handover',
        'shift_end',
        'special_report_created',
        'valuables_stored',
        'valuables_released',
    ];
    $locations=$pdo->query('SELECT id FROM locations')->fetchAll(PDO::FETCH_COLUMN);
    $stmt=$pdo->prepare(
        'INSERT IGNORE INTO dutybook_automatic_rules (location_id,event_code,enabled,updated_at)
         VALUES (:location_id,:event_code,1,NOW())'
    );
    foreach($locations as $locationId){
        foreach($events as $eventCode){
            $stmt->execute(['location_id'=>(int)$locationId,'event_code'=>$eventCode]);
        }
    }
};

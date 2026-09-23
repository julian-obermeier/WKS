<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS draft_autosaves (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            module VARCHAR(60) NOT NULL,
            context_key VARCHAR(120) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_draft_autosave (user_id,location_id,module,context_key),
            CONSTRAINT fk_draft_autosave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_draft_autosave_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_draft_autosave_module (module,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $category=(int)$pdo->query(
        "SELECT id FROM dutybook_categories WHERE name='Sonstiges' ORDER BY location_id IS NULL DESC,id LIMIT 1"
    )->fetchColumn();

    if($category>0){
        $locations=$pdo->query('SELECT id FROM locations WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);
        $count=$pdo->prepare('SELECT COUNT(*) FROM dutybook_event_types WHERE location_id=:location_id');
        $insert=$pdo->prepare(
            'INSERT INTO dutybook_event_types
             (location_id,category_id,name,sort_order,active,offer_special_report,offer_valuables,auto_entry_enabled,created_at,updated_at)
             VALUES (:location_id,:category_id,"Allgemeiner Vorgang",10,1,1,1,1,NOW(),NOW())'
        );
        foreach($locations as $locationId){
            $count->execute(['location_id'=>(int)$locationId]);
            if((int)$count->fetchColumn()===0)$insert->execute(['location_id'=>(int)$locationId,'category_id'=>$category]);
        }
    }
};

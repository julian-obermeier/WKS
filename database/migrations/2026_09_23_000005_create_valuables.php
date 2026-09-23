<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS valuables_sequence (
            id TINYINT UNSIGNED PRIMARY KEY,
            next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS storage_locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            location_type VARCHAR(20) NOT NULL,
            location_number INT UNSIGNED NULL,
            label VARCHAR(100) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_storage_label (location_id,label),
            CONSTRAINT fk_storage_locations_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_storage_locations_active (location_id,active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cassettes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            cassette_number INT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cassette_number (location_id,cassette_number),
            CONSTRAINT fk_cassettes_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_cassettes_active (location_id,active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS valuables_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            custody_number BIGINT UNSIGNED NOT NULL UNIQUE,
            location_id BIGINT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            birth_date DATE NOT NULL,
            internal_identifier VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'stored',
            handed_over_by_type VARCHAR(40) NOT NULL,
            handed_over_by_name VARCHAR(200) NOT NULL,
            handed_over_by_organization VARCHAR(200) NULL,
            handed_over_by_note VARCHAR(255) NULL,
            storage_note LONGTEXT NULL,
            stored_by BIGINT UNSIGNED NULL,
            stored_at DATETIME NOT NULL,
            released_by BIGINT UNSIGNED NULL,
            released_at DATETIME NULL,
            receiver_is_subject TINYINT(1) NULL,
            receiver_type VARCHAR(40) NULL,
            receiver_name VARCHAR(200) NULL,
            receiver_reason LONGTEXT NULL,
            receiver_organization VARCHAR(200) NULL,
            release_note LONGTEXT NULL,
            release_confirmed TINYINT(1) NOT NULL DEFAULT 0,
            correction_parent_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_valuables_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuables_stored_by FOREIGN KEY (stored_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_valuables_released_by FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_valuables_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_valuables_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_valuables_correction_parent FOREIGN KEY (correction_parent_id) REFERENCES valuables_records(id) ON DELETE SET NULL,
            INDEX idx_valuables_person (last_name,first_name,birth_date),
            INDEX idx_valuables_status (location_id,status),
            INDEX idx_valuables_stored_at (location_id,stored_at),
            INDEX idx_valuables_released_at (location_id,released_at),
            INDEX idx_valuables_internal_identifier (internal_identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS valuables_containers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            position_number INT UNSIGNED NOT NULL,
            container_type VARCHAR(40) NOT NULL,
            description VARCHAR(255) NULL,
            storage_location_id BIGINT UNSIGNED NOT NULL,
            cassette_id BIGINT UNSIGNED NULL,
            seal_left VARCHAR(40) NULL,
            seal_right VARCHAR(40) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_valuable_container_position (valuables_record_id,position_number),
            CONSTRAINT fk_valuable_container_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuable_container_storage FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id),
            CONSTRAINT fk_valuable_container_cassette FOREIGN KEY (cassette_id) REFERENCES cassettes(id) ON DELETE SET NULL,
            INDEX idx_valuable_container_type (container_type),
            INDEX idx_valuable_container_storage (storage_location_id),
            INDEX idx_valuable_container_cassette (cassette_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cassette_assignments (
            location_id BIGINT UNSIGNED NOT NULL,
            cassette_id BIGINT UNSIGNED NOT NULL,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            container_id BIGINT UNSIGNED NOT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (location_id,cassette_id),
            UNIQUE KEY uq_assignment_container (container_id),
            CONSTRAINT fk_cassette_assignment_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_cassette_assignment_cassette FOREIGN KEY (cassette_id) REFERENCES cassettes(id) ON DELETE CASCADE,
            CONSTRAINT fk_cassette_assignment_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_cassette_assignment_container FOREIGN KEY (container_id) REFERENCES valuables_containers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS seal_usages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            seal_number VARCHAR(40) NOT NULL UNIQUE,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            container_id BIGINT UNSIGNED NOT NULL,
            seal_side VARCHAR(10) NOT NULL,
            used_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_seal_usage_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_seal_usage_container FOREIGN KEY (container_id) REFERENCES valuables_containers(id) ON DELETE CASCADE,
            CONSTRAINT fk_seal_usage_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_seal_usage_record (valuables_record_id),
            INDEX idx_seal_usage_date (used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS valuables_release_checks (
            container_id BIGINT UNSIGNED PRIMARY KEY,
            seal_left_matches TINYINT(1) NULL,
            seal_left_condition VARCHAR(20) NULL,
            seal_left_actual VARCHAR(40) NULL,
            seal_left_reason LONGTEXT NULL,
            seal_right_matches TINYINT(1) NULL,
            seal_right_condition VARCHAR(20) NULL,
            seal_right_actual VARCHAR(40) NULL,
            seal_right_reason LONGTEXT NULL,
            checked_by BIGINT UNSIGNED NULL,
            checked_at DATETIME NOT NULL,
            CONSTRAINT fk_valuable_release_check_container FOREIGN KEY (container_id) REFERENCES valuables_containers(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuable_release_check_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS valuables_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            note_text LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_valuable_note_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuable_note_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_valuable_note_record (valuables_record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS valuables_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            field_name VARCHAR(160) NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            changed_by BIGINT UNSIGNED NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            change_reason VARCHAR(255) NULL,
            CONSTRAINT fk_valuable_history_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuable_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_valuable_history_record (valuables_record_id,changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);
    $pdo->exec('INSERT INTO valuables_sequence (id,next_number,updated_at) VALUES (1,1,NOW()) ON DUPLICATE KEY UPDATE id=id');

    $locations=array_map('intval',$pdo->query('SELECT id FROM locations')->fetchAll(PDO::FETCH_COLUMN));
    $cassette=$pdo->prepare(
        'INSERT INTO cassettes (location_id,cassette_number,active,created_at,updated_at)
         VALUES (:location_id,:number,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE cassette_number=cassette_number'
    );
    $storage=$pdo->prepare(
        'INSERT INTO storage_locations (location_id,location_type,location_number,label,active,sort_order,created_at,updated_at)
         VALUES (:location_id,:type,:number,:label,1,:sort_order,NOW(),NOW()) ON DUPLICATE KEY UPDATE label=label'
    );
    foreach($locations as $locationId){
        for($i=1;$i<=100;$i++)$cassette->execute(['location_id'=>$locationId,'number'=>$i]);
        for($i=1;$i<=50;$i++)$storage->execute(['location_id'=>$locationId,'type'=>'rack','number'=>$i,'label'=>'Regal '.$i,'sort_order'=>$i]);
        $storage->execute(['location_id'=>$locationId,'type'=>'floor','number'=>null,'label'=>'Boden','sort_order'=>1000]);
    }

    $setting=$pdo->prepare(
        'INSERT INTO settings (setting_key,setting_value,value_type,created_at,updated_at)
         VALUES (:key,:value,:type,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_key=setting_key'
    );
    $setting->execute(['key'=>'valuables.retention_days','value'=>'3650','type'=>'int']);
};

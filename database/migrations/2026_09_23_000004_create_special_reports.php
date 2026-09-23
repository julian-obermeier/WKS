<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS record_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            left_module VARCHAR(50) NOT NULL,
            left_record_id BIGINT UNSIGNED NOT NULL,
            right_module VARCHAR(50) NOT NULL,
            right_record_id BIGINT UNSIGNED NOT NULL,
            relation VARCHAR(80) NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_record_link (left_module,left_record_id,right_module,right_record_id,relation),
            CONSTRAINT fk_record_links_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_record_links_left (left_module,left_record_id),
            INDEX idx_record_links_right (right_module,right_record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(180) NOT NULL,
            code VARCHAR(80) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            force_section_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            UNIQUE KEY uq_special_report_type (location_id,code),
            CONSTRAINT fk_sr_types_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_types_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_types_updated FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_sr_types_active (location_id,active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_sequences (
            report_year SMALLINT UNSIGNED PRIMARY KEY,
            next_number INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_reports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            report_type_id BIGINT UNSIGNED NOT NULL,
            report_year SMALLINT UNSIGNED NOT NULL,
            report_number INT UNSIGNED NOT NULL,
            incident_date DATE NOT NULL,
            incident_started_at DATETIME NOT NULL,
            incident_ended_at DATETIME NULL,
            place_id BIGINT UNSIGNED NULL,
            place_free_text VARCHAR(255) NULL,
            facts LONGTEXT NOT NULL,
            measures_text LONGTEXT NULL,
            result_text LONGTEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            source_dutybook_entry_id BIGINT UNSIGNED NULL,
            current_version INT UNSIGNED NOT NULL DEFAULT 0,
            edit_locked_at DATETIME NULL,
            author_confirmed_at DATETIME NULL,
            completed_by BIGINT UNSIGNED NULL,
            completed_at DATETIME NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            review_note LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_special_report_year_number (report_year,report_number),
            CONSTRAINT fk_sr_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_type FOREIGN KEY (report_type_id) REFERENCES special_report_types(id),
            CONSTRAINT fk_sr_place FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_dutybook FOREIGN KEY (source_dutybook_entry_id) REFERENCES dutybook_entries(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_completed_by FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_sr_location_date (location_id,incident_date),
            INDEX idx_sr_status (location_id,status),
            INDEX idx_sr_type (report_type_id),
            INDEX idx_sr_creator (created_by),
            FULLTEXT KEY ft_sr_text (facts,measures_text,result_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_staff (
            report_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (report_id,user_id),
            CONSTRAINT fk_sr_staff_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_people (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NULL,
            person_type VARCHAR(80) NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            birth_date DATE NULL,
            area VARCHAR(160) NULL,
            internal_identifier VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_people_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_people_role FOREIGN KEY (role_id) REFERENCES person_roles(id) ON DELETE SET NULL,
            INDEX idx_sr_people_name (last_name,first_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_witnesses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            contact_details VARCHAR(255) NULL,
            statement_summary LONGTEXT NULL,
            written_statement TINYINT(1) NOT NULL DEFAULT 0,
            attachment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_witness_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_witness_attachment FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_external (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NULL,
            organization_name VARCHAR(160) NULL,
            contact_name VARCHAR(160) NULL,
            notified_at DATETIME NULL,
            feedback LONGTEXT NULL,
            reference_number VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_external_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_external_org FOREIGN KEY (organization_id) REFERENCES external_organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_injuries (
            report_id BIGINT UNSIGNED PRIMARY KEY,
            injury_present TINYINT(1) NOT NULL DEFAULT 0,
            description LONGTEXT NULL,
            medical_care LONGTEXT NULL,
            treating_entity VARCHAR(200) NULL,
            treated_at DATETIME NULL,
            attachment_id BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_injury_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_injury_attachment FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_force_actions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(180) NOT NULL,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            justification LONGTEXT NULL,
            result_text LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_force_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_force_staff (
            force_action_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (force_action_id,user_id),
            CONSTRAINT fk_sr_force_staff_action FOREIGN KEY (force_action_id) REFERENCES special_report_force_actions(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_force_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_revision_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            request_text LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_by BIGINT UNSIGNED NULL,
            completed_at DATETIME NULL,
            CONSTRAINT fk_sr_revision_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_revision_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_revision_completed FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_sr_revision_status (report_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            pdf_path VARCHAR(255) NULL,
            docx_path VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sr_version (report_id,version_number),
            CONSTRAINT fk_sr_version_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_version_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS special_report_addenda (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            changes_json LONGTEXT NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sr_addendum_report FOREIGN KEY (report_id) REFERENCES special_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_addendum_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_sr_addendum_report (report_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);

    $locationIds=array_map('intval',$pdo->query('SELECT id FROM locations')->fetchAll(PDO::FETCH_COLUMN));
    $types=[
        'Verbal aggressive Person','Körperlich aggressive Person','Alarmverfolgung','Diebstahl','Sachbeschädigung',
        'Feuerwehr','Fixierung','GT/GS','Sonstiges','Automaten','Vorgänge mit Patienten/Besucher','Hilfeleistung',
        'HLP','Parken/Verkehr','Balkontüren/Fenster','Obdachlose Personen','Patientensuche','Wertsachen','Bewachung'
    ];
    $stmt=$pdo->prepare(
        'INSERT INTO special_report_types (location_id,name,code,sort_order,active,force_section_enabled,created_at,updated_at)
         VALUES (:location_id,:name,:code,:sort_order,1,:force,NOW(),NOW())
         ON DUPLICATE KEY UPDATE name=VALUES(name),sort_order=VALUES(sort_order),force_section_enabled=VALUES(force_section_enabled)'
    );
    foreach($locationIds as $locationId){
        foreach($types as $i=>$name){
            $code=strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)?:$name);
            $code=preg_replace('/[^a-z0-9]+/','_',trim($code))?:'typ_'.($i+1);
            $stmt->execute([
                'location_id'=>$locationId,'name'=>$name,'code'=>$code,'sort_order'=>($i+1)*10,
                'force'=>in_array($name,['Fixierung','Körperlich aggressive Person'],true)?1:0
            ]);
        }
    }
};

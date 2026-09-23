<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS shifts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(30) NOT NULL,
            name VARCHAR(100) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            crosses_midnight TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            UNIQUE KEY uq_shifts_location_code (location_id, code),
            CONSTRAINT fk_shifts_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_shifts_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_shifts_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_shifts_location_active (location_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_days (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            duty_date DATE NOT NULL,
            archived_at DATETIME NULL,
            archived_by BIGINT UNSIGNED NULL,
            archive_file VARCHAR(255) NULL,
            archive_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dutybook_day (location_id, duty_date),
            CONSTRAINT fk_dutybook_days_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_days_archived_by FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_days_date (duty_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            dutybook_day_id BIGINT UNSIGNED NOT NULL,
            duty_date DATE NOT NULL,
            shift_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            started_at DATETIME NULL,
            started_by BIGINT UNSIGNED NULL,
            ended_at DATETIME NULL,
            ended_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shift_session (location_id, duty_date, shift_id),
            CONSTRAINT fk_shift_sessions_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_shift_sessions_day FOREIGN KEY (dutybook_day_id) REFERENCES dutybook_days(id) ON DELETE CASCADE,
            CONSTRAINT fk_shift_sessions_shift FOREIGN KEY (shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_shift_sessions_started_by FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_shift_sessions_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_shift_sessions_status (location_id, status),
            INDEX idx_shift_sessions_date (duty_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_attendance (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_session_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            checked_in_at DATETIME NOT NULL,
            duty_accepted_at DATETIME NULL,
            checked_out_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shift_attendance (shift_session_id, user_id),
            CONSTRAINT fk_shift_attendance_session FOREIGN KEY (shift_session_id) REFERENCES shift_sessions(id) ON DELETE CASCADE,
            CONSTRAINT fk_shift_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_shift_attendance_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NULL,
            name VARCHAR(140) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_dutybook_categories_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_categories_scope (location_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_event_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            offer_special_report TINYINT(1) NOT NULL DEFAULT 0,
            offer_valuables TINYINT(1) NOT NULL DEFAULT 0,
            auto_entry_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_dutybook_event_types_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_event_types_category FOREIGN KEY (category_id) REFERENCES dutybook_categories(id),
            CONSTRAINT fk_dutybook_event_types_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_event_types_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_event_types_location (location_id, active),
            INDEX idx_dutybook_event_types_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dynamic_fields (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL,
            definition_id BIGINT UNSIGNED NOT NULL,
            field_key VARCHAR(80) NOT NULL,
            label VARCHAR(160) NOT NULL,
            field_type VARCHAR(30) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            options_json LONGTEXT NULL,
            visibility_json LONGTEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            UNIQUE KEY uq_dynamic_field_key (module, definition_id, field_key),
            INDEX idx_dynamic_fields_definition (module, definition_id, active),
            CONSTRAINT fk_dynamic_fields_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_dynamic_fields_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS person_roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NULL,
            name VARCHAR(100) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_person_roles_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_person_roles_scope (location_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS places (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            place_type VARCHAR(30) NOT NULL,
            name VARCHAR(160) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_places_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_places_parent FOREIGN KEY (parent_id) REFERENCES places(id) ON DELETE CASCADE,
            INDEX idx_places_location_parent (location_id, parent_id, active),
            INDEX idx_places_type (place_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS measures (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NULL,
            module VARCHAR(50) NOT NULL DEFAULT 'dutybook',
            name VARCHAR(160) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_measures_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_measures_scope (module, location_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS event_type_measures (
            event_type_id BIGINT UNSIGNED NOT NULL,
            measure_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (event_type_id, measure_id),
            CONSTRAINT fk_event_type_measures_event FOREIGN KEY (event_type_id) REFERENCES dutybook_event_types(id) ON DELETE CASCADE,
            CONSTRAINT fk_event_type_measures_measure FOREIGN KEY (measure_id) REFERENCES measures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS external_organizations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NULL,
            organization_type VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_external_org_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_external_org_scope (location_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            dutybook_day_id BIGINT UNSIGNED NOT NULL,
            duty_date DATE NOT NULL,
            shift_session_id BIGINT UNSIGNED NULL,
            shift_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NULL,
            event_type_id BIGINT UNSIGNED NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            occurred_at DATETIME NOT NULL,
            event_started_at DATETIME NULL,
            event_ended_at DATETIME NULL,
            place_id BIGINT UNSIGNED NULL,
            place_free_text VARCHAR(255) NULL,
            facts LONGTEXT NOT NULL,
            measures_text LONGTEXT NULL,
            result_text LONGTEXT NULL,
            is_automatic TINYINT(1) NOT NULL DEFAULT 0,
            automatic_type VARCHAR(80) NULL,
            edit_locked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_dutybook_entries_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_entries_day FOREIGN KEY (dutybook_day_id) REFERENCES dutybook_days(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_entries_session FOREIGN KEY (shift_session_id) REFERENCES shift_sessions(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_shift FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_category FOREIGN KEY (category_id) REFERENCES dutybook_categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_event_type FOREIGN KEY (event_type_id) REFERENCES dutybook_event_types(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_place FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_dutybook_entries_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_entries_day (location_id, duty_date),
            INDEX idx_dutybook_entries_status (location_id, status),
            INDEX idx_dutybook_entries_event (event_type_id),
            INDEX idx_dutybook_entries_occurred (occurred_at),
            FULLTEXT KEY ft_dutybook_entries_text (facts, measures_text, result_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_entry_staff (
            entry_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, user_id),
            CONSTRAINT fk_dutybook_entry_staff_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_entry_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_entry_people (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NULL,
            person_type VARCHAR(80) NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            birth_date DATE NULL,
            area VARCHAR(160) NULL,
            internal_identifier VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dutybook_people_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_people_role FOREIGN KEY (role_id) REFERENCES person_roles(id) ON DELETE SET NULL,
            INDEX idx_dutybook_people_name (last_name, first_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_entry_measures (
            entry_id BIGINT UNSIGNED NOT NULL,
            measure_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, measure_id),
            CONSTRAINT fk_dutybook_entry_measures_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_entry_measures_measure FOREIGN KEY (measure_id) REFERENCES measures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_entry_external (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NULL,
            organization_name VARCHAR(160) NULL,
            contact_name VARCHAR(160) NULL,
            notified_at DATETIME NULL,
            feedback LONGTEXT NULL,
            reference_number VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dutybook_external_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_external_org FOREIGN KEY (organization_id) REFERENCES external_organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dynamic_values (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            field_id BIGINT UNSIGNED NOT NULL,
            value_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dynamic_value (module, record_id, field_id),
            CONSTRAINT fk_dynamic_values_field FOREIGN KEY (field_id) REFERENCES dynamic_fields(id) ON DELETE CASCADE,
            INDEX idx_dynamic_values_record (module, record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            description VARCHAR(255) NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_attachments_record (module, record_id, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS dutybook_addenda (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            original_content LONGTEXT NOT NULL,
            new_content LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dutybook_addenda_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_dutybook_addenda_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dutybook_addenda_entry (entry_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_handovers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            from_shift_session_id BIGINT UNSIGNED NOT NULL,
            to_shift_id BIGINT UNSIGNED NOT NULL,
            notes LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            outgoing_confirmed_by BIGINT UNSIGNED NULL,
            outgoing_confirmed_at DATETIME NULL,
            incoming_confirmed_by BIGINT UNSIGNED NULL,
            incoming_confirmed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_handovers_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_handovers_from_session FOREIGN KEY (from_shift_session_id) REFERENCES shift_sessions(id) ON DELETE CASCADE,
            CONSTRAINT fk_handovers_to_shift FOREIGN KEY (to_shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_handovers_out_user FOREIGN KEY (outgoing_confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_handovers_in_user FOREIGN KEY (incoming_confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_handovers_status (location_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_handover_entries (
            handover_id BIGINT UNSIGNED NOT NULL,
            entry_id BIGINT UNSIGNED NOT NULL,
            assigned_to_user_id BIGINT UNSIGNED NULL,
            assigned_to_next_shift TINYINT(1) NOT NULL DEFAULT 1,
            acknowledged_at DATETIME NULL,
            PRIMARY KEY (handover_id, entry_id),
            CONSTRAINT fk_handover_entries_handover FOREIGN KEY (handover_id) REFERENCES shift_handovers(id) ON DELETE CASCADE,
            CONSTRAINT fk_handover_entries_entry FOREIGN KEY (entry_id) REFERENCES dutybook_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_handover_entries_user FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($sql as $statement) {
        $pdo->exec($statement);
    }

    $locations = $pdo->query('SELECT id, code FROM locations')->fetchAll(PDO::FETCH_ASSOC);
    $shiftInsert = $pdo->prepare(
        'INSERT INTO shifts (location_id, code, name, start_time, end_time, crosses_midnight, sort_order, active, created_at, updated_at)
         VALUES (:location_id, :code, :name, :start_time, :end_time, :crosses_midnight, :sort_order, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), start_time = VALUES(start_time), end_time = VALUES(end_time),
                                 crosses_midnight = VALUES(crosses_midnight), sort_order = VALUES(sort_order)'
    );

    foreach ($locations as $location) {
        foreach ([
            ['F', 'Früh', '05:40:00', '13:58:00', 0, 10],
            ['S', 'Spät', '13:40:00', '21:58:00', 0, 20],
            ['N', 'Nacht', '21:40:00', '05:58:00', 1, 30],
            ['T', 'Tag', '08:00:00', '16:18:00', 0, 40],
        ] as [$code, $name, $start, $end, $crosses, $sort]) {
            $shiftInsert->execute([
                'location_id' => (int) $location['id'],
                'code' => $code,
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'crosses_midnight' => $crosses,
                'sort_order' => $sort,
            ]);
        }
    }

    $roleInsert = $pdo->prepare(
        'INSERT INTO person_roles (location_id, name, active, sort_order, created_at, updated_at)
         SELECT NULL, :name, 1, :sort_order, NOW(), NOW()
         WHERE NOT EXISTS (SELECT 1 FROM person_roles WHERE location_id IS NULL AND name = :name_check)'
    );
    foreach (['Patient', 'Besucher', 'Mitarbeiter', 'Zeuge', 'Geschädigter', 'Verursacher'] as $i => $name) {
        $roleInsert->execute(['name' => $name, 'sort_order' => ($i + 1) * 10, 'name_check' => $name]);
    }

    $categoryInsert = $pdo->prepare(
        'INSERT INTO dutybook_categories (location_id, name, sort_order, active, created_at, updated_at)
         SELECT NULL, :name, :sort_order, 1, NOW(), NOW()
         WHERE NOT EXISTS (SELECT 1 FROM dutybook_categories WHERE location_id IS NULL AND name = :name_check)'
    );
    foreach (['Einsatz', 'Kontrolle', 'Organisation', 'Technik', 'Sonstiges'] as $i => $name) {
        $categoryInsert->execute(['name' => $name, 'sort_order' => ($i + 1) * 10, 'name_check' => $name]);
    }

    $measureInsert = $pdo->prepare(
        'INSERT INTO measures (location_id, module, name, active, sort_order, created_at, updated_at)
         SELECT NULL, "dutybook", :name, 1, :sort_order, NOW(), NOW()
         WHERE NOT EXISTS (SELECT 1 FROM measures WHERE location_id IS NULL AND module = "dutybook" AND name = :name_check)'
    );
    foreach ([
        'Polizei verständigt', 'Feuerwehr verständigt', 'Pflege informiert', 'Arzt informiert',
        'Technik informiert', 'Bereich kontrolliert', 'Zugang gesperrt'
    ] as $i => $name) {
        $measureInsert->execute(['name' => $name, 'sort_order' => ($i + 1) * 10, 'name_check' => $name]);
    }

    $orgInsert = $pdo->prepare(
        'INSERT INTO external_organizations (location_id, organization_type, name, active, sort_order, created_at, updated_at)
         SELECT NULL, :type, :name, 1, :sort_order, NOW(), NOW()
         WHERE NOT EXISTS (SELECT 1 FROM external_organizations WHERE location_id IS NULL AND organization_type = :type_check AND name = :name_check)'
    );
    foreach ([
        ['Polizei', 'Polizei'], ['Feuerwehr', 'Feuerwehr'], ['Rettungsdienst', 'Rettungsdienst'],
        ['Technik', 'Technik'], ['Pflege', 'Pflege'], ['Arzt', 'Arzt'], ['Externe Firma', 'Externe Firma']
    ] as $i => [$type, $name]) {
        $orgInsert->execute([
            'type' => $type, 'name' => $name, 'sort_order' => ($i + 1) * 10,
            'type_check' => $type, 'name_check' => $name,
        ]);
    }
};

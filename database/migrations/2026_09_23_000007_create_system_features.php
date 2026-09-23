<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS trash_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(60) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NULL,
            summary VARCHAR(500) NOT NULL,
            deleted_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            metadata_json LONGTEXT NULL,
            UNIQUE KEY uq_trash_record (module,record_id),
            CONSTRAINT fk_trash_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
            CONSTRAINT fk_trash_user FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_trash_deleted_at (deleted_at),
            INDEX idx_trash_module (module),
            INDEX idx_trash_location (location_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS announcements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(220) NOT NULL,
            body LONGTEXT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'info',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            valid_from DATETIME NULL,
            valid_until DATETIME NULL,
            require_ack TINYINT(1) NOT NULL DEFAULT 0,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            published_at DATETIME NULL,
            archived_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_announcements_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_announcements_updated FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_announcements_status (status,valid_from,valid_until),
            FULLTEXT KEY ft_announcements (title,body)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS announcement_targets (
            announcement_id BIGINT UNSIGNED NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_value VARCHAR(100) NOT NULL DEFAULT '*',
            PRIMARY KEY (announcement_id,target_type,target_value),
            CONSTRAINT fk_announcement_targets_record FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
            INDEX idx_announcement_targets_value (target_type,target_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS announcement_reads (
            announcement_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            revision INT UNSIGNED NOT NULL,
            read_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            PRIMARY KEY (announcement_id,user_id,revision),
            CONSTRAINT fk_announcement_reads_record FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
            CONSTRAINT fk_announcement_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_announcement_reads_confirmed (announcement_id,revision,confirmed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NULL,
            event_code VARCHAR(100) NOT NULL,
            title VARCHAR(220) NOT NULL,
            message VARCHAR(500) NOT NULL,
            target_url VARCHAR(500) NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_notifications_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_notifications_user_read (user_id,read_at,created_at),
            INDEX idx_notifications_event (event_code,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS notification_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_code VARCHAR(100) NOT NULL,
            role_code VARCHAR(50) NULL,
            location_id BIGINT UNSIGNED NULL,
            internal_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_notification_rule (event_code,role_code,location_id),
            CONSTRAINT fk_notification_rules_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS mail_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NULL,
            trigger_code VARCHAR(120) NOT NULL,
            target_address VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(30) NOT NULL,
            error_message LONGTEXT NULL,
            mail_mode VARCHAR(30) NOT NULL,
            CONSTRAINT fk_mail_log_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
            INDEX idx_mail_log_date (occurred_at),
            INDEX idx_mail_log_location (location_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);

    $rules=[
        ['special_report_completed','management'],
        ['special_report_revision_required','employee'],
        ['long_term_valuables','management'],
        ['open_handover','employee']
    ];
    $stmt=$pdo->prepare(
        'INSERT INTO notification_rules (event_code,role_code,location_id,internal_enabled,email_enabled,active,created_at,updated_at)
         VALUES (:event_code,:role_code,NULL,1,0,1,NOW(),NOW())
         ON DUPLICATE KEY UPDATE event_code=event_code'
    );
    foreach($rules as [$event,$role])$stmt->execute(['event_code'=>$event,'role_code'=>$role]);
};

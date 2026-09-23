<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS update_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(80) NOT NULL,
            commit_sha VARCHAR(64) NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            migrations_json LONGTEXT NULL,
            changes_json LONGTEXT NULL,
            status VARCHAR(20) NOT NULL,
            technical_details LONGTEXT NULL,
            error_message LONGTEXT NULL,
            initiated_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_update_history_user FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_update_history_date (occurred_at),
            INDEX idx_update_history_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS release_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(80) NOT NULL UNIQUE,
            build_date DATE NOT NULL,
            title VARCHAR(220) NOT NULL,
            changelog_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_release_views (
            user_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(80) NOT NULL,
            seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id,version),
            CONSTRAINT fk_user_release_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_release_views_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);

    $setting=$pdo->prepare(
        'INSERT INTO settings (setting_key,setting_value,value_type,created_at,updated_at)
         VALUES (:key,:value,:type,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_key=setting_key'
    );
    $setting->execute(['key'=>'system.installed_commit','value'=>'','type'=>'string']);
    $setting->execute(['key'=>'system.installed_version','value'=>trim((string)@file_get_contents(BASE_PATH.'/VERSION')),'type'=>'string']);
};

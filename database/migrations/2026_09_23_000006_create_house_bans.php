<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS house_bans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            ban_date DATE NOT NULL,
            person_name VARCHAR(220) NOT NULL,
            reason LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            deleted_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_house_bans_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            CONSTRAINT fk_house_bans_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_house_bans_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_house_bans_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_house_bans_location_date (location_id,ban_date),
            INDEX idx_house_bans_name (person_name),
            INDEX idx_house_bans_deleted (deleted_at),
            FULLTEXT KEY ft_house_bans_text (person_name,reason)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS house_ban_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            house_ban_id BIGINT UNSIGNED NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            changed_by BIGINT UNSIGNED NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_house_ban_history_record FOREIGN KEY (house_ban_id) REFERENCES house_bans(id) ON DELETE CASCADE,
            CONSTRAINT fk_house_ban_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_house_ban_history_record (house_ban_id,changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);
};

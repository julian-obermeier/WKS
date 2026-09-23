<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS valuables_addenda (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            valuables_record_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            addendum_text LONGTEXT NOT NULL,
            correction_child_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_valuables_addendum_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE CASCADE,
            CONSTRAINT fk_valuables_addendum_child FOREIGN KEY (correction_child_id) REFERENCES valuables_records(id) ON DELETE SET NULL,
            CONSTRAINT fk_valuables_addendum_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_valuables_addendum_record (valuables_record_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};

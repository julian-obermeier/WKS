<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $exists=$pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name="shift_handovers" AND column_name="to_shift_session_id"'
    );
    $exists->execute();
    if(!(bool)$exists->fetchColumn()){
        $pdo->exec(
            'ALTER TABLE shift_handovers
             ADD COLUMN to_shift_session_id BIGINT UNSIGNED NULL AFTER to_shift_id,
             ADD CONSTRAINT fk_shift_handover_to_session FOREIGN KEY (to_shift_session_id) REFERENCES shift_sessions(id) ON DELETE SET NULL,
             ADD INDEX idx_shift_handover_to_session (to_shift_session_id)'
        );
    }
};

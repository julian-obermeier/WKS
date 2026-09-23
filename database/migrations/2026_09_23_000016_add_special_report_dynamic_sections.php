<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $stmt=$pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name="dynamic_fields" AND column_name="section_name"'
    );
    $stmt->execute();
    if(!(bool)$stmt->fetchColumn()){
        $pdo->exec('ALTER TABLE dynamic_fields ADD COLUMN section_name VARCHAR(160) NULL AFTER label');
    }
    $pdo->exec(
        'UPDATE dynamic_fields SET section_name="Zusatzangaben"
         WHERE module="special_report_type" AND (section_name IS NULL OR TRIM(section_name)="")'
    );
};

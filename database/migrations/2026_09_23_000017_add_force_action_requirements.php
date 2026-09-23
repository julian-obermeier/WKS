<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $stmt=$pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name="special_report_types" AND column_name="force_requirements_json"'
    );
    $stmt->execute();
    if(!(bool)$stmt->fetchColumn()){
        $pdo->exec('ALTER TABLE special_report_types ADD COLUMN force_requirements_json LONGTEXT NULL AFTER force_section_enabled');
    }
    $pdo->exec('UPDATE special_report_types SET force_requirements_json="{}" WHERE force_requirements_json IS NULL OR TRIM(force_requirements_json)=""');
};

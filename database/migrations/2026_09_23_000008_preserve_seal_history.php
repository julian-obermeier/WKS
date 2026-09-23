<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $columnExists=static function(string $table,string $column) use($pdo): bool {
        $stmt=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column'
        );
        $stmt->execute(['table'=>$table,'column'=>$column]);
        return (bool)$stmt->fetchColumn();
    };
    $constraintExists=static function(string $name) use($pdo): bool {
        $stmt=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema=DATABASE() AND constraint_name=:name'
        );
        $stmt->execute(['name'=>$name]);
        return (bool)$stmt->fetchColumn();
    };

    if(!$columnExists('seal_usages','custody_number_snapshot')){
        $pdo->exec('ALTER TABLE seal_usages ADD COLUMN custody_number_snapshot BIGINT UNSIGNED NULL AFTER seal_number');
    }
    $pdo->exec(
        'UPDATE seal_usages s JOIN valuables_records r ON r.id=s.valuables_record_id
         SET s.custody_number_snapshot=r.custody_number
         WHERE s.custody_number_snapshot IS NULL'
    );

    if($constraintExists('fk_seal_usage_record'))$pdo->exec('ALTER TABLE seal_usages DROP FOREIGN KEY fk_seal_usage_record');
    if($constraintExists('fk_seal_usage_container'))$pdo->exec('ALTER TABLE seal_usages DROP FOREIGN KEY fk_seal_usage_container');

    $pdo->exec('ALTER TABLE seal_usages MODIFY valuables_record_id BIGINT UNSIGNED NULL, MODIFY container_id BIGINT UNSIGNED NULL');
    $pdo->exec(
        'ALTER TABLE seal_usages
         ADD CONSTRAINT fk_seal_usage_record FOREIGN KEY (valuables_record_id) REFERENCES valuables_records(id) ON DELETE SET NULL,
         ADD CONSTRAINT fk_seal_usage_container FOREIGN KEY (container_id) REFERENCES valuables_containers(id) ON DELETE SET NULL'
    );
};

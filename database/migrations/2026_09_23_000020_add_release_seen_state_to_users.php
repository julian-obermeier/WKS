<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $columns=$pdo->query("SHOW COLUMNS FROM users LIKE 'last_seen_release_version'")->fetchAll(PDO::FETCH_ASSOC);
    if($columns===[]){
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_release_version VARCHAR(80) NULL AFTER theme");
    }
    $columns=$pdo->query("SHOW COLUMNS FROM users LIKE 'last_seen_release_at'")->fetchAll(PDO::FETCH_ASSOC);
    if($columns===[]){
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_release_at DATETIME NULL AFTER last_seen_release_version");
    }
};

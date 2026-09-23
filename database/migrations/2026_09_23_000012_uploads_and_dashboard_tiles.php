<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $setting=$pdo->prepare(
        'INSERT INTO settings (setting_key,setting_value,value_type,created_at,updated_at)
         VALUES (:key,:value,:type,NOW(),NOW())
         ON DUPLICATE KEY UPDATE setting_key=setting_key'
    );
    $defaults=[
        ['uploads.max_mb','10','int'],
        ['uploads.dutybook_extensions',json_encode(['jpg','jpeg','png','pdf','docx']),'json'],
        ['uploads.special_report_extensions',json_encode(['jpg','jpeg','png','pdf','docx']),'json'],
        ['uploads.valuables_extensions',json_encode(['jpg','jpeg','png','pdf']),'json'],
        ['uploads.house_bans_extensions',json_encode(['jpg','jpeg','png','pdf']),'json'],
        ['uploads.messages_extensions',json_encode(['jpg','jpeg','png','pdf']),'json'],
    ];
    foreach($defaults as [$key,$value,$type])$setting->execute(['key'=>$key,'value'=>$value,'type'=>$type]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS dashboard_role_tiles (
            role_id BIGINT UNSIGNED NOT NULL,
            tile_code VARCHAR(100) NOT NULL,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (role_id,tile_code),
            CONSTRAINT fk_dashboard_tiles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_dashboard_tiles_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $roles=$pdo->query('SELECT id,code FROM roles')->fetchAll(PDO::FETCH_ASSOC);
    $defaultsByRole=[
        'employee'=>['current_shift','open_dutybook','notifications','announcements','dutybook','special_reports','valuables','house_bans','information'],
        'management'=>['current_shift','open_dutybook','notifications','announcements','unreviewed_reports','revision_reports','valuables_metric','dutybook','special_reports','valuables','house_bans','information'],
        'admin'=>['current_shift','open_dutybook','notifications','announcements','unreviewed_reports','revision_reports','valuables_metric','dutybook','special_reports','valuables','house_bans','information','administration'],
    ];
    $insert=$pdo->prepare(
        'INSERT IGNORE INTO dashboard_role_tiles (role_id,tile_code,visible,updated_at)
         VALUES (:role_id,:tile_code,1,NOW())'
    );
    foreach($roles as $role)foreach($defaultsByRole[$role['code']]??[] as $tile)$insert->execute(['role_id'=>$role['id'],'tile_code'=>$tile]);
};

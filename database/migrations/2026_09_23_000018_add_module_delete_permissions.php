<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $permissions=[
        ['dutybook.delete','Dienstbucheinträge löschen','Dienstbuch'],
        ['special_reports.delete','Sonderberichte löschen','Sonderberichte'],
    ];
    $insert=$pdo->prepare(
        'INSERT INTO permissions (code,name,group_name,created_at)
         VALUES (:code,:name,:group_name,NOW())
         ON DUPLICATE KEY UPDATE name=VALUES(name),group_name=VALUES(group_name)'
    );
    foreach($permissions as [$code,$name,$group]){
        $insert->execute(['code'=>$code,'name'=>$name,'group_name'=>$group]);
    }

    $admin=$pdo->query('SELECT id FROM roles WHERE code="admin" LIMIT 1')->fetchColumn();
    if($admin){
        $assign=$pdo->prepare(
            'INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
             SELECT :role_id,id,NOW() FROM permissions WHERE code=:code'
        );
        foreach($permissions as [$code]){
            $assign->execute(['role_id'=>(int)$admin,'code'=>$code]);
        }
    }
};

<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class TrashRepository
{
    public function all(string $search=''): array
    {
        $params=[];$where='1=1';
        if($search!==''){
            $where='(t.summary LIKE :q OR t.module LIKE :q OR CAST(t.record_id AS CHAR) LIKE :q)';
            $params['q']='%'.$search.'%';
        }
        $stmt=Database::connection()->prepare(
            "SELECT t.*,l.name AS location_name,CONCAT(u.first_name,' ',u.last_name) AS deleted_by_name
             FROM trash_entries t
             LEFT JOIN locations l ON l.id=t.location_id
             LEFT JOIN users u ON u.id=t.deleted_by
             WHERE {$where}
             ORDER BY t.deleted_at DESC,t.id DESC"
        );
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM trash_entries WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function add(string $module,int $recordId,?int $locationId,string $summary,int $userId,array $metadata=[]): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO trash_entries (module,record_id,location_id,summary,deleted_by,deleted_at,metadata_json)
             VALUES (:module,:record_id,:location_id,:summary,:user_id,NOW(),:metadata)
             ON DUPLICATE KEY UPDATE summary=VALUES(summary),deleted_by=VALUES(deleted_by),deleted_at=NOW(),metadata_json=VALUES(metadata_json)'
        );
        $stmt->execute([
            'module'=>$module,'record_id'=>$recordId,'location_id'=>$locationId,'summary'=>$summary,'user_id'=>$userId,
            'metadata'=>$metadata===[]?null:json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function remove(int $id): void
    {
        Database::connection()->prepare('DELETE FROM trash_entries WHERE id=:id')->execute(['id'=>$id]);
    }
}

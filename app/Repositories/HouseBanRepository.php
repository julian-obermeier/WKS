<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class HouseBanRepository
{
    public function find(int $id,int $locationId,bool $includeDeleted=false): ?array
    {
        $sql='SELECT h.*,CONCAT(c.first_name," ",c.last_name) AS creator_name,
                    CONCAT(u.first_name," ",u.last_name) AS updater_name
             FROM house_bans h
             LEFT JOIN users c ON c.id=h.created_by
             LEFT JOIN users u ON u.id=h.updated_by
             WHERE h.id=:id AND h.location_id=:location_id';
        if(!$includeDeleted)$sql.=' AND h.deleted_at IS NULL';
        $sql.=' LIMIT 1';
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$id,'location_id'=>$locationId]);
        $record=$stmt->fetch(PDO::FETCH_ASSOC);if(!$record)return null;
        $record['history']=$this->history($id);
        $record['attachments']=$this->attachments($id);
        return $record;
    }

    public function create(array $data): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO house_bans (location_id,ban_date,person_name,reason,created_at,updated_at,created_by,updated_by)
             VALUES (:location_id,:ban_date,:person_name,:reason,NOW(),NOW(),:created_by,:updated_by)'
        );
        $stmt->execute($data);return (int)Database::connection()->lastInsertId();
    }

    public function update(int $id,int $locationId,array $data): void
    {
        Database::connection()->prepare(
            'UPDATE house_bans SET ban_date=:ban_date,person_name=:person_name,reason=:reason,
             updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND location_id=:location_id AND deleted_at IS NULL'
        )->execute($data+['id'=>$id,'location_id'=>$locationId]);
    }

    public function addHistory(int $id,string $field,mixed $old,mixed $new,int $userId): void
    {
        $encode=static fn(mixed $v): ?string=>$v===null?null:json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        Database::connection()->prepare(
            'INSERT INTO house_ban_history (house_ban_id,field_name,old_value,new_value,changed_by,changed_at)
             VALUES (:id,:field,:old,:new,:user,NOW())'
        )->execute(['id'=>$id,'field'=>$field,'old'=>$encode($old),'new'=>$encode($new),'user'=>$userId]);
    }

    public function history(int $id): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT h.*,CONCAT(u.first_name," ",u.last_name) AS user_name
             FROM house_ban_history h LEFT JOIN users u ON u.id=h.changed_by
             WHERE h.house_ban_id=:id ORDER BY h.changed_at,h.id'
        );
        $stmt->execute(['id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachments(int $id): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS uploader_name
             FROM attachments a LEFT JOIN users u ON u.id=a.uploaded_by
             WHERE a.module="house_bans" AND a.record_id=:id AND a.deleted_at IS NULL
             ORDER BY a.uploaded_at,a.id'
        );
        $stmt->execute(['id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id,int $locationId,int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE house_bans SET deleted_at=NOW(),deleted_by=:user,updated_at=NOW(),updated_by=:user
             WHERE id=:id AND location_id=:location_id AND deleted_at IS NULL'
        )->execute(['user'=>$userId,'id'=>$id,'location_id'=>$locationId]);
    }

    public function restore(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE house_bans SET deleted_at=NULL,deleted_by=NULL,updated_at=NOW() WHERE id=:id'
        )->execute(['id'=>$id]);
    }

    public function hardDelete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM house_bans WHERE id=:id')->execute(['id'=>$id]);
    }

    public function search(int $locationId,array $filters,int $page=1,int $perPage=30): array
    {
        $where=['h.location_id=:location_id','h.deleted_at IS NULL'];$params=['location_id'=>$locationId];
        if(($filters['name']??'')!==''){$where[]='h.person_name LIKE :name';$params['name']='%'.$filters['name'].'%';}
        if(($filters['reason']??'')!==''){$where[]='h.reason LIKE :reason';$params['reason']='%'.$filters['reason'].'%';}
        if(($filters['from']??'')!==''){$where[]='h.ban_date>=:from';$params['from']=$filters['from'];}
        if(($filters['to']??'')!==''){$where[]='h.ban_date<=:to';$params['to']=$filters['to'];}
        $sort=($filters['sort']??'date')==='name'?'h.person_name ASC,h.ban_date DESC':'h.ban_date DESC,h.id DESC';
        $clause=implode(' AND ',$where);$pdo=Database::connection();
        $count=$pdo->prepare("SELECT COUNT(*) FROM house_bans h WHERE {$clause}");$count->execute($params);$total=(int)$count->fetchColumn();
        $page=max(1,$page);$offset=($page-1)*$perPage;
        $stmt=$pdo->prepare(
            "SELECT h.*,CONCAT(u.first_name,' ',u.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM attachments a WHERE a.module='house_bans' AND a.record_id=h.id AND a.deleted_at IS NULL) AS attachment_count
             FROM house_bans h LEFT JOIN users u ON u.id=h.created_by
             WHERE {$clause} ORDER BY {$sort} LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    public function countByPeriod(int $locationId,string $from,string $to): int
    {
        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM house_bans WHERE location_id=:location_id AND deleted_at IS NULL AND ban_date BETWEEN :from AND :to'
        );
        $stmt->execute(['location_id'=>$locationId,'from'=>$from,'to'=>$to]);return (int)$stmt->fetchColumn();
    }
}

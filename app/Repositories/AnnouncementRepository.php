<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class AnnouncementRepository
{
    public function all(): array
    {
        return Database::connection()->query(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS creator_name
             FROM announcements a LEFT JOIN users u ON u.id=a.created_by
             WHERE a.deleted_at IS NULL ORDER BY a.created_at DESC,a.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS creator_name
             FROM announcements a LEFT JOIN users u ON u.id=a.created_by
             WHERE a.id=:id AND a.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id'=>$id]);$a=$stmt->fetch(PDO::FETCH_ASSOC);if(!$a)return null;
        $a['targets']=$this->targets($id);$a['attachments']=$this->attachments($id);$a['reads']=$this->reads($id,(int)$a['revision']);
        return $a;
    }

    public function findVisible(int $id,int $userId,int $locationId,string $roleCode): ?array
    {
        foreach($this->visibleForUser($userId,$locationId,$roleCode,1000) as $a)if((int)$a['id']===$id)return $this->find($id);
        return null;
    }

    public function visibleForUser(int $userId,int $locationId,string $roleCode,int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        $stmt=Database::connection()->prepare(
            "SELECT DISTINCT a.*,r.read_at,r.confirmed_at,r.revision AS read_revision,
                    CASE WHEN r.revision IS NOT NULL AND r.revision<a.revision THEN 1 ELSE 0 END AS is_updated
             FROM announcements a
             JOIN announcement_targets t ON t.announcement_id=a.id
             LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.user_id=:user_id AND r.revision=a.revision
             WHERE a.deleted_at IS NULL AND a.status='published'
               AND (a.valid_from IS NULL OR a.valid_from<=NOW())
               AND (a.valid_until IS NULL OR a.valid_until>=NOW())
               AND (
                    (t.target_type='all' AND t.target_value='*')
                    OR (t.target_type='location' AND t.target_value=:location)
                    OR (t.target_type='role' AND t.target_value=:role)
               )
             ORDER BY a.priority='important' DESC,a.published_at DESC,a.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['user_id'=>$userId,'location'=>(string)$locationId,'role'=>$roleCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data,array $targets): int
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare(
                'INSERT INTO announcements
                 (title,body,priority,status,valid_from,valid_until,require_ack,revision,published_at,created_at,updated_at,created_by,updated_by)
                 VALUES (:title,:body,:priority,:status,:valid_from,:valid_until,:require_ack,1,:published_at,NOW(),NOW(),:created_by,:updated_by)'
            );
            $stmt->execute($data);$id=(int)$pdo->lastInsertId();$this->syncTargets($id,$targets,false);$pdo->commit();return $id;
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public function update(int $id,array $data,array $targets,bool $publishedChanged): void
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $sql='UPDATE announcements SET title=:title,body=:body,priority=:priority,status=:status,valid_from=:valid_from,
                  valid_until=:valid_until,require_ack=:require_ack,published_at=:published_at,updated_at=NOW(),updated_by=:updated_by';
            if($publishedChanged)$sql.=',revision=revision+1';
            $sql.=' WHERE id=:id AND deleted_at IS NULL';
            $pdo->prepare($sql)->execute($data+['id'=>$id]);$this->syncTargets($id,$targets,false);$pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public function targets(int $id): array
    {
        $stmt=Database::connection()->prepare('SELECT target_type,target_value FROM announcement_targets WHERE announcement_id=:id ORDER BY target_type,target_value');
        $stmt->execute(['id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncTargets(int $id,array $targets,bool $transaction=true): void
    {
        $pdo=Database::connection();if($transaction)$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM announcement_targets WHERE announcement_id=:id')->execute(['id'=>$id]);
            $stmt=$pdo->prepare('INSERT INTO announcement_targets (announcement_id,target_type,target_value) VALUES (:id,:type,:value)');
            foreach($targets as $t)$stmt->execute(['id'=>$id,'type'=>$t['type'],'value'=>$t['value']]);
            if($transaction)$pdo->commit();
        }catch(\Throwable $e){if($transaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function markRead(int $id,int $userId,int $revision,bool $confirm=false): void
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO announcement_reads (announcement_id,user_id,revision,read_at,confirmed_at)
             VALUES (:id,:user_id,:revision,NOW(),:confirmed_at)
             ON DUPLICATE KEY UPDATE read_at=LEAST(read_at,NOW()),confirmed_at=COALESCE(confirmed_at,VALUES(confirmed_at))'
        );
        $stmt->execute(['id'=>$id,'user_id'=>$userId,'revision'=>$revision,'confirmed_at'=>$confirm?date('Y-m-d H:i:s'):null]);
    }

    public function reads(int $id,int $revision): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT r.*,CONCAT(u.first_name," ",u.last_name) AS user_name
             FROM announcement_reads r JOIN users u ON u.id=r.user_id
             WHERE r.announcement_id=:id AND r.revision=:revision ORDER BY r.read_at'
        );
        $stmt->execute(['id'=>$id,'revision'=>$revision]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachments(int $id): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT a.*,CONCAT(u.first_name," ",u.last_name) AS uploader_name FROM attachments a
             LEFT JOIN users u ON u.id=a.uploaded_by
             WHERE a.module="messages" AND a.record_id=:id AND a.deleted_at IS NULL ORDER BY a.uploaded_at,a.id'
        );
        $stmt->execute(['id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

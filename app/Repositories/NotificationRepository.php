<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class NotificationRepository
{
    public function forUser(int $userId,?int $locationId=null,int $limit=100): array
    {
        $where='n.user_id=:user_id';$params=['user_id'=>$userId];
        if($locationId!==null){$where.=' AND (n.location_id IS NULL OR n.location_id=:location_id)';$params['location_id']=$locationId;}
        $limit=max(1,min(500,$limit));
        $stmt=Database::connection()->prepare(
            "SELECT n.*,l.name AS location_name FROM notifications n
             LEFT JOIN locations l ON l.id=n.location_id
             WHERE {$where} ORDER BY n.read_at IS NULL DESC,n.created_at DESC,n.id DESC LIMIT {$limit}"
        );
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadCount(int $userId,?int $locationId=null): int
    {
        $where='user_id=:user_id AND read_at IS NULL';$params=['user_id'=>$userId];
        if($locationId!==null){$where.=' AND (location_id IS NULL OR location_id=:location_id)';$params['location_id']=$locationId;}
        $stmt=Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE {$where}");$stmt->execute($params);return (int)$stmt->fetchColumn();
    }

    public function create(int $userId,string $eventCode,?int $locationId,string $title,string $message,?string $url,string $severity): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO notifications (user_id,location_id,event_code,title,message,target_url,severity,created_at)
             VALUES (:user_id,:location_id,:event_code,:title,:message,:url,:severity,NOW())'
        );
        $stmt->execute(['user_id'=>$userId,'location_id'=>$locationId,'event_code'=>$eventCode,'title'=>$title,'message'=>$message,'url'=>$url,'severity'=>$severity]);
        return (int)Database::connection()->lastInsertId();
    }

    public function markRead(int $id,int $userId): void
    {
        Database::connection()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=:id AND user_id=:user_id')->execute(['id'=>$id,'user_id'=>$userId]);
    }

    public function markAllRead(int $userId,?int $locationId=null): void
    {
        $sql='UPDATE notifications SET read_at=NOW() WHERE user_id=:user_id AND read_at IS NULL';$params=['user_id'=>$userId];
        if($locationId!==null){$sql.=' AND (location_id IS NULL OR location_id=:location_id)';$params['location_id']=$locationId;}
        Database::connection()->prepare($sql)->execute($params);
    }

    public function internalEnabled(string $eventCode,?string $roleCode,?int $locationId): bool
    {
        $stmt=Database::connection()->prepare(
            'SELECT internal_enabled FROM notification_rules
             WHERE event_code=:event_code AND active=1
               AND (role_code=:role_code OR role_code IS NULL)
               AND (location_id=:location_id OR location_id IS NULL)
             ORDER BY location_id IS NOT NULL DESC,role_code IS NOT NULL DESC LIMIT 1'
        );
        $stmt->execute(['event_code'=>$eventCode,'role_code'=>$roleCode,'location_id'=>$locationId]);
        $value=$stmt->fetchColumn();return $value===false?true:(bool)$value;
    }

    public function emailEnabled(string $eventCode,?string $roleCode,?int $locationId): bool
    {
        $stmt=Database::connection()->prepare(
            'SELECT email_enabled FROM notification_rules
             WHERE event_code=:event_code AND active=1
               AND (role_code=:role_code OR role_code IS NULL)
               AND (location_id=:location_id OR location_id IS NULL)
             ORDER BY location_id IS NOT NULL DESC,role_code IS NOT NULL DESC LIMIT 1'
        );
        $stmt->execute(['event_code'=>$eventCode,'role_code'=>$roleCode,'location_id'=>$locationId]);
        $value=$stmt->fetchColumn();return $value===false?false:(bool)$value;
    }

    public function existsRecent(int $userId,string $eventCode,?string $targetUrl,int $hours=24): bool
    {
        $hours=max(1,min(168,$hours));
        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE user_id=:user_id AND event_code=:event_code
               AND ((target_url IS NULL AND :target_url IS NULL) OR target_url=:target_url2)
               AND created_at>=DATE_SUB(NOW(),INTERVAL '.$hours.' HOUR)'
        );
        $stmt->execute(['user_id'=>$userId,'event_code'=>$eventCode,'target_url'=>$targetUrl,'target_url2'=>$targetUrl]);
        return (bool)$stmt->fetchColumn();
    }

}

<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use WKS\Core\Database;
use WKS\Repositories\NotificationRepository;

final class NotificationService
{
    public function notifyUser(int $userId,string $eventCode,?int $locationId,string $title,string $message,?string $url=null,string $severity='info'): void
    {
        $stmt=Database::connection()->prepare('SELECT r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:id AND u.status="active" AND u.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id'=>$userId]);$role=$stmt->fetchColumn();if($role===false)return;
        $repo=new NotificationRepository();
        if($repo->internalEnabled($eventCode,(string)$role,$locationId)){
            $repo->create($userId,$eventCode,$locationId,$title,$message,$url,$severity);
        }
        if($locationId!==null && $repo->emailEnabled($eventCode,(string)$role,$locationId)){
            (new MailService())->queueLocation($locationId,$eventCode,$title,$message.($url?"\n\n".$url:''));
        }
    }

    public function notifyRole(string $eventCode,?int $locationId,string $roleCode,string $title,string $message,?string $url=null,string $severity='info'): void
    {
        $repo=new NotificationRepository();
        $internal=$repo->internalEnabled($eventCode,$roleCode,$locationId);
        $sql='SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id=u.role_id';
        $params=['role_code'=>$roleCode];
        if($locationId!==null){$sql.=' JOIN user_locations ul ON ul.user_id=u.id';}
        $sql.=' WHERE r.code=:role_code AND u.status="active" AND u.deleted_at IS NULL';
        if($locationId!==null){$sql.=' AND ul.location_id=:location_id';$params['location_id']=$locationId;}
        $stmt=Database::connection()->prepare($sql);$stmt->execute($params);
        if($internal)foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId)$repo->create((int)$userId,$eventCode,$locationId,$title,$message,$url,$severity);
        if($locationId!==null && $repo->emailEnabled($eventCode,$roleCode,$locationId)){
            (new MailService())->queueLocation($locationId,$eventCode,$title,$message.($url?"\n\n".$url:''));
        }
    }
}

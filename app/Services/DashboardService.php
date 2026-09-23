<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Repositories\AnnouncementRepository;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\NotificationRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Repositories\ValuablesRepository;
use WKS\Core\Database;

final class DashboardService
{
    public function data(int $userId,int $locationId,string $roleCode): array
    {
        $duty=new DutybookRepository();$current=$duty->currentOpenSessionForUser($locationId,$userId);
        $open=$duty->search($locationId,['status'=>'open'],1,1);
        $inProgress=$duty->search($locationId,['status'=>'in_progress'],1,1);
        $handover=$duty->search($locationId,['status'=>'handover'],1,1);
        $reports=new SpecialReportRepository();
        $unreviewed=$reports->search($locationId,['status'=>'completed'],1,1);
        $revision=$reports->search($locationId,['status'=>'revision_required'],1,1);
        $valuables=new ValuablesRepository();$vs=new ValuablesService();$counts=$valuables->counts($locationId,$vs->longTermDays());
        $attendance=$current?count($duty->attendance((int)$current['id'])):0;
        $roleStmt=Database::connection()->prepare('SELECT id FROM roles WHERE code=:code LIMIT 1');$roleStmt->execute(['code'=>$roleCode]);$roleId=(int)$roleStmt->fetchColumn();
        $tilesStmt=Database::connection()->prepare('SELECT tile_code FROM dashboard_role_tiles WHERE role_id=:role_id AND visible=1');$tilesStmt->execute(['role_id'=>$roleId]);
        $tiles=$tilesStmt->fetchAll(\PDO::FETCH_COLUMN);
        return [
            'tiles'=>$tiles,'current_shift'=>$current,'attendance_count'=>$attendance,
            'open_dutybook'=>$open['total']+$inProgress['total']+$handover['total'],
            'unreviewed_reports'=>$unreviewed['total'],
            'revision_reports'=>$revision['total'],
            'valuables'=>$counts,
            'notification_unread'=>(new NotificationRepository())->unreadCount($userId,$locationId),
            'announcements'=>(new AnnouncementRepository())->visibleForUser($userId,$locationId,$roleCode,5),
        ];
    }
}

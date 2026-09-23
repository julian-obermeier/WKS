<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Repositories\AnnouncementRepository;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\NotificationRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Repositories\ValuablesRepository;

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
        return [
            'current_shift'=>$current,
            'open_dutybook'=>$open['total']+$inProgress['total']+$handover['total'],
            'unreviewed_reports'=>$unreviewed['total'],
            'revision_reports'=>$revision['total'],
            'valuables'=>$counts,
            'notification_unread'=>(new NotificationRepository())->unreadCount($userId,$locationId),
            'announcements'=>(new AnnouncementRepository())->visibleForUser($userId,$locationId,$roleCode,5),
        ];
    }
}

<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AdminRepository;
use WKS\Services\AuditService;
use WKS\Services\CronService;

final class CronController
{
    public function index(Request $request): Response
    {
        $jobs=(new AdminRepository())->cronJobs();return View::render('admin/cron/index',compact('jobs'));
    }

    public function run(Request $request): Response
    {
        $result=(new CronService())->runDue(true);(new AuditService())->log('cron_manual_run','cron',null,null,$result,[],$request);
        flash('success','Cronjobs wurden manuell ausgeführt.');return Response::redirect(url('admin/cron'));
    }

    public function update(Request $request,string $id): Response
    {
        $minutes=max(1,min(10080,(int)$request->post('interval_minutes',5)));$active=(bool)$request->post('active');
        (new AdminRepository())->updateCron((int)$id,$minutes,$active);(new AuditService())->log('cron_job_updated','cron',$id,null,['minutes'=>$minutes,'active'=>$active],[],$request);
        flash('success','Cronjob wurde aktualisiert.');return Response::redirect(url('admin/cron'));
    }
}

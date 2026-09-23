<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\SettingsRepository;
use WKS\Services\AuditService;
use WKS\Services\SystemStatusService;

final class SystemController
{
    public function status(Request $request): Response
    {
        $result=(new SystemStatusService())->check();
        $maintenance=(bool)(new SettingsRepository())->get('system.maintenance_mode',false);
        return View::render('admin/system/status',compact('result','maintenance'));
    }

    public function runCheck(Request $request): Response
    {
        $result=(new SystemStatusService())->check();
        (new AuditService())->log('system_check_run','system','status',null,$result,[],$request);
        flash($result['overall']==='error'?'error':'success','Systemcheck abgeschlossen: '.strtoupper($result['overall']).'.');
        return Response::redirect(url('admin/system/status'));
    }

    public function maintenance(Request $request): Response
    {
        $enabled=(bool)$request->post('enabled',false);$repo=new SettingsRepository();$old=(bool)$repo->get('system.maintenance_mode',false);
        $repo->set('system.maintenance_mode',$enabled,'bool',Auth::id());
        (new AuditService())->log('maintenance_mode_changed','system','maintenance',['enabled'=>$old],['enabled'=>$enabled],[],$request);
        flash('success',$enabled?'Wartungsmodus wurde aktiviert.':'Wartungsmodus wurde deaktiviert.');
        return Response::redirect(url('admin/system/status'));
    }
}

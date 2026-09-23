<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\SettingsRepository;
use WKS\Services\AuditService;

final class SettingsController
{
    public function security(Request $request): Response
    {
        $repo = new SettingsRepository();
        $settings = [
            'inactivity_minutes' => (int) $repo->get('security.inactivity_minutes', 30),
            'login_max_attempts' => (int) $repo->get('security.login_max_attempts', 5),
            'login_lock_minutes' => (int) $repo->get('security.login_lock_minutes', 15),
        ];

        return View::render('admin/settings/security', compact('settings'));
    }

    public function updateSecurity(Request $request): Response
    {
        $inactivity = max(1, min(1440, (int) $request->post('inactivity_minutes')));
        $maxAttempts = max(1, min(20, (int) $request->post('login_max_attempts')));
        $lockMinutes = max(1, min(1440, (int) $request->post('login_lock_minutes')));

        $repo = new SettingsRepository();
        $old = [
            'inactivity_minutes' => $repo->get('security.inactivity_minutes', 30),
            'login_max_attempts' => $repo->get('security.login_max_attempts', 5),
            'login_lock_minutes' => $repo->get('security.login_lock_minutes', 15),
        ];

        $repo->set('security.inactivity_minutes', $inactivity, 'int', Auth::id());
        $repo->set('security.login_max_attempts', $maxAttempts, 'int', Auth::id());
        $repo->set('security.login_lock_minutes', $lockMinutes, 'int', Auth::id());

        (new AuditService())->log('security_settings_updated', 'settings', 'security', $old, [
            'inactivity_minutes' => $inactivity,
            'login_max_attempts' => $maxAttempts,
            'login_lock_minutes' => $lockMinutes,
        ], [], $request);

        flash('success', 'Sicherheitseinstellungen wurden gespeichert.');
        return Response::redirect(url('admin/settings/security'));
    }

    public function valuables(Request $request): Response
    {
        $repo=new SettingsRepository();
        $settings=[
            'long_term_days'=>(int)$repo->get('valuables.long_term_days',14),
            'retention_days'=>(int)$repo->get('valuables.retention_days',3650),
        ];
        return View::render('admin/settings/valuables',compact('settings'));
    }

    public function updateValuables(Request $request): Response
    {
        $longTerm=max(1,min(3650,(int)$request->post('long_term_days',14)));
        $retention=max(1,min(36500,(int)$request->post('retention_days',3650)));
        $repo=new SettingsRepository();
        $old=[
            'long_term_days'=>$repo->get('valuables.long_term_days',14),
            'retention_days'=>$repo->get('valuables.retention_days',3650),
        ];
        $repo->set('valuables.long_term_days',$longTerm,'int',Auth::id());
        $repo->set('valuables.retention_days',$retention,'int',Auth::id());
        (new AuditService())->log('valuables_settings_updated','settings','valuables',$old,[
            'long_term_days'=>$longTerm,'retention_days'=>$retention
        ],[],$request);
        flash('success','Wertsachen-Einstellungen wurden gespeichert.');
        return Response::redirect(url('admin/settings/valuables'));
    }

}

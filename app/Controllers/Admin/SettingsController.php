<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\SettingsRepository;
use WKS\Repositories\RoleRepository;
use WKS\Core\Database;
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


    public function uploads(Request $request): Response
    {
        $repo=new SettingsRepository();
        $settings=[
            'max_mb'=>(int)$repo->get('uploads.max_mb',10),
            'dutybook'=>(array)$repo->get('uploads.dutybook_extensions',['jpg','jpeg','png','pdf','docx']),
            'special_report'=>(array)$repo->get('uploads.special_report_extensions',['jpg','jpeg','png','pdf','docx']),
            'valuables'=>(array)$repo->get('uploads.valuables_extensions',['jpg','jpeg','png','pdf']),
            'house_bans'=>(array)$repo->get('uploads.house_bans_extensions',['jpg','jpeg','png','pdf']),
            'messages'=>(array)$repo->get('uploads.messages_extensions',['jpg','jpeg','png','pdf']),
        ];
        return View::render('admin/settings/uploads',compact('settings'));
    }

    public function updateUploads(Request $request): Response
    {
        $allowed=['jpg','jpeg','png','pdf','docx'];$repo=new SettingsRepository();$max=max(1,min(100,(int)$request->post('max_mb',10)));
        $repo->set('uploads.max_mb',$max,'int',Auth::id());
        foreach(['dutybook','special_report','valuables','house_bans','messages'] as $module){
            $values=array_values(array_unique(array_intersect($allowed,array_map('strtolower',array_map('strval',(array)$request->post($module,[]))))));
            if(in_array($module,['valuables','house_bans','messages'],true))$values=array_values(array_diff($values,['docx']));
            $repo->set('uploads.'.$module.'_extensions',$values,'json',Auth::id());
        }
        (new AuditService())->log('upload_settings_updated','settings','uploads',null,['max_mb'=>$max],[],$request);
        flash('success','Upload-Richtlinien wurden gespeichert.');
        return Response::redirect(url('admin/settings/uploads'));
    }

    public function dashboard(Request $request): Response
    {
        $roles=(new RoleRepository())->all();$tiles=self::dashboardTiles();$assigned=[];
        $stmt=Database::connection()->query('SELECT role_id,tile_code,visible FROM dashboard_role_tiles');
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)$assigned[(int)$row['role_id']][$row['tile_code']]=(bool)$row['visible'];
        return View::render('admin/settings/dashboard',compact('roles','tiles','assigned'));
    }

    public function updateDashboard(Request $request): Response
    {
        $tiles=self::dashboardTiles();$roles=(new RoleRepository())->all();$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare(
                'INSERT INTO dashboard_role_tiles (role_id,tile_code,visible,updated_at,updated_by)
                 VALUES (:role_id,:tile_code,:visible,NOW(),:user_id)
                 ON DUPLICATE KEY UPDATE visible=VALUES(visible),updated_at=NOW(),updated_by=VALUES(updated_by)'
            );
            foreach($roles as $role){
                $selected=array_map('strval',(array)$request->post('role_'.$role['id'],[]));
                foreach(array_keys($tiles) as $code)$stmt->execute([
                    'role_id'=>$role['id'],'tile_code'=>$code,'visible'=>in_array($code,$selected,true)?1:0,'user_id'=>Auth::id()
                ]);
            }
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
        (new AuditService())->log('dashboard_tiles_updated','settings','dashboard',null,null,[],$request);
        flash('success','Dashboard-Sichtbarkeit wurde gespeichert.');
        return Response::redirect(url('admin/settings/dashboard'));
    }

    private static function dashboardTiles(): array
    {
        return [
            'current_shift'=>'Aktuelle Schicht','open_dutybook'=>'Offene Dienstbuchvorgänge','notifications'=>'Ungelesene Benachrichtigungen',
            'unreviewed_reports'=>'Ungeprüfte Sonderberichte','revision_reports'=>'Nachbearbeitungen','valuables_metric'=>'Wertsachen / Kassetten',
            'announcements'=>'Wichtige Mitteilungen','dutybook'=>'Dienstbuch-Schnellzugriff','special_reports'=>'Sonderbericht-Schnellzugriff',
            'valuables'=>'Wertsachen-Schnellzugriff','house_bans'=>'Hausverbote-Schnellzugriff','information'=>'Informationen',
            'administration'=>'Administration'
        ];
    }

}

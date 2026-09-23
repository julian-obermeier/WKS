<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\NotificationRepository;
use WKS\Services\AuditService;

final class NotificationRuleController
{
    private const EVENTS=[
        'special_report_completed'=>'Sonderbericht abgeschlossen',
        'special_report_revision_required'=>'Sonderbericht Nachbearbeitung',
        'long_term_valuables'=>'Langzeitverwahrung',
        'open_handover'=>'Offene Schichtübergabe',
    ];

    public function index(Request $request): Response
    {
        $rules=(new NotificationRepository())->rules();$locations=(new LocationRepository())->all(false);$events=self::EVENTS;
        return View::render('admin/notifications/index',compact('rules','locations','events'));
    }

    public function save(Request $request): Response
    {
        $event=(string)$request->post('event_code','');if(!isset(self::EVENTS[$event]))throw new HttpException(422,'Ungültiges Ereignis.');
        $role=trim((string)$request->post('role_code',''));if($role!==''&&!in_array($role,['employee','management','admin'],true))throw new HttpException(422,'Ungültige Rolle.');
        $location=($l=(int)$request->post('location_id',0))>0?$l:null;
        if($location&&!(new LocationRepository())->find($location))throw new HttpException(422,'Ungültiger Standort.');
        $data=[
            'event_code'=>$event,'role_code'=>$role!==''?$role:null,'location_id'=>$location,
            'internal_enabled'=>$request->post('internal_enabled')?1:0,'email_enabled'=>$request->post('email_enabled')?1:0,
            'active'=>$request->post('active')?1:0
        ];
        $id=($x=(int)$request->post('id',0))>0?$x:null;
        $saved=(new NotificationRepository())->saveRule($id,$data);
        (new AuditService())->log('notification_rule_saved','notifications',(string)$saved,null,$data,[],$request);
        flash('success','Benachrichtigungsregel wurde gespeichert.');
        return Response::redirect(url('admin/notifications'));
    }
}

<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AdminRepository;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\SettingsRepository;
use WKS\Services\AuditService;

final class MailController
{
    public function index(Request $request): Response
    {
        $settingsRepo=new SettingsRepository();$settings=[
            'global_enabled'=>(bool)$settingsRepo->get('mail.global_enabled',false),
            'from_name'=>(string)$settingsRepo->get('mail.from_name','WKS'),
            'from_address'=>(string)$settingsRepo->get('mail.from_address','wks@localhost'),
        ];
        $locations=(new LocationRepository())->all(true);$admin=new AdminRepository();$queue=$admin->mailQueue(100);$log=$admin->mailLog(100);
        return View::render('admin/mail/index',compact('settings','locations','queue','log'));
    }

    public function update(Request $request): Response
    {
        $fromName=trim((string)$request->post('from_name','WKS'));$fromAddress=trim((string)$request->post('from_address',''));
        if(!filter_var($fromAddress,FILTER_VALIDATE_EMAIL))throw new HttpException(422,'Absenderadresse ist ungültig.');
        $enabled=(bool)$request->post('global_enabled');$settings=new SettingsRepository();
        $settings->set('mail.global_enabled',$enabled,'bool',Auth::id());$settings->set('mail.from_name',$fromName,'string',Auth::id());$settings->set('mail.from_address',$fromAddress,'string',Auth::id());

        $repo=new LocationRepository();
        foreach((array)$request->post('locations',[]) as $id=>$row){
            if(!is_array($row))continue;$location=$repo->find((int)$id);if(!$location)continue;
            $mode=(string)($row['mail_mode']??'disabled');if(!in_array($mode,['disabled','test','production'],true))$mode='disabled';
            $test=trim((string)($row['mail_test_address']??''));if($mode==='test'&&!filter_var($test,FILTER_VALIDATE_EMAIL))throw new HttpException(422,'Ungültige Testadresse für '.$location['name'].'.');
            $repo->update((int)$id,[
                'name'=>$location['name'],'code'=>$location['code'],'email_address'=>$location['email_address'],'mail_mode'=>$mode,
                'mail_test_address'=>$test!==''?$test:null,'active'=>$location['active'],'updated_by'=>Auth::id()
            ]);
        }
        (new AuditService())->log('mail_settings_updated','mail','configuration',null,['global_enabled'=>$enabled],[],$request);
        flash('success','Mailkonfiguration wurde gespeichert.');return Response::redirect(url('admin/mail'));
    }
}

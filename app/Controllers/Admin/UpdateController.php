<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use Throwable;
use WKS\Core\Auth;
use WKS\Core\MigrationRunner;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\UpdateRepository;
use WKS\Services\AuditService;
use WKS\Services\PostUpdateCheckService;
use WKS\Services\UpdateService;

final class UpdateController
{
    public function index(Request $request): Response
    {
        $error=null;$remote=null;try{$remote=(new UpdateService())->check();}catch(Throwable $e){$error=$e->getMessage();}
        $runner=new MigrationRunner();$pending=array_map('basename',$runner->pending());$history=(new UpdateRepository())->history();
        return View::render('admin/updates/index',compact('remote','error','pending','history'));
    }

    public function check(Request $request): Response
    {
        try{$info=(new UpdateService())->check();flash('success',$info['update_available']?'Neue Version/Commit auf main erkannt.':'Installierter Versionsstand entspricht dem veröffentlichten Stand.');}
        catch(Throwable $e){flash('error','Updateprüfung fehlgeschlagen: '.$e->getMessage());}
        return Response::redirect(url('admin/updates'));
    }

    public function install(Request $request): Response
    {
        try{$result=(new UpdateService())->install((int)Auth::id());flash($result['status']==='successful'?'success':'error','Update '.$result['version'].' abgeschlossen: '.$result['status'].'.');}
        catch(Throwable $e){flash('error','Update fehlgeschlagen. Wartungsmodus bleibt ggf. aktiv: '.$e->getMessage());}
        return Response::redirect(url('admin/updates'));
    }

    public function migrate(Request $request): Response
    {
        try{$runner=new MigrationRunner();$before=array_map('basename',$runner->pending());$done=$runner->migrate();$post=(new PostUpdateCheckService())->run();(new AuditService())->log('migrations_manual_run','updates','migrations',['pending'=>$before],['completed'=>$done,'post'=>$post],[],$request);flash('success',count($done).' Migration(en) ausgeführt.');}
        catch(Throwable $e){flash('error','Migration fehlgeschlagen: '.$e->getMessage());}
        return Response::redirect(url('admin/updates'));
    }
}

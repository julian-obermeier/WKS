<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AdminRepository;
use WKS\Services\AuditService;

final class ErrorLogController
{
    public function index(Request $request): Response
    {
        $filters=['status'=>(string)$request->query('status',''),'module'=>(string)$request->query('module','')];
        $errors=(new AdminRepository())->errors($filters);return View::render('admin/errors/index',compact('errors','filters'));
    }

    public function status(Request $request,string $id): Response
    {
        $status=(string)$request->post('status');if(!in_array($status,['open','reviewed','done'],true))throw new HttpException(422,'Ungültiger Fehlerstatus.');
        (new AdminRepository())->updateErrorStatus((int)$id,$status,(int)Auth::id());
        (new AuditService())->log('error_status_changed','system_errors',$id,null,['status'=>$status],[],$request);
        flash('success','Fehlerstatus wurde aktualisiert.');return Response::redirect(url('admin/errors'));
    }
}

<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\NotificationRepository;

final class NotificationController
{
    public function index(Request $request): Response
    {
        $repo=new NotificationRepository();$items=$repo->forUser((int)Auth::id(),active_location_id(),200);
        return View::render('notifications/index',compact('items'));
    }

    public function read(Request $request,string $id): Response
    {
        (new NotificationRepository())->markRead((int)$id,(int)Auth::id());
        $target=(string)$request->post('target',url('notifications'));
        if(!str_starts_with($target,url()))$target=url('notifications');
        return Response::redirect($target);
    }

    public function readAll(Request $request): Response
    {
        (new NotificationRepository())->markAllRead((int)Auth::id(),active_location_id());flash('success','Benachrichtigungen wurden als gelesen markiert.');
        return Response::redirect(url('notifications'));
    }
}

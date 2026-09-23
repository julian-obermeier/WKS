<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AnnouncementRepository;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\RoleRepository;
use WKS\Services\AnnouncementService;

final class AnnouncementController
{
    public function index(Request $request): Response
    {
        $user=Auth::user();$items=(new AnnouncementRepository())->visibleForUser((int)Auth::id(),(int)active_location_id(),(string)$user['role_code']);
        return View::render('announcements/index',compact('items'));
    }

    public function manage(Request $request): Response
    {
        $items=(new AnnouncementRepository())->all();return View::render('announcements/manage',compact('items'));
    }

    public function create(Request $request): Response{return $this->form(null);}

    public function store(Request $request): Response
    {
        try{$id=(new AnnouncementService())->save(null,$request->all(),$request->files());clear_old();flash('success','Mitteilung wurde gespeichert.');return Response::redirect(url('announcements/'.$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());return Response::redirect(url('announcements/create'));}
    }

    public function show(Request $request,string $id): Response
    {
        $user=Auth::user();$repo=new AnnouncementRepository();$record=$repo->findVisible((int)$id,(int)Auth::id(),(int)active_location_id(),(string)$user['role_code']);
        if(!$record&&can('messages.manage'))$record=$repo->find((int)$id);
        if(!$record)throw new HttpException(404,'Mitteilung nicht gefunden.');
        if($record['status']==='published')(new AnnouncementService())->markReadVisible((int)$id,(int)Auth::id(),(int)active_location_id(),(string)$user['role_code'],false);
        return View::render('announcements/show',compact('record'));
    }

    public function edit(Request $request,string $id): Response
    {
        $record=(new AnnouncementRepository())->find((int)$id);if(!$record)throw new HttpException(404,'Mitteilung nicht gefunden.');return $this->form($record);
    }

    public function update(Request $request,string $id): Response
    {
        try{(new AnnouncementService())->save((int)$id,$request->all(),$request->files());clear_old();flash('success','Mitteilung wurde aktualisiert.');return Response::redirect(url('announcements/'.(int)$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());return Response::redirect(url('announcements/'.(int)$id.'/edit'));}
    }

    public function confirm(Request $request,string $id): Response
    {
        $user=Auth::user();(new AnnouncementService())->markReadVisible((int)$id,(int)Auth::id(),(int)active_location_id(),(string)$user['role_code'],true);
        flash('success','Lesebestätigung wurde gespeichert.');return Response::redirect(url('announcements/'.(int)$id));
    }

    private function form(?array $record): Response
    {
        return View::render('announcements/form',['record'=>$record,'locations'=>(new LocationRepository())->all(false),'roles'=>(new RoleRepository())->all()]);
    }
}

<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Services\HandoverService;

final class HandoverController
{
    public function show(Request $request,string $sessionId): Response
    {
        $locationId=(int)active_location_id();$id=(int)$sessionId;
        $repo=new DutybookRepository();
        $session=$repo->session($id,$locationId);
        if(!$session)throw new \WKS\Core\HttpException(404,'Schicht nicht gefunden.');
        $openEntries=$repo->openEntriesForSession($locationId,$id);
        $handover=$repo->handoverForSession($id);
        $shifts=(new MasterDataRepository())->shifts($locationId);
        $users=(new MasterDataRepository())->usersForLocation($locationId);
        return View::render('dutybook/handover',compact('session','openEntries','handover','shifts','users'));
    }

    public function save(Request $request,string $sessionId): Response
    {
        $assignments=[];
        foreach((array)$request->post('entries',[]) as $entryId=>$row){
            if(!is_array($row))$row=[];
            $assignments[(int)$entryId]=['user_id'=>(int)($row['user_id']??0)];
        }
        (new HandoverService())->save(
            (int)active_location_id(),(int)$sessionId,(int)$request->post('to_shift_id'),
            (string)$request->post('notes',''),$assignments
        );
        flash('success','Übergabe wurde vorbereitet. Beide Schichten müssen sie noch bestätigen.');
        return Response::redirect(url('handover/'.(int)$sessionId));
    }

    public function confirmOutgoing(Request $request,string $sessionId): Response
    {
        (new HandoverService())->confirmOutgoing((int)active_location_id(),(int)$sessionId);
        flash('success','Abgebende Schicht hat die Übergabe bestätigt.');
        return Response::redirect(url('handover/'.(int)$sessionId));
    }

    public function confirmIncoming(Request $request,string $sessionId): Response
    {
        (new HandoverService())->confirmIncoming((int)active_location_id(),(int)$sessionId);
        flash('success','Übergabe ist vollständig abgeschlossen.');
        return Response::redirect(url('handover/'.(int)$sessionId));
    }
}

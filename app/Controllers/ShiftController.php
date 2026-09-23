<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Services\AuditService;
use WKS\Services\ShiftService;

final class ShiftController
{
    public function accept(Request $request): Response
    {
        $locationId=(int)active_location_id();
        $shiftId=(int)$request->post('shift_id');
        $session=(new ShiftService())->acceptDuty($locationId,$shiftId,(int)Auth::id());
        (new AuditService())->log('duty_accepted','dutybook',(string)($session['id']??''),null,['shift_id'=>$shiftId,'duty_date'=>$session['duty_date']??null],[],$request);
        flash('success','Dienst wurde übernommen.');
        return Response::redirect(url('dutybook'));
    }

    public function end(Request $request,string $id): Response
    {
        (new ShiftService())->endShift((int)active_location_id(),(int)$id,(int)Auth::id());
        (new AuditService())->log('shift_ended','dutybook',$id,null,null,[],$request);
        flash('success','Schicht wurde beendet.');
        return Response::redirect(url('dutybook'));
    }
}

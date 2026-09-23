<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Services\DraftAutosaveService;

final class AutosaveController
{
    public function store(Request $request): Response
    {
        $module=(string)$request->post('module','');$context=(string)$request->post('context_key','new');
        (new DraftAutosaveService())->save((int)Auth::id(),(int)active_location_id(),$module,$context,$request->all());
        return new Response(json_encode(['ok'=>true,'saved_at'=>date('c')],JSON_THROW_ON_ERROR),200,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
    }

    public function discard(Request $request): Response
    {
        $module=(string)$request->post('module','');$context=(string)$request->post('context_key','new');
        (new DraftAutosaveService())->delete((int)Auth::id(),(int)active_location_id(),$module,$context);
        $target=(string)$request->post('redirect_to',url());
        if(!str_starts_with($target,url()))$target=url();
        if ((string)$request->post('json','') === '1') {
            return new Response(json_encode(['ok'=>true],JSON_THROW_ON_ERROR),200,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
        }
        flash('success','Automatischer Entwurf wurde verworfen.');
        return Response::redirect($target);
    }
}

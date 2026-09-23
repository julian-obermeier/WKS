<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Authorization;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\GlobalSearchRepository;
use WKS\Repositories\LocationRepository;

final class GlobalSearchController
{
    public function index(Request $request): Response
    {
        $q=trim((string)$request->query('q',''));$module=(string)$request->query('module','');
        $locations=(new LocationRepository())->forUser((int)\WKS\Core\Auth::id());
        $selectedLocation=(int)$request->query('location_id',active_location_id());
        if(!(new LocationRepository())->userHasLocation((int)\WKS\Core\Auth::id(),$selectedLocation))$selectedLocation=(int)active_location_id();
        $allowed=[];
        if(Authorization::can('dutybook.read'))$allowed[]='dutybook';
        if(Authorization::can('special_reports.read'))$allowed[]='special_reports';
        if(Authorization::can('valuables.read'))$allowed[]='valuables';
        if(Authorization::can('house_bans.read'))$allowed[]='house_bans';
        if($module!==''&&in_array($module,$allowed,true))$allowed=[$module];
        $results=$q!==''?(new GlobalSearchRepository())->search($q,$selectedLocation,$allowed):[];
        return View::render('search/index',compact('q','module','results','allowed','locations','selectedLocation'));
    }
}

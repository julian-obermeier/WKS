<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\TrashRepository;
use WKS\Services\TrashService;

final class TrashController
{
    public function index(Request $request): Response
    {
        $search=trim((string)$request->query('q',''));$entries=(new TrashRepository())->all($search);
        return View::render('admin/trash/index',compact('entries','search'));
    }

    public function move(Request $request,string $module,string $id): Response
    {
        $this->requireModuleDelete($module);
        (new TrashService())->move($module,(int)$id,(int)active_location_id(),(int)Auth::id());
        flash('success','Datensatz wurde in den zentralen Papierkorb verschoben.');
        return Response::redirect(url('admin/trash'));
    }

    public function restore(Request $request,string $trashId): Response
    {
        $trash=(new TrashRepository())->find((int)$trashId);if(!$trash)throw new HttpException(404,'Papierkorbeintrag nicht gefunden.');
        $this->requireModuleDelete((string)$trash['module']);
        (new TrashService())->restore((int)$trashId,(int)Auth::id());flash('success','Datensatz wurde wiederhergestellt.');
        return Response::redirect(url('admin/trash'));
    }

    private function requireModuleDelete(string $module): void
    {
        $permission=match($module){
            'dutybook'=>'dutybook.delete',
            'special_reports'=>'special_reports.delete',
            'valuables'=>'valuables.delete',
            'house_bans'=>'house_bans.delete',
            default=>null,
        };
        if($permission===null||!Authorization::can($permission))throw new HttpException(403,'Für dieses Modul fehlt das Löschrecht.');
    }

    public function hardDelete(Request $request,string $trashId): Response
    {
        $trash=(new TrashRepository())->find((int)$trashId);if(!$trash)throw new HttpException(404,'Papierkorbeintrag nicht gefunden.');
        $this->requireModuleDelete((string)$trash['module']);
        (new TrashService())->hardDelete((int)$trashId,(int)Auth::id());flash('success','Datensatz wurde endgültig gelöscht.');
        return Response::redirect(url('admin/trash'));
    }
}

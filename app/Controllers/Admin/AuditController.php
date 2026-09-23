<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Request;
use WKS\Core\Authorization;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Services\AuditService;
use WKS\Repositories\AuditLogRepository;
use WKS\Repositories\LocationRepository;

final class AuditController
{
    public function index(Request $request): Response
    {
        $canFilter=Authorization::can('system.audit.filter');
        $filters = $canFilter ? [
            'module' => trim((string) $request->query('module', '')),
            'action' => trim((string) $request->query('action', '')),
            'user_id' => (int) $request->query('user_id', 0),
            'location_id' => (int) $request->query('location_id', 0),
        ] : ['module'=>'','action'=>'','user_id'=>0,'location_id'=>0];
        $page = max(1, (int) $request->query('page', 1));
        $logs = (new AuditLogRepository())->paginate($filters, $page);
        $locations = (new LocationRepository())->all(true);
        return View::render('admin/audit/index', compact('logs', 'filters', 'locations','canFilter'));
    }

    public function exportCsv(Request $request): Response
    {
        $canFilter=Authorization::can('system.audit.filter');
        $filters=$canFilter?[
            'module'=>trim((string)$request->query('module','')),
            'action'=>trim((string)$request->query('action','')),
            'user_id'=>(int)$request->query('user_id',0),
            'location_id'=>(int)$request->query('location_id',0),
        ]:['module'=>'','action'=>'','user_id'=>0,'location_id'=>0];
        $logs=(new AuditLogRepository())->paginate($filters,1,10000);
        $fp=fopen('php://temp','r+');
        fputcsv($fp,['Zeit','Benutzer','Aktion','Modul','Datensatz','Standort','Alter Wert','Neuer Wert','Metadaten'],';');
        foreach($logs['items'] as $row)fputcsv($fp,[
            $row['occurred_at'],$row['user_name'],$row['action'],$row['module'],$row['record_id'],$row['location_name'],
            $row['old_value'],$row['new_value'],$row['metadata']
        ],';');
        rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        (new AuditService())->log('audit_export_csv','audit',null,null,['filters'=>$filters,'count'=>$logs['total']],[],$request);
        return new Response("\xEF\xBB\xBF".$csv,200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="WKS_Audit_Log.csv"'
        ]);
    }

}

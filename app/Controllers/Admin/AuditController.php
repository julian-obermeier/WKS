<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AuditLogRepository;
use WKS\Repositories\LocationRepository;

final class AuditController
{
    public function index(Request $request): Response
    {
        $filters = [
            'module' => trim((string) $request->query('module', '')),
            'action' => trim((string) $request->query('action', '')),
            'user_id' => (int) $request->query('user_id', 0),
            'location_id' => (int) $request->query('location_id', 0),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $logs = (new AuditLogRepository())->paginate($filters, $page);
        $locations = (new LocationRepository())->all(true);
        return View::render('admin/audit/index', compact('logs', 'filters', 'locations'));
    }
}

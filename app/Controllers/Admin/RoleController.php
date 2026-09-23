<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\RoleRepository;
use WKS\Services\AuditService;

final class RoleController
{
    public function index(Request $request): Response
    {
        $repo = new RoleRepository();
        $roles = $repo->all();
        $permissions = $repo->permissions();
        $assigned = [];
        foreach ($roles as $role) {
            $assigned[(int) $role['id']] = $repo->permissionIds((int) $role['id']);
        }

        return View::render('admin/roles/index', compact('roles', 'permissions', 'assigned'));
    }

    public function update(Request $request, string $id): Response
    {
        $roleId = (int) $id;
        $repo = new RoleRepository();
        $role = $repo->find($roleId);
        if (!$role) {
            throw new HttpException(404, 'Rolle nicht gefunden.');
        }

        $old = $repo->permissionIds($roleId);
        $new = array_values(array_filter(array_map('intval', (array) $request->post('permission_ids', []))));
        $repo->syncPermissions($roleId, $new);
        Authorization::reset();

        (new AuditService())->log('role_permissions_updated', 'roles', (string) $roleId, ['permission_ids' => $old], ['permission_ids' => $new], ['role' => $role['code']], $request);
        flash('success', 'Rechte der Rolle „' . $role['name'] . '“ wurden gespeichert.');

        return Response::redirect(url('admin/roles'));
    }
}

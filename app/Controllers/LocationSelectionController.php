<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\Session;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;
use WKS\Services\AuditService;

final class LocationSelectionController
{
    public function show(Request $request): Response
    {
        $locations = (new LocationRepository())->forUser((int) Auth::id());
        return View::render('locations/select', compact('locations'));
    }

    public function select(Request $request): Response
    {
        $locationId = (int) $request->post('location_id');
        $userId = (int) Auth::id();

        if (!(new LocationRepository())->userHasLocation($userId, $locationId)) {
            throw new HttpException(403, 'Dieser Standort ist Ihrem Benutzerkonto nicht zugeordnet.');
        }

        $old = active_location_id();
        Session::put('active_location_id', $locationId);
        Authorization::reset();
        (new AuditService())->log('location_switched', 'locations', (string) $locationId, ['location_id' => $old], ['location_id' => $locationId], [], $request, $userId, $locationId);

        return Response::redirect(url());
    }
}

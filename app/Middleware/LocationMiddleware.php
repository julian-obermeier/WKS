<?php
declare(strict_types=1);

namespace WKS\Middleware;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\Session;
use WKS\Repositories\LocationRepository;

final class LocationMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect(url('login'));
        }

        $repo = new LocationRepository();
        $locations = $repo->forUser($userId);

        if ($locations === []) {
            throw new HttpException(403, 'Ihrem Benutzerkonto ist kein Standort zugeordnet.');
        }

        $active = active_location_id();
        if ($active !== null && $repo->userHasLocation($userId, $active)) {
            return $next($request);
        }

        if (count($locations) === 1) {
            Session::put('active_location_id', (int) $locations[0]['id']);
            return $next($request);
        }

        return Response::redirect(url('location/select'));
    }
}

<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $location = active_location_id() ? (new LocationRepository())->find((int) active_location_id()) : null;

        return View::render('dashboard/index', compact('user', 'location'));
    }
}

<?php
declare(strict_types=1);

namespace WKS\Middleware;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\Session;
use WKS\Repositories\SettingsRepository;
use WKS\Services\AuthService;
use WKS\Services\SessionService;

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return Response::redirect(url('login'));
        }

        $user = Auth::user();
        if (!$user || $user['status'] !== 'active') {
            (new AuthService())->logout();
            flash('error', 'Ihr Benutzerkonto ist derzeit nicht aktiv.');
            return Response::redirect(url('login'));
        }

        if ((new SessionService())->currentSessionIsRevoked()) {
            (new AuthService())->logout(false);
            flash('error', 'Diese Sitzung wurde durch die Administration beendet.');
            return Response::redirect(url('login'));
        }

        $settings = new SettingsRepository();
        if ((bool) $settings->get('system.maintenance_mode', false) && ($user['role_code'] ?? '') !== 'admin' && $request->path() !== '/logout') {
            return \WKS\Core\View::render('maintenance/index', [], 503);
        }
        $timeoutMinutes = (int) $settings->get('security.inactivity_minutes', config('security.default_inactivity_minutes', 30));
        $lastActivity = (int) Session::get('last_activity', time());

        if ($timeoutMinutes > 0 && time() - $lastActivity > ($timeoutMinutes * 60)) {
            (new AuthService())->logout();
            flash('info', 'Sie wurden wegen Inaktivität automatisch abgemeldet.');
            return Response::redirect(url('login'));
        }

        Session::put('last_activity', time());
        (new SessionService())->touch();

        return $next($request);
    }
}

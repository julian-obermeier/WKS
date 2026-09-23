<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\UserRepository;
use WKS\Services\AuditService;
use WKS\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        if (Auth::check()) {
            return Response::redirect(url());
        }

        return View::render('auth/login');
    }

    public function login(Request $request): Response
    {
        $username = trim((string) $request->post('username'));
        $password = (string) $request->post('password');

        set_old(['username' => $username]);
        $result = (new AuthService())->attempt($username, $password, $request);

        if (!$result['ok']) {
            flash('error', (string) $result['message']);
            return Response::redirect(url('login'));
        }

        clear_old();
        $user = $result['user'];

        if ((bool) $user['must_change_password']) {
            return Response::redirect(url('password/change'));
        }

        $locations = (new LocationRepository())->forUser((int) $user['id']);
        return count($locations) > 1
            ? Response::redirect(url('location/select'))
            : Response::redirect(url());
    }

    public function logout(Request $request): Response
    {
        if (Auth::check()) {
            (new AuditService())->log('logout', 'auth', (string) Auth::id(), null, null, [], $request);
        }
        (new AuthService())->logout();
        return Response::redirect(url('login'));
    }

    public function showChangePassword(Request $request): Response
    {
        return View::render('auth/change-password');
    }

    public function changePassword(Request $request): Response
    {
        $password = (string) $request->post('password');
        $confirm = (string) $request->post('password_confirmation');

        if (strlen($password) < 12) {
            flash('error', 'Das neue Passwort muss mindestens 12 Zeichen lang sein.');
            return Response::redirect(url('password/change'));
        }
        if ($password !== $confirm) {
            flash('error', 'Die Passwortbestätigung stimmt nicht überein.');
            return Response::redirect(url('password/change'));
        }

        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect(url('login'));
        }

        (new UserRepository())->changePassword($userId, password_hash($password, PASSWORD_DEFAULT));
        Auth::forgetCache();
        (new AuditService())->log('password_changed', 'users', (string) $userId, null, null, [], $request);
        flash('success', 'Ihr Passwort wurde geändert.');
        return Response::redirect(url());
    }
}

<?php
declare(strict_types=1);

namespace WKS\Middleware;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;

final class ForcePasswordChangeMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $user = Auth::user();
        if ($user && (bool) $user['must_change_password']) {
            return Response::redirect(url('password/change'));
        }

        return $next($request);
    }
}

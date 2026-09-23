<?php
declare(strict_types=1);

namespace WKS\Middleware;

use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;

final class PermissionMiddleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Authorization::can($this->permission)) {
            throw new HttpException(403, 'Sie besitzen nicht die erforderliche Berechtigung für diese Funktion.');
        }

        return $next($request);
    }
}

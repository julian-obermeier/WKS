<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\Request;
use WKS\Core\Response;

final class ProfileController
{
    public function theme(Request $request): Response
    {
        $theme = (string) $request->post('theme');
        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        $stmt = Database::connection()->prepare('UPDATE users SET theme = :theme, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['theme' => $theme, 'id' => Auth::id()]);
        Auth::forgetCache();

        $target = (string) $request->post('redirect_to', url());
        if (!str_starts_with($target, url())) {
            $target = url();
        }

        return Response::redirect($target);
    }
}

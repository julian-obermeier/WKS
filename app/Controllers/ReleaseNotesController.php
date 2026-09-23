<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Services\ReleaseNotesService;

final class ReleaseNotesController
{
    public function index(Request $request): Response
    {
        $releases=(new ReleaseNotesService())->all();return View::render('whats-new/index',compact('releases'));
    }

    public function seen(Request $request,string $version): Response
    {
        (new ReleaseNotesService())->markSeen((int)Auth::id(),$version);return Response::redirect(url());
    }
}

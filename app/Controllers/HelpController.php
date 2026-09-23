<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;

final class HelpController
{
    public function index(Request $request): Response
    {
        return View::render('help/index');
    }
}

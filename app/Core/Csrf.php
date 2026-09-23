<?php
declare(strict_types=1);

namespace WKS\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }

        return $token;
    }

    public static function validate(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $provided = (string) $request->post('_token', '');
        $expected = (string) Session::get('_csrf', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new HttpException(419, 'Die Sicherheitsprüfung ist abgelaufen. Bitte laden Sie die Seite neu.');
        }
    }
}

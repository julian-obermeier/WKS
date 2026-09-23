<?php
declare(strict_types=1);

namespace WKS\Core;

use WKS\Repositories\UserRepository;

final class Auth
{
    private static ?array $userCache = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        return is_int(Session::get('user_id')) || ctype_digit((string) Session::get('user_id', ''));
    }

    public static function id(): ?int
    {
        return self::check() ? (int) Session::get('user_id') : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (!self::$loaded) {
            self::$userCache = (new UserRepository())->findById((int) Session::get('user_id'));
            self::$loaded = true;
        }

        return self::$userCache;
    }

    public static function forgetCache(): void
    {
        self::$loaded = false;
        self::$userCache = null;
    }
}

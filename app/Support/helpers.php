<?php
declare(strict_types=1);

use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\Csrf;
use WKS\Core\Session;

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];
    $parts = explode('.', $key);
    $file = array_shift($parts);

    if ($file === null || $file === '') {
        return $default;
    }

    if (!array_key_exists($file, $cache)) {
        $path = BASE_PATH . '/config/' . $file . '.php';
        $cache[$file] = is_file($path) ? require $path : [];
    }

    $value = $cache[$file];
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    $appBase = trim((string) config('app.base_path', '/'), '/');
    $prefix = $appBase === '' ? '' : '/' . $appBase;
    return $base . $prefix . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    Session::flash('notice', ['type' => $type, 'message' => $message]);
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);
    return is_array($old) ? ($old[$key] ?? $default) : $default;
}

function set_old(array $values): void
{
    Session::put('_old', $values);
}

function clear_old(): void
{
    Session::forget('_old');
}

function current_user(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Authorization::can($permission);
}

function active_location_id(): ?int
{
    $value = Session::get('active_location_id');
    return $value === null ? null : (int) $value;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '–';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

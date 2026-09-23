<?php
declare(strict_types=1);

namespace WKS\Core;

final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = (string) config('app.base_path', '/');

        if ($base !== '/' && $base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . ltrim($path, '/'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER
        );
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function isMethod(string $method): bool { return $this->method === strtoupper($method); }
    public function input(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $this->query[$key] ?? $default; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function all(): array { return array_merge($this->query, $this->post); }
    public function files(): array { return $this->files; }
    public function file(string $key): ?array { return isset($this->files[$key]) && is_array($this->files[$key]) ? $this->files[$key] : null; }
    public function ip(): string { return (string) ($this->server['REMOTE_ADDR'] ?? 'unknown'); }
    public function userAgent(): string { return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255); }
    public function referer(): ?string { return isset($this->server['HTTP_REFERER']) ? (string) $this->server['HTTP_REFERER'] : null; }
}

<?php
declare(strict_types=1);

namespace WKS\Core;

use Throwable;

final class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): never
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        self::log($e);

        $message = $status >= 500
            ? 'Es ist ein technischer Fehler aufgetreten. Bitte versuchen Sie es erneut oder informieren Sie die Administration.'
            : $e->getMessage();

        if ((bool) config('app.debug', false) && config('app.env') !== 'production') {
            $message .= "\n\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        try {
            $view = match ($status) {
                403 => 'errors/403',
                404 => 'errors/404',
                419 => 'errors/419',
                default => 'errors/500',
            };

            View::render($view, ['message' => $message], $status)->send();
        } catch (Throwable) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
            echo $message;
            exit;
        }
    }

    private static function log(Throwable $e): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($dir . '/application.log', $entry, FILE_APPEND | LOCK_EX);
    }
}

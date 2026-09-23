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

        try {
            $pdo = Database::connection();
            $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='error_logs'")->fetchColumn();
            if ($exists) {
                $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
                $module = trim((string) strtok(trim((string) parse_url($uri, PHP_URL_PATH), '/'), '/')) ?: 'system';
                $userId = Session::get('user_id');
                $stmt = $pdo->prepare(
                    'INSERT INTO error_logs (occurred_at,module,user_id,message,technical_details,status)
                     VALUES (NOW(),:module,:user_id,:message,:details,"open")'
                );
                $stmt->execute([
                    'module' => substr($module, 0, 100),
                    'user_id' => is_numeric($userId) ? (int) $userId : null,
                    'message' => $e->getMessage(),
                    'details' => $e::class . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(),
                ]);
            }
        } catch (Throwable) {
            // Datei-Logging bleibt die Fallback-Ebene, insbesondere bei DB-Ausfällen.
        }
    }
}

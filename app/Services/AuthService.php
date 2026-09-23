<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\Request;
use WKS\Core\Session;
use WKS\Repositories\SettingsRepository;
use WKS\Repositories\UserRepository;

final class AuthService
{
    public function attempt(string $username, string $password, Request $request): array
    {
        $username = trim($username);
        $identifier = mb_strtolower($username) . '|' . $request->ip();
        $settings = new SettingsRepository();
        $maxAttempts = (int) $settings->get('security.login_max_attempts', config('security.default_login_max_attempts', 5));
        $lockMinutes = (int) $settings->get('security.login_lock_minutes', config('security.default_login_lock_minutes', 15));

        $attempt = $this->attemptRecord($identifier);
        if ($attempt && $attempt['locked_until'] && strtotime((string) $attempt['locked_until']) > time()) {
            return ['ok' => false, 'message' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es später erneut.'];
        }

        $user = (new UserRepository())->findByUsername($username);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailure($identifier, $maxAttempts, $lockMinutes);
            (new AuditService())->log(
                'login_failed',
                'auth',
                null,
                null,
                null,
                ['username' => $username],
                $request,
                $user ? (int) $user['id'] : null,
                null
            );
            return ['ok' => false, 'message' => 'Benutzername oder Passwort ist nicht korrekt.'];
        }

        if ($user['status'] !== 'active') {
            (new AuditService())->log('login_blocked_status', 'auth', (string) $user['id'], null, ['status' => $user['status']], [], $request, (int) $user['id'], null);
            return ['ok' => false, 'message' => 'Dieses Benutzerkonto ist gesperrt oder deaktiviert.'];
        }

        $this->clearFailures($identifier);

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('last_activity', time());
        Session::forget('active_location_id');
        Auth::forgetCache();

        (new UserRepository())->markLogin((int) $user['id']);
        (new SessionService())->register($request->ip(), $request->userAgent());
        (new AuditService())->log('login_success', 'auth', (string) $user['id'], null, null, [], $request, (int) $user['id'], null);

        return ['ok' => true, 'user' => (new UserRepository())->findById((int) $user['id'])];
    }

    public function logout(bool $revoke = true): void
    {
        if ($revoke && Auth::check()) {
            try {
                (new SessionService())->revokeCurrent();
            } catch (\Throwable) {
            }
        }

        Session::destroy();
        Auth::forgetCache();
    }

    private function attemptRecord(string $identifier): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM login_attempts WHERE identifier_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => hash('sha256', $identifier)]);
        return $stmt->fetch() ?: null;
    }

    private function recordFailure(string $identifier, int $maxAttempts, int $lockMinutes): void
    {
        $hash = hash('sha256', $identifier);
        $existing = $this->attemptRecord($identifier);
        $count = $existing ? ((int) $existing['attempt_count'] + 1) : 1;
        $lockedUntil = $count >= max(1, $maxAttempts)
            ? date('Y-m-d H:i:s', time() + max(1, $lockMinutes) * 60)
            : null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO login_attempts (identifier_hash, attempt_count, last_attempt_at, locked_until)
             VALUES (:hash, :count, NOW(), :locked_until)
             ON DUPLICATE KEY UPDATE attempt_count = VALUES(attempt_count), last_attempt_at = NOW(), locked_until = VALUES(locked_until)'
        );
        $stmt->execute(['hash' => $hash, 'count' => $count, 'locked_until' => $lockedUntil]);
    }

    private function clearFailures(string $identifier): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM login_attempts WHERE identifier_hash = :hash');
        $stmt->execute(['hash' => hash('sha256', $identifier)]);
    }
}

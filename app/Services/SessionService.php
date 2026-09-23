<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Auth;
use WKS\Core\Database;

final class SessionService
{
    public function register(string $ip, string $userAgent): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO user_sessions (session_id, user_id, created_at, last_seen_at, ip_hash, user_agent, revoked_at)
             VALUES (:session_id, :user_id, NOW(), NOW(), :ip_hash, :user_agent, NULL)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_seen_at = NOW(), revoked_at = NULL'
        );
        $stmt->execute([
            'session_id' => session_id(),
            'user_id' => $userId,
            'ip_hash' => hash('sha256', $ip),
            'user_agent' => $userAgent,
        ]);
    }

    public function touch(): void
    {
        if (!Auth::check()) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE user_sessions SET last_seen_at = NOW() WHERE session_id = :session_id AND revoked_at IS NULL'
        );
        $stmt->execute(['session_id' => session_id()]);
    }

    public function currentSessionIsRevoked(): bool
    {
        $stmt = Database::connection()->prepare('SELECT revoked_at FROM user_sessions WHERE session_id = :session_id LIMIT 1');
        $stmt->execute(['session_id' => session_id()]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null;
    }

    public function revokeCurrent(): void
    {
        $stmt = Database::connection()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE session_id = :session_id');
        $stmt->execute(['session_id' => session_id()]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
    }
}

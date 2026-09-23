<?php
declare(strict_types=1);

namespace WKS\Core;

use PDO;

final class Authorization
{
    private static array $cache = [];

    public static function can(string $permission, ?int $locationId = null): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        $locationId ??= active_location_id();
        $cacheKey = $userId . ':' . ($locationId ?? 0) . ':' . $permission;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.id, rp.permission_id IS NOT NULL AS role_allowed
             FROM users u
             JOIN permissions p ON p.code = :permission
             LEFT JOIN role_permissions rp ON rp.role_id = u.role_id AND rp.permission_id = p.id
             WHERE u.id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['permission' => $permission, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return self::$cache[$cacheKey] = false;
        }

        $allowed = (bool) $row['role_allowed'];

        if ($locationId !== null) {
            $override = $pdo->prepare(
                'SELECT allowed
                 FROM user_location_permission_overrides
                 WHERE user_id = :user_id AND location_id = :location_id AND permission_id = :permission_id
                 LIMIT 1'
            );
            $override->execute([
                'user_id' => $userId,
                'location_id' => $locationId,
                'permission_id' => (int) $row['id'],
            ]);

            $value = $override->fetchColumn();
            if ($value !== false) {
                $allowed = (bool) $value;
            }
        }

        return self::$cache[$cacheKey] = $allowed;
    }

    public static function reset(): void
    {
        self::$cache = [];
    }
}

<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class RoleRepository
{
    public function all(): array
    {
        return Database::connection()->query('SELECT * FROM roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function permissions(): array
    {
        return Database::connection()->query('SELECT * FROM permissions ORDER BY group_name, name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function permissionIds(int $roleId): array
    {
        $stmt = Database::connection()->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
        $stmt->execute(['role_id' => $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);
            $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, NOW())');

            foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
                if ($permissionId > 0) {
                    $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

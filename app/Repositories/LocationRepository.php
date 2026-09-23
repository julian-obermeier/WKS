<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class LocationRepository
{
    public function all(bool $includeInactive = true): array
    {
        $sql = 'SELECT * FROM locations';
        if (!$includeInactive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name';
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM locations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*
             FROM locations l
             JOIN user_locations ul ON ul.location_id = l.id
             WHERE ul.user_id = :user_id AND l.active = 1
             ORDER BY l.name'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userHasLocation(int $userId, int $locationId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM user_locations ul
             JOIN locations l ON l.id = ul.location_id
             WHERE ul.user_id = :user_id AND ul.location_id = :location_id AND l.active = 1
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'location_id' => $locationId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO locations (name, code, email_address, mail_mode, mail_test_address, active, created_at, updated_at, created_by, updated_by)
             VALUES (:name, :code, :email_address, :mail_mode, :mail_test_address, :active, NOW(), NOW(), :created_by, :updated_by)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE locations SET name = :name, code = :code, email_address = :email_address,
             mail_mode = :mail_mode, mail_test_address = :mail_test_address, active = :active,
             updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($data + ['id' => $id]);
    }
}

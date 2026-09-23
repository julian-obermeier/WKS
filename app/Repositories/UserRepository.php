<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class UserRepository
{
    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.username) = LOWER(:username) AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['username' => trim($username)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
    }

    public function paginate(string $search = '', int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = 'u.deleted_at IS NULL';

        if ($search !== '') {
            $where .= ' AND (u.first_name LIKE :search_first OR u.last_name LIKE :search_last OR u.username LIKE :search_user OR u.personnel_number LIKE :search_personnel)';
            $like = '%' . $search . '%';
            $params['search_first'] = $like;
            $params['search_last'] = $like;
            $params['search_user'] = $like;
            $params['search_personnel'] = $like;
        }

        $count = Database::connection()->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = "SELECT u.*, r.name AS role_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE {$where}
                ORDER BY u.last_name, u.first_name
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function create(array $data, array $locationIds): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users
                (first_name, last_name, personnel_number, username, email, password_hash, role_id, status,
                 must_change_password, first_login, theme, created_at, updated_at, created_by, updated_by)
                VALUES
                (:first_name, :last_name, :personnel_number, :username, :email, :password_hash, :role_id, :status,
                 :must_change_password, :first_login, :theme, NOW(), NOW(), :created_by, :updated_by)'
            );
            $stmt->execute($data);
            $id = (int) $pdo->lastInsertId();

            $this->syncLocations($id, $locationIds, false);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, array $locationIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET
                    first_name = :first_name,
                    last_name = :last_name,
                    personnel_number = :personnel_number,
                    username = :username,
                    email = :email,
                    role_id = :role_id,
                    updated_by = :updated_by,
                    updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute($data + ['id' => $id]);
            $this->syncLocations($id, $locationIds, false);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['status' => $status, 'updated_by' => $updatedBy, 'id' => $id]);
    }

    public function resetPassword(int $id, string $hash, int $updatedBy): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 1, updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['hash' => $hash, 'updated_by' => $updatedBy, 'id' => $id]);
    }

    public function changePassword(int $id, string $hash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0, first_login = 0, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }

    public function markLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function locationIds(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT location_id FROM user_locations WHERE user_id = :id ORDER BY location_id');
        $stmt->execute(['id' => $id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function syncLocations(int $id, array $locationIds, bool $transaction = true): void
    {
        $pdo = Database::connection();
        if ($transaction) {
            $pdo->beginTransaction();
        }

        try {
            $pdo->prepare('DELETE FROM user_locations WHERE user_id = :id')->execute(['id' => $id]);
            $insert = $pdo->prepare('INSERT INTO user_locations (user_id, location_id, created_at) VALUES (:user_id, :location_id, NOW())');
            foreach (array_unique(array_map('intval', $locationIds)) as $locationId) {
                if ($locationId > 0) {
                    $insert->execute(['user_id' => $id, 'location_id' => $locationId]);
                }
            }

            if ($transaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

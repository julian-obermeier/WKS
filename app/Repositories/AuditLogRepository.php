<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class AuditLogRepository
{
    public function paginate(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['module'])) {
            $where[] = 'a.module = :module';
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action LIKE :action';
            $params['action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['location_id'])) {
            $where[] = 'a.location_id = :location_id';
            $params['location_id'] = (int) $filters['location_id'];
        }

        $clause = implode(' AND ', $where);

        $count = Database::connection()->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$clause}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, l.name AS location_name
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN locations l ON l.id = a.location_id
                WHERE {$clause}
                ORDER BY a.id DESC
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
}

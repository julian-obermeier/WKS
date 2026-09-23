<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = Database::connection()->prepare('SELECT setting_value, value_type FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $default;
        }

        return match ($row['value_type']) {
            'int' => (int) $row['setting_value'],
            'bool' => (bool) ((int) $row['setting_value']),
            'json' => json_decode((string) $row['setting_value'], true) ?? $default,
            default => $row['setting_value'],
        };
    }

    public function set(string $key, mixed $value, string $type = 'string', ?int $userId = null): void
    {
        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, value_type, updated_by, created_at, updated_at)
             VALUES (:key, :value, :type, :updated_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type),
                                     updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute(['key' => $key, 'value' => $stored, 'type' => $type, 'updated_by' => $userId]);
    }
}

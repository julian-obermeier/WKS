<?php
declare(strict_types=1);

namespace WKS\Core;

use PDO;

final class MigrationRunner
{
    public function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function pending(): array
    {
        $this->ensureTable();
        $executed = Database::connection()->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $pending = [];
        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (!in_array($name, $executed, true)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    public function migrate(): array
    {
        $this->ensureTable();
        $pending = $this->pending();

        if ($pending === []) {
            return [];
        }

        $batch = (int) Database::connection()->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $completed = [];

        foreach ($pending as $file) {
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new \RuntimeException('Ungültige Migration: ' . basename($file));
            }

            $migration(Database::connection());

            $stmt = Database::connection()->prepare(
                'INSERT INTO migrations (migration, batch, executed_at) VALUES (:migration, :batch, NOW())'
            );
            $stmt->execute(['migration' => basename($file), 'batch' => $batch]);
            $completed[] = basename($file);
        }

        return $completed;
    }

    private function migrationFiles(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }
}

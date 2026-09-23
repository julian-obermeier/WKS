<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\Request;

final class AuditService
{
    public function log(
        string $action,
        string $module,
        ?string $recordId = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $userId = null,
        ?int $locationId = null,
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs
             (user_id, occurred_at, action, module, record_id, location_id, old_value, new_value, metadata, ip_address, user_agent)
             VALUES
             (:user_id, NOW(), :action, :module, :record_id, :location_id, :old_value, :new_value, :metadata, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'location_id' => $locationId ?? active_location_id(),
            'old_value' => $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'new_value' => $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use WKS\Core\Database;
use WKS\Core\HttpException;

final class DraftAutosaveService
{
    private const MODULES=['dutybook','special_report'];

    public function save(int $userId,int $locationId,string $module,string $contextKey,array $payload): void
    {
        $this->validate($module,$contextKey);
        unset($payload['_token'],$payload['module'],$payload['context_key']);
        $stmt=Database::connection()->prepare(
            'INSERT INTO draft_autosaves (user_id,location_id,module,context_key,payload_json,created_at,updated_at)
             VALUES (:user_id,:location_id,:module,:context_key,:payload,NOW(),NOW())
             ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),updated_at=NOW()'
        );
        $stmt->execute([
            'user_id'=>$userId,'location_id'=>$locationId,'module'=>$module,'context_key'=>$contextKey,
            'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)
        ]);
    }

    public function get(int $userId,int $locationId,string $module,string $contextKey): ?array
    {
        $this->validate($module,$contextKey);
        $stmt=Database::connection()->prepare(
            'SELECT payload_json,updated_at FROM draft_autosaves
             WHERE user_id=:user_id AND location_id=:location_id AND module=:module AND context_key=:context_key LIMIT 1'
        );
        $stmt->execute(['user_id'=>$userId,'location_id'=>$locationId,'module'=>$module,'context_key'=>$contextKey]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
        return ['payload'=>json_decode((string)$row['payload_json'],true)?:[],'updated_at'=>$row['updated_at']];
    }

    public function delete(int $userId,int $locationId,string $module,string $contextKey): void
    {
        $this->validate($module,$contextKey);
        Database::connection()->prepare(
            'DELETE FROM draft_autosaves WHERE user_id=:user_id AND location_id=:location_id AND module=:module AND context_key=:context_key'
        )->execute(['user_id'=>$userId,'location_id'=>$locationId,'module'=>$module,'context_key'=>$contextKey]);
    }

    private function validate(string $module,string $contextKey): void
    {
        if(!in_array($module,self::MODULES,true))throw new HttpException(422,'Autosave-Modul ist nicht freigegeben.');
        if($contextKey===''||strlen($contextKey)>120||!preg_match('/^[a-zA-Z0-9:_-]+$/',$contextKey))throw new HttpException(422,'Ungültiger Autosave-Kontext.');
    }
}

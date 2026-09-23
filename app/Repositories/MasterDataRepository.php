<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class MasterDataRepository
{
    public function shifts(int $locationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM shifts WHERE location_id = :location_id';
        if ($activeOnly) $sql .= ' AND active = 1';
        $sql .= ' ORDER BY sort_order, start_time, id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function shift(int $id, int $locationId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM shifts WHERE id = :id AND location_id = :location_id LIMIT 1');
        $stmt->execute(['id' => $id, 'location_id' => $locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function categories(int $locationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM dutybook_categories WHERE (location_id IS NULL OR location_id = :location_id)';
        if ($activeOnly) $sql .= ' AND active = 1';
        $sql .= ' ORDER BY sort_order, name';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eventTypes(int $locationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT e.*, c.name AS category_name
                FROM dutybook_event_types e
                JOIN dutybook_categories c ON c.id = e.category_id
                WHERE e.location_id = :location_id';
        if ($activeOnly) $sql .= ' AND e.active = 1';
        $sql .= ' ORDER BY c.sort_order, e.sort_order, e.name';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eventType(int $id, int $locationId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, c.name AS category_name FROM dutybook_event_types e
             JOIN dutybook_categories c ON c.id = e.category_id
             WHERE e.id = :id AND e.location_id = :location_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'location_id' => $locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function dynamicFields(string $module, int $definitionId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM dynamic_fields WHERE module = :module AND definition_id = :definition_id';
        if ($activeOnly) $sql .= ' AND active = 1';
        $sql .= ' ORDER BY sort_order, id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['module' => $module, 'definition_id' => $definitionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['options'] = $row['options_json'] ? (json_decode((string) $row['options_json'], true) ?: []) : [];
            $row['visibility'] = $row['visibility_json'] ? (json_decode((string) $row['visibility_json'], true) ?: []) : [];
        }
        return $rows;
    }

    public function allDutybookDynamicFields(int $locationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.*, e.name AS event_type_name
             FROM dynamic_fields f
             JOIN dutybook_event_types e ON e.id = f.definition_id AND f.module = "dutybook_event"
             WHERE e.location_id = :location_id
             ORDER BY e.sort_order, e.name, f.sort_order, f.id'
        );
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personRoles(int $locationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM person_roles
             WHERE active = 1 AND (location_id IS NULL OR location_id = :location_id)
             ORDER BY sort_order, name'
        );
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function places(int $locationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT p.*, parent.name AS parent_name
                FROM places p LEFT JOIN places parent ON parent.id = p.parent_id
                WHERE p.location_id = :location_id';
        if ($activeOnly) $sql .= ' AND p.active = 1';
        $sql .= ' ORDER BY p.place_type, p.sort_order, p.name';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function measures(int $locationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM measures WHERE module = "dutybook" AND (location_id IS NULL OR location_id = :location_id)';
        if ($activeOnly) $sql .= ' AND active = 1';
        $sql .= ' ORDER BY sort_order, name';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function measuresForEventType(int $eventTypeId, int $locationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.*
             FROM measures m
             JOIN event_type_measures em ON em.measure_id = m.id
             JOIN dutybook_event_types e ON e.id = em.event_type_id
             WHERE em.event_type_id = :event_type_id AND e.location_id = :location_id AND m.active = 1
             ORDER BY m.sort_order, m.name'
        );
        $stmt->execute(['event_type_id' => $eventTypeId, 'location_id' => $locationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows !== [] ? $rows : $this->measures($locationId);
    }

    public function externalOrganizations(int $locationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM external_organizations
             WHERE active = 1 AND (location_id IS NULL OR location_id = :location_id)
             ORDER BY sort_order, organization_type, name'
        );
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function usersForLocation(int $locationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.personnel_number
             FROM users u
             JOIN user_locations ul ON ul.user_id = u.id
             WHERE ul.location_id = :location_id AND u.status = "active" AND u.deleted_at IS NULL
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveShift(?int $id, int $locationId, array $data, int $userId): int
    {
        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO shifts (location_id, code, name, start_time, end_time, crosses_midnight, sort_order, active, created_at, updated_at, created_by, updated_by)
                 VALUES (:location_id, :code, :name, :start_time, :end_time, :crosses_midnight, :sort_order, :active, NOW(), NOW(), :user_id, :user_id)'
            );
            $stmt->execute($data + ['location_id' => $locationId, 'user_id' => $userId]);
            return (int) Database::connection()->lastInsertId();
        }

        $stmt = Database::connection()->prepare(
            'UPDATE shifts SET code=:code,name=:name,start_time=:start_time,end_time=:end_time,crosses_midnight=:crosses_midnight,
             sort_order=:sort_order,active=:active,updated_at=NOW(),updated_by=:user_id
             WHERE id=:id AND location_id=:location_id'
        );
        $stmt->execute($data + ['id' => $id, 'location_id' => $locationId, 'user_id' => $userId]);
        return $id;
    }

    public function saveCategory(?int $id, int $locationId, array $data, int $userId): int
    {
        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO dutybook_categories (location_id,name,sort_order,active,created_at,updated_at,created_by,updated_by)
                 VALUES (:location_id,:name,:sort_order,:active,NOW(),NOW(),:user_id,:user_id)'
            );
            $stmt->execute($data + ['location_id' => $locationId, 'user_id' => $userId]);
            return (int) Database::connection()->lastInsertId();
        }

        $stmt = Database::connection()->prepare(
            'UPDATE dutybook_categories SET name=:name,sort_order=:sort_order,active=:active,updated_at=NOW(),updated_by=:user_id
             WHERE id=:id AND (location_id=:location_id OR location_id IS NULL)'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId,'user_id'=>$userId]);
        return $id;
    }

    public function saveEventType(?int $id, int $locationId, array $data, int $userId): int
    {
        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO dutybook_event_types
                 (location_id,category_id,name,sort_order,active,offer_special_report,offer_valuables,auto_entry_enabled,created_at,updated_at,created_by,updated_by)
                 VALUES (:location_id,:category_id,:name,:sort_order,:active,:offer_special_report,:offer_valuables,:auto_entry_enabled,NOW(),NOW(),:user_id,:user_id)'
            );
            $stmt->execute($data + ['location_id'=>$locationId,'user_id'=>$userId]);
            return (int) Database::connection()->lastInsertId();
        }

        $stmt = Database::connection()->prepare(
            'UPDATE dutybook_event_types SET category_id=:category_id,name=:name,sort_order=:sort_order,active=:active,
             offer_special_report=:offer_special_report,offer_valuables=:offer_valuables,auto_entry_enabled=:auto_entry_enabled,
             updated_at=NOW(),updated_by=:user_id WHERE id=:id AND location_id=:location_id'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId,'user_id'=>$userId]);
        return $id;
    }

    public function saveDynamicField(?int $id, int $locationId, array $data, int $userId): int
    {
        $event = $this->eventType((int) $data['definition_id'], $locationId);
        if (!$event) throw new \InvalidArgumentException('Ungültige Ereignisart für Zusatzfeld.');

        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO dynamic_fields
                 (module,definition_id,field_key,label,field_type,required,sort_order,options_json,visibility_json,active,created_at,updated_at,created_by,updated_by)
                 VALUES ("dutybook_event",:definition_id,:field_key,:label,:field_type,:required,:sort_order,:options_json,NULL,:active,NOW(),NOW(),:user_id,:user_id)'
            );
            $stmt->execute($data + ['user_id'=>$userId]);
            return (int) Database::connection()->lastInsertId();
        }

        $stmt = Database::connection()->prepare(
            'UPDATE dynamic_fields f
             JOIN dutybook_event_types e ON e.id=f.definition_id
             SET f.definition_id=:definition_id,f.field_key=:field_key,f.label=:label,f.field_type=:field_type,
                 f.required=:required,f.sort_order=:sort_order,f.options_json=:options_json,f.active=:active,
                 f.updated_at=NOW(),f.updated_by=:user_id
             WHERE f.id=:id AND f.module="dutybook_event" AND e.location_id=:location_id'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId,'user_id'=>$userId]);
        return $id;
    }

    public function savePlace(?int $id, int $locationId, array $data): int
    {
        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO places (location_id,parent_id,place_type,name,sort_order,active,created_at,updated_at)
                 VALUES (:location_id,:parent_id,:place_type,:name,:sort_order,:active,NOW(),NOW())'
            );
            $stmt->execute($data + ['location_id'=>$locationId]);
            return (int) Database::connection()->lastInsertId();
        }

        $stmt = Database::connection()->prepare(
            'UPDATE places SET parent_id=:parent_id,place_type=:place_type,name=:name,sort_order=:sort_order,active=:active,updated_at=NOW()
             WHERE id=:id AND location_id=:location_id'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId]);
        return $id;
    }

    public function saveMeasure(?int $id, int $locationId, array $data): int
    {
        if ($id === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO measures (location_id,module,name,active,sort_order,created_at,updated_at)
                 VALUES (:location_id,"dutybook",:name,:active,:sort_order,NOW(),NOW())'
            );
            $stmt->execute($data + ['location_id'=>$locationId]);
            return (int) Database::connection()->lastInsertId();
        }
        $stmt = Database::connection()->prepare(
            'UPDATE measures SET name=:name,active=:active,sort_order=:sort_order,updated_at=NOW()
             WHERE id=:id AND module="dutybook" AND (location_id=:location_id OR location_id IS NULL)'
        );
        $stmt->execute($data + ['id'=>$id,'location_id'=>$locationId]);
        return $id;
    }

    public function syncEventMeasures(int $eventTypeId,int $locationId,array $measureIds): void
    {
        if(!$this->eventType($eventTypeId,$locationId)) throw new \\InvalidArgumentException('Ungültige Ereignisart.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM event_type_measures WHERE event_type_id=:id')->execute(['id'=>$eventTypeId]);
            $stmt=$pdo->prepare('INSERT IGNORE INTO event_type_measures (event_type_id,measure_id) VALUES (:event_type_id,:measure_id)');
            foreach(array_unique(array_map('intval',$measureIds)) as $measureId){
                if($measureId>0)$stmt->execute(['event_type_id'=>$eventTypeId,'measure_id'=>$measureId]);
            }
            $pdo->commit();
        }catch(\\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public function savePersonRole(?int $id,int $locationId,array $data): int
    {
        if($id===null){
            $stmt=Database::connection()->prepare(
                'INSERT INTO person_roles (location_id,name,active,sort_order,created_at,updated_at)
                 VALUES (:location_id,:name,:active,:sort_order,NOW(),NOW())'
            );
            $stmt->execute($data+['location_id'=>$locationId]);
            return (int)Database::connection()->lastInsertId();
        }
        $stmt=Database::connection()->prepare(
            'UPDATE person_roles SET name=:name,active=:active,sort_order=:sort_order,updated_at=NOW()
             WHERE id=:id AND (location_id=:location_id OR location_id IS NULL)'
        );
        $stmt->execute($data+['id'=>$id,'location_id'=>$locationId]);
        return $id;
    }

    public function saveExternalOrganization(?int $id,int $locationId,array $data): int
    {
        if($id===null){
            $stmt=Database::connection()->prepare(
                'INSERT INTO external_organizations (location_id,organization_type,name,active,sort_order,created_at,updated_at)
                 VALUES (:location_id,:organization_type,:name,:active,:sort_order,NOW(),NOW())'
            );
            $stmt->execute($data+['location_id'=>$locationId]);
            return (int)Database::connection()->lastInsertId();
        }
        $stmt=Database::connection()->prepare(
            'UPDATE external_organizations SET organization_type=:organization_type,name=:name,active=:active,sort_order=:sort_order,updated_at=NOW()
             WHERE id=:id AND (location_id=:location_id OR location_id IS NULL)'
        );
        $stmt->execute($data+['id'=>$id,'location_id'=>$locationId]);
        return $id;
    }

}

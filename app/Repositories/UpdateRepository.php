<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class UpdateRepository
{
    public function history(): array
    {
        return Database::connection()->query(
            'SELECT h.*,CONCAT(u.first_name," ",u.last_name) AS user_name
             FROM update_history h LEFT JOIN users u ON u.id=h.initiated_by ORDER BY h.occurred_at DESC,h.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function start(string $version,?string $sha,int $userId): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO update_history (version,commit_sha,occurred_at,status,initiated_by)
             VALUES (:version,:sha,NOW(),"partial",:user_id)'
        );
        $stmt->execute(['version'=>$version,'sha'=>$sha,'user_id'=>$userId]);return (int)Database::connection()->lastInsertId();
    }

    public function finish(int $id,string $status,array $migrations,array $changes,array $details,?string $error=null): void
    {
        Database::connection()->prepare(
            'UPDATE update_history SET status=:status,migrations_json=:migrations,changes_json=:changes,
             technical_details=:details,error_message=:error WHERE id=:id'
        )->execute([
            'status'=>$status,'migrations'=>json_encode($migrations,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'changes'=>json_encode($changes,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'details'=>json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'error'=>$error,'id'=>$id
        ]);
    }

    public function releaseNotes(): array
    {
        $rows=Database::connection()->query('SELECT * FROM release_notes ORDER BY build_date DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row)$row['sections']=json_decode((string)$row['changelog_json'],true)?:[];
        return $rows;
    }

    public function upsertRelease(array $release): void
    {
        Database::connection()->prepare(
            'INSERT INTO release_notes (version,build_date,title,changelog_json,created_at)
             VALUES (:version,:build_date,:title,:changelog,NOW())
             ON DUPLICATE KEY UPDATE build_date=VALUES(build_date),title=VALUES(title),changelog_json=VALUES(changelog_json)'
        )->execute([
            'version'=>$release['version'],'build_date'=>$release['build_date'],'title'=>$release['title'],
            'changelog'=>json_encode($release['sections'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)
        ]);
    }

    public function markSeen(int $userId,string $version): void
    {
        Database::connection()->prepare(
            'INSERT INTO user_release_views (user_id,version,seen_at) VALUES (:user_id,:version,NOW())
             ON DUPLICATE KEY UPDATE seen_at=VALUES(seen_at)'
        )->execute(['user_id'=>$userId,'version'=>$version]);
    }

    public function latestUnseen(int $userId): ?array
    {
        // Das automatische Modal darf ausschließlich die aktuellste Release-Version zeigen.
        // Ältere, nie bestätigte Releases bleiben über "Was ist neu?" einsehbar, werden aber
        // nach Bestätigung der aktuellen Version nicht nacheinander als Modal geöffnet.
        $stmt=Database::connection()->prepare(
            'SELECT r.*
             FROM release_notes r
             WHERE NOT EXISTS (
                 SELECT 1 FROM user_release_views v
                 WHERE v.user_id=:user_id AND v.version=r.version
             )
             AND r.id=(
                 SELECT latest.id FROM release_notes latest
                 ORDER BY latest.build_date DESC,latest.id DESC LIMIT 1
             )
             LIMIT 1'
        );
        $stmt->execute(['user_id'=>$userId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        $row['sections']=json_decode((string)$row['changelog_json'],true)?:[];
        return $row;
    }
}

<?php
declare(strict_types=1);

namespace WKS\Repositories;

use PDO;
use WKS\Core\Database;

final class AdminRepository
{
    public function errors(array $filters=[]): array
    {
        $where=['1=1'];$params=[];
        if(($filters['status']??'')!==''){$where[]='e.status=:status';$params['status']=$filters['status'];}
        if(($filters['module']??'')!==''){$where[]='e.module=:module';$params['module']=$filters['module'];}
        $stmt=Database::connection()->prepare(
            'SELECT e.*,CONCAT(u.first_name," ",u.last_name) AS user_name,CONCAT(r.first_name," ",r.last_name) AS resolver_name
             FROM error_logs e LEFT JOIN users u ON u.id=e.user_id LEFT JOIN users r ON r.id=e.resolved_by
             WHERE '.implode(' AND ',$where).' ORDER BY e.occurred_at DESC,e.id DESC LIMIT 500'
        );
        $stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateErrorStatus(int $id,string $status,int $userId): void
    {
        $resolved=$status==='done'?'NOW()':'NULL';$resolver=$status==='done'?':user_id':'NULL';
        Database::connection()->prepare(
            'UPDATE error_logs SET status=:status,resolved_by='.$resolver.',resolved_at='.$resolved.' WHERE id=:id'
        )->execute($status==='done'?['status'=>$status,'user_id'=>$userId,'id'=>$id]:['status'=>$status,'id'=>$id]);
    }

    public function cronJobs(): array
    {
        return Database::connection()->query('SELECT * FROM cron_jobs ORDER BY job_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCron(int $id,int $minutes,bool $active): void
    {
        Database::connection()->prepare(
            'UPDATE cron_jobs SET interval_minutes=:minutes,active=:active,updated_at=NOW() WHERE id=:id'
        )->execute(['minutes'=>$minutes,'active'=>$active?1:0,'id'=>$id]);
    }

    public function templates(): array
    {
        $rows=Database::connection()->query('SELECT * FROM document_templates ORDER BY template_name')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row)$this->hydrateTemplate($row);
        return $rows;
    }

    public function template(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM document_templates WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        if($row)$this->hydrateTemplate($row);
        return $row;
    }

    public function templateByCode(string $code): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM document_templates WHERE template_code=:code LIMIT 1');$stmt->execute(['code'=>$code]);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        if($row)$this->hydrateTemplate($row);
        return $row;
    }

    private function hydrateTemplate(array &$row): void
    {
        $settings=$row['settings_json']??null;
        $decoded=is_string($settings)&&$settings!==''?json_decode($settings,true):[];
        $row['settings']=is_array($decoded)?$decoded:[];
        if(!in_array((string)($row['settings']['layout']??'standard'),['standard','compact'],true))$row['settings']['layout']='standard';
    }

    public function updateTemplate(int $id,array $data,int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE document_templates SET header_text=:header_text,footer_text=:footer_text,logo_path=:logo_path,
             show_page_numbers=:show_page_numbers,watermark_text=:watermark_text,settings_json=:settings_json,
             updated_at=NOW(),updated_by=:updated_by WHERE id=:id'
        )->execute($data+['updated_by'=>$userId,'id'=>$id]);
    }

    public function mailQueue(int $limit=200): array
    {
        $limit=max(1,min(500,$limit));
        return Database::connection()->query(
            "SELECT q.*,l.name AS location_name FROM mail_queue q JOIN locations l ON l.id=q.location_id
             ORDER BY q.created_at DESC,q.id DESC LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mailLog(int $limit=200): array
    {
        $limit=max(1,min(500,$limit));
        return Database::connection()->query(
            "SELECT m.*,l.name AS location_name FROM mail_log m LEFT JOIN locations l ON l.id=m.location_id
             ORDER BY m.occurred_at DESC,m.id DESC LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

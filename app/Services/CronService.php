<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use Throwable;
use WKS\Core\Database;
use WKS\Repositories\NotificationRepository;
use WKS\Repositories\ValuablesRepository;

final class CronService
{
    public function runDue(bool $force=false): array
    {
        $pdo=Database::connection();$jobs=$pdo->query('SELECT * FROM cron_jobs WHERE active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);$results=[];
        foreach($jobs as $job){
            $due=$force||!$job['last_run_at']||strtotime($job['last_run_at'])<=time()-(int)$job['interval_minutes']*60;
            if(!$due)continue;
            $results[$job['job_code']]=$this->runJob($job);
        }
        return $results;
    }

    private function runJob(array $job): array
    {
        $pdo=Database::connection();$pdo->prepare('UPDATE cron_jobs SET last_run_at=NOW(),status="running",last_error=NULL WHERE id=:id')->execute(['id'=>$job['id']]);
        try{
            $detail=match($job['job_code']){
                'announcements'=>$this->announcements(),
                'notifications'=>$this->notifications(),
                'mail_queue'=>(new MailService())->processQueue(),
                'maintenance'=>$this->maintenance(),
                'system_status'=>(new SystemStatusService())->check(),
                default=>['skipped'=>'unbekannter Job']
            };
            $pdo->prepare('UPDATE cron_jobs SET last_success_at=NOW(),status="ok",last_error=NULL,next_run_at=DATE_ADD(NOW(),INTERVAL interval_minutes MINUTE) WHERE id=:id')->execute(['id'=>$job['id']]);
            return ['status'=>'ok','detail'=>$detail];
        }catch(Throwable $e){
            $pdo->prepare('UPDATE cron_jobs SET status="error",last_error=:error,next_run_at=DATE_ADD(NOW(),INTERVAL interval_minutes MINUTE) WHERE id=:id')->execute(['error'=>$e->getMessage(),'id'=>$job['id']]);
            return ['status'=>'error','error'=>$e->getMessage()];
        }
    }

    private function announcements(): array
    {
        $pdo=Database::connection();
        $published=$pdo->exec('UPDATE announcements SET status="published",published_at=COALESCE(published_at,NOW()),updated_at=NOW() WHERE status="scheduled" AND valid_from IS NOT NULL AND valid_from<=NOW() AND deleted_at IS NULL');
        $archived=$pdo->exec('UPDATE announcements SET status="archived",archived_at=NOW(),updated_at=NOW() WHERE status="published" AND valid_until IS NOT NULL AND valid_until<NOW() AND deleted_at IS NULL');
        return ['published'=>$published,'archived'=>$archived];
    }

    private function notifications(): array
    {
        $pdo=Database::connection();$locations=$pdo->query('SELECT id FROM locations WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);$created=0;
        $notificationRepo=new NotificationRepository();
        foreach($locations as $locationId){
            $vs=new ValuablesService();$records=(new ValuablesRepository())->longTerm((int)$locationId,$vs->longTermDays());
            foreach($records as $r){
                $url=url('valuables/'.$r['id']);$users=$pdo->prepare(
                    'SELECT DISTINCT u.id FROM users u JOIN roles ro ON ro.id=u.role_id JOIN user_locations ul ON ul.user_id=u.id
                     WHERE ro.code="management" AND ul.location_id=:location_id AND u.status="active" AND u.deleted_at IS NULL'
                );$users->execute(['location_id'=>$locationId]);
                foreach($users->fetchAll(PDO::FETCH_COLUMN) as $uid){
                    if($notificationRepo->existsRecent((int)$uid,'long_term_valuables',$url,24))continue;
                    $notificationRepo->create((int)$uid,'long_term_valuables',(int)$locationId,'Langzeitverwahrung','Verwahrnummer '.str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT).' ist seit '.$r['storage_days'].' Tagen eingelagert.',$url,'important');$created++;
                }
            }
        }
        return ['created'=>$created];
    }

    private function maintenance(): array
    {
        $deleted=Database::connection()->exec('DELETE FROM login_attempts WHERE last_attempt_at<DATE_SUB(NOW(),INTERVAL 7 DAY)');
        return ['expired_login_attempts_deleted'=>$deleted];
    }
}

<?php
declare(strict_types=1);

namespace WKS\Services;

use PDO;
use WKS\Core\Database;
use WKS\Repositories\SettingsRepository;

final class MailService
{
    public function queueLocation(int $locationId,string $trigger,string $subject,string $body): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO mail_queue (location_id,trigger_code,subject,body,status,attempts,available_at,created_at)
             VALUES (:location_id,:trigger,:subject,:body,"pending",0,NOW(),NOW())'
        );
        $stmt->execute(['location_id'=>$locationId,'trigger'=>$trigger,'subject'=>$subject,'body'=>$body]);
        return (int)Database::connection()->lastInsertId();
    }

    public function processQueue(int $limit=25): array
    {
        $pdo=Database::connection();$settings=new SettingsRepository();$global=(bool)$settings->get('mail.global_enabled',false);
        $stmt=$pdo->prepare(
            'SELECT q.*,l.email_address,l.mail_mode,l.mail_test_address,l.name AS location_name
             FROM mail_queue q JOIN locations l ON l.id=q.location_id
             WHERE q.status="pending" AND q.available_at<=NOW()
             ORDER BY q.id LIMIT '.max(1,min(100,$limit))
        );
        $stmt->execute();$done=['sent'=>0,'suppressed'=>0,'failed'=>0];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $mail){
            $mode=(string)$mail['mail_mode'];$target=(string)$mail['email_address'];
            if($mode==='test'&&$mail['mail_test_address'])$target=(string)$mail['mail_test_address'];
            if(!$global||$mode==='disabled'){
                $this->finish((int)$mail['id'],$mail,'suppressed',$target,'Mailversand global oder für Standort deaktiviert.');
                $done['suppressed']++;continue;
            }
            if($mode==='test'&&!filter_var($target,FILTER_VALIDATE_EMAIL)){
                $this->finish((int)$mail['id'],$mail,'failed',$target,'Ungültige Testadresse.');$done['failed']++;continue;
            }
            if($mode==='production')$target=(string)$mail['email_address'];
            $fromName=(string)$settings->get('mail.from_name','WKS');$from=(string)$settings->get('mail.from_address','wks@localhost');
            $headers=['MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','From: '.$fromName.' <'.$from.'>'];
            $ok=@mail($target,(string)$mail['subject'],(string)$mail['body'],implode("\r\n",$headers));
            if($ok){$this->finish((int)$mail['id'],$mail,'sent',$target,null);$done['sent']++;}
            else{$this->finish((int)$mail['id'],$mail,'failed',$target,'PHP mail() meldete einen Fehler.');$done['failed']++;}
        }
        return $done;
    }

    private function finish(int $id,array $mail,string $status,string $target,?string $error): void
    {
        $pdo=Database::connection();
        $pdo->prepare(
            'UPDATE mail_queue SET status=:status,attempts=attempts+1,sent_at=:sent_at,last_error=:error WHERE id=:id'
        )->execute(['status'=>$status,'sent_at'=>$status==='sent'?date('Y-m-d H:i:s'):null,'error'=>$error,'id'=>$id]);
        $pdo->prepare(
            'INSERT INTO mail_log (location_id,trigger_code,target_address,subject,occurred_at,status,error_message,mail_mode)
             VALUES (:location_id,:trigger,:target,:subject,NOW(),:status,:error,:mode)'
        )->execute(['location_id'=>$mail['location_id'],'trigger'=>$mail['trigger_code'],'target'=>$target,'subject'=>$mail['subject'],'status'=>$status,'error'=>$error,'mode'=>$mail['mail_mode']]);
    }
}

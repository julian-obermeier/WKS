<?php
declare(strict_types=1);

namespace WKS\Services;

use Throwable;
use WKS\Core\Database;
use WKS\Repositories\AdminRepository;

final class SystemStatusService
{
    public function check(): array
    {
        $checks=[];
        $checks[]=$this->item('PHP-Version',version_compare(PHP_VERSION,'8.1.0','>=')?'ok':'error',PHP_VERSION);
        try{Database::connection()->query('SELECT 1');$checks[]=$this->item('Datenbankverbindung','ok','Verbindung erfolgreich');}
        catch(Throwable $e){$checks[]=$this->item('Datenbankverbindung','error',$e->getMessage());}

        try{
            $required=['users','locations','dutybook_entries','special_reports','valuables_records','house_bans','audit_logs','migrations'];
            $missing=[];$pdo=Database::connection();
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
            foreach($required as $table){$stmt->execute(['table'=>$table]);if(!(bool)$stmt->fetchColumn())$missing[]=$table;}
            $checks[]=$this->item('Datenbankstruktur',$missing===[]?'ok':'error',$missing===[]?'Kerntabellen vorhanden':'Fehlend: '.implode(', ',$missing));
        }catch(Throwable $e){$checks[]=$this->item('Datenbankstruktur','error',$e->getMessage());}

        foreach(['storage','storage/uploads','storage/logs','storage/generated'] as $dir){
            $path=BASE_PATH.'/'.$dir;$ok=is_dir($path)&&is_writable($path);$checks[]=$this->item('Schreibrechte '.$dir,$ok?'ok':'error',$ok?'beschreibbar':'nicht beschreibbar');
        }

        $free=@disk_free_space(BASE_PATH);$checks[]=$this->item('Speicher',$free!==false&&$free>100*1024*1024?'ok':'warning',$free===false?'nicht ermittelbar':number_format($free/1024/1024,0,',','.').' MB frei');

        $locations=Database::connection()->query('SELECT name,email_address,mail_mode,mail_test_address FROM locations WHERE active=1')->fetchAll(\PDO::FETCH_ASSOC);
        $mailProblems=[];foreach($locations as $l){
            if(!filter_var($l['email_address'],FILTER_VALIDATE_EMAIL))$mailProblems[]=$l['name'].': ungültige Standortadresse';
            if($l['mail_mode']==='test'&&!filter_var($l['mail_test_address'],FILTER_VALIDATE_EMAIL))$mailProblems[]=$l['name'].': ungültige Testadresse';
        }
        $checks[]=$this->item('Mailkonfiguration',$mailProblems===[]?'ok':'warning',$mailProblems===[]?'Standortadressen plausibel':implode('; ',$mailProblems));

        $jobs=(new AdminRepository())->cronJobs();$stale=[];foreach($jobs as $j)if($j['active']&&$j['last_run_at']&&strtotime($j['last_run_at'])<time()-max(3600,(int)$j['interval_minutes']*60*3))$stale[]=$j['job_name'];
        $checks[]=$this->item('Cronjobs',$stale===[]?'ok':'warning',$stale===[]?'keine überfälligen Jobs erkannt':'Überfällig: '.implode(', ',$stale));

        try{
            $pdf=(new DocumentGeneratorService())->pdf('WKS Systemcheck',['Dokumenterzeugung'=>'PDF-Test']);
            $pdfOk=str_starts_with($pdf,'%PDF-');
            $docxOk=class_exists('ZipArchive');
            $checks[]=$this->item('Dokumenterzeugung',$pdfOk&&$docxOk?'ok':($pdfOk?'warning':'error'),'PDF '.($pdfOk?'OK':'Fehler').' · DOCX '.($docxOk?'OK':'ZipArchive fehlt'));
        }catch(Throwable $e){$checks[]=$this->item('Dokumenterzeugung','error',$e->getMessage());}

        $overall='ok';foreach($checks as $c){if($c['status']==='error'){$overall='error';break;}if($c['status']==='warning')$overall='warning';}
        return ['overall'=>$overall,'checks'=>$checks,'checked_at'=>date('Y-m-d H:i:s')];
    }

    private function item(string $name,string $status,string $detail): array{return ['name'=>$name,'status'=>$status,'detail'=>$detail];}
}

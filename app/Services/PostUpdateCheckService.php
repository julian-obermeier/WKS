<?php
declare(strict_types=1);

namespace WKS\Services;

use Throwable;
use WKS\Core\Database;
use WKS\Core\MigrationRunner;

final class PostUpdateCheckService
{
    public function run(): array
    {
        $base=(new SystemStatusService())->check();$checks=$base['checks'];
        try{
            $pending=(new MigrationRunner())->pending();$checks[]=['name'=>'Migrationen','status'=>$pending===[]?'ok':'error','detail'=>$pending===[]?'Keine ausstehenden Migrationen':'Ausstehend: '.implode(', ',array_map('basename',$pending))];
        }catch(Throwable $e){$checks[]=['name'=>'Migrationen','status'=>'error','detail'=>$e->getMessage()];}

        foreach([
            'Dienstbuch-Grundfunktion'=>'dutybook_entries',
            'Sonderbericht-Grundfunktion'=>'special_reports',
            'Wertsachen-Grundfunktion'=>'valuables_records',
            'Hausverbote-Grundfunktion'=>'house_bans',
            'Interne Benachrichtigungen'=>'notifications'
        ] as $name=>$table){
            try{$stmt=Database::connection()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$stmt->execute(['table'=>$table]);$ok=(bool)$stmt->fetchColumn();$checks[]=['name'=>$name,'status'=>$ok?'ok':'error','detail'=>$ok?'Tabelle erreichbar':'Tabelle fehlt'];}
            catch(Throwable $e){$checks[]=['name'=>$name,'status'=>'error','detail'=>$e->getMessage()];}
        }
        $overall='ok';foreach($checks as $c){if($c['status']==='error'){$overall='error';break;}if($c['status']==='warning')$overall='warning';}
        return ['overall'=>$overall,'checks'=>$checks,'checked_at'=>date('Y-m-d H:i:s')];
    }
}

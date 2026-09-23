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

        $probes=[
            'Dienstbuch-Grundfunktion'=>'SELECT id,location_id,dutybook_day_id,duty_date,status,occurred_at,created_by FROM dutybook_entries LIMIT 0',
            'Sonderbericht-Grundfunktion'=>'SELECT id,location_id,report_type_id,report_year,report_number,status,current_version,created_by FROM special_reports LIMIT 0',
            'Wertsachen-Grundfunktion'=>'SELECT id,location_id,custody_number,status,stored_at,released_at,created_by FROM valuables_records LIMIT 0',
            'Hausverbote-Grundfunktion'=>'SELECT id,location_id,ban_date,person_name,reason,created_by FROM house_bans LIMIT 0',
            'Interne Benachrichtigungen'=>'SELECT id,user_id,location_id,event_code,title,read_at FROM notifications LIMIT 0',
        ];
        foreach($probes as $name=>$sql){
            try{
                Database::connection()->query($sql);
                $checks[]=['name'=>$name,'status'=>'ok','detail'=>'Kerntabelle und erwartete Spalten sind abfragbar'];
            }catch(Throwable $e){
                $checks[]=['name'=>$name,'status'=>'error','detail'=>'Schema-/Abfragefehler: '.$e->getMessage()];
            }
        }
        $overall='ok';foreach($checks as $c){if($c['status']==='error'){$overall='error';break;}if($c['status']==='warning')$overall='warning';}
        return ['overall'=>$overall,'checks'=>$checks,'checked_at'=>date('Y-m-d H:i:s')];
    }
}

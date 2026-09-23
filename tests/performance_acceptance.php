<?php
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
require_once BASE_PATH.'/app/Core/Env.php';
spl_autoload_register(static function(string $class): void {
    $prefix='WKS\\';if(!str_starts_with($class,$prefix))return;
    $path=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
WKS\Core\Env::load(BASE_PATH.'/.env');
require_once BASE_PATH.'/app/Support/helpers.php';
date_default_timezone_set((string)config('app.timezone','Europe/Berlin'));

use WKS\Core\Database;
use WKS\Repositories\GlobalSearchRepository;
use WKS\Repositories\HouseBanRepository;
use WKS\Repositories\StatisticsRepository;

$pdo=Database::connection();
$gi=(int)$pdo->query('SELECT id FROM locations WHERE code="GI"')->fetchColumn();
if($gi<=0)throw new RuntimeException('Gießen fehlt für Performance-Test.');

$requiredIndexes=[
    'dutybook_entries'=>['idx_dutybook_entries_day','idx_dutybook_entries_status','idx_dutybook_entries_occurred'],
    'special_reports'=>['idx_sr_location_date','idx_sr_status','idx_sr_type'],
    'valuables_records'=>['idx_valuables_status','idx_valuables_stored_at','idx_valuables_released_at'],
    'house_bans'=>['idx_house_bans_location_date','idx_house_bans_name','ft_house_bans_text'],
    'notifications'=>['idx_notifications_user_read','idx_notifications_event'],
    'attachments'=>['idx_attachments_record'],
];
foreach($requiredIndexes as $table=>$names){
    $stmt=$pdo->prepare(
        'SELECT INDEX_NAME FROM information_schema.statistics
         WHERE table_schema=DATABASE() AND table_name=:table'
    );
    $stmt->execute(['table'=>$table]);$present=$stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach($names as $name)if(!in_array($name,$present,true))throw new RuntimeException("Performance index missing: {$table}.{$name}");
}

$pdo->beginTransaction();
try{
    $insert=$pdo->prepare(
        'INSERT INTO house_bans (location_id,ban_date,person_name,reason,created_at,updated_at,created_by,updated_by)
         VALUES (:location_id,:ban_date,:person_name,:reason,NOW(),NOW(),NULL,NULL)'
    );
    for($i=1;$i<=2500;$i++){
        $insert->execute([
            'location_id'=>$gi,'ban_date'=>'2026-09-'.str_pad((string)(1+($i%23)),2,'0',STR_PAD_LEFT),
            'person_name'=>'Performance Person '.str_pad((string)$i,4,'0',STR_PAD_LEFT),
            'reason'=>'Performance Testdatensatz '.$i.' CI Suchwort'
        ]);
    }
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

$measure=static function(string $label,callable $fn,float $maxSeconds): mixed {
    $start=microtime(true);$result=$fn();$elapsed=microtime(true)-$start;
    printf("%s: %.4f s\n",$label,$elapsed);
    if($elapsed>$maxSeconds)throw new RuntimeException(sprintf('%s exceeded %.2f s guardrail (%.4f s)',$label,$maxSeconds,$elapsed));
    return $result;
};

$house=$measure('House-ban paginated date search',fn()=>(new HouseBanRepository())->search($gi,['from'=>'2026-09-01','to'=>'2026-09-30','sort'=>'date'],1,30),2.5);
if($house['total']<2500)throw new RuntimeException('House-ban performance fixture count is incomplete.');

$global=$measure('Global cross-module house-ban search',fn()=>(new GlobalSearchRepository())->search('CI Suchwort',$gi,['house_bans']),2.5);
if(count($global)===0)throw new RuntimeException('Global search returned no performance fixture.');

$stats=$measure('Statistics aggregation',fn()=>(new StatisticsRepository())->summary($gi,'2026-09-01','2026-09-30'),2.5);
if($stats['house_bans']<2500)throw new RuntimeException('Statistics did not aggregate performance fixtures.');

$plans=[
    ['dutybook_entries','EXPLAIN SELECT id FROM dutybook_entries WHERE location_id='.$gi.' AND duty_date BETWEEN "2026-09-01" AND "2026-09-30" ORDER BY occurred_at LIMIT 30',['idx_dutybook_entries_day','idx_dutybook_entries_occurred']],
    ['special_reports','EXPLAIN SELECT id FROM special_reports WHERE location_id='.$gi.' AND incident_date BETWEEN "2026-09-01" AND "2026-09-30" AND deleted_at IS NULL ORDER BY incident_started_at DESC LIMIT 30',['idx_sr_location_date']],
    ['valuables_records','EXPLAIN SELECT id FROM valuables_records WHERE location_id='.$gi.' AND status="stored" AND deleted_at IS NULL ORDER BY stored_at LIMIT 30',['idx_valuables_status','idx_valuables_stored_at']],
    ['house_bans','EXPLAIN SELECT id FROM house_bans WHERE location_id='.$gi.' AND deleted_at IS NULL AND ban_date BETWEEN "2026-09-01" AND "2026-09-30" ORDER BY ban_date DESC LIMIT 30',['idx_house_bans_location_date']],
];
foreach($plans as [$label,$sql,$expected]){
    $row=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    $possible=(string)($row['possible_keys']??'');
    $ok=false;foreach($expected as $index)if(str_contains($possible,$index)){$ok=true;break;}
    if(!$ok)throw new RuntimeException($label.' query plan no longer exposes an expected index; possible_keys='.$possible);
}

echo "WKS performance guardrails OK.\n";

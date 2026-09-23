<?php
declare(strict_types=1);

ob_start();

define('BASE_PATH',dirname(__DIR__));
require_once BASE_PATH.'/app/Core/Env.php';

spl_autoload_register(static function(string $class): void {
    $prefix='WKS\\';
    if(!str_starts_with($class,$prefix))return;
    $path=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});

WKS\Core\Env::load(BASE_PATH.'/.env');
require_once BASE_PATH.'/app/Support/helpers.php';
date_default_timezone_set((string)config('app.timezone','Europe/Berlin'));
WKS\Core\Session::start();

use WKS\Controllers\Admin\LocationController;
use WKS\Controllers\DutybookController;
use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Core\MigrationRunner;
use WKS\Core\Request;
use WKS\Core\Session;
use WKS\Repositories\AnnouncementRepository;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\GlobalSearchRepository;
use WKS\Repositories\HouseBanRepository;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Repositories\StatisticsRepository;
use WKS\Repositories\TrashRepository;
use WKS\Repositories\UserRepository;
use WKS\Repositories\ValuablesRepository;
use WKS\Services\AnnouncementService;
use WKS\Services\AuthService;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\DraftAutosaveService;
use WKS\Services\DynamicFormService;
use WKS\Services\DutybookService;
use WKS\Services\HandoverService;
use WKS\Services\HouseBanService;
use WKS\Services\LocationProvisioningService;
use WKS\Services\MailService;
use WKS\Services\PostUpdateCheckService;
use WKS\Services\ReleaseNotesService;
use WKS\Services\ShiftService;
use WKS\Services\SpecialReportService;
use WKS\Services\SystemStatusService;
use WKS\Services\TrashService;
use WKS\Services\ValuablesService;

$assertions=0;
$assert=static function(bool $condition,string $message) use (&$assertions): void {
    $assertions++;
    if(!$condition)throw new RuntimeException('ASSERTION FAILED: '.$message);
};
$expectHttp=static function(callable $callback,int $status,string $message) use ($assert): void {
    try{$callback();}catch(HttpException $e){$assert($e->status===$status,$message.' (HTTP '.$e->status.')');return;}
    throw new RuntimeException('ASSERTION FAILED: '.$message.' (no exception)');
};
$switchUser=static function(int $userId,int $locationId): void {
    Session::put('user_id',$userId);
    Session::put('active_location_id',$locationId);
    Session::put('last_activity',time());
    Auth::forgetCache();
    Authorization::reset();
};
$request=static fn(string $method,string $path,array $post=[]): Request =>
    new Request($method,$path,[],$post,[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_USER_AGENT'=>'WKS-CI']);

echo "[1/9] Fresh migrations and seed data\n";
$runner=new MigrationRunner();
$done=$runner->migrate();
$assert(count($done)>=13,'all versioned migrations execute on a fresh MySQL 8 database');
$assert($runner->pending()===[],'no migrations remain pending');
$assert($runner->migrate()===[],'migrations are idempotent through migration tracking');

$pdo=Database::connection();
$locations=$pdo->query('SELECT * FROM locations ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
$byCode=[];foreach($locations as $loc)$byCode[$loc['code']]=$loc;
$assert(isset($byCode['GI'],$byCode['MR']),'Gießen and Marburg are seeded');
$gi=(int)$byCode['GI']['id'];$mr=(int)$byCode['MR']['id'];
$assert($byCode['GI']['email_address']==='Security.gi@uk-gm.de','Gießen site mail is exact');
$assert($byCode['MR']['email_address']==='Security.mr@uk-gm.de','Marburg site mail is exact');
$assert($byCode['GI']['mail_mode']==='disabled'&&$byCode['MR']['mail_mode']==='disabled','site mail is disabled initially');
foreach([$gi,$mr] as $locationId){
    $s=$pdo->prepare('SELECT COUNT(*) FROM cassettes WHERE location_id=:id');$s->execute(['id'=>$locationId]);$assert((int)$s->fetchColumn()===100,'100 cassettes are seeded per site');
    $s=$pdo->prepare('SELECT COUNT(*) FROM storage_locations WHERE location_id=:id');$s->execute(['id'=>$locationId]);$assert((int)$s->fetchColumn()===51,'50 racks plus floor are seeded per site');
}
$night=$pdo->prepare('SELECT * FROM shifts WHERE location_id=:id AND code="N"');$night->execute(['id'=>$gi]);$night=$night->fetch(PDO::FETCH_ASSOC);
$assert($night&&$night['start_time']==='21:40:00'&&$night['end_time']==='05:58:00'&&(int)$night['crosses_midnight']===1,'night shift seed is correct');

echo "[2/9] Users, login, permissions and site separation\n";
$roleRows=$pdo->query('SELECT code,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$userRepo=new UserRepository();
$makeUser=static function(string $first,string $last,string $username,string $personnel,string $email,int $roleId,array $siteIds) use ($userRepo): int {
    return $userRepo->create([
        'first_name'=>$first,'last_name'=>$last,'personnel_number'=>$personnel,'username'=>$username,'email'=>$email,
        'password_hash'=>password_hash('IntegrationPass!2026',PASSWORD_DEFAULT),'role_id'=>$roleId,'status'=>'active',
        'must_change_password'=>0,'first_login'=>0,'theme'=>'light','created_by'=>null,'updated_by'=>null
    ],$siteIds);
};
$adminId=$makeUser('Ada','Admin','integration.admin','IT-A1','admin.integration@example.test',(int)$roleRows['admin'],[$gi,$mr]);
$managementId=$makeUser('Mara','Leitung','integration.management','IT-M1','management.integration@example.test',(int)$roleRows['management'],[$gi]);
$employeeId=$makeUser('Emil','Mitarbeiter','integration.employee','IT-E1','employee.integration@example.test',(int)$roleRows['employee'],[$gi]);

$loginRequest=new Request('POST','/login',[],[],[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_USER_AGENT'=>'WKS-CI']);
$login=(new AuthService())->attempt('integration.admin','IntegrationPass!2026',$loginRequest);
$assert($login['ok']===true,'admin login succeeds');
Session::put('active_location_id',$gi);Authorization::reset();
$assert(Authorization::can('system.users.manage'),'admin has system user management right');
$assert((new LocationRepository())->userHasLocation($adminId,$mr),'multi-site admin is assigned to Marburg');
$assert(!(new LocationRepository())->userHasLocation($managementId,$mr),'Gießen management user is not assigned to Marburg');
$results=$userRepo->paginate('Integration',1,25);
$assert($results['total']===3,'native PDO user search works');
$switchUser($employeeId,$gi);
$assert(Authorization::can('dutybook.read'),'employee can read dutybook');
$assert(!Authorization::can('system.users.manage'),'employee cannot manage users');

$switchUser($adminId,$gi);
$locationController=new LocationController();
$locationController->store($request('POST','/admin/locations',[
    'name'=>'Integration Standort','code'=>'ITEST','email_address'=>'security.itest@example.test',
    'mail_mode'=>'disabled','active'=>'1'
]));
$testLocation=$pdo->query('SELECT * FROM locations WHERE code="ITEST" LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert((bool)$testLocation,'new site is created through administration');
$testLocationId=(int)$testLocation['id'];
foreach([
    'shifts'=>4,
    'cassettes'=>100,
    'storage_locations'=>51,
    'special_report_types'=>19,
    'dutybook_automatic_rules'=>6,
] as $table=>$expected){
    $s=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE location_id=:location_id');
    $s->execute(['location_id'=>$testLocationId]);
    $assert((int)$s->fetchColumn()===$expected,'new site provisions '.$table);
}
$s=$pdo->prepare('SELECT COUNT(*) FROM dutybook_event_types WHERE location_id=:location_id');$s->execute(['location_id'=>$testLocationId]);
$assert((int)$s->fetchColumn()>=1,'new site receives a default dutybook event type');
(new LocationProvisioningService())->provision($testLocationId,$adminId);
$s=$pdo->prepare('SELECT COUNT(*) FROM cassettes WHERE location_id=:location_id');$s->execute(['location_id'=>$testLocationId]);
$assert((int)$s->fetchColumn()===100,'site provisioning is idempotent');

echo "[3/9] Master data writes and dynamic configuration\n";
$switchUser($adminId,$gi);
$master=new MasterDataRepository();
$testShift=$master->saveShift(null,$gi,[
    'code'=>'X','name'=>'Integration','start_time'=>'10:00:00','end_time'=>'11:00:00','crosses_midnight'=>0,'sort_order'=>99,'active'=>1
],$adminId);
$master->saveShift($testShift,$gi,[
    'code'=>'X','name'=>'Integration geändert','start_time'=>'10:00:00','end_time'=>'11:00:00','crosses_midnight'=>0,'sort_order'=>99,'active'=>1
],$adminId);
$categoryId=$master->saveCategory(null,$gi,['name'=>'Integration','sort_order'=>990,'active'=>1],$adminId);
$master->saveCategory($categoryId,$gi,['name'=>'Integration geändert','sort_order'=>990,'active'=>1],$adminId);
$eventId=$master->saveEventType(null,$gi,[
    'category_id'=>$categoryId,'name'=>'Integration Ereignis','sort_order'=>990,'active'=>1,
    'offer_special_report'=>1,'offer_valuables'=>1,'auto_entry_enabled'=>1
],$adminId);
$master->saveEventType($eventId,$gi,[
    'category_id'=>$categoryId,'name'=>'Integration Ereignis geändert','sort_order'=>990,'active'=>1,
    'offer_special_report'=>1,'offer_valuables'=>1,'auto_entry_enabled'=>1
],$adminId);
$dynamicId=$master->saveDynamicField(null,$gi,[
    'definition_id'=>$eventId,'field_key'=>'integration_code','label'=>'Integrationscode','field_type'=>'text',
    'required'=>1,'sort_order'=>10,'options_json'=>null,'active'=>1
],$adminId);
$master->saveDynamicField($dynamicId,$gi,[
    'definition_id'=>$eventId,'field_key'=>'integration_code','label'=>'Integrationscode geändert','field_type'=>'text',
    'required'=>1,'sort_order'=>10,'options_json'=>null,'active'=>1
],$adminId);
$conditionalId=$master->saveDynamicField(null,$gi,[
    'definition_id'=>$eventId,'field_key'=>'integration_conditional','label'=>'Bedingtes Pflichtfeld','field_type'=>'text',
    'required'=>1,'sort_order'=>20,'options_json'=>null,
    'visibility_json'=>json_encode(['field_id'=>$dynamicId,'value'=>'show'],JSON_THROW_ON_ERROR),
    'active'=>1
],$adminId);
$dynamicValidation=new DynamicFormService();
$hiddenValidation=$dynamicValidation->validateDutybookValues($eventId,[$dynamicId=>'hide',$conditionalId=>'manipulated']);
$assert($hiddenValidation['errors']===[]&&!array_key_exists($conditionalId,$hiddenValidation['values']),'hidden dynamic field is ignored server-side');
$visibleValidation=$dynamicValidation->validateDutybookValues($eventId,[$dynamicId=>'show']);
$assert($visibleValidation['errors']!==[],'visible conditional required field is enforced server-side');
$placeId=$master->savePlace(null,$gi,['parent_id'=>null,'place_type'=>'building','name'=>'CI Gebäude','sort_order'=>990,'active'=>1]);
$assert($master->places($mr)!==array_filter($master->places($gi),static fn(array $p):bool=>(int)$p['id']===$placeId),'site-specific place data is separated');

$srRepo=new SpecialReportRepository();
$srTypeId=$srRepo->saveType(null,$gi,['name'=>'CI Einsatz','code'=>'ci_einsatz','sort_order'=>990,'active'=>1,'force_section_enabled'=>1],$adminId);
$srRepo->saveType($srTypeId,$gi,['name'=>'CI Einsatz geändert','code'=>'ci_einsatz','sort_order'=>990,'active'=>1,'force_section_enabled'=>1],$adminId);
$srDynamicId=$master->saveDynamicFieldForModule(null,'special_report_type',[
    'definition_id'=>$srTypeId,'field_key'=>'ci_detail','label'=>'CI Detail','field_type'=>'text',
    'required'=>1,'sort_order'=>10,'options_json'=>null,'active'=>1
],$adminId);
$master->saveDynamicFieldForModule($srDynamicId,'special_report_type',[
    'definition_id'=>$srTypeId,'field_key'=>'ci_detail','label'=>'CI Detail geändert','field_type'=>'text',
    'required'=>1,'sort_order'=>10,'options_json'=>null,'active'=>1
],$adminId);

echo "[4/9] Dutybook full shift and handover chain\n";
$shiftRows=$master->shifts($gi);$shiftByCode=[];foreach($shiftRows as $s)$shiftByCode[$s['code']]=$s;
$shiftService=new ShiftService();
$dutyService=new DutybookService();
$handoverService=new HandoverService();
$dutyRepo=new DutybookRepository();
$tz=new DateTimeZone('Europe/Berlin');

$switchUser($adminId,$gi);
$fSession=$shiftService->acceptDuty($gi,(int)$shiftByCode['F']['id'],$adminId,new DateTimeImmutable('2026-09-23 06:00:00',$tz));
$measure=(new MasterDataRepository())->measures($gi)[0];
$org=(new MasterDataRepository())->externalOrganizations($gi)[0];
$role=(new MasterDataRepository())->personRoles($gi)[0];
$entryId=$dutyService->create($gi,[
    'shift_id'=>$shiftByCode['F']['id'],'event_type_id'=>$eventId,'status'=>'open','occurred_at'=>'2026-09-23 06:30',
    'event_started_at'=>'2026-09-23 06:25','place_id'=>$placeId,'facts'=>'CI offener Übergabevorgang',
    'measures_text'=>'CI Maßnahme','result_text'=>'noch offen','staff_ids'=>[$adminId],'measure_ids'=>[$measure['id']],
    'people'=>[['role_id'=>$role['id'],'person_type'=>'Patient','first_name'=>'Pat','last_name'=>'Test','birth_date'=>'1980-01-01','area'=>'CI','internal_identifier'=>'CASE-CI']],
    'external'=>[['organization_id'=>$org['id'],'organization_name'=>$org['name'],'contact_name'=>'CI Kontakt','notified_at'=>'2026-09-23 06:35','feedback'=>'erfasst','reference_number'=>'CI-EXT']],
    'dynamic'=>[$dynamicId=>'DYN-CI']
]);
$entry=$dutyRepo->findEntry($entryId,$gi);
$assert($entry&&$entry['duty_date']==='2026-09-23','dutybook entry belongs to shift-start day');
$assert($dutyRepo->findEntry($entryId,$mr)===null,'dutybook record is site-isolated');
$assert(($entry['dynamic_values'][$dynamicId]['value']??null)==='DYN-CI','dynamic dutybook field persists');
$assert(count($entry['people'])===1&&count($entry['external'])===1,'dutybook people and external parties persist');

$pdo->prepare('UPDATE dutybook_entries SET edit_locked_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=:id')->execute(['id'=>$entryId]);
$expectHttp(fn()=>$dutyService->update($gi,$entryId,['event_type_id'=>$eventId,'status'=>'open','occurred_at'=>'2026-09-23 06:30','facts'=>'blocked','dynamic'=>[$dynamicId=>'X']]),423,'locked dutybook entry rejects direct edit');
$dutyService->addendum($gi,$entryId,'CI Nachtrag',['status'=>'open','facts'=>'CI offener Übergabevorgang mit Nachtrag','measures_text'=>'CI Maßnahme','result_text'=>'noch offen']);
$assert(count($dutyRepo->findEntry($entryId,$gi)['addenda'])===1,'dutybook addendum is versioned');

$handoverService->save($gi,(int)$fSession['id'],(int)$shiftByCode['S']['id'],'F nach S',[$entryId=>['user_id'=>0]]);
$handoverService->confirmOutgoing($gi,(int)$fSession['id']);
$switchUser($managementId,$gi);
$sSession=$shiftService->acceptDuty($gi,(int)$shiftByCode['S']['id'],$managementId,new DateTimeImmutable('2026-09-23 14:00:00',$tz));
$handoverService->confirmIncoming($gi,(int)$fSession['id']);
$switchUser($adminId,$gi);$shiftService->endShift($gi,(int)$fSession['id'],$adminId);
$assert(in_array($entryId,array_map('intval',array_column($dutyRepo->openEntriesForSession($gi,(int)$sSession['id']),'id')),true),'carried entry belongs to receiving late shift handover scope');

$switchUser($managementId,$gi);
$handoverService->save($gi,(int)$sSession['id'],(int)$shiftByCode['N']['id'],'S nach N',[$entryId=>['user_id'=>0]]);
$handoverService->confirmOutgoing($gi,(int)$sSession['id']);
$switchUser($employeeId,$gi);
$nSession=$shiftService->acceptDuty($gi,(int)$shiftByCode['N']['id'],$employeeId,new DateTimeImmutable('2026-09-23 22:00:00',$tz));
$assert($nSession['duty_date']==='2026-09-23','night shift dutybook date is the shift-start date');
$handoverService->confirmIncoming($gi,(int)$sSession['id']);
$switchUser($managementId,$gi);$shiftService->endShift($gi,(int)$sSession['id'],$managementId);
$assert(in_array($entryId,array_map('intval',array_column($dutyRepo->openEntriesForSession($gi,(int)$nSession['id']),'id')),true),'carried entry is visible in night shift');

$switchUser($employeeId,$gi);
$handoverService->save($gi,(int)$nSession['id'],(int)$shiftByCode['F']['id'],'N nach F',[$entryId=>['user_id'=>0]]);
$handoverService->confirmOutgoing($gi,(int)$nSession['id']);
$switchUser($adminId,$gi);
$nextF=$shiftService->acceptDuty($gi,(int)$shiftByCode['F']['id'],$adminId,new DateTimeImmutable('2026-09-24 06:00:00',$tz));
$assert($nextF['duty_date']==='2026-09-24','next early shift opens the next dutybook day');
$handoverService->confirmIncoming($gi,(int)$nSession['id']);
$switchUser($employeeId,$gi);$shiftService->endShift($gi,(int)$nSession['id'],$employeeId);
$assert(in_array($entryId,array_map('intval',array_column($dutyRepo->openEntriesForSession($gi,(int)$nextF['id']),'id')),true),'multi-hop carried entry is scoped to next receiving session');
$assert(!$dutyRepo->hasOpenSessionsForDay($gi,'2026-09-23'),'all shift sessions of the archived day are closed');

echo "[5/9] Special report review, revision and immutable version\n";
$switchUser($adminId,$gi);
$specialService=new SpecialReportService();
$reportId=$specialService->create($gi,[
    'report_type_id'=>$srTypeId,'incident_date'=>'2026-09-23','incident_started_at'=>'2026-09-23 07:00',
    'incident_ended_at'=>'2026-09-23 07:25','place_id'=>$placeId,'facts'=>'CI Sonderbericht Sachverhalt',
    'measures_text'=>'CI Maßnahmen','result_text'=>'CI Ergebnis','status'=>'draft','source_dutybook_entry_id'=>$entryId,
    'staff_ids'=>[$adminId],'people'=>[['role_id'=>$role['id'],'person_type'=>'Patient','first_name'=>'Bericht','last_name'=>'Person','birth_date'=>'1970-02-03','area'=>'CI','internal_identifier'=>'SB-CI']],
    'witnesses'=>[['name'=>'Zeuge CI','contact_details'=>'intern','statement_summary'=>'gesehen','written_statement'=>1]],
    'external'=>[['organization_id'=>$org['id'],'organization_name'=>$org['name'],'contact_name'=>'Kontakt','notified_at'=>'2026-09-23 07:10','feedback'=>'ok','reference_number'=>'SB-EXT']],
    'injury'=>['injury_present'=>1,'description'=>'CI Verletzung','medical_care'=>'Versorgung','treating_entity'=>'CI','treated_at'=>'2026-09-23 07:15'],
    'force_actions'=>[['action_type'=>'Fixierung','started_at'=>'2026-09-23 07:05','ended_at'=>'2026-09-23 07:08','justification'=>'CI','result_text'=>'beendet','staff_ids'=>[$adminId]]],
    'dynamic'=>[$srDynamicId=>'SB-DYN']
]);
$report=$srRepo->find($reportId,$gi);
$assert($report&&$srRepo->find($reportId,$mr)===null,'special report is site-isolated');
$assert(count($report['witnesses'])===1&&count($report['force_actions'])===1&&($report['dynamic_values'][$srDynamicId]['value']??null)==='SB-DYN','special report structured sections persist');
$specialService->complete($gi,$reportId);
$specialService->requestRevision($gi,$reportId,['Bitte CI Detail ergänzen'],'CI Prüfung');
$report=$srRepo->find($reportId,$gi);$requestId=(int)$report['revision_requests'][0]['id'];
$specialService->update($gi,$reportId,[
    'report_type_id'=>$srTypeId,'incident_date'=>'2026-09-23','incident_started_at'=>'2026-09-23 07:00',
    'incident_ended_at'=>'2026-09-23 07:25','place_id'=>$placeId,'facts'=>'CI Sonderbericht ergänzt',
    'measures_text'=>'CI Maßnahmen','result_text'=>'CI Ergebnis','status'=>'revision_required','staff_ids'=>[$adminId],
    'people'=>[['role_id'=>$role['id'],'person_type'=>'Patient','first_name'=>'Bericht','last_name'=>'Person','birth_date'=>'1970-02-03','area'=>'CI','internal_identifier'=>'SB-CI']],
    'witnesses'=>[['name'=>'Zeuge CI','contact_details'=>'intern','statement_summary'=>'gesehen','written_statement'=>1]],
    'external'=>[],'injury'=>['injury_present'=>0],'force_actions'=>[],'dynamic'=>[$srDynamicId=>'SB-DYN-2']
]);
$specialService->completeRevisionRequest($gi,$reportId,$requestId);
$specialService->complete($gi,$reportId);
$specialService->approve($gi,$reportId,'CI Freigabe');
$report=$srRepo->find($reportId,$gi);
$assert($report['status']==='reviewed'&&(int)$report['current_version']===1,'special report reaches reviewed version 1');
$assert(count($report['versions'])===1,'reviewed special report stores immutable version snapshot');
$version=$report['versions'][0];
$assert(is_file(BASE_PATH.'/storage/generated/'.$version['pdf_path'])&&str_starts_with((string)file_get_contents(BASE_PATH.'/storage/generated/'.$version['pdf_path']),'%PDF-'),'versioned PDF is generated');
$assert(is_file(BASE_PATH.'/storage/generated/'.$version['docx_path'])&&filesize(BASE_PATH.'/storage/generated/'.$version['docx_path'])>0,'versioned DOCX is generated');

$switchUser($adminId,$gi);
$archiveController=new DutybookController();
$archiveController->archive($request('POST','/dutybook/archive',['date'=>'2026-09-23']));
$archived=$dutyRepo->archivedDay($gi,'2026-09-23');
$assert($archived&&is_file(BASE_PATH.'/storage/generated/'.$archived['archive_file']),'closed dutybook day creates immutable archive PDF');
$assert(hash_equals($archived['archive_hash'],hash_file('sha256',BASE_PATH.'/storage/generated/'.$archived['archive_file'])),'dutybook archive SHA-256 matches');

echo "[6/9] Valuables concurrency rules, release and seal tombstones\n";
$valRepo=new ValuablesRepository();$valService=new ValuablesService();
$storage=$valRepo->storageLocations($gi)[0];
$cassettes=$valRepo->cassettes($gi);$cassette1=$cassettes[0];$cassette2=$cassettes[1];
$valuableId=$valService->store($gi,[
    'first_name'=>'Wert','last_name'=>'Sache','birth_date'=>'1985-04-05','internal_identifier'=>'VAL-CI',
    'handed_over_by_type'=>'patient','handed_over_by_name'=>'Wert Sache','stored_at'=>'2026-09-24 07:00',
    'containers'=>[[
        'container_type'=>'cassette','description'=>'CI Kassette','storage_location_id'=>$storage['id'],
        'cassette_id'=>$cassette1['id'],'seal_left'=>'100001','seal_right'=>'100002'
    ]]
]);
$valuable=$valRepo->find($valuableId,$gi);
$assert($valuable&&$valRepo->find($valuableId,$mr)===null,'valuables record is site-isolated');
$assert($valRepo->cassette((int)$cassette1['id'],$gi)['valuables_record_id']!==null,'cassette is locked while stored');
$expectHttp(fn()=>$valService->store($gi,[
    'first_name'=>'Doppelt','last_name'=>'Kassette','birth_date'=>'1980-01-01','handed_over_by_type'=>'patient','handed_over_by_name'=>'X','stored_at'=>'2026-09-24 07:10',
    'containers'=>[['container_type'=>'cassette','storage_location_id'=>$storage['id'],'cassette_id'=>$cassette1['id'],'seal_left'=>'200001','seal_right'=>'200002']]
]),409,'occupied cassette cannot be assigned twice');

$container=$valuable['containers'][0];
$valService->release($gi,$valuableId,[
    'released_at'=>'2026-09-24 08:00','receiver_is_subject'=>1,'release_confirmed'=>1,
    'seal_checks'=>[(int)$container['id']=>[
        'left_matches'=>'1','left_condition'=>'intact','left_actual'=>'100001','left_reason'=>'',
        'right_matches'=>'1','right_condition'=>'intact','right_actual'=>'100002','right_reason'=>''
    ]]
]);
$assert($valRepo->find($valuableId,$gi)['status']==='released','valuables release closes whole record');
$assert($valRepo->cassette((int)$cassette1['id'],$gi)['valuables_record_id']===null,'cassette is free after full release');
$archiveFiltered=$valRepo->archive($gi,$valService->retentionDays(),[
    'released_from'=>'2026-09-24','released_to'=>'2026-09-24','container_type'=>'cassette',
    'cassette_number'=>(int)$cassette1['cassette_number'],'storage_location_id'=>(int)$storage['id']
],1,30);
$assert($archiveFiltered['total']===1&&(int)$archiveFiltered['items'][0]['id']===$valuableId,'valuables archive filters released date, type, cassette and storage location');
$switchUser($employeeId,$gi);
$assert(!Authorization::can('valuables.archive'),'employee has no valuables archive right');
$expectHttp(fn()=>$valService->addNote($gi,$valuableId,'Nach Auslagerung unzulässig'),422,'released valuables reject internal notes');
$switchUser($adminId,$gi);
$expectHttp(fn()=>$valService->store($gi,[
    'first_name'=>'Reuse','last_name'=>'Seal','birth_date'=>'1980-01-01','handed_over_by_type'=>'patient','handed_over_by_name'=>'X','stored_at'=>'2026-09-24 08:10',
    'containers'=>[['container_type'=>'cassette','storage_location_id'=>$storage['id'],'cassette_id'=>$cassette2['id'],'seal_left'=>'100001','seal_right'=>'300002']]
]),409,'used seal cannot be reused after release');
$mrCassettes=$valRepo->cassettes($mr);
$assert(isset($mrCassettes[0])&&(int)$mrCassettes[0]['cassette_number']===1&&$mrCassettes[0]['valuables_record_id']===null,'Marburg has its own free cassette number 1');

$trash=new TrashService();$trash->move('valuables',$valuableId,$gi,$adminId);
$trashRow=$pdo->query('SELECT * FROM trash_entries WHERE module="valuables" AND record_id='.(int)$valuableId)->fetch(PDO::FETCH_ASSOC);
$assert((bool)$trashRow,'released valuables can enter central trash');
$trash->hardDelete((int)$trashRow['id'],$adminId);
$assert($valRepo->sealUsage('100001')!==null,'used seal remains permanently blocked after hard delete');

echo "[7/9] House bans, trash restore and isolated search\n";
$houseService=new HouseBanService();$houseRepo=new HouseBanRepository();
$banId=$houseService->create($gi,['ban_date'=>'2026-09-23','person_name'=>'CI Hausverbot','reason'=>'Integrationstest']);
$houseService->update($gi,$banId,['ban_date'=>'2026-09-23','person_name'=>'CI Hausverbot geändert','reason'=>'Integrationstest geändert']);
$assert($houseRepo->find($banId,$gi)!==null&&$houseRepo->find($banId,$mr)===null,'house ban is site-isolated');
$assert($houseRepo->search($gi,['name'=>'CI Hausverbot'],1,30)['total']===1,'house ban search finds record');
$houseService->delete($gi,$banId);
$trashRow=$pdo->query('SELECT * FROM trash_entries WHERE module="house_bans" AND record_id='.(int)$banId)->fetch(PDO::FETCH_ASSOC);
$assert((bool)$trashRow&&$houseRepo->find($banId,$gi)===null,'house ban soft-delete enters central trash');
$trash->restore((int)$trashRow['id'],$adminId);
$assert($houseRepo->find($banId,$gi)!==null,'house ban restores from central trash');

echo "[8/9] Announcements, autosave, mail suppression, search and statistics\n";
$announcementService=new AnnouncementService();$announcementRepo=new AnnouncementRepository();
$announcementId=$announcementService->save(null,[
    'title'=>'CI Mitteilung','body'=>'Version eins','priority'=>'important','status'=>'published','require_ack'=>1,'target_all'=>1
]);
$visible=$announcementRepo->visibleForUser($adminId,$gi,'admin');
$assert(in_array($announcementId,array_map('intval',array_column($visible,'id')),true),'published announcement is visible to target');
$announcementService->markReadVisible($announcementId,$adminId,$gi,'admin',true);
$announcementService->save($announcementId,[
    'title'=>'CI Mitteilung','body'=>'Version zwei','priority'=>'important','status'=>'published','require_ack'=>1,'target_all'=>1
]);
$updated=array_values(array_filter($announcementRepo->visibleForUser($adminId,$gi,'admin'),static fn(array $a):bool=>(int)$a['id']===$announcementId))[0];
$assert((int)$updated['revision']===2&&(int)$updated['is_updated']===1,'published update increments revision and is marked updated');

$autosave=new DraftAutosaveService();$autosave->save($adminId,$gi,'dutybook','new',['facts'=>'CI autosave']);
$assert(($autosave->get($adminId,$gi,'dutybook','new')['payload']['facts']??null)==='CI autosave','server autosave restores payload');
$autosave->delete($adminId,$gi,'dutybook','new');$assert($autosave->get($adminId,$gi,'dutybook','new')===null,'server autosave can be discarded');

$mail=new MailService();$mail->queueLocation($gi,'ci_mail','CI Mail','Test body');$processed=$mail->processQueue(10);
$assert($processed['suppressed']>=1,'mail queue is suppressed while global sending is disabled');
$mailLog=$pdo->query('SELECT * FROM mail_log WHERE trigger_code="ci_mail" ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert($mailLog&&$mailLog['target_address']==='Security.gi@uk-gm.de'&&$mailLog['status']==='suppressed','suppressed mail still targets only the site address');

$search=new GlobalSearchRepository();
$assert(count($search->search('CI Hausverbot',$gi,['house_bans']))>=1,'global search finds Gießen data');
$assert(count($search->search('CI Hausverbot',$mr,['house_bans']))===0,'global search does not leak Gießen data to Marburg');
$stats=(new StatisticsRepository())->summary($gi,'2026-09-23','2026-09-24');
$assert($stats['dutybook']>0&&$stats['special_reports']>0&&$stats['house_bans']>0,'statistics aggregate created Gießen records');
$statsMr=(new StatisticsRepository())->summary($mr,'2026-09-23','2026-09-24');
$assert($statsMr['special_reports']===0&&$statsMr['house_bans']===0,'statistics remain site-separated');

echo "[9/9] Documents, release notes and system checks\n";
$generator=new DocumentGeneratorService();
$pdf=$generator->pdfForTemplate('dutybook','CI PDF',['Test'=>['Zeile']]);$assert(str_starts_with($pdf,'%PDF-'),'PDF generator creates valid PDF header');
$docx=$generator->docxForTemplate('special_report','CI DOCX',['Test'=>['Zeile']]);$assert(is_file($docx)&&filesize($docx)>0,'DOCX generator creates archive');@unlink($docx);
$release=(new ReleaseNotesService())->syncLocal();$assert($release&&$release['version']===trim((string)file_get_contents(BASE_PATH.'/VERSION')),'release notes match VERSION');
$status=(new SystemStatusService())->check();$assert($status['overall']!=='error','system status has no error after full integration run');
$post=(new PostUpdateCheckService())->run();$assert($post['overall']!=='error','post-update acceptance check has no error');
$auditCount=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();$assert($auditCount>10,'critical actions generated audit entries');

echo "WKS integration acceptance OK: {$assertions} assertions.\n";
ob_end_flush();

<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Services\DutybookService;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\ShiftService;
use WKS\Services\DraftAutosaveService;

final class DutybookController
{
    public function index(Request $request): Response
    {
        $locationId=(int)active_location_id();$repo=new DutybookRepository();
        $current=$repo->currentOpenSessionForUser($locationId,(int)Auth::id());
        $detected=(new ShiftService())->detect($locationId);
        $date=trim((string)$request->query('date',$current['duty_date']??date('Y-m-d')));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$date=date('Y-m-d');
        $day=$repo->day($locationId,$date);
        $entries=$repo->entriesForDay($locationId,$date);
        $attendance=$current?$repo->attendance((int)$current['id']):[];
        $shifts=(new MasterDataRepository())->shifts($locationId);
        return View::render('dutybook/index',compact('current','detected','date','day','entries','attendance','shifts'));
    }

    public function create(Request $request): Response
    {
        return $this->form(null,$request);
    }

    public function store(Request $request): Response
    {
        try{
            $id=(new DutybookService())->create((int)active_location_id(),$request->all(),$request->files());
            (new DraftAutosaveService())->delete((int)Auth::id(),(int)active_location_id(),'dutybook','new');
            flash('success','Dienstbucheintrag wurde gespeichert.');
            return Response::redirect(url('dutybook/'.$id));
        }catch(HttpException $e){
            set_old($request->all());flash('error',$e->getMessage());
            return Response::redirect(url('dutybook/create'));
        }
    }

    public function show(Request $request,string $id): Response
    {
        $entry=(new DutybookRepository())->findEntry((int)$id,(int)active_location_id());
        if(!$entry)throw new HttpException(404,'Dienstbucheintrag nicht gefunden.');
        $locked=(new DutybookService())->isLocked($entry);
        return View::render('dutybook/show',compact('entry','locked'));
    }

    public function edit(Request $request,string $id): Response
    {
        $entry=(new DutybookRepository())->findEntry((int)$id,(int)active_location_id());
        if(!$entry)throw new HttpException(404,'Dienstbucheintrag nicht gefunden.');
        if((new DutybookService())->isLocked($entry)){
            flash('info','Die Bearbeitungsfrist ist abgelaufen. Änderungen sind nur noch als Nachtrag möglich.');
            return Response::redirect(url('dutybook/'.$id));
        }
        return $this->form($entry,$request);
    }

    public function update(Request $request,string $id): Response
    {
        try{
            (new DutybookService())->update((int)active_location_id(),(int)$id,$request->all(),$request->files());
            (new DraftAutosaveService())->delete((int)Auth::id(),(int)active_location_id(),'dutybook','edit:'.(int)$id);
            flash('success','Dienstbucheintrag wurde aktualisiert.');
            return Response::redirect(url('dutybook/'.(int)$id));
        }catch(HttpException $e){
            set_old($request->all());flash('error',$e->getMessage());
            return Response::redirect(url('dutybook/'.(int)$id.'/edit'));
        }
    }

    public function addendum(Request $request,string $id): Response
    {
        (new DutybookService())->addendum(
            (int)active_location_id(),(int)$id,(string)$request->post('reason',''),$request->all()
        );
        flash('success','Dokumentierter Nachtrag wurde gespeichert.');
        return Response::redirect(url('dutybook/'.(int)$id));
    }

    public function search(Request $request): Response
    {
        $filters=[
            'from'=>(string)$request->query('from',''),'to'=>(string)$request->query('to',''),
            'status'=>(string)$request->query('status',''),'shift_id'=>(int)$request->query('shift_id',0),
            'category_id'=>(int)$request->query('category_id',0),'event_type_id'=>(int)$request->query('event_type_id',0),
            'creator_id'=>(int)$request->query('creator_id',0),'staff_id'=>(int)$request->query('staff_id',0),
            'attachments'=>(int)$request->query('attachments',0),'q'=>trim((string)$request->query('q',''))
        ];
        $result=(new DutybookRepository())->search((int)active_location_id(),$filters,max(1,(int)$request->query('page',1)));
        $master=new MasterDataRepository();$locationId=(int)active_location_id();
        return View::render('dutybook/search',[
            'filters'=>$filters,'result'=>$result,'shifts'=>$master->shifts($locationId),
            'categories'=>$master->categories($locationId),'eventTypes'=>$master->eventTypes($locationId),
            'users'=>$master->usersForLocation($locationId)
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $date=(string)$request->query('date',date('Y-m-d'));
        $entries=(new DutybookRepository())->entriesForDay((int)active_location_id(),$date);
        $fp=fopen('php://temp','r+');
        fputcsv($fp,['Datum','Uhrzeit','Schicht','Kategorie','Ereignisart','Status','Sachverhalt','Maßnahmen','Ergebnis'],';');
        foreach($entries as $e){
            fputcsv($fp,[
                $e['duty_date'],date('H:i',strtotime((string)$e['occurred_at'])),$e['shift_name'],$e['category_name'],$e['event_type_name'],
                $e['status'],$e['facts'],$e['measures_text'],$e['result_text']
            ],';');
        }
        rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        (new \WKS\Services\AuditService())->log('dutybook_export_csv','dutybook',$date,null,['count'=>count($entries)],['date'=>$date],$request);
        return new Response("\xEF\xBB\xBF".$csv,200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="'.$date.'_Dienstbuch.csv"',
        ]);
    }

    public function printDay(Request $request): Response
    {
        $date=(string)$request->query('date',date('Y-m-d'));
        $repo=new DutybookRepository();
        $day=$repo->day((int)active_location_id(),$date);
        $entries=$repo->entriesForDay((int)active_location_id(),$date);
        return View::render('dutybook/print',compact('day','entries','date'),200,'print-layout');
    }

    private function form(?array $entry,?Request $request=null): Response
    {
        $locationId=(int)active_location_id();$master=new MasterDataRepository();
        $context=$entry?'edit:'.(int)$entry['id']:'new';
        $draft=(new DraftAutosaveService())->get((int)Auth::id(),$locationId,'dutybook',$context);
        if($request && (string)$request->query('restore_autosave','')==='1' && $draft){
            set_old($draft['payload']);
        }
        $eventTypes=$master->eventTypes($locationId);
        $dynamic=[];
        foreach($eventTypes as $type)$dynamic[(int)$type['id']]=$master->dynamicFields('dutybook_event',(int)$type['id']);
        $current=(new DutybookRepository())->currentOpenSessionForUser($locationId,(int)Auth::id());
        return View::render('dutybook/form',[
            'entry'=>$entry,'current'=>$current,'shifts'=>$master->shifts($locationId),'categories'=>$master->categories($locationId),
            'eventTypes'=>$eventTypes,'dynamicByEvent'=>$dynamic,'personRoles'=>$master->personRoles($locationId),
            'places'=>$master->places($locationId),'measures'=>$master->measures($locationId),
            'externalOrganizations'=>$master->externalOrganizations($locationId),'users'=>$master->usersForLocation($locationId),
            'autosave'=>$draft,'autosaveContext'=>$context
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $date=(string)$request->query('date',date('Y-m-d'));
        $repo=new DutybookRepository();$day=$repo->day((int)active_location_id(),$date);$entries=$repo->entriesForDay((int)active_location_id(),$date);
        $rows=[];
        foreach($entries as $e){
            $rows[]=date('H:i',strtotime((string)$e['occurred_at'])).' · '.($e['shift_name']??'–').' · '.($e['event_type_name']??'Automatisch').' · '.$e['facts']
                .($e['measures_text']?"\nMaßnahmen: ".$e['measures_text']:'').($e['result_text']?"\nErgebnis: ".$e['result_text']:'');
        }
        $title=$date.'_Dienstbuch_'.($day['location_name']??'Standort');
        $pdf=(new DocumentGeneratorService())->pdfForTemplate('dutybook',$title,['Einträge'=>$rows]);
        (new \WKS\Services\AuditService())->log('dutybook_export_pdf','dutybook',$date,null,['count'=>count($entries)],['date'=>$date],$request);
        return new Response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$title.'.pdf"']);
    }


    public function archive(Request $request): Response
    {
        $date=(string)$request->post('date',date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new HttpException(422,'Ungültiges Dienstbuchdatum.');
        $locationId=(int)active_location_id();$repo=new DutybookRepository();
        if($repo->archivedDay($locationId,$date)){
            flash('info','Für diesen Tag existiert bereits ein unveränderlicher Archivstand.');
            return Response::redirect(url('dutybook?date='.$date));
        }
        $day=$repo->day($locationId,$date);
        if(!$day){
            flash('error','Für diesen Tag existiert noch kein Tagesdienstbuch.');
            return Response::redirect(url('dutybook?date='.$date));
        }
        $entries=$repo->entriesForDay($locationId,$date);$rows=[];
        foreach($entries as $e){
            $rows[]=date('H:i',strtotime((string)$e['occurred_at'])).' · '.($e['shift_name']??'–').' · '.($e['event_type_name']??'Automatisch').' · '.$e['facts']
                .($e['measures_text']?"\nMaßnahmen: ".$e['measures_text']:'').($e['result_text']?"\nErgebnis: ".$e['result_text']:'');
        }
        $title=$date.'_Dienstbuch_'.$day['location_name'];
        $pdf=(new DocumentGeneratorService())->pdfForTemplate('dutybook',$title,['Einträge'=>$rows]);
        $year=substr($date,0,4);$month=substr($date,5,2);
        $dir=BASE_PATH.'/storage/generated/dutybook/'.$year.'/'.$month;
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Archivverzeichnis konnte nicht angelegt werden.');
        $safeLocation=preg_replace('/[^A-Za-z0-9_-]+/u','_',str_replace(['ä','ö','ü','Ä','Ö','Ü','ß'],['ae','oe','ue','Ae','Oe','Ue','ss'],$day['location_name']))?:'Standort';
        $relative='dutybook/'.$year.'/'.$month.'/'.$date.'_Dienstbuch_'.$safeLocation.'.pdf';
        $absolute=BASE_PATH.'/storage/generated/'.$relative;
        if(is_file($absolute))throw new HttpException(409,'Die Archivdatei existiert bereits und wird aus Sicherheitsgründen nicht überschrieben.');
        if(file_put_contents($absolute,$pdf,LOCK_EX)===false)throw new \RuntimeException('Archiv-PDF konnte nicht geschrieben werden.');
        @chmod($absolute,0440);$hash=hash_file('sha256',$absolute);
        try{
            $repo->archiveDay($locationId,$date,$relative,$hash,(int)Auth::id());
        }catch(\Throwable $e){
            @chmod($absolute,0640);@unlink($absolute);throw $e;
        }
        (new \WKS\Services\AuditService())->log('dutybook_archived','dutybook',$date,null,['archive_file'=>$relative,'sha256'=>$hash,'entries'=>count($entries)],[],$request);
        flash('success','Unveränderlicher PDF-Archivstand wurde erzeugt.');
        return Response::redirect(url('dutybook?date='.$date));
    }

    public function archiveDownload(Request $request): Response
    {
        $date=(string)$request->query('date',date('Y-m-d'));$record=(new DutybookRepository())->archivedDay((int)active_location_id(),$date);
        if(!$record||!$record['archive_file'])throw new HttpException(404,'Kein Archivstand für diesen Tag vorhanden.');
        $root=realpath(BASE_PATH.'/storage/generated');$path=realpath(BASE_PATH.'/storage/generated/'.$record['archive_file']);
        if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path))throw new HttpException(404,'Archivdatei nicht gefunden.');
        $hash=hash_file('sha256',$path);if(!hash_equals((string)$record['archive_hash'],$hash))throw new HttpException(500,'Integritätsprüfung der Archivdatei ist fehlgeschlagen.');
        (new \WKS\Services\AuditService())->log('dutybook_archive_downloaded','dutybook',$date,null,['sha256'=>$hash],[],$request);
        return new Response((string)file_get_contents($path),200,[
            'Content-Type'=>'application/pdf','Content-Length'=>(string)filesize($path),
            'Content-Disposition'=>'attachment; filename="'.basename((string)$record['archive_file']).'"',
            'X-Content-Type-Options'=>'nosniff'
        ]);
    }

}

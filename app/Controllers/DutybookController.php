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
use WKS\Services\ShiftService;

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
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try{
            $id=(new DutybookService())->create((int)active_location_id(),$request->all(),$request->files());
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
        return $this->form($entry);
    }

    public function update(Request $request,string $id): Response
    {
        try{
            (new DutybookService())->update((int)active_location_id(),(int)$id,$request->all(),$request->files());
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

    private function form(?array $entry): Response
    {
        $locationId=(int)active_location_id();$master=new MasterDataRepository();
        $eventTypes=$master->eventTypes($locationId);
        $dynamic=[];
        foreach($eventTypes as $type)$dynamic[(int)$type['id']]=$master->dynamicFields('dutybook_event',(int)$type['id']);
        $current=(new DutybookRepository())->currentOpenSessionForUser($locationId,(int)Auth::id());
        return View::render('dutybook/form',[
            'entry'=>$entry,'current'=>$current,'shifts'=>$master->shifts($locationId),'categories'=>$master->categories($locationId),
            'eventTypes'=>$eventTypes,'dynamicByEvent'=>$dynamic,'personRoles'=>$master->personRoles($locationId),
            'places'=>$master->places($locationId),'measures'=>$master->measures($locationId),
            'externalOrganizations'=>$master->externalOrganizations($locationId),'users'=>$master->usersForLocation($locationId)
        ]);
    }
}

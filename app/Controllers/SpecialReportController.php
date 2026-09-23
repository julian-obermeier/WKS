<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\DutybookRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\SpecialReportService;

final class SpecialReportController
{
    public function index(Request $request): Response
    {
        $repo=new SpecialReportRepository();$locationId=(int)active_location_id();
        $open=$repo->search($locationId,['status'=>'in_progress'],1,10);
        $drafts=$repo->search($locationId,['status'=>'draft'],1,10);
        $revision=$repo->search($locationId,['status'=>'revision_required'],1,10);
        $unreviewed=$repo->search($locationId,['status'=>'completed'],1,15);
        $reviewed=$repo->search($locationId,['status'=>'reviewed'],1,10);
        return View::render('special-reports/index',compact('open','drafts','revision','unreviewed','reviewed'));
    }

    public function create(Request $request): Response
    {
        $sourceId=(int)$request->query('dutybook_id',0);$prefill=null;
        if($sourceId>0){
            $prefill=(new DutybookRepository())->findEntry($sourceId,(int)active_location_id());
            if(!$prefill)throw new HttpException(404,'Dienstbucheintrag nicht gefunden.');
        }
        return $this->form(null,$prefill);
    }

    public function store(Request $request): Response
    {
        try{$id=(new SpecialReportService())->create((int)active_location_id(),$request->all(),$request->files());clear_old();flash('success','Sonderbericht wurde angelegt.');return Response::redirect(url('special-reports/'.$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());$source=(int)$request->post('source_dutybook_entry_id',0);return Response::redirect(url('special-reports/create'.($source?'?dutybook_id='.$source:'')));}
    }

    public function show(Request $request,string $id): Response
    {
        $report=(new SpecialReportRepository())->find((int)$id,(int)active_location_id());
        if(!$report)throw new HttpException(404,'Sonderbericht nicht gefunden.');
        return View::render('special-reports/show',['report'=>$report,'service'=>new SpecialReportService()]);
    }

    public function edit(Request $request,string $id): Response
    {
        $report=(new SpecialReportRepository())->find((int)$id,(int)active_location_id());if(!$report)throw new HttpException(404,'Sonderbericht nicht gefunden.');
        if(in_array($report['status'],['completed','reviewed'],true)){flash('info','Dieser Bericht ist gesperrt.');return Response::redirect(url('special-reports/'.$id));}
        return $this->form($report,null);
    }

    public function update(Request $request,string $id): Response
    {
        try{(new SpecialReportService())->update((int)active_location_id(),(int)$id,$request->all(),$request->files());clear_old();flash('success','Sonderbericht wurde gespeichert.');return Response::redirect(url('special-reports/'.(int)$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());return Response::redirect(url('special-reports/'.(int)$id.'/edit'));}
    }

    public function complete(Request $request,string $id): Response
    {
        (new SpecialReportService())->complete((int)active_location_id(),(int)$id);flash('success','Bericht wurde digital bestätigt und zur Leitungsprüfung abgeschlossen.');return Response::redirect(url('special-reports/'.(int)$id));
    }

    public function completeRevisionRequest(Request $request,string $id,string $requestId): Response
    {
        (new SpecialReportService())->completeRevisionRequest((int)active_location_id(),(int)$id,(int)$requestId);flash('success','Nachforderung wurde als erledigt markiert.');return Response::redirect(url('special-reports/'.(int)$id));
    }

    public function addendum(Request $request,string $id): Response
    {
        (new SpecialReportService())->addendum((int)active_location_id(),(int)$id,(string)$request->post('reason',''),$request->all());flash('success','Nachtrag und neue Dokumentversion wurden erstellt.');return Response::redirect(url('special-reports/'.(int)$id));
    }

    public function search(Request $request): Response
    {
        $filters=['from'=>(string)$request->query('from',''),'to'=>(string)$request->query('to',''),'status'=>(string)$request->query('status',''),'type_id'=>(int)$request->query('type_id',0),'creator_id'=>(int)$request->query('creator_id',0),'staff_id'=>(int)$request->query('staff_id',0),'person'=>trim((string)$request->query('person','')),'attachments'=>(int)$request->query('attachments',0),'q'=>trim((string)$request->query('q',''))];
        $locationId=(int)active_location_id();$repo=new SpecialReportRepository();$result=$repo->search($locationId,$filters,max(1,(int)$request->query('page',1)));
        $master=new MasterDataRepository();return View::render('special-reports/search',['filters'=>$filters,'result'=>$result,'types'=>$repo->types($locationId),'users'=>$master->usersForLocation($locationId)]);
    }

    public function pdf(Request $request,string $id): Response
    {
        $r=$this->report((int)$id);$service=new SpecialReportService();$content=(new DocumentGeneratorService())->pdf('Sonderbericht '.$service->displayNumber($r),$service->exportSections($r));
        (new \WKS\Services\AuditService())->log('special_report_export_pdf','special_reports',$id,null,['version'=>$r['current_version']],[],$request);
        return new Response($content,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$service->filename($r,'pdf').'"']);
    }

    public function docx(Request $request,string $id): Response
    {
        $r=$this->report((int)$id);$service=new SpecialReportService();$path=(new DocumentGeneratorService())->docx('Sonderbericht '.$service->displayNumber($r),$service->exportSections($r));$content=(string)file_get_contents($path);@unlink($path);
        (new \WKS\Services\AuditService())->log('special_report_export_docx','special_reports',$id,null,['version'=>$r['current_version']],[],$request);
        return new Response($content,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','Content-Disposition'=>'attachment; filename="'.$service->filename($r,'docx').'"']);
    }

    public function printReport(Request $request,string $id): Response{$report=$this->report((int)$id);$service=new SpecialReportService();return View::render('special-reports/print',compact('report','service'),200,'print-layout');}

    private function report(int $id): array{$r=(new SpecialReportRepository())->find($id,(int)active_location_id());if(!$r)throw new HttpException(404,'Sonderbericht nicht gefunden.');return $r;}

    private function form(?array $report,?array $prefill): Response
    {
        $locationId=(int)active_location_id();$repo=new SpecialReportRepository();$master=new MasterDataRepository();$types=$repo->types($locationId);$dynamic=[];foreach($types as $t)$dynamic[(int)$t['id']]=$master->dynamicFields('special_report_type',(int)$t['id']);
        return View::render('special-reports/form',['report'=>$report,'prefill'=>$prefill,'types'=>$types,'dynamicByType'=>$dynamic,'places'=>$master->places($locationId),'roles'=>$master->personRoles($locationId),'users'=>$master->usersForLocation($locationId),'externalOrganizations'=>$master->externalOrganizations($locationId)]);
    }
}

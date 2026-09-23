<?php
declare(strict_types=1);

namespace WKS\Controllers;

use DateTimeImmutable;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\StatisticsRepository;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Services\AuditService;
use WKS\Services\DocumentGeneratorService;
use WKS\Repositories\LocationRepository;

final class StatisticsController
{
    public function index(Request $request): Response
    {
        $data=$this->data($request);
        return View::render('statistics/index',$data);
    }

    public function exportCsv(Request $request): Response
    {
        $data=$this->data($request);$fp=fopen('php://temp','r+');
        fputcsv($fp,['WKS Statistik','Wert'],';');
        fputcsv($fp,['Zeitraum',$data['from'].' bis '.$data['to']],';');
        fputcsv($fp,['Dienstbucheinträge',$data['summary']['dutybook']],';');
        fputcsv($fp,['Sonderberichte',$data['summary']['special_reports']],';');
        fputcsv($fp,['Ungeprüfte Sonderberichte',$data['summary']['unreviewed']],';');
        fputcsv($fp,['Einlagerungen',$data['summary']['valuables_stored']],';');
        fputcsv($fp,['Auslagerungen',$data['summary']['valuables_released']],';');
        fputcsv($fp,['Hausverbote',$data['summary']['house_bans']],';');
        fputcsv($fp,['Durchschnittliche Verwahrdauer (Tage)',number_format((float)$data['summary']['avg_storage_days'],2,'.','')],';');
        fputcsv($fp,[],';');fputcsv($fp,['Dienstbuch Ereignisart','Anzahl'],';');
        foreach($data['events'] as $row)fputcsv($fp,[$row['label'],$row['value']],';');
        fputcsv($fp,[],';');fputcsv($fp,['Sonderbericht Einsatzart','Anzahl'],';');
        foreach($data['reportTypes'] as $row)fputcsv($fp,[$row['label'],$row['value']],';');
        fputcsv($fp,[],';');fputcsv($fp,['Tag','Vorgänge gesamt'],';');
        foreach($data['timeline'] as $row)fputcsv($fp,[$row['day'],$row['value']],';');
        rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        $this->auditExport($request,$data,'csv');
        return new Response("\xEF\xBB\xBF".$csv,200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="WKS_Statistik_'.$data['from'].'_bis_'.$data['to'].'.csv"'
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $data=$this->data($request);$location=(new LocationRepository())->find((int)active_location_id());
        $sections=[
            'Zeitraum'=>$data['from'].' bis '.$data['to'],
            'Kennzahlen'=>[
                'Dienstbucheinträge: '.(int)$data['summary']['dutybook'],
                'Sonderberichte: '.(int)$data['summary']['special_reports'],
                'Ungeprüfte Sonderberichte: '.(int)$data['summary']['unreviewed'],
                'Einlagerungen: '.(int)$data['summary']['valuables_stored'],
                'Auslagerungen: '.(int)$data['summary']['valuables_released'],
                'Hausverbote: '.(int)$data['summary']['house_bans'],
                'Durchschnittliche Verwahrdauer: '.number_format((float)$data['summary']['avg_storage_days'],1,',','.').' Tage',
            ],
            'Dienstbuch · Ereignisarten'=>array_map(static fn(array $row):string=>$row['label'].': '.$row['value'],$data['events']),
            'Sonderberichte · Einsatzarten'=>array_map(static fn(array $row):string=>$row['label'].': '.$row['value'],$data['reportTypes']),
            'Zeitverlauf'=>array_map(static fn(array $row):string=>$row['day'].': '.$row['value'],$data['timeline']),
        ];
        $pdf=(new DocumentGeneratorService())->pdfForTemplate(
            'statistics','Statistik · '.($location['name']??'Standort'),$sections
        );
        $this->auditExport($request,$data,'pdf');
        return new Response($pdf,200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="WKS_Statistik_'.$data['from'].'_bis_'.$data['to'].'.pdf"'
        ]);
    }

    private function data(Request $request): array
    {
        $from=(string)$request->query('from',(new DateTimeImmutable('first day of this month'))->format('Y-m-d'));
        $to=(string)$request->query('to',date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');
        if($from>$to)[$from,$to]=[$to,$from];
        $eventTypeId=max(0,(int)$request->query('event_type_id',0));$reportTypeId=max(0,(int)$request->query('report_type_id',0));
        $repo=new StatisticsRepository();$locationId=(int)active_location_id();
        $master=new MasterDataRepository();$eventTypes=$master->eventTypes($locationId);
        $specialReportTypes=(new SpecialReportRepository())->types($locationId);
        if($eventTypeId&&!in_array($eventTypeId,array_map('intval',array_column($eventTypes,'id')),true))$eventTypeId=0;
        if($reportTypeId&&!in_array($reportTypeId,array_map('intval',array_column($specialReportTypes,'id')),true))$reportTypeId=0;
        $summary=$repo->summary($locationId,$from,$to,$eventTypeId?:null,$reportTypeId?:null);
        $events=$repo->dutybookByEvent($locationId,$from,$to,$eventTypeId?:null);
        $reportTypes=$repo->specialReportsByType($locationId,$from,$to,$reportTypeId?:null);
        $timeline=$repo->timeline($locationId,$from,$to);
        return compact('from','to','summary','events','reportTypes','timeline','eventTypes','specialReportTypes','eventTypeId','reportTypeId');
    }

    private function auditExport(Request $request,array $data,string $format): void
    {
        (new AuditService())->log(
            'statistics_export_'.$format,'statistics',null,null,null,
            ['format'=>$format,'from'=>$data['from'],'to'=>$data['to'],'event_type_id'=>$data['eventTypeId']?:null,'report_type_id'=>$data['reportTypeId']?:null],
            $request
        );
    }
}

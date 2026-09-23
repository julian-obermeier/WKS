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

final class StatisticsController
{
    public function index(Request $request): Response
    {
        $from=(string)$request->query('from',(new DateTimeImmutable('first day of this month'))->format('Y-m-d'));
        $to=(string)$request->query('to',date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');
        $eventTypeId=max(0,(int)$request->query('event_type_id',0));$reportTypeId=max(0,(int)$request->query('report_type_id',0));
        $repo=new StatisticsRepository();$locationId=(int)active_location_id();
        $summary=$repo->summary($locationId,$from,$to,$eventTypeId?:null,$reportTypeId?:null);
        $events=$repo->dutybookByEvent($locationId,$from,$to,$eventTypeId?:null);
        $reportTypes=$repo->specialReportsByType($locationId,$from,$to,$reportTypeId?:null);$timeline=$repo->timeline($locationId,$from,$to);
        $master=new MasterDataRepository();$eventTypes=$master->eventTypes($locationId);$specialReportTypes=(new SpecialReportRepository())->types($locationId);
        return View::render('statistics/index',compact('from','to','summary','events','reportTypes','timeline','eventTypes','specialReportTypes','eventTypeId','reportTypeId'));
    }
}

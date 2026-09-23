<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Services\SpecialReportService;

final class SpecialReportReviewController
{
    public function requestRevision(Request $request,string $id): Response
    {
        $items=(array)$request->post('requests',[]);(new SpecialReportService())->requestRevision((int)active_location_id(),(int)$id,$items,(string)$request->post('review_note',''));
        flash('success','Nachbearbeitung wurde mit den einzelnen Nachforderungen angefordert.');return Response::redirect(url('special-reports/'.(int)$id));
    }
    public function approve(Request $request,string $id): Response
    {
        (new SpecialReportService())->approve((int)active_location_id(),(int)$id,(string)$request->post('review_note',''));
        flash('success','Leitungsprüfung wurde digital bestätigt. Version wurde archiviert.');return Response::redirect(url('special-reports/'.(int)$id));
    }
}

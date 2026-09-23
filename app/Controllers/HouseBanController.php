<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\HouseBanRepository;
use WKS\Repositories\LocationRepository;
use WKS\Services\AuditService;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\HouseBanService;

final class HouseBanController
{
    public function index(Request $request): Response
    {
        $filters=['name'=>trim((string)$request->query('name','')),'reason'=>trim((string)$request->query('reason','')),'from'=>(string)$request->query('from',''),'to'=>(string)$request->query('to',''),'sort'=>(string)$request->query('sort','date')];
        $result=(new HouseBanRepository())->search((int)active_location_id(),$filters,max(1,(int)$request->query('page',1)));
        return View::render('house-bans/index',compact('filters','result'));
    }

    public function create(Request $request): Response{return View::render('house-bans/form',['record'=>null]);}

    public function store(Request $request): Response
    {
        try{$id=(new HouseBanService())->create((int)active_location_id(),$request->all(),$request->files());clear_old();flash('success','Hausverbot wurde angelegt.');return Response::redirect(url('house-bans/'.$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());return Response::redirect(url('house-bans/create'));}
    }

    public function show(Request $request,string $id): Response
    {
        $record=(new HouseBanRepository())->find((int)$id,(int)active_location_id());if(!$record)throw new HttpException(404,'Hausverbot nicht gefunden.');
        return View::render('house-bans/show',compact('record'));
    }

    public function edit(Request $request,string $id): Response
    {
        $record=(new HouseBanRepository())->find((int)$id,(int)active_location_id());if(!$record)throw new HttpException(404,'Hausverbot nicht gefunden.');
        return View::render('house-bans/form',compact('record'));
    }

    public function update(Request $request,string $id): Response
    {
        try{(new HouseBanService())->update((int)active_location_id(),(int)$id,$request->all(),$request->files());clear_old();flash('success','Hausverbot wurde aktualisiert.');return Response::redirect(url('house-bans/'.(int)$id));}
        catch(HttpException $e){set_old($request->all());flash('error',$e->getMessage());return Response::redirect(url('house-bans/'.(int)$id.'/edit'));}
    }

    public function delete(Request $request,string $id): Response
    {
        (new HouseBanService())->delete((int)active_location_id(),(int)$id);flash('success','Hausverbot wurde in den Papierkorb verschoben.');return Response::redirect(url('house-bans'));
    }

    public function exportCsv(Request $request): Response
    {
        $filters=['name'=>trim((string)$request->query('name','')),'reason'=>trim((string)$request->query('reason','')),'from'=>(string)$request->query('from',''),'to'=>(string)$request->query('to',''),'sort'=>'date'];
        $result=(new HouseBanRepository())->search((int)active_location_id(),$filters,1,10000);
        $fp=fopen('php://temp','r+');fputcsv($fp,['Datum','Name','Grund','Erstellt von','Erstellt am'],';');
        foreach($result['items'] as $r)fputcsv($fp,[$r['ban_date'],$r['person_name'],$r['reason'],$r['creator_name'],$r['created_at']],';');
        rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        (new AuditService())->log('house_bans_export_csv','house_bans',null,null,['filters'=>$filters,'count'=>$result['total']],[],$request);
        return new Response("\xEF\xBB\xBF".$csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="Hausverbote.csv"']);
    }

    public function exportPdf(Request $request): Response
    {
        $filters=['name'=>trim((string)$request->query('name','')),'reason'=>trim((string)$request->query('reason','')),'from'=>(string)$request->query('from',''),'to'=>(string)$request->query('to',''),'sort'=>'date'];
        $result=(new HouseBanRepository())->search((int)active_location_id(),$filters,1,10000);
        $rows=array_map(static fn(array $r):string=>$r['ban_date'].' · '.$r['person_name'].' · '.$r['reason'],$result['items']);
        $location=(new LocationRepository())->find((int)active_location_id());
        $content=(new DocumentGeneratorService())->pdfForTemplate('house_bans','Hausverbotsliste · '.($location['name']??'Standort'),['Hausverbote'=>$rows]);
        (new AuditService())->log('house_bans_export_pdf','house_bans',null,null,['filters'=>$filters,'count'=>$result['total']],[],$request);
        return new Response($content,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="Hausverbote.pdf"']);
    }
}

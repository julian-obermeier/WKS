<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Auth;
use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\ValuablesRepository;
use WKS\Services\AuditService;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\ValuablesService;

final class ValuablesController
{
    public function index(Request $request): Response
    {
        $locationId=(int)active_location_id();$repo=new ValuablesRepository();$service=new ValuablesService();
        $filters=['container_type'=>(string)$request->query('container_type',''),'storage_location_id'=>(int)$request->query('storage_location_id',0)];
        $records=$repo->active($locationId,$filters);$counts=$repo->counts($locationId,$service->longTermDays());$storage=$repo->storageLocations($locationId);
        $occupancy=$repo->storageOccupancy($locationId);
        return View::render('valuables/index',compact('records','counts','filters','storage','occupancy','service'));
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function correction(Request $request,string $id): Response
    {
        if(!Authorization::can('valuables.archive'))throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        $parent=(new ValuablesRepository())->find((int)$id,(int)active_location_id());
        if(!$parent)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($parent['status']!=='released')throw new HttpException(422,'Nur ein vollständig ausgelagerter Vorgang kann als Korrekturfolge neu angelegt werden.');
        return $this->form($parent);
    }

    public function store(Request $request): Response
    {
        try{
            $id=(new ValuablesService())->store((int)active_location_id(),$request->all(),$request->files());
            clear_old();flash('success','Wertsache wurde vollständig eingelagert.');
            return Response::redirect(url('valuables/'.$id));
        }catch(HttpException $e){
            set_old($request->all());flash('error',$e->getMessage());
            $parent=(int)$request->post('correction_parent_id',0);
            return Response::redirect($parent?url('valuables/'.$parent.'/correction'):url('valuables/create'));
        }
    }

    public function show(Request $request,string $id): Response
    {
        $record=(new ValuablesRepository())->find((int)$id,(int)active_location_id());
        if(!$record)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($record['status']==='released'&&!Authorization::can('valuables.archive'))throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        $retentionDays=(new ValuablesService())->retentionDays();
        $retentionUntil=$record['released_at']?(new \DateTimeImmutable((string)$record['released_at']))->modify('+'.$retentionDays.' days'):null;
        return View::render('valuables/show',compact('record','retentionDays','retentionUntil'));
    }

    public function releaseForm(Request $request,string $id): Response
    {
        $record=(new ValuablesRepository())->find((int)$id,(int)active_location_id());
        if(!$record)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($record['status']!=='stored'){
            if(!Authorization::can('valuables.archive'))throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
            flash('info','Der Vorgang ist bereits vollständig ausgelagert.');return Response::redirect(url('valuables/'.$id));
        }
        return View::render('valuables/release',compact('record'));
    }

    public function release(Request $request,string $id): Response
    {
        $existing=(new ValuablesRepository())->find((int)$id,(int)active_location_id());
        if(!$existing)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($existing['status']!=='stored'&&!Authorization::can('valuables.archive'))throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        try{(new ValuablesService())->release((int)active_location_id(),(int)$id,$request->all());flash('success','Vorgang wurde vollständig ausgelagert.');return Response::redirect(url('valuables/'.(int)$id));}
        catch(HttpException $e){flash('error',$e->getMessage());return Response::redirect(url('valuables/'.(int)$id.'/release'));}
    }

    public function note(Request $request,string $id): Response
    {
        (new ValuablesService())->addNote((int)active_location_id(),(int)$id,(string)$request->post('note_text',''));flash('success','Interne Notiz wurde ergänzt.');return Response::redirect(url('valuables/'.(int)$id));
    }

    public function cassettes(Request $request): Response
    {
        $cassettes=(new ValuablesRepository())->cassettes((int)active_location_id());
        return View::render('valuables/cassettes',compact('cassettes'));
    }

    public function longTerm(Request $request): Response
    {
        $service=new ValuablesService();$days=$service->longTermDays();$records=(new ValuablesRepository())->longTerm((int)active_location_id(),$days);
        return View::render('valuables/long-term',compact('records','days'));
    }

    public function checkSeal(Request $request): Response
    {
        $seal=trim((string)$request->query('seal',''));
        $data=['valid'=>false,'available'=>false,'message'=>'Bitte eine numerische Siegelnummer eingeben.'];
        if(preg_match('/^\d+$/',$seal)){
            $usage=(new ValuablesRepository())->sealUsage($seal);
            if($usage){
                $data=['valid'=>true,'available'=>false,'message'=>'Bereits verwendet','custody_number'=>str_pad((string)$usage['custody_number'],4,'0',STR_PAD_LEFT),'used_at'=>$usage['used_at']];
            }else $data=['valid'=>true,'available'=>true,'message'=>'Siegelnummer frei'];
        }
        return new Response(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),200,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
    }

    public function search(Request $request): Response
    {
        $filters=[];
        foreach(['custody_number','first_name','last_name','birth_date','internal_identifier','stored_from','stored_to','cassette_number','seal','storage_location_id'] as $key)$filters[$key]=$request->query($key,'');
        $filters['status']='stored';
        $locationId=(int)active_location_id();$repo=new ValuablesRepository();$result=$repo->search($locationId,$filters,max(1,(int)$request->query('page',1)));$storage=$repo->storageLocations($locationId);
        return View::render('valuables/search',compact('filters','result','storage'));
    }

    public function archive(Request $request): Response
    {
        $service=new ValuablesService();$retentionDays=$service->retentionDays();$locationId=(int)active_location_id();
        $filters=$this->archiveFilters($request);
        $repo=new ValuablesRepository();$result=$repo->archive($locationId,$retentionDays,$filters,max(1,(int)$request->query('page',1)));
        $storage=$repo->storageLocations($locationId);
        return View::render('valuables/archive',compact('result','retentionDays','filters','storage'));
    }

    public function exportCsv(Request $request): Response
    {
        $status=(string)$request->query('status','stored');if(!in_array($status,['stored','released'],true))$status='stored';
        $repo=new ValuablesRepository();$locationId=(int)active_location_id();$service=new ValuablesService();
        if($status==='released'){
            if(!Authorization::can('valuables.archive'))throw new HttpException(403,'Für den Export ausgelagerter Wertsachen ist das Archivrecht erforderlich.');
            $filters=$this->archiveFilters($request);
            $result=$repo->archive($locationId,$service->retentionDays(),$filters,1,10000);
        }else{
            $filters=[
                'status'=>'stored','stored_from'=>$this->dateQuery($request,'stored_from','from'),
                'stored_to'=>$this->dateQuery($request,'stored_to','to'),'container_type'=>(string)$request->query('container_type',''),
                'cassette_number'=>(int)$request->query('cassette_number',0),'storage_location_id'=>(int)$request->query('storage_location_id',0)
            ];
            $result=$repo->search($locationId,$filters,1,10000);
        }
        $fp=fopen('php://temp','r+');fputcsv($fp,['Verwahrnummer','Vorname','Nachname','Geburtsdatum','Kennung','Status','Einlagerung','Auslagerung','Behältnisse'],';');
        foreach($result['items'] as $r)fputcsv($fp,[str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT),$r['first_name'],$r['last_name'],$r['birth_date'],$r['internal_identifier'],$r['status'],$r['stored_at'],$r['released_at'],$r['container_count']],';');
        rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        (new AuditService())->log('valuables_export_csv','valuables',null,null,['status'=>$status,'filter'=>$filters,'count'=>$result['total']],[],$request);
        return new Response("\xEF\xBB\xBF".$csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="Wertsachen_'.$status.'_Export.csv"']);
    }

    private function form(?array $correctionParent): Response
    {
        $repo=new ValuablesRepository();$locationId=(int)active_location_id();
        return View::render('valuables/form',['storage'=>$repo->storageLocations($locationId),'cassettes'=>$repo->cassettes($locationId),'correctionParent'=>$correctionParent]);
    }

    public function pdf(Request $request,string $id): Response
    {
        $record=$this->record((int)$id);
        $title='Wertsachen · Verwahrnummer '.str_pad((string)$record['custody_number'],4,'0',STR_PAD_LEFT);
        $content=(new DocumentGeneratorService())->pdfForTemplate('valuables',$title,$this->exportSections($record));
        (new AuditService())->log('valuables_export_pdf','valuables',$id,null,['custody_number'=>$record['custody_number']],[],$request);
        return new Response($content,200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="Wertsache_'.str_pad((string)$record['custody_number'],4,'0',STR_PAD_LEFT).'.pdf"'
        ]);
    }

    public function printRecord(Request $request,string $id): Response
    {
        $record=$this->record((int)$id);
        $sections=$this->exportSections($record);
        return View::render('valuables/print',compact('record','sections'),200,'print-layout');
    }

    private function record(int $id): array
    {
        $record=(new ValuablesRepository())->find($id,(int)active_location_id());
        if(!$record)throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        if($record['status']==='released'&&!Authorization::can('valuables.archive'))throw new HttpException(404,'Wertsachenvorgang nicht gefunden.');
        return $record;
    }

    private function archiveFilters(Request $request): array
    {
        $types=['cassette','bag','sack','case','pocket','other'];$containerType=(string)$request->query('container_type','');
        if(!in_array($containerType,$types,true))$containerType='';
        $from=$this->dateQuery($request,'released_from','from');$to=$this->dateQuery($request,'released_to','to');
        if($from!==''&&$to!==''&&$from>$to)[$from,$to]=[$to,$from];
        $cassette=(int)$request->query('cassette_number',0);if($cassette<1||$cassette>100)$cassette=0;
        return [
            'released_from'=>$from,'released_to'=>$to,'container_type'=>$containerType,
            'cassette_number'=>$cassette,'storage_location_id'=>max(0,(int)$request->query('storage_location_id',0))
        ];
    }

    private function dateQuery(Request $request,string $primary,string $fallback=''): string
    {
        $value=trim((string)$request->query($primary,$fallback!==''?$request->query($fallback,''):''));
        if($value==='')return '';
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date&&$date->format('Y-m-d')===$value?$value:'';
    }

    private function exportSections(array $record): array
    {
        $containers=[];
        foreach($record['containers'] as $c){
            $line='Pos. '.(int)$c['position_number'].' · '.$c['container_type'].' · '.$c['storage_label'];
            if($c['cassette_number'])$line.=' · Kassette '.(int)$c['cassette_number'].' · Siegel L '.$c['seal_left'].' / R '.$c['seal_right'];
            $containers[]=$line;
        }
        $sections=[
            'Person'=>[
                'Name: '.$record['first_name'].' '.$record['last_name'],
                'Geburtsdatum: '.$record['birth_date'],
                'Interne Kennung: '.($record['internal_identifier']??'–'),
            ],
            'Einlagerung'=>[
                'Zeit: '.format_datetime($record['stored_at']),
                'Mitarbeiter: '.($record['stored_by_name']??'–'),
                'Übergeben durch: '.$record['handed_over_by_name'].' · '.$record['handed_over_by_type'],
                'Organisation/Bereich: '.($record['handed_over_by_organization']??'–'),
                'Bemerkung: '.($record['storage_note']??'–'),
            ],
            'Behältnisse'=>$containers,
        ];
        if($record['status']==='released'){
            $sections['Auslagerung']=[
                'Zeit: '.format_datetime($record['released_at']),
                'Mitarbeiter: '.($record['released_by_name']??'–'),
                'Empfänger: '.($record['receiver_name']??'–').' · '.($record['receiver_type']??'–'),
                'Grund Fremdausgabe: '.($record['receiver_reason']??'–'),
                'Organisation/Bereich: '.($record['receiver_organization']??'–'),
                'Bemerkung: '.($record['release_note']??'–'),
            ];
            $checks=[];
            foreach($record['containers'] as $c){
                if(!$c['cassette_id'])continue;
                $checks[]='Kassette '.(int)$c['cassette_number'].' · L: '.($c['seal_left_matches']?'stimmt':'abweichend').' / '.($c['seal_left_condition']??'–').' · R: '.($c['seal_right_matches']?'stimmt':'abweichend').' / '.($c['seal_right_condition']??'–');
            }
            $sections['Siegelprüfung']=$checks;
        }
        return $sections;
    }

}

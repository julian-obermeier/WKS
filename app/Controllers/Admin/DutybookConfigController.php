<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\DutybookAutomaticRuleRepository;
use WKS\Services\AuditService;

final class DutybookConfigController
{
    public function index(Request $request): Response
    {
        $locationId=(int)active_location_id();$repo=new MasterDataRepository();
        return View::render('admin/dutybook/index',[
            'shifts'=>$repo->shifts($locationId,false),'categories'=>$repo->categories($locationId,false),
            'eventTypes'=>$repo->eventTypes($locationId,false),'dynamicFields'=>$repo->allDutybookDynamicFields($locationId),
            'places'=>$repo->places($locationId,false),'measures'=>$repo->measures($locationId,false),
            'personRoles'=>$repo->personRoles($locationId),'externalOrganizations'=>$repo->externalOrganizations($locationId),
            'automaticRules'=>(new DutybookAutomaticRuleRepository())->all($locationId)
        ]);
    }

    public function saveShift(Request $request): Response
    {
        $id=$this->id($request);$data=[
            'code'=>strtoupper(trim((string)$request->post('code'))),'name'=>trim((string)$request->post('name')),
            'start_time'=>$this->time($request->post('start_time')),'end_time'=>$this->time($request->post('end_time')),
            'crosses_midnight'=>$request->post('crosses_midnight')?1:0,'sort_order'=>(int)$request->post('sort_order',0),
            'active'=>$request->post('active')?1:0
        ];
        if($data['code']===''||$data['name']==='')throw new HttpException(422,'Kürzel und Name sind erforderlich.');
        $saved=(new MasterDataRepository())->saveShift($id,(int)active_location_id(),$data,(int)Auth::id());
        $this->audit('shift_saved',$saved,$data,$request);return $this->back('Schicht wurde gespeichert.');
    }

    public function saveCategory(Request $request): Response
    {
        $id=$this->id($request);$data=['name'=>trim((string)$request->post('name')),'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0];
        if($data['name']==='')throw new HttpException(422,'Bezeichnung ist erforderlich.');
        $saved=(new MasterDataRepository())->saveCategory($id,(int)active_location_id(),$data,(int)Auth::id());
        $this->audit('category_saved',$saved,$data,$request);return $this->back('Kategorie wurde gespeichert.');
    }

    public function saveEventType(Request $request): Response
    {
        $id=$this->id($request);$data=[
            'category_id'=>(int)$request->post('category_id'),'name'=>trim((string)$request->post('name')),
            'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0,
            'offer_special_report'=>$request->post('offer_special_report')?1:0,'offer_valuables'=>$request->post('offer_valuables')?1:0,
            'auto_entry_enabled'=>$request->post('auto_entry_enabled')?1:0
        ];
        if($data['category_id']<1||$data['name']==='')throw new HttpException(422,'Kategorie und Bezeichnung sind erforderlich.');
        $repo=new MasterDataRepository();$saved=$repo->saveEventType($id,(int)active_location_id(),$data,(int)Auth::id());
        $repo->syncEventMeasures($saved,(int)active_location_id(),(array)$request->post('measure_ids',[]));
        $this->audit('event_type_saved',$saved,$data,$request);return $this->back('Ereignisart wurde gespeichert.');
    }

    public function saveDynamicField(Request $request): Response
    {
        $id=$this->id($request);$type=(string)$request->post('field_type');
        $allowed=['text','textarea','number','date','time','datetime','select','multiselect','checkbox','yesno'];
        if(!in_array($type,$allowed,true))throw new HttpException(422,'Ungültiger Feldtyp.');
        $options=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)$request->post('options',''))?:[])));
        $definitionId=(int)$request->post('definition_id');$sortOrder=(int)$request->post('sort_order',0);
        $data=[
            'definition_id'=>$definitionId,'field_key'=>preg_replace('/[^a-z0-9_]/','_',strtolower(trim((string)$request->post('field_key')))),
            'label'=>trim((string)$request->post('label')),'field_type'=>$type,'required'=>$request->post('required')?1:0,
            'sort_order'=>$sortOrder,'options_json'=>$options===[]?null:json_encode($options,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'visibility_json'=>$this->visibilityJson($request,'dutybook_event',$definitionId,$id,$sortOrder),
            'active'=>$request->post('active')?1:0
        ];
        if($data['definition_id']<1||$data['field_key']===''||$data['label']==='')throw new HttpException(422,'Ereignisart, Feldschlüssel und Bezeichnung sind erforderlich.');
        $saved=(new MasterDataRepository())->saveDynamicField($id,(int)active_location_id(),$data,(int)Auth::id());
        $this->audit('dynamic_field_saved',$saved,$data,$request);return $this->back('Zusatzfeld wurde gespeichert.');
    }

    public function savePlace(Request $request): Response
    {
        $id=$this->id($request);$type=(string)$request->post('place_type');
        if(!in_array($type,['building','floor','area','room','station'],true))throw new HttpException(422,'Ungültiger Ortstyp.');
        $data=['parent_id'=>($p=(int)$request->post('parent_id'))>0?$p:null,'place_type'=>$type,'name'=>trim((string)$request->post('name')),
            'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0];
        if($data['name']==='')throw new HttpException(422,'Ortsbezeichnung ist erforderlich.');
        $saved=(new MasterDataRepository())->savePlace($id,(int)active_location_id(),$data);
        $this->audit('place_saved',$saved,$data,$request);return $this->back('Ort wurde gespeichert.');
    }

    public function saveMeasure(Request $request): Response
    {
        $id=$this->id($request);$data=['name'=>trim((string)$request->post('name')),'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0];
        if($data['name']==='')throw new HttpException(422,'Maßnahme ist erforderlich.');
        $saved=(new MasterDataRepository())->saveMeasure($id,(int)active_location_id(),$data);
        $this->audit('measure_saved',$saved,$data,$request);return $this->back('Maßnahme wurde gespeichert.');
    }

    public function savePersonRole(Request $request): Response
    {
        $id=$this->id($request);$data=['name'=>trim((string)$request->post('name')),'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0];
        if($data['name']==='')throw new HttpException(422,'Personenrolle ist erforderlich.');
        $saved=(new MasterDataRepository())->savePersonRole($id,(int)active_location_id(),$data);
        $this->audit('person_role_saved',$saved,$data,$request);return $this->back('Personenrolle wurde gespeichert.');
    }

    public function saveAutomaticRule(Request $request): Response
    {
        $eventCode=trim((string)$request->post('event_code',''));
        $enabled=(bool)$request->post('enabled');
        $repo=new DutybookAutomaticRuleRepository();
        if(!array_key_exists($eventCode,$repo->events()))throw new HttpException(422,'Unbekannter Typ für automatische Dienstbucheinträge.');
        $repo->save((int)active_location_id(),$eventCode,$enabled,(int)Auth::id());
        (new AuditService())->log(
            'dutybook_automatic_rule_saved','masterdata',$eventCode,null,
            ['enabled'=>$enabled],['event_code'=>$eventCode],$request
        );
        return $this->back('Regel für automatische Dienstbucheinträge wurde gespeichert.');
    }

    public function saveExternal(Request $request): Response
    {
        $id=$this->id($request);$data=['organization_type'=>trim((string)$request->post('organization_type')),'name'=>trim((string)$request->post('name')),
            'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0];
        if($data['organization_type']===''||$data['name']==='')throw new HttpException(422,'Typ und Bezeichnung sind erforderlich.');
        $saved=(new MasterDataRepository())->saveExternalOrganization($id,(int)active_location_id(),$data);
        $this->audit('external_organization_saved',$saved,$data,$request);return $this->back('Externe Stelle wurde gespeichert.');
    }

    private function visibilityJson(Request $request,string $module,int $definitionId,?int $currentId,int $sortOrder): ?string
    {
        $fieldId=(int)$request->post('visibility_field_id',0);
        if($fieldId<=0)return null;
        $value=trim((string)$request->post('visibility_value',''));
        if($value==='')throw new HttpException(422,'Für die Sichtbarkeitsbedingung ist ein Vergleichswert erforderlich.');
        $fields=(new MasterDataRepository())->dynamicFields($module,$definitionId,false);$target=null;
        foreach($fields as $field)if((int)$field['id']===$fieldId){$target=$field;break;}
        if(!$target||($currentId!==null&&$fieldId===$currentId))throw new HttpException(422,'Ungültiges Steuerfeld für die Sichtbarkeitsbedingung.');
        if((int)$target['sort_order']>=$sortOrder)throw new HttpException(422,'Das Steuerfeld muss in der Sortierung vor dem abhängigen Feld liegen.');
        return json_encode(['field_id'=>$fieldId,'value'=>$value],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }

    private function id(Request $request): ?int{$id=(int)$request->post('id',0);return $id>0?$id:null;}
    private function time(mixed $value): string{$v=trim((string)$value);if(!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$v))throw new HttpException(422,'Ungültige Uhrzeit.');return strlen($v)===5?$v.':00':$v;}
    private function audit(string $action,int $id,array $data,Request $request): void{(new AuditService())->log($action,'masterdata',(string)$id,null,$data,[],$request);}
    private function back(string $message): Response{flash('success',$message);return Response::redirect(url('admin/dutybook'));}
}

<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\MasterDataRepository;
use WKS\Repositories\SpecialReportRepository;
use WKS\Services\AuditService;

final class SpecialReportConfigController
{
    public function index(Request $request): Response
    {
        $locationId=(int)active_location_id();$repo=new SpecialReportRepository();$types=$repo->types($locationId,false);$fields=[];
        foreach($types as $type)foreach((new MasterDataRepository())->dynamicFields('special_report_type',(int)$type['id'],false) as $f){$f['type_name']=$type['name'];$fields[]=$f;}
        return View::render('admin/special-reports/index',compact('types','fields'));
    }

    public function saveType(Request $request): Response
    {
        $id=($x=(int)$request->post('id',0))>0?$x:null;$name=trim((string)$request->post('name'));$code=trim((string)$request->post('code'));
        if($name===''||$code==='')throw new HttpException(422,'Name und Schlüssel sind erforderlich.');
        $data=['name'=>$name,'code'=>preg_replace('/[^a-z0-9_]/','_',strtolower($code)),'sort_order'=>(int)$request->post('sort_order',0),'active'=>$request->post('active')?1:0,'force_section_enabled'=>$request->post('force_section_enabled')?1:0];
        $saved=(new SpecialReportRepository())->saveType($id,(int)active_location_id(),$data,(int)Auth::id());
        (new AuditService())->log('special_report_type_saved','masterdata',(string)$saved,null,$data,[],$request);flash('success','Sonderbericht-Einsatzart wurde gespeichert.');return Response::redirect(url('admin/special-reports'));
    }

    public function saveField(Request $request): Response
    {
        $id=($x=(int)$request->post('id',0))>0?$x:null;$type=(string)$request->post('field_type');$allowed=['text','textarea','number','date','time','datetime','select','multiselect','checkbox','yesno'];if(!in_array($type,$allowed,true))throw new HttpException(422,'Ungültiger Feldtyp.');
        $options=array_values(array_filter(array_map('trim',preg_split('/\R/u',(string)$request->post('options',''))?:[])));
        $definitionId=(int)$request->post('definition_id');$sortOrder=(int)$request->post('sort_order',0);
        $data=['definition_id'=>$definitionId,'field_key'=>preg_replace('/[^a-z0-9_]/','_',strtolower(trim((string)$request->post('field_key')))),'label'=>trim((string)$request->post('label')),'section_name'=>trim((string)$request->post('section_name',''))?:'Zusatzangaben','field_type'=>$type,'required'=>$request->post('required')?1:0,'sort_order'=>$sortOrder,'options_json'=>$options?json_encode($options,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,'visibility_json'=>$this->visibilityJson($request,'special_report_type',$definitionId,$id,$sortOrder),'active'=>$request->post('active')?1:0];
        if($data['definition_id']<1||$data['field_key']===''||$data['label']==='')throw new HttpException(422,'Einsatzart, Feldschlüssel und Bezeichnung sind erforderlich.');
        if(!(new SpecialReportRepository())->type($data['definition_id'],(int)active_location_id()))throw new HttpException(422,'Ungültige Einsatzart.');
        $saved=(new MasterDataRepository())->saveDynamicFieldForModule($id,'special_report_type',$data,(int)Auth::id());
        (new AuditService())->log('special_report_dynamic_field_saved','masterdata',(string)$saved,null,$data,[],$request);flash('success','Zusatzfeld wurde gespeichert.');return Response::redirect(url('admin/special-reports'));
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

}

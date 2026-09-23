<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Repositories\MasterDataRepository;

final class DynamicFormService
{
    private const TYPES = ['text','textarea','number','date','time','datetime','select','multiselect','checkbox','yesno'];

    public function validateDutybookValues(int $eventTypeId,array $input): array
    {
        return $this->validate('dutybook_event',$eventTypeId,$input);
    }

    public function validateSpecialReportValues(int $reportTypeId,array $input): array
    {
        return $this->validate('special_report_type',$reportTypeId,$input);
    }

    private function validate(string $module,int $definitionId,array $input): array
    {
        $fields=(new MasterDataRepository())->dynamicFields($module,$definitionId);
        $normalized=[];$types=[];$errors=[];

        foreach($fields as $field){
            $id=(int)$field['id'];$type=(string)$field['field_type'];$types[$id]=$type;
            if(!in_array($type,self::TYPES,true)){
                $errors[]='Unbekannter Feldtyp bei „'.$field['label'].'“.';
                continue;
            }
            $normalized[$id]=$this->normalize($type,$input[$id]??null);
        }

        $values=[];
        foreach($fields as $field){
            $id=(int)$field['id'];$type=$types[$id]??'';
            if(!in_array($type,self::TYPES,true))continue;
            if(!$this->isVisible($field,$normalized))continue;

            $value=$normalized[$id]??null;
            $empty=$type==='checkbox' ? $value!==true : ($value===''||$value===null||$value===[]);
            if((bool)$field['required']&&$empty){
                $errors[]='Das Zusatzfeld „'.$field['label'].'“ ist erforderlich.';
                continue;
            }

            if(!$empty && in_array($type,['select','multiselect'],true)){
                $allowed=array_map('strval',(array)$field['options']);
                $check=$type==='multiselect'?$value:[$value];
                foreach($check as $selected){
                    if(!in_array((string)$selected,$allowed,true)){
                        $errors[]='Ungültige Auswahl im Feld „'.$field['label'].'“.';
                        continue 2;
                    }
                }
            }

            if(!$empty && $type==='number' && !is_numeric((string)$value)){
                $errors[]='Das Zusatzfeld „'.$field['label'].'“ muss eine Zahl enthalten.';
                continue;
            }

            $values[$id]=$value;
        }

        return ['values'=>$values,'errors'=>$errors,'fields'=>$fields];
    }

    private function normalize(string $type,mixed $raw): mixed
    {
        if($type==='multiselect'){
            return array_values(array_filter(array_map('strval',(array)$raw),static fn(string $v):bool=>$v!==''));
        }
        if($type==='checkbox')return (bool)$raw;
        return is_array($raw)?'':trim((string)($raw??''));
    }

    private function isVisible(array $field,array $values): bool
    {
        $visibility=(array)($field['visibility']??[]);
        $fieldId=(int)($visibility['field_id']??0);
        if($fieldId<=0)return true;
        $expected=(string)($visibility['value']??'');
        $actual=$values[$fieldId]??null;
        if(is_array($actual))return in_array($expected,array_map('strval',$actual),true);
        if(is_bool($actual))$actual=$actual?'1':'0';
        return (string)($actual??'')===$expected;
    }
}

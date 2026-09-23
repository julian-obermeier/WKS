<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Repositories\MasterDataRepository;

final class DynamicFormService
{
    private const TYPES = ['text','textarea','number','date','time','datetime','select','multiselect','checkbox','yesno'];

    public function validateDutybookValues(int $eventTypeId,array $input): array
    {
        $fields=(new MasterDataRepository())->dynamicFields('dutybook_event',$eventTypeId);
        $values=[];
        $errors=[];

        foreach($fields as $field){
            $id=(int)$field['id'];
            $raw=$input[$id]??null;
            $type=(string)$field['field_type'];

            if(!in_array($type,self::TYPES,true)){
                $errors[]='Unbekannter Feldtyp bei „'.$field['label'].'“.';
                continue;
            }

            if($type==='multiselect'){
                $value=array_values(array_filter(array_map('strval',(array)$raw),static fn(string $v):bool=>$v!==''));
            } elseif($type==='checkbox'){
                $value=$raw ? true : false;
            } else {
                $value=is_array($raw)?'':trim((string)($raw??''));
            }

            $empty=($value===''||$value===null||$value===[]);
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
}

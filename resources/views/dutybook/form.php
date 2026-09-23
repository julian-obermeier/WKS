<?php
$editing=is_array($entry);$title=$editing?'Dienstbucheintrag bearbeiten':'Dienstbucheintrag erstellen';
$selectedEvent=(int)old('event_type_id',$entry['event_type_id']??0);
$oldDynamic=(array)old('dynamic',[]);
$selectedStaff=array_map('intval',(array)old('staff_ids',$editing?array_column($entry['staff'],'id'):[]));
$selectedMeasures=array_map('intval',(array)old('measure_ids',$editing?array_column($entry['measures'],'id'):[]));
$people=(array)old('people',$editing?$entry['people']:[]);
$external=(array)old('external',$editing?$entry['external']:[]);
?>
<div class="page-header split"><div><p class="eyebrow">Dienstbuch</p><h1><?= e($title) ?></h1><p>Strukturierte Erfassung mit Freitext, Beteiligten, Maßnahmen und Anlagen.</p></div><a class="button ghost" href="<?= e($editing?url('dutybook/'.$entry['id']):url('dutybook')) ?>">Zurück</a></div>
<?php if(!empty($autosave)): ?><div class="notice info"><strong>Automatischer Entwurf gefunden.</strong> Gespeichert <?= e(format_datetime($autosave['updated_at'])) ?>. <a href="<?= e(($editing?url('dutybook/'.$entry['id'].'/edit'):url('dutybook/create')).'?restore_autosave=1') ?>">Wiederherstellen</a><form method="post" action="<?= e(url('autosave/discard')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="module" value="dutybook"><input type="hidden" name="context_key" value="<?= e($autosaveContext) ?>"><input type="hidden" name="redirect_to" value="<?= e($editing?url('dutybook/'.$entry['id'].'/edit'):url('dutybook/create')) ?>"><button class="button ghost compact" type="submit">Verwerfen</button></form></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e($editing?url('dutybook/'.$entry['id']):url('dutybook')) ?>" class="panel form-grid" data-unsaved-warning data-autosave data-autosave-module="dutybook" data-autosave-context="<?= e($autosaveContext) ?>" data-autosave-url="<?= e(url('autosave')) ?>">
<?= csrf_field() ?>
<label>Datum/Uhrzeit*<input type="datetime-local" name="occurred_at" required value="<?= e(str_replace(' ','T',substr((string)old('occurred_at',$entry['occurred_at']??date('Y-m-d H:i:s')),0,16))) ?>"></label>
<label>Schicht*<select name="shift_id" required><?php foreach($shifts as $shift):?><option value="<?= (int)$shift['id'] ?>" <?= (int)old('shift_id',$entry['shift_id']??$current['shift_id']??0)===(int)$shift['id']?'selected':'' ?>><?= e($shift['name']) ?></option><?php endforeach;?></select></label>
<label>Ereignisart*<select name="event_type_id" required data-event-type-select><option value="">Bitte wählen …</option><?php foreach($eventTypes as $type):?><option value="<?= (int)$type['id'] ?>" <?= $selectedEvent===(int)$type['id']?'selected':'' ?>><?= e($type['category_name'].' · '.$type['name']) ?></option><?php endforeach;?></select></label>
<label>Status*<select name="status"><?php foreach(['open'=>'Offen','in_progress'=>'In Bearbeitung','done'=>'Erledigt','handover'=>'Zur Übergabe'] as $v=>$label):?><option value="<?= e($v) ?>" <?= old('status',$entry['status']??'open')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach;?></select></label>
<label>Beginn Ereignis<input type="datetime-local" name="event_started_at" value="<?= e(($v=old('event_started_at',$entry['event_started_at']??''))?str_replace(' ','T',substr((string)$v,0,16)):'') ?>"></label>
<label>Ende Ereignis<input type="datetime-local" name="event_ended_at" value="<?= e(($v=old('event_ended_at',$entry['event_ended_at']??''))?str_replace(' ','T',substr((string)$v,0,16)):'') ?>"></label>
<label>Ort<select name="place_id"><option value="">Keine feste Auswahl</option><?php foreach($places as $place):?><option value="<?= (int)$place['id'] ?>" <?= (int)old('place_id',$entry['place_id']??0)===(int)$place['id']?'selected':'' ?>><?= e(ucfirst($place['place_type']).' · '.($place['parent_name']?$place['parent_name'].' / ':'').$place['name']) ?></option><?php endforeach;?></select></label>
<label>Genaue Ortsbeschreibung<input name="place_free_text" maxlength="255" value="<?= e(old('place_free_text',$entry['place_free_text']??'')) ?>" placeholder="z. B. vor Aufzug 3"></label>

<div class="span-2">
<?php foreach($dynamicByEvent as $eventId=>$fields):?>
<div class="form-grid dynamic-group" data-dynamic-event="<?= (int)$eventId ?>">
<?php foreach($fields as $field):
$value=$oldDynamic[$field['id']]??($editing&&isset($entry['dynamic_values'][$field['id']])?$entry['dynamic_values'][$field['id']]['value']:'');
$name='dynamic['.(int)$field['id'].']';$vis=(array)($field['visibility']??[]);?>
<label data-dynamic-field="<?= (int)$field['id'] ?>"<?= !empty($vis['field_id'])?' data-dynamic-condition-field="'.(int)$vis['field_id'].'" data-dynamic-condition-value="'.e((string)($vis['value']??'')).'"':'' ?>><?= e($field['label']) ?><?= $field['required']?'*':'' ?>
<?php if($field['field_type']==='textarea'):?><textarea name="<?= e($name) ?>" <?= $field['required']?'required':'' ?>><?= e($value) ?></textarea>
<?php elseif($field['field_type']==='select'):?><select name="<?= e($name) ?>" <?= $field['required']?'required':'' ?>><option value="">Bitte wählen …</option><?php foreach($field['options'] as $opt):?><option value="<?= e($opt) ?>" <?= (string)$value===(string)$opt?'selected':'' ?>><?= e($opt) ?></option><?php endforeach;?></select>
<?php elseif($field['field_type']==='multiselect'):?><select name="<?= e($name) ?>[]" multiple <?= $field['required']?'required':'' ?>><?php foreach($field['options'] as $opt):?><option value="<?= e($opt) ?>" <?= in_array((string)$opt,(array)$value,true)?'selected':'' ?>><?= e($opt) ?></option><?php endforeach;?></select>
<?php elseif($field['field_type']==='checkbox'):?><span class="check-row"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= $value?'checked':'' ?>><span>Ja</span></span>
<?php elseif($field['field_type']==='yesno'):?><select name="<?= e($name) ?>" <?= $field['required']?'required':'' ?>><option value="">Bitte wählen …</option><option value="ja" <?= $value==='ja'?'selected':'' ?>>Ja</option><option value="nein" <?= $value==='nein'?'selected':'' ?>>Nein</option></select>
<?php else:?><input type="<?= e(match($field['field_type']){'number'=>'number','date'=>'date','time'=>'time','datetime'=>'datetime-local',default=>'text'}) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $field['required']?'required':'' ?>>
<?php endif;?></label>
<?php endforeach;?>
</div>
<?php endforeach;?>
</div>

<label class="span-2">Sachverhalt*<textarea name="facts" rows="8" required><?= e(old('facts',$entry['facts']??'')) ?></textarea></label>

<fieldset class="span-2 checkbox-panel"><legend>Beteiligte Mitarbeiter</legend><div class="checkbox-stack"><?php foreach($users as $u):?><label class="check-row"><input type="checkbox" name="staff_ids[]" value="<?= (int)$u['id'] ?>" <?= in_array((int)$u['id'],$selectedStaff,true)?'checked':'' ?>><span><?= e($u['last_name'].', '.$u['first_name']) ?></span></label><?php endforeach;?></div></fieldset>

<div class="span-2"><div class="section-title">Betroffene / beteiligte Personen</div><div id="people-repeat" class="repeat-list">
<?php foreach($people as $i=>$person):?><div class="repeat-row people" data-repeat-row>
<label>Rolle<select name="people[<?= $i ?>][role_id]"><option value="">–</option><?php foreach($personRoles as $role):?><option value="<?= (int)$role['id'] ?>" <?= (int)($person['role_id']??0)===(int)$role['id']?'selected':'' ?>><?= e($role['name']) ?></option><?php endforeach;?></select></label>
<label>Vorname<input name="people[<?= $i ?>][first_name]" value="<?= e($person['first_name']??'') ?>"></label><label>Nachname<input name="people[<?= $i ?>][last_name]" value="<?= e($person['last_name']??'') ?>"></label>
<label>Geburtsdatum<input type="date" name="people[<?= $i ?>][birth_date]" value="<?= e($person['birth_date']??'') ?>"></label>
<label>Personentyp<input name="people[<?= $i ?>][person_type]" value="<?= e($person['person_type']??'') ?>"></label><label>Station/Bereich<input name="people[<?= $i ?>][area]" value="<?= e($person['area']??'') ?>"></label><label>Interne Kennung<input name="people[<?= $i ?>][internal_identifier]" value="<?= e($person['internal_identifier']??'') ?>"></label>
<button type="button" class="button ghost compact" data-repeat-remove>Entfernen</button></div><?php endforeach;?>
<template><div class="repeat-row people" data-repeat-row><label>Rolle<select name="people[__INDEX__][role_id]"><option value="">–</option><?php foreach($personRoles as $role):?><option value="<?= (int)$role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach;?></select></label><label>Vorname<input name="people[__INDEX__][first_name]"></label><label>Nachname<input name="people[__INDEX__][last_name]"></label><label>Geburtsdatum<input type="date" name="people[__INDEX__][birth_date]"></label><label>Personentyp<input name="people[__INDEX__][person_type]"></label><label>Station/Bereich<input name="people[__INDEX__][area]"></label><label>Interne Kennung<input name="people[__INDEX__][internal_identifier]"></label><button type="button" class="button ghost compact" data-repeat-remove>Entfernen</button></div></template>
</div><button type="button" class="button secondary" data-repeat-add="#people-repeat">+ Person hinzufügen</button></div>

<fieldset class="span-2 checkbox-panel"><legend>Strukturierte Maßnahmen</legend><div class="checkbox-stack"><?php foreach($measures as $measure):?><label class="check-row"><input type="checkbox" name="measure_ids[]" value="<?= (int)$measure['id'] ?>" <?= in_array((int)$measure['id'],$selectedMeasures,true)?'checked':'' ?>><span><?= e($measure['name']) ?></span></label><?php endforeach;?></div></fieldset>
<label class="span-2">Maßnahmen – Freitext<textarea name="measures_text" rows="4"><?= e(old('measures_text',$entry['measures_text']??'')) ?></textarea></label>
<label class="span-2">Ergebnis<textarea name="result_text" rows="4"><?= e(old('result_text',$entry['result_text']??'')) ?></textarea></label>

<div class="span-2"><div class="section-title">Externe Stellen</div><div id="external-repeat" class="repeat-list">
<?php foreach($external as $i=>$x):?><div class="repeat-row external" data-repeat-row><label>Organisation<select name="external[<?= $i ?>][organization_id]"><option value="">Freitext</option><?php foreach($externalOrganizations as $o):?><option value="<?= (int)$o['id'] ?>" <?= (int)($x['organization_id']??0)===(int)$o['id']?'selected':'' ?>><?= e($o['organization_type'].' · '.$o['name']) ?></option><?php endforeach;?></select></label><label>Organisation Freitext<input name="external[<?= $i ?>][organization_name]" value="<?= e($x['organization_name']??'') ?>"></label><label>Ansprechpartner<input name="external[<?= $i ?>][contact_name]" value="<?= e($x['contact_name']??'') ?>"></label><label>Verständigt<input type="datetime-local" name="external[<?= $i ?>][notified_at]" value="<?= e(!empty($x['notified_at'])?str_replace(' ','T',substr($x['notified_at'],0,16)):'') ?>"></label><label>Vorgangs-/Einsatznr.<input name="external[<?= $i ?>][reference_number]" value="<?= e($x['reference_number']??'') ?>"></label><label>Rückmeldung<input name="external[<?= $i ?>][feedback]" value="<?= e($x['feedback']??'') ?>"></label><button type="button" class="button ghost compact" data-repeat-remove>Entfernen</button></div><?php endforeach;?>
<template><div class="repeat-row external" data-repeat-row><label>Organisation<select name="external[__INDEX__][organization_id]"><option value="">Freitext</option><?php foreach($externalOrganizations as $o):?><option value="<?= (int)$o['id'] ?>"><?= e($o['organization_type'].' · '.$o['name']) ?></option><?php endforeach;?></select></label><label>Organisation Freitext<input name="external[__INDEX__][organization_name]"></label><label>Ansprechpartner<input name="external[__INDEX__][contact_name]"></label><label>Verständigt<input type="datetime-local" name="external[__INDEX__][notified_at]"></label><label>Vorgangs-/Einsatznr.<input name="external[__INDEX__][reference_number]"></label><label>Rückmeldung<input name="external[__INDEX__][feedback]"></label><button type="button" class="button ghost compact" data-repeat-remove>Entfernen</button></div></template>
</div><button type="button" class="button secondary" data-repeat-add="#external-repeat">+ Externe Stelle hinzufügen</button></div>

<label class="span-2">Anhänge<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.docx"><small>Bilder, PDF oder DOCX. Serverseitige MIME- und Größenprüfung.</small></label>
<div class="span-2 form-actions"><button class="button primary" type="submit"><?= $editing?'Änderungen speichern':'Eintrag speichern' ?></button></div>
</form>

<?php
$title=$correctionParent?'Korrekturfolge einlagern':'Wertsache einlagern';
$types=['cassette'=>'Kassette','bag'=>'Tüte','sack'=>'Sack','case'=>'Koffer','pocket'=>'Tasche','other'=>'Sonstiges'];
$handover=['patient'=>'Patient','relative'=>'Angehöriger','care'=>'Pflege','police'=>'Polizei','other'=>'Sonstige'];
$containers=(array)old('containers',[]);
if($containers===[])$containers=[['container_type'=>'cassette']];
$prefill=$correctionParent??[];
?>
<div class="page-header split"><div><p class="eyebrow">Wertsachen</p><h1><?= e($title) ?></h1><p><?= $correctionParent?'Neuer, eigenständiger Verwahrvorgang mit neuer Verwahrnummer. Der alte Vorgang bleibt abgeschlossen.':'Keine einzelnen Inhalte erfassen – dokumentiert werden Person, Behältnisse, Lagerorte, Kassetten und Siegel.' ?></p></div><a class="button ghost" href="<?= e(url($correctionParent?'valuables/'.$correctionParent['id']:'valuables')) ?>">Zurück</a></div>
<?php if($correctionParent):?><div class="notice warning"><strong>Korrekturfolge:</strong> Ausgangsvorgang Verwahrnr. <?= e(str_pad((string)$correctionParent['custody_number'],4,'0',STR_PAD_LEFT)) ?> bleibt unverändert vollständig ausgelagert.</div><?php endif;?>
<?php if($correctionParent):?><div class="panel"><label>Korrekturgrund*<textarea name="correction_reason" rows="4" required><?= e(old('correction_reason')) ?></textarea><small>Dieser Grund wird als dokumentierter Nachtrag am ursprünglichen, weiterhin abgeschlossenen Vorgang gespeichert.</small></label></div><?php endif;?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('valuables')) ?>" class="panel form-grid" data-unsaved-warning>
<?= csrf_field() ?><input type="hidden" name="correction_parent_id" value="<?= (int)($correctionParent['id']??0) ?>">
<label>Vorname*<input name="first_name" required value="<?= e(old('first_name',$prefill['first_name']??'')) ?>"></label>
<label>Nachname*<input name="last_name" required value="<?= e(old('last_name',$prefill['last_name']??'')) ?>"></label>
<label>Geburtsdatum*<input type="date" name="birth_date" required value="<?= e(old('birth_date',$prefill['birth_date']??'')) ?>"></label>
<label>Interne Kennung<input name="internal_identifier" value="<?= e(old('internal_identifier',$prefill['internal_identifier']??'')) ?>" placeholder="z. B. Patienten-/Fallnummer"></label>
<label>Einlagerung Datum/Uhrzeit*<input type="datetime-local" name="stored_at" required value="<?= e(str_replace(' ','T',substr((string)old('stored_at',date('Y-m-d H:i:s')),0,16))) ?>"></label>
<label>Übergeben durch – Typ*<select name="handed_over_by_type" required><?php foreach($handover as $v=>$l):?><option value="<?= e($v) ?>" <?= old('handed_over_by_type')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach;?></select></label>
<label>Übergeben durch – Name*<input name="handed_over_by_name" required value="<?= e(old('handed_over_by_name')) ?>"></label>
<label>Organisation / Bereich<input name="handed_over_by_organization" value="<?= e(old('handed_over_by_organization')) ?>"></label>
<label class="span-2">Bemerkung Übergabe<input name="handed_over_by_note" value="<?= e(old('handed_over_by_note')) ?>"></label>

<div class="span-2"><div class="section-title">Behältnisse</div><div class="repeat-list" id="valuable-containers">
<?php foreach($containers as $i=>$c):?><div class="panel" data-repeat-row data-container-row><div class="form-grid">
<label>Typ*<select name="containers[<?= $i ?>][container_type]" required data-container-type><?php foreach($types as $v=>$l):?><option value="<?= e($v) ?>" <?= ($c['container_type']??'cassette')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach;?></select></label>
<label>Kurze Beschreibung<input name="containers[<?= $i ?>][description]" value="<?= e($c['description']??'') ?>"></label>
<label>Lagerort*<select name="containers[<?= $i ?>][storage_location_id]" required><option value="">Bitte wählen …</option><?php foreach($storage as $s):?><option value="<?= (int)$s['id'] ?>" <?= (int)($c['storage_location_id']??0)===(int)$s['id']?'selected':'' ?>><?= e($s['label']) ?></option><?php endforeach;?></select></label>
<div class="span-2 form-grid" data-cassette-fields>
<label>Kassette*<select name="containers[<?= $i ?>][cassette_id]" data-cassette-required="1"><option value="">Bitte wählen …</option><?php foreach($cassettes as $cass):?><option value="<?= (int)$cass['id'] ?>" <?= $cass['valuables_record_id']!==null?'disabled':'' ?> <?= (int)($c['cassette_id']??0)===(int)$cass['id']?'selected':'' ?>>Kassette <?= (int)$cass['cassette_number'] ?><?= $cass['valuables_record_id']!==null?' – belegt':'' ?></option><?php endforeach;?></select></label>
<label>Siegel links*<input name="containers[<?= $i ?>][seal_left]" inputmode="numeric" pattern="[0-9]+" data-cassette-required="1" data-seal-input data-seal-endpoint="<?= e(url('valuables/check-seal')) ?>" value="<?= e($c['seal_left']??'') ?>"><small data-seal-result class="field-hint"></small></label>
<label>Siegel rechts*<input name="containers[<?= $i ?>][seal_right]" inputmode="numeric" pattern="[0-9]+" data-cassette-required="1" data-seal-input data-seal-endpoint="<?= e(url('valuables/check-seal')) ?>" value="<?= e($c['seal_right']??'') ?>"><small data-seal-result class="field-hint"></small></label>
</div></div>
<button type="button" class="button ghost compact" data-repeat-remove>Behältnis entfernen</button></div><?php endforeach;?>
<template><div class="panel" data-repeat-row data-container-row><div class="form-grid"><label>Typ*<select name="containers[__INDEX__][container_type]" required data-container-type><?php foreach($types as $v=>$l):?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach;?></select></label><label>Kurze Beschreibung<input name="containers[__INDEX__][description]"></label><label>Lagerort*<select name="containers[__INDEX__][storage_location_id]" required><option value="">Bitte wählen …</option><?php foreach($storage as $s):?><option value="<?= (int)$s['id'] ?>"><?= e($s['label']) ?></option><?php endforeach;?></select></label><div class="span-2 form-grid" data-cassette-fields><label>Kassette*<select name="containers[__INDEX__][cassette_id]" data-cassette-required="1"><option value="">Bitte wählen …</option><?php foreach($cassettes as $cass):?><option value="<?= (int)$cass['id'] ?>" <?= $cass['valuables_record_id']!==null?'disabled':'' ?>>Kassette <?= (int)$cass['cassette_number'] ?><?= $cass['valuables_record_id']!==null?' – belegt':'' ?></option><?php endforeach;?></select></label><label>Siegel links*<input name="containers[__INDEX__][seal_left]" inputmode="numeric" pattern="[0-9]+" data-cassette-required="1" data-seal-input data-seal-endpoint="<?= e(url('valuables/check-seal')) ?>"><small data-seal-result class="field-hint"></small></label><label>Siegel rechts*<input name="containers[__INDEX__][seal_right]" inputmode="numeric" pattern="[0-9]+" data-cassette-required="1" data-seal-input data-seal-endpoint="<?= e(url('valuables/check-seal')) ?>"><small data-seal-result class="field-hint"></small></label></div></div><button type="button" class="button ghost compact" data-repeat-remove>Behältnis entfernen</button></div></template>
</div><button type="button" class="button secondary" data-repeat-add="#valuable-containers">+ Behältnis hinzufügen</button></div>

<label class="span-2">Bemerkung Einlagerung<textarea name="storage_note" rows="4"><?= e(old('storage_note')) ?></textarea></label>
<label class="span-2">Anhänge<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf"><small>Nur Bilder und PDF. Keine Inhaltsliste der Wertsachen erfassen.</small></label>
<div class="span-2 form-actions"><button class="button primary" type="submit">Einlagerung abschließen</button></div>
</form>

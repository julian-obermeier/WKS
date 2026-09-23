<?php $editing=is_array($record);$title=$editing?'Hausverbot bearbeiten':'Hausverbot anlegen'; ?>
<div class="page-header split"><div><p class="eyebrow">Hausverbote</p><h1><?= e($title) ?></h1><p>Keine Gültigkeitsdauer und kein Aktiv-/Abgelaufen-Status.</p></div><a class="button ghost" href="<?= e($editing?url('house-bans/'.$record['id']):url('house-bans')) ?>">Zurück</a></div>
<form method="post" enctype="multipart/form-data" action="<?= e($editing?url('house-bans/'.$record['id']):url('house-bans')) ?>" class="panel form-grid" data-unsaved-warning><?= csrf_field() ?>
<label>Datum*<input type="date" name="ban_date" required value="<?= e(old('ban_date',$record['ban_date']??date('Y-m-d'))) ?>"></label>
<label>Name*<input name="person_name" required value="<?= e(old('person_name',$record['person_name']??'')) ?>"></label>
<label class="span-2">Grund*<textarea name="reason" rows="7" required><?= e(old('reason',$record['reason']??'')) ?></textarea></label>
<label class="span-2">Anhänge<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf"><small>Bilder und PDF; mehrere Anhänge möglich.</small></label>
<div class="span-2 form-actions"><button class="button primary" type="submit"><?= $editing?'Änderungen speichern':'Hausverbot anlegen' ?></button></div></form>

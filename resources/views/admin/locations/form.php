<?php $editing = is_array($location); $title = $editing ? 'Standort bearbeiten' : 'Standort anlegen'; ?>
<div class="page-header split">
<div><p class="eyebrow">Administration · Standorte</p><h1><?= e($title) ?></h1><p>Mail bleibt während der Testphase deaktiviert.</p></div>
<a class="button ghost" href="<?= e(url('admin/locations')) ?>">Zurück</a>
</div>
<div class="panel">
<form method="post" action="<?= e($editing ? url('admin/locations/' . $location['id']) : url('admin/locations')) ?>" class="form-grid">
<?= csrf_field() ?>
<label>Name*<input name="name" required value="<?= e($location['name'] ?? '') ?>"></label>
<label>Kürzel*<input name="code" maxlength="30" required value="<?= e($location['code'] ?? '') ?>"></label>
<label class="span-2">Standort-Mailadresse*<input type="email" name="email_address" required value="<?= e($location['email_address'] ?? '') ?>"><small>Keine privaten Mitarbeiteradressen verwenden.</small></label>
<label>Mailmodus
<select name="mail_mode">
<option value="disabled" <?= ($location['mail_mode'] ?? 'disabled') === 'disabled' ? 'selected' : '' ?>>Deaktiviert</option>
<option value="test" <?= ($location['mail_mode'] ?? '') === 'test' ? 'selected' : '' ?>>Testmodus</option>
<option value="production" <?= ($location['mail_mode'] ?? '') === 'production' ? 'selected' : '' ?>>Produktiv</option>
</select>
</label>
<label>Testadresse<input type="email" name="mail_test_address" value="<?= e($location['mail_test_address'] ?? '') ?>"></label>
<label class="check-row span-2"><input type="checkbox" name="active" value="1" <?= !$editing || ($location['active'] ?? 1) ? 'checked' : '' ?>><span>Standort aktiv</span></label>
<div class="span-2 notice warning"><strong>Sicherheitsvorgabe:</strong> Der globale Mailversand ist im Testbetrieb abgeschaltet. Die Auswahl „Produktiv“ allein versendet noch keine E-Mails.</div>
<div class="span-2 form-actions"><button class="button primary" type="submit">Standort speichern</button></div>
</form>
</div>

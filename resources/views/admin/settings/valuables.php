<?php $title='Wertsachen-Einstellungen'; ?>
<div class="page-header split"><div><p class="eyebrow">Administration · Wertsachen</p><h1>Aufbewahrung & Langzeitverwahrung</h1><p>Schwellenwerte werden standortunabhängig als Systemeinstellung geführt.</p></div><a class="button ghost" href="<?= e(url('valuables')) ?>">Wertsachen</a></div>
<div class="panel narrow"><form method="post" action="<?= e(url('admin/settings/valuables')) ?>" class="stack-form"><?= csrf_field() ?>
<label>Langzeitverwahrung ab (Tage)<input type="number" min="1" max="3650" name="long_term_days" value="<?= (int)$settings['long_term_days'] ?>" required><small>Überschrittene Vorgänge werden visuell und in der eigenen Ansicht hervorgehoben.</small></label>
<label>Aufbewahrungsfrist (Tage)<input type="number" min="1" max="36500" name="retention_days" value="<?= (int)$settings['retention_days'] ?>" required><small>Nach Ablauf darf archiviert werden. Es erfolgt keine automatische endgültige Löschung.</small></label>
<button class="button primary" type="submit">Einstellungen speichern</button>
</form></div>

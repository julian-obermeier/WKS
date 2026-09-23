<?php $title = 'Standorte'; ?>
<div class="page-header split">
    <div><p class="eyebrow">Administration</p><h1>Standorte</h1><p>Standorttrennung, Standort-Mailadresse und Mailmodus.</p></div>
    <a class="button primary" href="<?= e(url('admin/locations/create')) ?>">+ Standort anlegen</a>
</div>
<div class="panel">
<div class="table-wrap">
<table>
<thead><tr><th>Standort</th><th>Kürzel</th><th>Standort-Mail</th><th>Mailmodus</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($locations as $location): ?>
<tr>
    <td><strong><?= e($location['name']) ?></strong></td>
    <td><?= e($location['code']) ?></td>
    <td><?= e($location['email_address']) ?></td>
    <td><span class="badge <?= $location['mail_mode'] === 'production' ? 'warning' : 'neutral' ?>"><?= e(match ($location['mail_mode']) { 'test' => 'Testmodus', 'production' => 'Produktiv', default => 'Deaktiviert' }) ?></span></td>
    <td><span class="badge <?= $location['active'] ? 'active' : 'disabled' ?>"><?= $location['active'] ? 'Aktiv' : 'Deaktiviert' ?></span></td>
    <td class="align-right"><a class="button ghost compact" href="<?= e(url('admin/locations/' . $location['id'] . '/edit')) ?>">Bearbeiten</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

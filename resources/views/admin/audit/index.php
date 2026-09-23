<?php $title = 'Audit-Log'; ?>
<div class="page-header"><div><p class="eyebrow">Administration</p><h1>Audit-Log</h1><p>Nachvollziehbare sicherheits- und fachrelevante Systemaktionen.</p></div></div>
<div class="panel">
<form method="get" action="<?= e(url('admin/audit')) ?>" class="filterbar multi">
<input name="module" placeholder="Modul" value="<?= e($filters['module']) ?>">
<input name="action" placeholder="Aktion" value="<?= e($filters['action']) ?>">
<select name="location_id"><option value="">Alle Standorte</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>" <?= (int) $filters['location_id'] === (int) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach; ?></select>
<button class="button secondary" type="submit">Filtern</button>
</form>
<div class="table-wrap">
<table>
<thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Modul</th><th>Datensatz</th><th>Standort</th></tr></thead>
<tbody>
<?php foreach ($logs['items'] as $log): ?>
<tr>
<td><?= e(format_datetime($log['occurred_at'])) ?></td>
<td><?= e($log['user_name'] ?? 'System / unbekannt') ?></td>
<td><code><?= e($log['action']) ?></code></td>
<td><?= e($log['module']) ?></td>
<td><?= e($log['record_id'] ?? '–') ?></td>
<td><?= e($log['location_name'] ?? '–') ?></td>
</tr>
<?php endforeach; ?>
<?php if ($logs['items'] === []): ?><tr><td colspan="6" class="empty">Keine Audit-Einträge gefunden.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="pagination"><span><?= (int) $logs['total'] ?> Einträge</span><span>Seite <?= (int) $logs['page'] ?> / <?= (int) $logs['pages'] ?></span></div>
</div>

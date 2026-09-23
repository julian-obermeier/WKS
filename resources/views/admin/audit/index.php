<?php $title = 'Audit-Log'; ?>
<div class="page-header split"><div><p class="eyebrow">Administration</p><h1>Audit-Log</h1><p>Nachvollziehbare sicherheits- und fachrelevante Systemaktionen.</p></div><?php if(can('system.audit.export')):$exportQuery=$canFilter?http_build_query(array_filter($filters,static fn($v)=>$v!==''&&$v!==0)):'';?><a class="button ghost" href="<?= e(url('admin/audit/export.csv'.($exportQuery?'?'.$exportQuery:''))) ?>">CSV exportieren</a><?php endif;?></div>
<div class="panel">
<?php if($canFilter):?><form method="get" action="<?= e(url('admin/audit')) ?>" class="filterbar multi">
<input name="module" placeholder="Modul" value="<?= e($filters['module']) ?>">
<input name="action" placeholder="Aktion" value="<?= e($filters['action']) ?>">
<input type="number" min="1" name="user_id" placeholder="Benutzer-ID" value="<?= $filters['user_id']?(int)$filters['user_id']:'' ?>">
<select name="location_id"><option value="">Alle Standorte</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>" <?= (int) $filters['location_id'] === (int) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach; ?></select>
<button class="button secondary" type="submit">Filtern</button>
</form><?php else:?><div class="notice info">Sie dürfen das Audit-Log anzeigen, besitzen aber kein separates Recht zum Filtern.</div><?php endif;?>
<div class="table-wrap">
<table>
<thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Modul</th><th>Datensatz</th><th>Standort</th><th>Details</th></tr></thead>
<tbody>
<?php foreach ($logs['items'] as $log): ?>
<tr>
<td><?= e(format_datetime($log['occurred_at'])) ?></td>
<td><?= e($log['user_name'] ?? 'System / unbekannt') ?></td>
<td><code><?= e($log['action']) ?></code></td>
<td><?= e($log['module']) ?></td>
<td><?= e($log['record_id'] ?? '–') ?></td>
<td><?= e($log['location_name'] ?? '–') ?></td>
<td><details><summary>Anzeigen</summary><div class="stack-form"><div><strong>Alter Wert</strong><pre style="white-space:pre-wrap"><?= e($log['old_value']??'–') ?></pre></div><div><strong>Neuer Wert</strong><pre style="white-space:pre-wrap"><?= e($log['new_value']??'–') ?></pre></div><div><strong>Metadaten</strong><pre style="white-space:pre-wrap"><?= e($log['metadata']??'–') ?></pre></div><small>IP: <?= e($log['ip_address']??'–') ?> · User-Agent: <?= e($log['user_agent']??'–') ?></small></div></details></td>
</tr>
<?php endforeach; ?>
<?php if ($logs['items'] === []): ?><tr><td colspan="7" class="empty">Keine Audit-Einträge gefunden.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="pagination"><span><?= (int) $logs['total'] ?> Einträge</span><span>Seite <?= (int) $logs['page'] ?> / <?= (int) $logs['pages'] ?></span></div>
</div>

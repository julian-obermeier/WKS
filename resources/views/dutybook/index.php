<?php $title='Dienstbuch'; ?>
<div class="page-header split">
<div><p class="eyebrow">Dienstbuch · <?= e($date) ?></p><h1>Tagesdienstbuch</h1><p>Alle Vorgänge des gewählten Arbeitstags am aktiven Standort.</p></div>
<?php if(can('dutybook.create')):?><a class="button primary" href="<?= e(url('dutybook/create')) ?>">+ Eintrag erfassen</a><?php endif;?>
</div>

<div class="toolbar">
<form method="get" action="<?= e(url('dutybook')) ?>" class="inline-actions">
<input type="date" name="date" value="<?= e($date) ?>"><button class="button secondary" type="submit">Tag öffnen</button>
</form>
<a class="button ghost" href="<?= e(url('dutybook/search')) ?>">Suche</a>
<?php if(can('dutybook.export')):?><a class="button ghost" href="<?= e(url('dutybook-export.csv?date='.$date)) ?>">CSV</a><a class="button ghost" target="_blank" href="<?= e(url('dutybook-print?date='.$date)) ?>">Druckansicht</a><?php endif;?>
</div>

<?php if($current): ?>
<div class="panel">
<div class="panel-header"><div><h2>Aktuelle Schicht: <?= e($current['shift_name']) ?></h2><p>Dienstbuchdatum <?= e($current['duty_date']) ?> · Dienst übernommen <?= e(format_datetime($current['duty_accepted_at'])) ?></p></div><span class="badge active">Dienst aktiv</span></div>
<div class="detail-grid">
<div class="detail-item"><small>Schicht</small><strong><?= e($current['shift_name']) ?> (<?= e($current['shift_code']) ?>)</strong></div>
<div class="detail-item"><small>Anwesende Mitarbeiter</small><strong><?= count($attendance) ?></strong></div>
<div class="detail-item"><small>Beginn</small><strong><?= e(format_datetime($current['started_at'])) ?></strong></div>
</div>
<div class="form-actions">
<a class="button secondary" href="<?= e(url('handover/'.$current['id'])) ?>">Schichtübergabe</a>
<form method="post" action="<?= e(url('shift/'.$current['id'].'/end')) ?>"><?= csrf_field() ?><button class="button danger" type="submit">Schicht beenden</button></form>
</div>
</div>
<?php else: ?>
<div class="panel">
<div class="panel-header"><div><h2>Dienst übernehmen</h2><p>Das System schlägt anhand der konfigurierten Zeiten eine Schicht vor. Die Zuordnung kann korrigiert werden.</p></div></div>
<form method="post" action="<?= e(url('shift/accept')) ?>" class="inline-actions">
<?= csrf_field() ?>
<select name="shift_id" required>
<option value="">Schicht wählen …</option>
<?php foreach($shifts as $shift):?><option value="<?= (int)$shift['id'] ?>" <?= $detected&&(int)$detected['id']===(int)$shift['id']?'selected':'' ?>><?= e($shift['name']) ?> · <?= e(substr($shift['start_time'],0,5)) ?>–<?= e(substr($shift['end_time'],0,5)) ?></option><?php endforeach;?>
</select>
<button class="button primary" type="submit">Dienst übernommen</button>
</form>
<?php if($detected):?><p class="muted">Automatisch erkannt: <?= e($detected['name']) ?> · zugeordnetes Dienstbuchdatum <?= e($detected['detected_duty_date']) ?></p><?php endif;?>
</div>
<?php endif;?>

<div class="panel">
<div class="panel-header"><div><h2>Einträge</h2><p><?= count($entries) ?> Vorgänge am <?= e((new DateTimeImmutable($date))->format('d.m.Y')) ?></p></div></div>
<div class="table-wrap"><table>
<thead><tr><th>Zeit</th><th>Schicht</th><th>Ereignis</th><th>Status</th><th>Sachverhalt</th><th>Anlagen</th><th></th></tr></thead>
<tbody>
<?php foreach($entries as $entry):?>
<tr>
<td><?= e(date('H:i',strtotime($entry['occurred_at']))) ?></td>
<td><?= e($entry['shift_name']??'–') ?></td>
<td><?php if($entry['is_automatic']):?><span class="badge neutral">Automatisch</span><?php else:?><strong><?= e($entry['event_type_name']??'–') ?></strong><small class="table-sub"><?= e($entry['category_name']??'') ?></small><?php endif;?></td>
<td><span class="badge status-<?= e($entry['status']) ?>"><?= e(match($entry['status']){'open'=>'Offen','in_progress'=>'In Bearbeitung','done'=>'Erledigt','handover'=>'Zur Übergabe',default=>$entry['status']}) ?></span></td>
<td><?= e(mb_strimwidth($entry['facts'],0,110,'…')) ?></td>
<td><?= (int)$entry['attachment_count'] ?></td>
<td class="align-right"><a class="button ghost compact" href="<?= e(url('dutybook/'.$entry['id'])) ?>">Öffnen</a></td>
</tr>
<?php endforeach;?>
<?php if($entries===[]):?><tr><td colspan="7" class="empty">Für diesen Arbeitstag sind noch keine Einträge vorhanden.</td></tr><?php endif;?>
</tbody></table></div>
</div>

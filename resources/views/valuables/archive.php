<?php
$title='Wertsachenarchiv';
$types=['cassette'=>'Kassette','bag'=>'Tüte','sack'=>'Sack','case'=>'Koffer','pocket'=>'Tasche','other'=>'Sonstiges'];
$query=$filters;unset($query['page']);
$exportQuery=$query+['status'=>'released'];
?>
<div class="page-header split">
    <div><p class="eyebrow">Wertsachen</p><h1>Archiv</h1><p>Vollständig ausgelagerte Vorgänge des aktiven Standorts. Aufbewahrungsfrist: <?= (int)$retentionDays ?> Tage ab Auslagerung.</p></div>
    <div class="inline-actions"><a class="button ghost" href="<?= e(url('valuables')) ?>">Aktuelle Verwahrung</a><?php if(can('valuables.export')):?><a class="button ghost" href="<?= e(url('valuables-export.csv?'.http_build_query($exportQuery))) ?>">CSV</a><?php endif;?></div>
</div>
<div class="notice info">Das Ablaufen der Aufbewahrungsfrist löscht keine Daten automatisch. Es kennzeichnet den Vorgang nur für eine manuelle datenschutz- und organisationsrechtliche Prüfung.</div>

<div class="panel">
<form method="get" action="<?= e(url('valuables/archive')) ?>" class="form-grid">
    <label>Auslagerung von<input type="date" name="released_from" value="<?= e($filters['released_from']) ?>"></label>
    <label>Auslagerung bis<input type="date" name="released_to" value="<?= e($filters['released_to']) ?>"></label>
    <label>Behältnistyp<select name="container_type"><option value="">Alle</option><?php foreach($types as $value=>$label):?><option value="<?= e($value) ?>" <?= $filters['container_type']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach;?></select></label>
    <label>Kassettennummer<input type="number" min="1" max="100" name="cassette_number" value="<?= $filters['cassette_number']?(int)$filters['cassette_number']:'' ?>"></label>
    <label>Lagerort<select name="storage_location_id"><option value="">Alle</option><?php foreach($storage as $row):?><option value="<?= (int)$row['id'] ?>" <?= (int)$filters['storage_location_id']===(int)$row['id']?'selected':'' ?>><?= e($row['label']) ?></option><?php endforeach;?></select></label>
    <div class="span-2 form-actions"><button class="button primary" type="submit">Filter anwenden</button><a class="button ghost" href="<?= e(url('valuables/archive')) ?>">Zurücksetzen</a></div>
</form>
</div>

<div class="panel"><div class="table-wrap"><table>
<thead><tr><th>Verwahrnr.</th><th>Person</th><th>Ausgelagert</th><th>Aufbewahrung bis</th><th>Frist</th><th>Behältnisse</th><th></th></tr></thead>
<tbody>
<?php foreach($result['items'] as $r): ?>
<tr>
<td><strong><?= e(str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT)) ?></strong></td>
<td><?= e($r['last_name'].', '.$r['first_name']) ?><small class="table-sub"><?= e((new DateTimeImmutable($r['birth_date']))->format('d.m.Y')) ?></small></td>
<td><?= e(format_datetime($r['released_at'])) ?></td>
<td><?= e((new DateTimeImmutable($r['retention_until']))->format('d.m.Y')) ?></td>
<td><?php if($r['retention_expired']):?><span class="badge warning">Prüfung fällig</span><?php else:?><span class="badge neutral"><?= (int)$r['retention_days_remaining'] ?> Tage</span><?php endif;?></td>
<td><?= (int)$r['container_count'] ?></td>
<td><a class="button ghost compact" href="<?= e(url('valuables/'.$r['id'])) ?>">Öffnen</a></td>
</tr>
<?php endforeach; ?>
<?php if($result['items']===[]):?><tr><td colspan="7" class="empty">Keine ausgelagerten Vorgänge für diese Filter.</td></tr><?php endif;?>
</tbody></table></div>
<div class="pagination">
    <span><?= (int)$result['total'] ?> Vorgänge · Seite <?= (int)$result['page'] ?> / <?= (int)$result['pages'] ?></span>
    <div class="inline-actions">
        <?php if($result['page']>1):$prev=$query+['page'=>$result['page']-1];?><a class="button ghost compact" href="<?= e(url('valuables/archive?'.http_build_query($prev))) ?>">← Zurück</a><?php endif;?>
        <?php if($result['page']<$result['pages']):$next=$query+['page'=>$result['page']+1];?><a class="button ghost compact" href="<?= e(url('valuables/archive?'.http_build_query($next))) ?>">Weiter →</a><?php endif;?>
    </div>
</div></div>

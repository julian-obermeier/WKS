<?php $title='Wertsachenarchiv'; ?>
<div class="page-header split">
    <div><p class="eyebrow">Wertsachen</p><h1>Archiv</h1><p>Vollständig ausgelagerte Vorgänge des aktiven Standorts. Aufbewahrungsfrist: <?= (int)$retentionDays ?> Tage ab Auslagerung.</p></div>
    <a class="button ghost" href="<?= e(url('valuables')) ?>">Aktuelle Verwahrung</a>
</div>
<div class="notice info">Das Ablaufen der Aufbewahrungsfrist löscht keine Daten automatisch. Es kennzeichnet den Vorgang nur für eine manuelle datenschutz- und organisationsrechtliche Prüfung.</div>
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
<?php if($result['items']===[]):?><tr><td colspan="7" class="empty">Noch keine vollständig ausgelagerten Vorgänge im Archiv.</td></tr><?php endif;?>
</tbody></table></div>
<div class="pagination"><span><?= (int)$result['total'] ?> Vorgänge</span><span>Seite <?= (int)$result['page'] ?> / <?= (int)$result['pages'] ?></span></div></div>

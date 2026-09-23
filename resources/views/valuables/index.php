<?php
$title='Wertsachen';
$types=['cassette'=>'Kassette','bag'=>'Tüte','sack'=>'Sack','case'=>'Koffer','pocket'=>'Tasche','other'=>'Sonstiges'];
$longTermDays=$service->longTermDays();
?>
<div class="page-header split">
<div><p class="eyebrow">Wertsachen</p><h1>Aktuelle Verwahrung</h1><p>Vollständige Verwahrvorgänge des aktiven Standorts.</p></div>
<?php if(can('valuables.store')):?><a class="button primary" href="<?= e(url('valuables/create')) ?>">+ Wertsache einlagern</a><?php endif;?>
</div>
<div class="metric-grid">
<div class="metric-card"><span class="metric-icon">▣</span><div><small>Aktuell eingelagert</small><strong><?= (int)$counts['stored_count'] ?></strong></div></div>
<div class="metric-card"><span class="metric-icon">▦</span><div><small>Belegte Kassetten</small><strong><?= (int)$counts['occupied_cassettes'] ?></strong></div></div>
<div class="metric-card"><span class="metric-icon">◷</span><div><small>Langzeitverwahrung</small><strong><?= (int)$counts['long_term_count'] ?></strong></div></div>
</div>

<div class="toolbar"><a class="button ghost" href="<?= e(url('valuables/search')) ?>">Suche</a><?php if(can('valuables.archive')):?><a class="button ghost" href="<?= e(url('valuables/archive')) ?>">Archiv</a><?php endif;?><a class="button ghost" href="<?= e(url('valuables/cassettes')) ?>">Kassetten 1–100</a><a class="button ghost" href="<?= e(url('valuables/long-term')) ?>">Langzeitverwahrung</a><?php if(can('valuables.export')):?><a class="button ghost" href="<?= e(url('valuables-export.csv?status=stored')) ?>">CSV</a><?php endif;?></div>

<div class="panel">
<form method="get" action="<?= e(url('valuables')) ?>" class="filterbar multi">
<select name="container_type"><option value="">Alle Behältnistypen</option><?php foreach($types as $v=>$l):?><option value="<?= e($v) ?>" <?= $filters['container_type']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach;?></select>
<select name="storage_location_id"><option value="">Alle Lagerorte</option><?php foreach($storage as $s):?><option value="<?= (int)$s['id'] ?>" <?= (int)$filters['storage_location_id']===(int)$s['id']?'selected':'' ?>><?= e($s['label']) ?></option><?php endforeach;?></select>
<button class="button secondary" type="submit">Filtern</button>
<?php if($filters['container_type']!==''||(int)$filters['storage_location_id']>0):?><a class="button ghost" href="<?= e(url('valuables')) ?>">Filter zurücksetzen</a><?php endif;?>
</form>

<div class="record-cards">
<?php foreach($records as $r):$isLong=(int)$r['storage_days']>=$longTermDays;?>
<a class="record-card<?= $isLong?' warning':'' ?>" href="<?= e(url('valuables/'.$r['id'])) ?>">
<span class="record-number">Verwahrnr. <?= e(str_pad((string)$r['custody_number'],4,'0',STR_PAD_LEFT)) ?><?php if($isLong):?> · <span class="badge warning">Langzeit · <?= (int)$r['storage_days'] ?> Tage</span><?php endif;?></span>
<strong><?= e($r['last_name'].', '.$r['first_name']) ?></strong>
<span class="record-meta">Geb. <?= e((new DateTimeImmutable($r['birth_date']))->format('d.m.Y')) ?> · <?= (int)$r['container_count'] ?> Behältnis(se)</span>
<span class="record-meta">Eingelagert <?= e(format_datetime($r['stored_at'])) ?></span>
</a>
<?php endforeach;?>
<?php if($records===[]):?><div class="empty">Keine aktuell eingelagerten Vorgänge für diese Filter.</div><?php endif;?>
</div>
</div>

<div class="panel">
<div class="panel-header"><div><h2>Lagerorte</h2><p>Anzahl aktuell eingelagerter Vorgänge und Behältnisse je Regal beziehungsweise Boden.</p></div></div>
<div class="table-wrap"><table>
<thead><tr><th>Lagerort</th><th>Vorgänge</th><th>Behältnisse</th><th></th></tr></thead>
<tbody>
<?php foreach($occupancy as $row):?>
<tr>
<td><strong><?= e($row['label']) ?></strong></td>
<td><?= (int)$row['record_count'] ?></td>
<td><?= (int)$row['container_count'] ?></td>
<td><a class="button ghost compact" href="<?= e(url('valuables?'.http_build_query(['storage_location_id'=>$row['id'],'container_type'=>$filters['container_type']?:null]))) ?>">Filtern</a></td>
</tr>
<?php endforeach;?>
</tbody>
</table></div>
</div>

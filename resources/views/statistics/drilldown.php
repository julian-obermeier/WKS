<?php $title='Statistik · Drill-down'; ?>
<div class="page-header split">
    <div>
        <p class="eyebrow">Statistik · Drill-down</p>
        <h1><?= e((new DateTimeImmutable($day))->format('d.m.Y')) ?></h1>
        <p>Datensätze des ausgewählten Diagrammpunkts unter Beibehaltung der Statistikfilter und Ihrer Modulrechte.</p>
    </div>
    <a class="button ghost" href="<?= e(url('statistics?'.http_build_query(['from'=>$day,'to'=>$day,'event_type_id'=>$eventTypeId?:null,'report_type_id'=>$reportTypeId?:null]))) ?>">Zur Statistik</a>
</div>
<div class="panel">
    <div class="timeline-list">
        <?php foreach($items as $item):?>
            <a class="timeline-item" href="<?= e($item['url']) ?>">
                <div><span class="badge neutral"><?= e($item['module']) ?></span> <strong><?= e($item['title']) ?></strong></div>
                <div class="prose"><?= e($item['subtitle']) ?></div>
                <small class="table-sub"><?= e(format_datetime($item['date'])) ?></small>
            </a>
        <?php endforeach;?>
        <?php if($items===[]):?><div class="empty">Keine für Sie sichtbaren Datensätze für diesen Tag und diese Filter.</div><?php endif;?>
    </div>
</div>

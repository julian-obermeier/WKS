<?php $title='Schichtübergabe'; ?>
<div class="page-header split"><div><p class="eyebrow">Dienstbuch · Schichtübergabe</p><h1><?= e($session['shift_name']) ?> übergeben</h1><p>Alle offenen Vorgänge müssen ausdrücklich übergeben werden.</p></div><a class="button ghost" href="<?= e(url('dutybook?date='.$session['duty_date'])) ?>">Zurück</a></div>
<?php if($handover&&$handover['status']==='completed'):?><div class="notice success"><strong>Übergabe abgeschlossen.</strong> Abgebend: <?= e($handover['outgoing_name']??'–') ?> · Übernehmend: <?= e($handover['incoming_name']??'–') ?></div><?php endif;?>
<div class="panel">
<form method="post" action="<?= e(url('handover/'.$session['id'])) ?>" class="stack-form">
<?= csrf_field() ?>
<label>Übernehmende Schicht*<select name="to_shift_id" required><option value="">Bitte wählen …</option><?php foreach($shifts as $shift):if((int)$shift['id']===(int)$session['shift_id'])continue;?><option value="<?= (int)$shift['id'] ?>" <?= $handover&&(int)$handover['to_shift_id']===(int)$shift['id']?'selected':'' ?>><?= e($shift['name']) ?></option><?php endforeach;?></select></label>
<label>Übergabehinweise<textarea name="notes" rows="4"><?= e($handover['notes']??'') ?></textarea></label>
<div class="section-title">Offene Vorgänge</div>
<?php foreach($openEntries as $entry):
$currentAssignment=null;if($handover)foreach($handover['entries'] as $he)if((int)$he['entry_id']===(int)$entry['id'])$currentAssignment=$he;?>
<div class="handover-entry"><div><strong><?= e($entry['event_type_name']??'Vorgang') ?> · <?= e(date('H:i',strtotime($entry['occurred_at']))) ?></strong><p><?= e(mb_strimwidth($entry['facts'],0,180,'…')) ?></p><input type="hidden" name="entries[<?= (int)$entry['id'] ?>][included]" value="1"></div><label>Übergabe an<select name="entries[<?= (int)$entry['id'] ?>][user_id]"><option value="0">Folgeschicht</option><?php foreach($users as $u):?><option value="<?= (int)$u['id'] ?>" <?= $currentAssignment&&(int)$currentAssignment['assigned_to_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['last_name'].', '.$u['first_name']) ?></option><?php endforeach;?></select></label></div>
<?php endforeach;?>
<?php if($openEntries===[]):?><p class="muted">Keine offenen Vorgänge. Die Übergabe selbst muss dennoch bestätigt werden.</p><?php endif;?>
<?php if(!$handover||$handover['status']!=='completed'):?><button class="button primary" type="submit">Übergabe vorbereiten / aktualisieren</button><?php endif;?>
</form>
</div>
<?php if($handover):?><div class="two-col">
<div class="panel"><h2>Abgebende Schicht</h2><p><?= $handover['outgoing_confirmed_at']?'Bestätigt '.e(format_datetime($handover['outgoing_confirmed_at'])).' durch '.e($handover['outgoing_name']??''):'Noch nicht bestätigt' ?></p><?php if(!$handover['outgoing_confirmed_at']&&$handover['status']!=='completed'):?><form method="post" action="<?= e(url('handover/'.$session['id'].'/confirm-outgoing')) ?>"><?= csrf_field() ?><button class="button primary" type="submit">Übergabe bestätigen</button></form><?php endif;?></div>
<div class="panel"><h2>Übernehmende Schicht</h2><p><?= $handover['incoming_confirmed_at']?'Bestätigt '.e(format_datetime($handover['incoming_confirmed_at'])).' durch '.e($handover['incoming_name']??''):'Noch nicht bestätigt' ?></p><?php if($handover['outgoing_confirmed_at']&&!$handover['incoming_confirmed_at']):?><form method="post" action="<?= e(url('handover/'.$session['id'].'/confirm-incoming')) ?>"><?= csrf_field() ?><button class="button primary" type="submit">Übernahme bestätigen</button></form><?php endif;?></div>
</div><?php endif;?>

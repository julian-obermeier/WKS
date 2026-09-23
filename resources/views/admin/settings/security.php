<?php $title = 'Sicherheitseinstellungen'; ?>
<div class="page-header"><div><p class="eyebrow">Administration · System</p><h1>Sicherheit</h1><p>Serverseitig durchgesetzte Session- und Loginregeln.</p></div></div>
<div class="panel narrow">
<form method="post" action="<?= e(url('admin/settings/security')) ?>" class="stack-form">
<?= csrf_field() ?>
<label>Automatischer Logout nach Inaktivität (Minuten)<input type="number" min="1" max="1440" name="inactivity_minutes" value="<?= (int) $settings['inactivity_minutes'] ?>" required></label>
<label>Maximale fehlgeschlagene Loginversuche<input type="number" min="1" max="20" name="login_max_attempts" value="<?= (int) $settings['login_max_attempts'] ?>" required></label>
<label>Sperrdauer nach Fehlversuchen (Minuten)<input type="number" min="1" max="1440" name="login_lock_minutes" value="<?= (int) $settings['login_lock_minutes'] ?>" required></label>
<button class="button primary" type="submit">Einstellungen speichern</button>
</form>
</div>

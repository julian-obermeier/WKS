<?php $title = 'Passwort ändern'; ?>
<div class="page-header"><div><p class="eyebrow">Kontosicherheit</p><h1>Passwort ändern</h1><p>Vor der weiteren Nutzung ist ein neues Passwort erforderlich.</p></div></div>
<div class="panel narrow">
    <form method="post" action="<?= e(url('password/change')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label>Neues Passwort<input type="password" name="password" minlength="12" autocomplete="new-password" required><small>Mindestens 12 Zeichen.</small></label>
        <label>Passwort bestätigen<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
        <button class="button primary" type="submit">Passwort speichern</button>
    </form>
</div>

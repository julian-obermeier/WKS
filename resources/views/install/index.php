<?php $title = 'Erstinstallation'; ?>
<section class="auth-wrap">
    <div class="auth-card wide">
        <div class="auth-brand"><span class="brand-mark large">W</span><div><h1>WKS einrichten</h1><p>Grundsystem und erster Administrator</p></div></div>

        <?php if (!$connectionOk): ?>
            <div class="notice error">
                <strong>Datenbank nicht erreichbar.</strong><br>
                Prüfen Sie die Werte in <code>.env</code>. Technische Meldung: <?= e($error) ?>
            </div>
        <?php else: ?>
            <div class="setup-state">
                <strong>Datenbankverbindung: OK</strong>
                <span><?= count($pending) ?> ausstehende Migration(en)</span>
            </div>

            <form method="post" action="<?= e(url('install')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Vorname*<input name="first_name" required value="<?= e(old('first_name')) ?>"></label>
                <label>Nachname*<input name="last_name" required value="<?= e(old('last_name')) ?>"></label>
                <label>Personalnummer*<input name="personnel_number" required value="<?= e(old('personnel_number')) ?>"></label>
                <label>Benutzername*<input name="username" autocomplete="username" required value="<?= e(old('username')) ?>"></label>
                <label class="span-2">E-Mail*<input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
                <label class="span-2">Admin-Passwort*<input type="password" name="password" minlength="12" autocomplete="new-password" required><small>Mindestens 12 Zeichen.</small></label>
                <div class="span-2 form-actions">
                    <button class="button primary" type="submit">Installation ausführen</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

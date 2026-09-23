<?php $title = 'Anmeldung'; ?>
<section class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-mark large">W</span>
            <div><h1>WKS</h1><p>Wach- & Sicherheitsdienst-System</p></div>
        </div>
        <form method="post" action="<?= e(url('login')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <label>Benutzername
                <input name="username" autocomplete="username" required autofocus value="<?= e(old('username')) ?>">
            </label>
            <label>Passwort
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button primary full" type="submit">Anmelden</button>
        </form>
        <p class="muted auth-note">Interne Anwendung. Zugriff nur für berechtigte Benutzer.</p>
    </div>
</section>

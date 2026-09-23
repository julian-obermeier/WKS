<?php $editing = is_array($user); $title = $editing ? 'Benutzer bearbeiten' : 'Benutzer anlegen'; ?>
<div class="page-header split">
    <div><p class="eyebrow">Administration · Benutzer</p><h1><?= e($title) ?></h1><p><?= $editing ? 'Stammdaten, Rolle und Standortzuordnung verwalten.' : 'Neues internes Benutzerkonto mit temporärem Passwort anlegen.' ?></p></div>
    <a class="button ghost" href="<?= e(url('admin/users')) ?>">Zurück</a>
</div>

<div class="panel">
<form method="post" action="<?= e($editing ? url('admin/users/' . $user['id']) : url('admin/users')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <label>Vorname*<input name="first_name" required value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>"></label>
    <label>Nachname*<input name="last_name" required value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>"></label>
    <label>Personalnummer*<input name="personnel_number" required value="<?= e(old('personnel_number', $user['personnel_number'] ?? '')) ?>"></label>
    <label>Benutzername*<input name="username" required autocomplete="off" value="<?= e(old('username', $user['username'] ?? '')) ?>"></label>
    <label>E-Mail*<input type="email" name="email" required value="<?= e(old('email', $user['email'] ?? '')) ?>"></label>
    <label>Rolle*
        <select name="role_id" required>
            <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= (int) old('role_id', $user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (!$editing): ?>
        <label class="span-2">Temporäres Passwort*<input type="password" name="temporary_password" minlength="12" required><small>Beim ersten Login muss der Benutzer ein eigenes Passwort festlegen.</small></label>
    <?php endif; ?>

    <fieldset class="span-2 checkbox-panel">
        <legend>Standorte*</legend>
        <div class="checkbox-grid">
            <?php foreach ($locations as $location): ?>
                <label class="check-row"><input type="checkbox" name="location_ids[]" value="<?= (int) $location['id'] ?>" <?= in_array((int) $location['id'], $selectedLocations, true) ? 'checked' : '' ?>><span><?= e($location['name']) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="span-2 form-actions"><button class="button primary" type="submit"><?= $editing ? 'Änderungen speichern' : 'Benutzer anlegen' ?></button></div>
</form>
</div>

<?php if ($editing): ?>
<div class="two-col">
    <div class="panel">
        <div class="panel-header"><div><h2>Kontostatus</h2><p>Gesperrte und deaktivierte Konten können sich nicht anmelden.</p></div></div>
        <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/status')) ?>" class="inline-actions">
            <?= csrf_field() ?>
            <select name="status">
                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktiv</option>
                <option value="locked" <?= $user['status'] === 'locked' ? 'selected' : '' ?>>Gesperrt</option>
                <option value="disabled" <?= $user['status'] === 'disabled' ? 'selected' : '' ?>>Deaktiviert</option>
            </select>
            <button class="button secondary" type="submit">Status ändern</button>
        </form>
    </div>
    <div class="panel">
        <div class="panel-header"><div><h2>Sitzungen</h2><p>Beendet alle aktiven Sitzungen dieses Benutzers.</p></div></div>
        <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/revoke-sessions')) ?>">
            <?= csrf_field() ?><button class="button danger" type="submit">Aktive Sessions beenden</button>
        </form>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><div><h2>Passwort zurücksetzen</h2><p>Der Benutzer muss das temporäre Passwort beim nächsten Login ändern.</p></div></div>
    <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/reset-password')) ?>" class="inline-actions">
        <?= csrf_field() ?>
        <input type="password" name="temporary_password" minlength="12" placeholder="Temporäres Passwort" required>
        <button class="button danger" type="submit">Passwort zurücksetzen</button>
    </form>
</div>
<?php endif; ?>

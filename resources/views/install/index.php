<?php
$title='WKS Online-Installation';
$appUrl=(string)old('app_url',$defaults['app_url']??'');
$appBase=(string)old('app_base_path',$defaults['app_base_path']??'');
$dbHost=(string)old('db_host',$defaults['db_host']??'127.0.0.1');
$dbPort=(string)old('db_port',$defaults['db_port']??'3306');
$dbDatabase=(string)old('db_database',$defaults['db_database']??'');
$dbUsername=(string)old('db_username',$defaults['db_username']??'');
$requirementsBlocked=($requirements['overall']??'error')==='error';
?>
<section class="auth-wrap">
<div class="auth-card wide">
    <div class="auth-brand">
        <span class="brand-mark large">W</span>
        <div><h1>WKS Online-Installation</h1><p>Server prüfen · Datenbank konfigurieren · Administrator anlegen</p></div>
    </div>

    <div class="metric-grid">
        <div class="metric-card"><span class="metric-icon">1</span><div><small>Server</small><strong><?= $requirementsBlocked?'Prüfen':'Bereit' ?></strong></div></div>
        <div class="metric-card"><span class="metric-icon">2</span><div><small>Datenbank</small><strong><?= $connectionOk?'Verbunden':($envConfigured?'Fehler':'Offen') ?></strong></div></div>
        <div class="metric-card"><span class="metric-icon">3</span><div><small>WKS</small><strong><?= $connectionOk?'Bereit zur Installation':'Ausstehend' ?></strong></div></div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><p class="eyebrow">Schritt 1</p><h2>Servervoraussetzungen</h2><p>Der Installer prüft die für WKS benötigte PHP-Umgebung und Schreibrechte.</p></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Prüfung</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach($requirements['items'] as $item):?>
                <tr>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><span class="badge <?= $item['status']==='ok'?'success':($item['status']==='warning'?'warning':'danger') ?>"><?= e(strtoupper($item['status'])) ?></span></td>
                    <td><?= e($item['detail']) ?></td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table></div>
        <?php if($requirementsBlocked):?><div class="notice error">Mindestens eine zwingende Voraussetzung fehlt. Beheben Sie die rot markierten Punkte, bevor Sie fortfahren.</div><?php endif;?>
    </div>

    <div class="panel">
        <div class="panel-header"><div><p class="eyebrow">Schritt 2</p><h2>Anwendung und Datenbank</h2><p>Die Daten werden geprüft und anschließend automatisch in einer geschützten <code>.env</code> gespeichert. Das Datenbankpasswort wird nicht wieder im Formular angezeigt.</p></div></div>

        <?php if($envConfigured&&$connectionOk):?>
            <div class="notice success"><strong>Datenbankverbindung erfolgreich.</strong> <?= count($pending) ?> Migration(en) sind noch ausstehend.</div>
        <?php elseif($envConfigured&&$error):?>
            <div class="notice error"><strong>Die gespeicherte Datenbankkonfiguration funktioniert nicht.</strong><br><?= e($error) ?></div>
        <?php else:?>
            <div class="notice info">Sie benötigen nur eine bereits angelegte, leere MySQL-/MariaDB-Datenbank. Tabellen müssen Sie nicht selbst importieren.</div>
        <?php endif;?>

        <form method="post" action="<?= e(url('install/configure')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <label class="span-2">WKS-Adresse*<input type="url" name="app_url" value="<?= e($appUrl) ?>" required placeholder="https://wks.example.de"><small>Wird automatisch erkannt. Bitte prüfen Sie die Adresse.</small></label>
            <label class="span-2">Base-Pfad<input name="app_base_path" value="<?= e($appBase) ?>" placeholder=""><small>Bei eigener Domain/Subdomain normalerweise leer lassen.</small></label>
            <label>Datenbankserver*<input name="db_host" value="<?= e($dbHost) ?>" required placeholder="localhost"></label>
            <label>Port*<input name="db_port" value="<?= e($dbPort) ?>" required inputmode="numeric"></label>
            <label>Datenbankname*<input name="db_database" value="<?= e($dbDatabase) ?>" required></label>
            <label>Datenbankbenutzer*<input name="db_username" value="<?= e($dbUsername) ?>" required></label>
            <label class="span-2">Datenbankpasswort<input type="password" name="db_password" autocomplete="new-password"><small><?= $envConfigured?'Leer lassen, um das bereits gespeicherte Passwort beizubehalten.':'Passwort des von Ihnen angelegten Datenbankbenutzers.' ?></small></label>
            <div class="span-2 form-actions">
                <button class="button <?= $connectionOk?'secondary':'primary' ?>" type="submit" <?= $requirementsBlocked?'disabled':'' ?>><?= $connectionOk?'Konfiguration erneut prüfen':'Verbindung prüfen und Konfiguration speichern' ?></button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header"><div><p class="eyebrow">Schritt 3</p><h2>Erster Administrator</h2><p>Nach dem Absenden werden alle Migrationen ausgeführt, Grunddaten angelegt und ein abschließender Systemcheck gestartet.</p></div></div>

        <?php if(!$connectionOk):?>
            <div class="notice info">Dieser Schritt wird freigeschaltet, sobald die Datenbankverbindung erfolgreich geprüft wurde.</div>
        <?php else:?>
            <form method="post" action="<?= e(url('install')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Vorname*<input name="first_name" required value="<?= e(old('first_name')) ?>"></label>
                <label>Nachname*<input name="last_name" required value="<?= e(old('last_name')) ?>"></label>
                <label>Personalnummer*<input name="personnel_number" required value="<?= e(old('personnel_number')) ?>"></label>
                <label>Benutzername*<input name="username" autocomplete="username" required value="<?= e(old('username')) ?>"></label>
                <label class="span-2">E-Mail*<input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
                <label>Admin-Passwort*<input type="password" name="password" minlength="12" autocomplete="new-password" required><small>Mindestens 12 Zeichen.</small></label>
                <label>Passwort wiederholen*<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
                <div class="span-2 notice info">Der Mailversand wird bei der Erstinstallation ausdrücklich <strong>deaktiviert</strong>. Gießen und Marburg sowie die Grunddaten werden automatisch eingerichtet.</div>
                <div class="span-2 form-actions"><button class="button primary" type="submit">WKS jetzt vollständig installieren</button></div>
            </form>
        <?php endif;?>
    </div>

    <div class="notice info">
        <strong>Nach der Installation:</strong> WKS sperrt den Installer automatisch. Einzig der Cronjob muss anschließend noch im Hosting-Panel auf <code>php /…/WKS/bin/cron.php</code> eingerichtet werden.
    </div>
</div>
</section>

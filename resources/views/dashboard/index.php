<?php $title = 'Dashboard'; ?>
<div class="page-header">
    <div>
        <p class="eyebrow"><?= e($location['name'] ?? 'WKS') ?></p>
        <h1>Dashboard</h1>
        <p>Aktiver Arbeitskontext und Systemzugriff für <?= e($user['first_name'] ?? '') ?> <?= e($user['last_name'] ?? '') ?>.</p>
    </div>
</div>

<div class="metric-grid">
    <div class="metric-card"><span class="metric-icon">⌖</span><div><small>Aktiver Standort</small><strong><?= e($location['name'] ?? '–') ?></strong></div></div>
    <div class="metric-card"><span class="metric-icon">♙</span><div><small>Rolle</small><strong><?= e($user['role_name'] ?? '–') ?></strong></div></div>
    <div class="metric-card"><span class="metric-icon">✓</span><div><small>Kontostatus</small><strong>Aktiv</strong></div></div>
</div>

<?php if (can('dutybook.read')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Dienstbuch</h2><p>Schicht, Anwesenheit, offene Vorgänge und Übergaben</p></div></div>
    <div class="quick-grid">
        <a class="quick-card" href="<?= e(url('dutybook')) ?>"><span>▤</span><strong>Tagesdienstbuch</strong><small>Aktuelle Schicht und Tagesereignisse</small></a>
        <?php if (can('dutybook.create')): ?><a class="quick-card" href="<?= e(url('dutybook/create')) ?>"><span>＋</span><strong>Eintrag erfassen</strong><small>Neuen Vorgang dokumentieren</small></a><?php endif; ?>
        <a class="quick-card" href="<?= e(url('dutybook/search')) ?>"><span>⌕</span><strong>Suche</strong><small>Dienstbucheinträge gezielt filtern</small></a>
    </div>
</div>
<?php endif; ?>

<?php if (can('special_reports.read')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Sonderberichte</h2><p>Dynamische Berichte mit Leitungsprüfung und Versionierung</p></div></div>
    <div class="quick-grid">
        <a class="quick-card" href="<?= e(url('special-reports')) ?>"><span>▧</span><strong>Arbeitsübersicht</strong><small>Entwürfe, Nachbearbeitung und Prüfung</small></a>
        <?php if (can('special_reports.create')): ?><a class="quick-card" href="<?= e(url('special-reports/create')) ?>"><span>＋</span><strong>Sonderbericht</strong><small>Bericht direkt erfassen</small></a><?php endif; ?>
        <a class="quick-card" href="<?= e(url('special-reports/search')) ?>"><span>⌕</span><strong>Berichtssuche</strong><small>Archiv und Volltextsuche</small></a>
    </div>
</div>
<?php endif; ?>

<?php if (can('valuables.read')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Wertsachen</h2><p>Sichere Verwahrung, Kassetten und Siegel</p></div></div>
    <div class="quick-grid">
        <a class="quick-card" href="<?= e(url('valuables')) ?>"><span>▣</span><strong>Aktuelle Verwahrung</strong><small>Eingelagerte Vorgänge und Lagerorte</small></a>
        <?php if (can('valuables.store')): ?><a class="quick-card" href="<?= e(url('valuables/create')) ?>"><span>＋</span><strong>Einlagern</strong><small>Neuen Verwahrvorgang erfassen</small></a><?php endif; ?>
        <a class="quick-card" href="<?= e(url('valuables/cassettes')) ?>"><span>▦</span><strong>Kassetten</strong><small>Frei-/Belegt-Übersicht 1–100</small></a>
        <a class="quick-card" href="<?= e(url('valuables/long-term')) ?>"><span>◷</span><strong>Langzeitverwahrung</strong><small>Vorgänge über dem Schwellenwert</small></a>
    </div>
</div>
<?php endif; ?>

<?php if (can('house_bans.read')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Hausverbote</h2><p>Eigenständige Hausverbotsliste des aktiven Standorts</p></div></div>
    <div class="quick-grid">
        <a class="quick-card" href="<?= e(url('house-bans')) ?>"><span>⊘</span><strong>Hausverbotsliste</strong><small>Suchen, filtern und öffnen</small></a>
        <?php if (can('house_bans.create')): ?><a class="quick-card" href="<?= e(url('house-bans/create')) ?>"><span>＋</span><strong>Hausverbot anlegen</strong><small>Name, Datum, Grund und Anhänge</small></a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (can('messages.read') || can('notifications.read')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Informationen</h2><p>Mitteilungen und interne Benachrichtigungen</p></div></div>
    <div class="quick-grid">
        <?php if (can('messages.read')): ?><a class="quick-card" href="<?= e(url('announcements')) ?>"><span>☷</span><strong>Mitteilungen</strong><small>Veröffentlichte Informationen und Lesebestätigungen</small></a><?php endif; ?>
        <?php if (can('notifications.read')): ?><a class="quick-card" href="<?= e(url('notifications')) ?>"><span>◉</span><strong>Benachrichtigungen</strong><small>Ereignisbezogene interne Hinweise</small></a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (can('system.users.manage') || can('system.locations.manage') || can('system.audit.view')): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Administration</h2><p>Grundkonfiguration des Systems</p></div></div>
    <div class="quick-grid">
        <?php if (can('system.users.manage')): ?><a class="quick-card" href="<?= e(url('admin/users')) ?>"><span>♙</span><strong>Benutzer</strong><small>Konten, Rollen, Standorte und Sitzungen</small></a><?php endif; ?>
        <?php if (can('system.roles.manage')): ?><a class="quick-card" href="<?= e(url('admin/roles')) ?>"><span>⚿</span><strong>Rollen & Rechte</strong><small>Granulare Berechtigungen konfigurieren</small></a><?php endif; ?>
        <?php if (can('system.locations.manage')): ?><a class="quick-card" href="<?= e(url('admin/locations')) ?>"><span>⌖</span><strong>Standorte</strong><small>Standortdaten und Mailmodus</small></a><?php endif; ?>
        <?php if (can('system.audit.view')): ?><a class="quick-card" href="<?= e(url('admin/audit')) ?>"><span>≡</span><strong>Audit-Log</strong><small>Nachvollziehbare Systemaktionen</small></a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

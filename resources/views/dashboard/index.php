<?php $title = 'Dashboard'; ?>
<div class="page-header">
    <div>
        <p class="eyebrow"><?= e($location['name'] ?? 'WKS') ?></p>
        <h1>Dashboard</h1>
        <p>Aktiver Arbeitskontext und Systemzugriff für <?= e($user['first_name'] ?? '') ?> <?= e($user['last_name'] ?? '') ?>.</p>
    </div>
</div>

<div class="metric-grid">
    <a class="metric-card" href="<?= e(url('dutybook')) ?>"><span class="metric-icon">◷</span><div><small>Aktuelle Schicht</small><strong><?= e($dashboard['current_shift']['shift_name'] ?? 'Nicht übernommen') ?></strong></div></a>
    <a class="metric-card" href="<?= e(url('dutybook/search?status=open')) ?>"><span class="metric-icon">▤</span><div><small>Offene Dienstbuchvorgänge</small><strong><?= (int)$dashboard['open_dutybook'] ?></strong></div></a>
    <a class="metric-card" href="<?= e(url('notifications')) ?>"><span class="metric-icon">◉</span><div><small>Ungelesene Benachrichtigungen</small><strong><?= (int)$dashboard['notification_unread'] ?></strong></div></a>
</div>
<?php if (can('special_reports.review') || can('valuables.read')): ?>
<div class="metric-grid">
    <?php if (can('special_reports.review')): ?><a class="metric-card" href="<?= e(url('special-reports/search?status=completed')) ?>"><span class="metric-icon">!</span><div><small>Ungeprüfte Sonderberichte</small><strong><?= (int)$dashboard['unreviewed_reports'] ?></strong></div></a><?php endif; ?>
    <?php if (can('special_reports.read')): ?><a class="metric-card" href="<?= e(url('special-reports/search?status=revision_required')) ?>"><span class="metric-icon">↺</span><div><small>Nachbearbeitungen</small><strong><?= (int)$dashboard['revision_reports'] ?></strong></div></a><?php endif; ?>
    <?php if (can('valuables.read')): ?><a class="metric-card" href="<?= e(url('valuables')) ?>"><span class="metric-icon">▣</span><div><small>Wertsachen / Kassetten</small><strong><?= (int)$dashboard['valuables']['stored_count'] ?> / <?= (int)$dashboard['valuables']['occupied_cassettes'] ?></strong></div></a><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($dashboard['announcements']): ?>
<div class="panel">
    <div class="panel-header"><div><h2>Wichtige Mitteilungen</h2><p>Aktuell gültige Veröffentlichungen</p></div><a class="button ghost compact" href="<?= e(url('announcements')) ?>">Alle</a></div>
    <div class="timeline-list"><?php foreach ($dashboard['announcements'] as $a): ?><a class="timeline-item" href="<?= e(url('announcements/'.$a['id'])) ?>"><span class="badge <?= $a['priority']==='important'?'warning':'neutral' ?>"><?= $a['priority']==='important'?'Wichtig':'Info' ?></span> <strong><?= e($a['title']) ?></strong><?php if($a['require_ack']&&!$a['confirmed_at']): ?><small class="table-sub">Lesebestätigung offen</small><?php endif; ?></a><?php endforeach; ?></div>
</div>
<?php endif; ?>

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

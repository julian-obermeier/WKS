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

<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $roles = [
        ['employee', 'Mitarbeiter'],
        ['management', 'Leitung'],
        ['admin', 'Admin'],
    ];

    $roleStmt = $pdo->prepare(
        'INSERT INTO roles (code, name, is_system, created_at, updated_at)
         VALUES (:code, :name, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = 1, updated_at = NOW()'
    );
    foreach ($roles as [$code, $name]) {
        $roleStmt->execute(['code' => $code, 'name' => $name]);
    }

    $permissions = [
        ['dashboard.view', 'Dashboard anzeigen', 'Dashboard'],
        ['dutybook.read', 'Dienstbuch lesen', 'Dienstbuch'],
        ['dutybook.create', 'Dienstbucheinträge erstellen', 'Dienstbuch'],
        ['dutybook.edit', 'Dienstbucheinträge innerhalb Frist bearbeiten', 'Dienstbuch'],
        ['dutybook.addendum', 'Dienstbuch-Nachträge erstellen', 'Dienstbuch'],
        ['dutybook.export', 'Dienstbuch exportieren', 'Dienstbuch'],
        ['special_reports.read', 'Sonderberichte lesen', 'Sonderberichte'],
        ['special_reports.create', 'Sonderberichte erstellen', 'Sonderberichte'],
        ['special_reports.close', 'Sonderberichte abschließen', 'Sonderberichte'],
        ['special_reports.review', 'Sonderberichte prüfen', 'Sonderberichte'],
        ['special_reports.request_revision', 'Nachbearbeitung anfordern', 'Sonderberichte'],
        ['special_reports.export', 'Sonderberichte exportieren', 'Sonderberichte'],
        ['valuables.read', 'Wertsachen lesen', 'Wertsachen'],
        ['valuables.store', 'Wertsachen einlagern', 'Wertsachen'],
        ['valuables.release', 'Wertsachen auslagern', 'Wertsachen'],
        ['valuables.add_note', 'Wertsachen-Nachträge erstellen', 'Wertsachen'],
        ['valuables.archive', 'Wertsachenarchiv sehen', 'Wertsachen'],
        ['valuables.delete', 'Wertsachen löschen', 'Wertsachen'],
        ['valuables.export', 'Wertsachen exportieren', 'Wertsachen'],
        ['house_bans.read', 'Hausverbote lesen', 'Hausverbote'],
        ['house_bans.create', 'Hausverbote erstellen', 'Hausverbote'],
        ['house_bans.edit', 'Hausverbote bearbeiten', 'Hausverbote'],
        ['house_bans.delete', 'Hausverbote löschen', 'Hausverbote'],
        ['house_bans.export', 'Hausverbote exportieren', 'Hausverbote'],
        ['messages.read', 'Mitteilungen lesen', 'Mitteilungen'],
        ['messages.manage', 'Mitteilungen verwalten', 'Mitteilungen'],
        ['notifications.read', 'Benachrichtigungen lesen', 'Benachrichtigungen'],
        ['notifications.manage', 'Benachrichtigungen konfigurieren', 'Benachrichtigungen'],
        ['statistics.view', 'Statistiken anzeigen', 'Statistik'],
        ['statistics.export', 'Statistiken exportieren', 'Statistik'],
        ['search.use', 'Globale Suche verwenden', 'Suche'],
        ['system.users.manage', 'Benutzer verwalten', 'System'],
        ['system.roles.manage', 'Rollen und Rechte verwalten', 'System'],
        ['system.locations.manage', 'Standorte verwalten', 'System'],
        ['system.settings.manage', 'Einstellungen verwalten', 'System'],
        ['system.masterdata.manage', 'Stammdaten verwalten', 'System'],
        ['system.audit.view', 'Audit-Log anzeigen', 'System'],
        ['system.audit.filter', 'Audit-Log filtern', 'System'],
        ['system.audit.export', 'Audit-Log exportieren', 'System'],
        ['system.updates.manage', 'Systemupdates verwalten', 'System'],
        ['system.errors.view', 'Fehlerprotokoll anzeigen', 'System'],
        ['system.errors.manage', 'Fehlerprotokoll bearbeiten', 'System'],
        ['system.status.view', 'Systemstatus anzeigen', 'System'],
        ['system.maintenance.manage', 'Wartungsmodus verwalten', 'System'],
        ['system.trash.manage', 'Papierkorb verwalten', 'System'],
        ['system.templates.manage', 'Vorlagen verwalten', 'System'],
        ['system.mail.manage', 'Mailkonfiguration verwalten', 'System'],
        ['system.cron.manage', 'Cronjobs verwalten', 'System'],
    ];

    $permissionStmt = $pdo->prepare(
        'INSERT INTO permissions (code, name, group_name, created_at)
         VALUES (:code, :name, :group_name, NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), group_name = VALUES(group_name)'
    );
    foreach ($permissions as [$code, $name, $group]) {
        $permissionStmt->execute(['code' => $code, 'name' => $name, 'group_name' => $group]);
    }

    $roleIds = [];
    foreach ($pdo->query('SELECT id, code FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $role) {
        $roleIds[$role['code']] = (int) $role['id'];
    }

    $permissionIds = [];
    foreach ($pdo->query('SELECT id, code FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $permission) {
        $permissionIds[$permission['code']] = (int) $permission['id'];
    }

    $employee = [
        'dashboard.view',
        'dutybook.read', 'dutybook.create', 'dutybook.edit', 'dutybook.addendum',
        'special_reports.read', 'special_reports.create', 'special_reports.close',
        'valuables.read', 'valuables.store', 'valuables.release', 'valuables.add_note',
        'house_bans.read', 'messages.read', 'notifications.read', 'search.use',
    ];

    $management = array_values(array_unique(array_merge($employee, [
        'dutybook.export',
        'special_reports.review', 'special_reports.request_revision', 'special_reports.export',
        'valuables.archive', 'valuables.export',
        'house_bans.create', 'house_bans.edit', 'house_bans.export',
        'messages.manage', 'statistics.view', 'statistics.export',
        'system.audit.view', 'system.audit.filter',
    ])));

    $all = array_column($permissions, 0);
    $assign = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
         VALUES (:role_id, :permission_id, NOW())'
    );

    foreach ([
        'employee' => $employee,
        'management' => $management,
        'admin' => $all,
    ] as $roleCode => $codes) {
        foreach ($codes as $code) {
            $assign->execute([
                'role_id' => $roleIds[$roleCode],
                'permission_id' => $permissionIds[$code],
            ]);
        }
    }

    $locationStmt = $pdo->prepare(
        'INSERT INTO locations (name, code, email_address, mail_mode, active, created_at, updated_at)
         VALUES (:name, :code, :email, :mail_mode, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), email_address = VALUES(email_address)'
    );
    $locationStmt->execute([
        'name' => 'Gießen',
        'code' => 'GI',
        'email' => 'Security.gi@uk-gm.de',
        'mail_mode' => 'disabled',
    ]);
    $locationStmt->execute([
        'name' => 'Marburg',
        'code' => 'MR',
        'email' => 'Security.mr@uk-gm.de',
        'mail_mode' => 'disabled',
    ]);

    $settingStmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:key, :value, :type, NOW(), NOW())
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );

    foreach ([
        ['security.inactivity_minutes', '30', 'int'],
        ['security.login_max_attempts', '5', 'int'],
        ['security.login_lock_minutes', '15', 'int'],
        ['mail.global_enabled', '0', 'bool'],
        ['dutybook.edit_window_minutes', '120', 'int'],
        ['special_reports.edit_window_minutes', '120', 'int'],
        ['valuables.long_term_days', '14', 'int'],
    ] as [$key, $value, $type]) {
        $settingStmt->execute(['key' => $key, 'value' => $value, 'type' => $type]);
    }
};

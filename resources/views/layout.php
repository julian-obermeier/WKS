<?php
declare(strict_types=1);

use WKS\Core\Auth;
use WKS\Core\Session;
use WKS\Repositories\LocationRepository;

$user = Auth::check() ? Auth::user() : null;
$notice = Session::consumeFlash('notice');
$theme = $user['theme'] ?? 'light';
$activeLocation = null;

if ($user && active_location_id()) {
    try {
        $activeLocation = (new LocationRepository())->find((int) active_location_id());
    } catch (Throwable) {
        $activeLocation = null;
    }
}
?>
<!doctype html>
<html lang="de" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= isset($title) ? e($title) . ' – ' : '' ?>WKS</title>
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <meta name="theme-color" content="#174f7c">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <header class="topbar">
            <a class="brand" href="<?= e(url()) ?>">
                <span class="brand-mark">W</span>
                <span><strong>WKS</strong><small>Wach- & Sicherheitsdienst</small></span>
            </a>
            <div class="topbar-actions">
                <?php if ($activeLocation): ?>
                    <a class="location-switch" href="<?= e(url('location/select')) ?>" title="Standort wechseln">
                        <span class="dot"></span><?= e($activeLocation['name']) ?>
                    </a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('profile/theme')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="theme" value="<?= $theme === 'dark' ? 'light' : 'dark' ?>">
                    <input type="hidden" name="redirect_to" value="<?= e(url()) ?>">
                    <button class="icon-button" type="submit" title="Darstellung wechseln"><?= $theme === 'dark' ? '☀' : '☾' ?></button>
                </form>
                <div class="user-chip">
                    <span><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                    <small><?= e($user['role_name']) ?></small>
                </div>
                <form method="post" action="<?= e(url('logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="button ghost compact" type="submit">Abmelden</button>
                </form>
            </div>
        </header>
        <div class="app-body">
            <aside class="sidebar">
                <nav>
                    <a href="<?= e(url()) ?>" class="nav-link">⌂ <span>Dashboard</span></a>
                    <?php if (can('search.use')): ?><a href="<?= e(url('search')) ?>" class="nav-link">⌕ <span>Globale Suche</span></a><?php endif; ?>
                    <?php if (can('statistics.view')): ?><a href="<?= e(url('statistics')) ?>" class="nav-link">◫ <span>Statistik</span></a><?php endif; ?>
                    <?php if (can('dutybook.read')): ?>
                        <a href="<?= e(url('dutybook')) ?>" class="nav-link">▤ <span>Dienstbuch</span></a>
                    <?php endif; ?>
                    <?php if (can('special_reports.read')): ?>
                        <a href="<?= e(url('special-reports')) ?>" class="nav-link">▧ <span>Sonderberichte</span></a>
                    <?php endif; ?>
                    <?php if (can('valuables.read')): ?>
                        <a href="<?= e(url('valuables')) ?>" class="nav-link">▣ <span>Wertsachen</span></a>
                    <?php endif; ?>
                    <?php if (can('house_bans.read')): ?>
                        <a href="<?= e(url('house-bans')) ?>" class="nav-link">⊘ <span>Hausverbote</span></a>
                    <?php endif; ?>
                    <?php if (can('messages.read')): ?>
                        <a href="<?= e(url('announcements')) ?>" class="nav-link">☷ <span>Mitteilungen</span></a>
                    <?php endif; ?>
                    <?php if (can('notifications.read')): ?>
                        <a href="<?= e(url('notifications')) ?>" class="nav-link">◉ <span>Benachrichtigungen</span></a>
                    <?php endif; ?>
                    <?php if (can('system.users.manage') || can('system.roles.manage') || can('system.locations.manage') || can('system.audit.view') || can('system.settings.manage')): ?>
                        <div class="nav-section">Administration</div>
                    <?php endif; ?>
                    <?php if (can('system.users.manage')): ?>
                        <a href="<?= e(url('admin/users')) ?>" class="nav-link">♙ <span>Benutzer</span></a>
                    <?php endif; ?>
                    <?php if (can('system.roles.manage')): ?>
                        <a href="<?= e(url('admin/roles')) ?>" class="nav-link">⚿ <span>Rollen & Rechte</span></a>
                    <?php endif; ?>
                    <?php if (can('system.locations.manage')): ?>
                        <a href="<?= e(url('admin/locations')) ?>" class="nav-link">⌖ <span>Standorte</span></a>
                    <?php endif; ?>
                    <?php if (can('system.masterdata.manage')): ?>
                        <a href="<?= e(url('admin/dutybook')) ?>" class="nav-link">⌘ <span>Dienstbuch-Stammdaten</span></a>
                    <?php endif; ?>
                    <?php if (can('system.masterdata.manage')): ?>
                        <a href="<?= e(url('admin/special-reports')) ?>" class="nav-link">▧ <span>Sonderbericht-Stammdaten</span></a>
                    <?php endif; ?>
                    <?php if (can('system.audit.view')): ?>
                        <a href="<?= e(url('admin/audit')) ?>" class="nav-link">≡ <span>Audit-Log</span></a>
                    <?php endif; ?>
                    <?php if (can('messages.manage')): ?>
                        <a href="<?= e(url('announcements/manage')) ?>" class="nav-link">✎ <span>Mitteilungen verwalten</span></a>
                    <?php endif; ?>
                    <?php if (can('system.trash.manage')): ?>
                        <a href="<?= e(url('admin/trash')) ?>" class="nav-link">⌫ <span>Papierkorb</span></a>
                    <?php endif; ?>
                    <?php if (can('system.settings.manage')): ?>
                        <a href="<?= e(url('admin/settings/security')) ?>" class="nav-link">⚙ <span>Sicherheit</span></a>
                        <a href="<?= e(url('admin/settings/valuables')) ?>" class="nav-link">▣ <span>Wertsachen-Einstellungen</span></a>
                    <?php endif; ?>
                <a href="<?= e(url('help')) ?>" class="nav-link">? <span>Hilfe</span></a>
                </nav>
                <div class="sidebar-footer">Version <?= e(config('app.version', 'dev')) ?></div>
            </aside>
            <main class="main-content">
    <?php else: ?>
        <main class="public-main">
    <?php endif; ?>

    <?php if (is_array($notice)): ?>
        <div class="notice <?= e((string) ($notice['type'] ?? 'info')) ?>" role="status">
            <?= e((string) ($notice['message'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <?= $content ?>

    <?php if ($user): ?>
            </main>
        </div>
    <?php else: ?>
        </main>
    <?php endif; ?>
</div>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>

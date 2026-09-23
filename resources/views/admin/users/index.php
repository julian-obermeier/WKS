<?php $title = 'Benutzer'; ?>
<div class="page-header split">
    <div><p class="eyebrow">Administration</p><h1>Benutzer</h1><p>Konten werden ausschließlich durch Administratoren verwaltet.</p></div>
    <a class="button primary" href="<?= e(url('admin/users/create')) ?>">+ Benutzer anlegen</a>
</div>

<div class="panel">
    <form method="get" action="<?= e(url('admin/users')) ?>" class="filterbar">
        <input type="search" name="q" placeholder="Name, Benutzername, Personalnummer …" value="<?= e($search) ?>">
        <button class="button secondary" type="submit">Suchen</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Personalnr.</th><th>Benutzername</th><th>Rolle</th><th>Status</th><th>Letzter Login</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['last_name'] . ', ' . $item['first_name']) ?></strong><small class="table-sub"><?= e($item['email']) ?></small></td>
                    <td><?= e($item['personnel_number']) ?></td>
                    <td><?= e($item['username']) ?></td>
                    <td><?= e($item['role_name']) ?></td>
                    <td><span class="badge <?= e($item['status']) ?>"><?= e(match ($item['status']) { 'active' => 'Aktiv', 'locked' => 'Gesperrt', default => 'Deaktiviert' }) ?></span></td>
                    <td><?= e(format_datetime($item['last_login_at'])) ?></td>
                    <td class="align-right"><a class="button ghost compact" href="<?= e(url('admin/users/' . $item['id'] . '/edit')) ?>">Bearbeiten</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users['items'] === []): ?><tr><td colspan="7" class="empty">Keine Benutzer gefunden.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination"><span><?= (int) $users['total'] ?> Benutzer</span><span>Seite <?= (int) $users['page'] ?> / <?= (int) $users['pages'] ?></span></div>
</div>

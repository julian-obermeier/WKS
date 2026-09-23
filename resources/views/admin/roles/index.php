<?php $title = 'Rollen & Rechte'; ?>
<div class="page-header"><div><p class="eyebrow">Administration</p><h1>Rollen & Rechte</h1><p>Die drei Grundrollen sind fest. Ihre Berechtigungen sind granular konfigurierbar.</p></div></div>

<?php foreach ($roles as $role): ?>
<div class="panel">
    <div class="panel-header"><div><h2><?= e($role['name']) ?></h2><p>Systemrolle: <?= e($role['code']) ?></p></div></div>
    <form method="post" action="<?= e(url('admin/roles/' . $role['id'])) ?>">
        <?= csrf_field() ?>
        <?php $currentGroup = null; ?>
        <div class="permission-list">
        <?php foreach ($permissions as $permission): ?>
            <?php if ($currentGroup !== $permission['group_name']): $currentGroup = $permission['group_name']; ?>
                <h3><?= e($currentGroup) ?></h3>
            <?php endif; ?>
            <label class="permission-row">
                <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $assigned[(int) $role['id']], true) ? 'checked' : '' ?>>
                <span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['code']) ?></small></span>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="button primary" type="submit">Rechte speichern</button></div>
    </form>
</div>
<?php endforeach; ?>

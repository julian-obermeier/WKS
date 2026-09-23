<?php $title = 'Standort auswählen'; ?>
<div class="page-header"><div><p class="eyebrow">Arbeitskontext</p><h1>Standort auswählen</h1><p>Alle Ansichten und Eingaben werden dem aktiven Standort zugeordnet.</p></div></div>
<div class="location-grid">
    <?php foreach ($locations as $location): ?>
        <form method="post" action="<?= e(url('location/select')) ?>" class="location-card">
            <?= csrf_field() ?>
            <input type="hidden" name="location_id" value="<?= (int) $location['id'] ?>">
            <div class="location-icon">⌖</div>
            <h2><?= e($location['name']) ?></h2>
            <p><?= e($location['code']) ?></p>
            <button class="button primary" type="submit">Standort öffnen</button>
        </form>
    <?php endforeach; ?>
</div>

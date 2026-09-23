<?php $title = 'Kein Zugriff'; ?>
<section class="error-page"><div class="error-code">403</div><h1>Kein Zugriff</h1><p><?= nl2br(e($message ?? 'Sie besitzen nicht die erforderliche Berechtigung.')) ?></p><a class="button primary" href="<?= e(url()) ?>">Zum Dashboard</a></section>

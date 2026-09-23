<?php $title = 'Technischer Fehler'; ?>
<section class="error-page"><div class="error-code">500</div><h1>Technischer Fehler</h1><p><?= nl2br(e($message ?? 'Es ist ein technischer Fehler aufgetreten.')) ?></p><a class="button primary" href="<?= e(url()) ?>">Zum Dashboard</a></section>

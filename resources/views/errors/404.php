<?php $title = 'Nicht gefunden'; ?>
<section class="error-page"><div class="error-code">404</div><h1>Seite nicht gefunden</h1><p><?= nl2br(e($message ?? 'Die angeforderte Seite wurde nicht gefunden.')) ?></p><a class="button primary" href="<?= e(url()) ?>">Zum Dashboard</a></section>

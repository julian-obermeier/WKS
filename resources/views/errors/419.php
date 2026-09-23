<?php $title = 'Sitzung abgelaufen'; ?>
<section class="error-page"><div class="error-code">419</div><h1>Sicherheitsprüfung abgelaufen</h1><p><?= nl2br(e($message ?? 'Bitte laden Sie die Seite neu und versuchen Sie es erneut.')) ?></p><a class="button primary" href="<?= e(url()) ?>">Neu laden</a></section>

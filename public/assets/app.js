(() => {
    'use strict';

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm || 'Aktion wirklich ausführen?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('input, select, textarea').forEach((field) => {
        field.addEventListener('invalid', () => field.classList.add('field-invalid'));
        field.addEventListener('input', () => field.classList.remove('field-invalid'));
    });
})();

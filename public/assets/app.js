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

    const syncDynamic = () => {
        const select = document.querySelector('[data-event-type-select]');
        if (!select) return;
        document.querySelectorAll('[data-dynamic-event]').forEach((group) => {
            const active = group.dataset.dynamicEvent === select.value;
            group.classList.toggle('active', active);
            group.querySelectorAll('input,select,textarea').forEach((field) => field.disabled = !active);
        });
        document.querySelectorAll('[data-event-category]').forEach((el) => {
            el.hidden = el.dataset.eventCategory !== select.value;
        });
    };
    document.querySelector('[data-event-type-select]')?.addEventListener('change', syncDynamic);
    syncDynamic();

    document.querySelectorAll('[data-repeat-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.dataset.repeatAdd);
            if (!target) return;
            const template = target.querySelector('template');
            if (!template) return;
            const index = target.querySelectorAll('[data-repeat-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            target.insertAdjacentHTML('beforeend', html);
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-repeat-remove]');
        if (button) button.closest('[data-repeat-row]')?.remove();
    });

    const form = document.querySelector('form[data-unsaved-warning]');
    if (form) {
        let dirty = false;
        form.addEventListener('input', () => dirty = true);
        form.addEventListener('submit', () => dirty = false);
        window.addEventListener('beforeunload', (event) => {
            if (!dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }
})();

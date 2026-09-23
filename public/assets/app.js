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
        const selected = select.options[select.selectedIndex];
        document.querySelectorAll('[data-force-section]').forEach((el) => {
            el.hidden = selected?.dataset?.force !== '1';
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

    const syncContainerRow = (row) => {
        const type = row.querySelector('[data-container-type]');
        const cassetteFields = row.querySelector('[data-cassette-fields]');
        if (!type || !cassetteFields) return;
        const isCassette = type.value === 'cassette';
        cassetteFields.hidden = !isCassette;
        cassetteFields.querySelectorAll('select,input').forEach((field) => {
            field.disabled = !isCassette;
            if (isCassette && field.dataset.cassetteRequired === '1') field.required = true;
            else field.required = false;
        });
    };
    const syncAllContainers = () => document.querySelectorAll('[data-container-row]').forEach(syncContainerRow);
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-container-type]')) syncContainerRow(event.target.closest('[data-container-row]'));
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-repeat-add]')) setTimeout(syncAllContainers, 0);
    });
    syncAllContainers();

    let sealTimer = null;
    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-seal-input]');
        if (!input) return;
        const output = input.parentElement.querySelector('[data-seal-result]');
        if (!output) return;
        clearTimeout(sealTimer);
        const value = input.value.trim();
        output.textContent = '';
        output.className = 'field-hint';
        if (!/^\d+$/.test(value)) return;
        sealTimer = setTimeout(async () => {
            try {
                const endpoint = input.dataset.sealEndpoint;
                const response = await fetch(endpoint + '?seal=' + encodeURIComponent(value), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
                const data = await response.json();
                output.textContent = data.available ? '✓ ' + data.message : '⚠ ' + data.message + (data.custody_number ? ' · Verwahrnr. ' + data.custody_number : '');
                output.className = 'field-hint ' + (data.available ? 'ok' : 'error');
            } catch (_) {
                output.textContent = 'Live-Prüfung derzeit nicht verfügbar – serverseitige Prüfung erfolgt beim Speichern.';
                output.className = 'field-hint';
            }
        }, 350);
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-receiver-subject]')) return;
        const foreign = document.querySelector('[data-foreign-receiver]');
        if (foreign) foreign.hidden = event.target.value === '1';
    });
})();

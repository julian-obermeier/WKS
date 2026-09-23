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

    const chartColors = () => {
        const css = getComputedStyle(document.documentElement);
        return {
            text: css.getPropertyValue('--text').trim() || '#152033',
            border: css.getPropertyValue('--border').trim() || '#dce3ea',
            primary: css.getPropertyValue('--primary').trim() || '#174f7c',
            success: css.getPropertyValue('--success').trim() || '#237a4b',
            warning: css.getPropertyValue('--warning').trim() || '#916400',
            danger: css.getPropertyValue('--danger').trim() || '#a52b35'
        };
    };
    const drawChart = (canvas) => {
        const type = canvas.dataset.chart;
        let labels = [], values = [];
        try { labels = JSON.parse(canvas.dataset.labels || '[]'); values = JSON.parse(canvas.dataset.values || '[]'); } catch (_) { return; }
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(300, canvas.clientWidth || 600);
        const height = type === 'pie' ? 300 : 280;
        canvas.width = width * dpr; canvas.height = height * dpr;
        canvas.style.height = height + 'px';
        const ctx = canvas.getContext('2d'); ctx.scale(dpr,dpr);
        const colors = chartColors(); ctx.font = '12px system-ui'; ctx.fillStyle = colors.text; ctx.strokeStyle = colors.border;
        if (!values.length) { ctx.fillText('Keine Daten im gewählten Zeitraum.', 12, 30); return; }
        if (type === 'bar') {
            const max = Math.max(...values,1), left=45, bottom=45, top=15, plotH=height-bottom-top, barW=Math.max(8,(width-left-20)/values.length*.62);
            ctx.beginPath();ctx.moveTo(left,top);ctx.lineTo(left,height-bottom);ctx.lineTo(width-10,height-bottom);ctx.stroke();
            values.forEach((v,i)=>{const x=left+12+i*((width-left-20)/values.length);const h=plotH*(v/max);ctx.fillStyle=colors.primary;ctx.fillRect(x,height-bottom-h,barW,h);ctx.fillStyle=colors.text;ctx.fillText(String(v),x,height-bottom-h-5);ctx.save();ctx.translate(x+barW/2,height-bottom+7);ctx.rotate(-.55);ctx.fillText(String(labels[i]||''),0,0);ctx.restore();});
        } else if (type === 'line') {
            const max=Math.max(...values,1),left=45,bottom=42,top=18,plotW=width-left-15,plotH=height-bottom-top;
            ctx.beginPath();ctx.moveTo(left,top);ctx.lineTo(left,height-bottom);ctx.lineTo(width-10,height-bottom);ctx.stroke();
            ctx.strokeStyle=colors.primary;ctx.lineWidth=2;ctx.beginPath();
            values.forEach((v,i)=>{const x=left+(values.length===1?0:i*plotW/(values.length-1));const y=height-bottom-(v/max)*plotH;if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);});
            ctx.stroke();ctx.fillStyle=colors.primary;values.forEach((v,i)=>{const x=left+(values.length===1?0:i*plotW/(values.length-1));const y=height-bottom-(v/max)*plotH;ctx.beginPath();ctx.arc(x,y,3,0,Math.PI*2);ctx.fill();});
            ctx.fillStyle=colors.text;if(labels.length){ctx.fillText(String(labels[0]),left,height-12);ctx.fillText(String(labels[labels.length-1]),Math.max(left,width-90),height-12);}
        } else if (type === 'pie') {
            const total=values.reduce((a,b)=>a+b,0)||1,cx=Math.min(width*.38,150),cy=140,r=105;
            const palette=[colors.primary,colors.success,colors.warning,colors.danger,'#6f5aa8','#3c8196','#8f6c42','#55705f'];
            let angle=-Math.PI/2;
            values.forEach((v,i)=>{const next=angle+(v/total)*Math.PI*2;ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,r,angle,next);ctx.closePath();ctx.fillStyle=palette[i%palette.length];ctx.fill();angle=next;});
            ctx.font='11px system-ui';labels.slice(0,10).forEach((label,i)=>{const y=35+i*23;ctx.fillStyle=palette[i%palette.length];ctx.fillRect(width*.62,y-9,10,10);ctx.fillStyle=colors.text;ctx.fillText(String(label)+' ('+values[i]+')',width*.62+16,y);});
        }
    };
    document.querySelectorAll('canvas[data-chart]').forEach(drawChart);

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const manifest = document.querySelector('link[rel="manifest"]');
            const swUrl = manifest ? new URL('service-worker.js', manifest.href).href : '/service-worker.js';
            navigator.serviceWorker.register(swUrl).catch(() => {});
        });
    }

    document.querySelectorAll('form[data-autosave]').forEach((autosaveForm) => {
        let changed = false;
        let saving = false;
        let timer = null;
        const mark = () => {
            changed = true;
            clearTimeout(timer);
            timer = setTimeout(save, 2500);
        };
        const save = async () => {
            if (!changed || saving) return;
            saving = true;
            const params = new URLSearchParams();
            Array.from(autosaveForm.elements).forEach((field) => {
                if (!field.name || field.disabled || field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
                if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
                if (field.tagName === 'SELECT' && field.multiple) {
                    Array.from(field.selectedOptions).forEach((opt) => params.append(field.name, opt.value));
                } else {
                    params.append(field.name, field.value);
                }
            });
            params.set('module', autosaveForm.dataset.autosaveModule || '');
            params.set('context_key', autosaveForm.dataset.autosaveContext || 'new');
            try {
                const response = await fetch(autosaveForm.dataset.autosaveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
                    body: params.toString(),
                    credentials: 'same-origin'
                });
                if (response.ok) changed = false;
            } catch (_) {
                // Nicht blockierend: reguläres Speichern bleibt möglich.
            } finally {
                saving = false;
            }
        };
        autosaveForm.addEventListener('input', mark);
        autosaveForm.addEventListener('change', mark);
        autosaveForm.addEventListener('submit', () => { changed = false; clearTimeout(timer); });
        window.addEventListener('pagehide', () => { if (changed) save(); });
        setInterval(save, 20000);
    });
})();

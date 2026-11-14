(() => {
    const english = document.documentElement.lang === 'en';
    const t = (es, en) => english ? en : es;
    // Limita cada grupo de selección a un registro para abrir su formulario de edición.
    document.querySelectorAll('[data-selection]').forEach(input => {
        input.addEventListener('change', () => {
            if (!input.checked) return;
            document.querySelectorAll('[data-selection]').forEach(other => {
                if (other !== input && other.dataset.selection === input.dataset.selection) other.checked = false;
            });
        });
    });
    document.querySelectorAll('[data-edit-selection]').forEach(button => {
        button.addEventListener('click', () => {
            const selected = Array.from(document.querySelectorAll('[data-selection]:checked')).find(input => input.dataset.selection === button.dataset.editSelection);
            if (!selected) { window.alert(button.dataset.selectionMessage); return; }
            const target = new URL(button.dataset.editUrl, window.location.href);
            target.searchParams.set('id', selected.value);
            window.location.assign(target);
        });
    });
    // Las vistas previas usan URL temporales del navegador; se liberan al cambiar o abandonar la página.
    const previews = new Map();
    document.querySelectorAll('[data-image-preview]').forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const preview = document.getElementById(input.dataset.imagePreview);
            if (!file || !preview) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                input.value = '';
                window.alert(t('Seleccione una imagen JPG, PNG o WebP de hasta 2 MB.', 'Select a JPG, PNG or WebP image up to 2 MB.'));
                return;
            }
            if (previews.has(input)) URL.revokeObjectURL(previews.get(input));
            const url = URL.createObjectURL(file);
            previews.set(input, url);
            const image = document.createElement('img');
            image.alt = t('Vista previa de la imagen', 'Image preview');
            image.src = url;
            preview.replaceChildren(image);
        });
    });
    window.addEventListener('pagehide', () => previews.forEach(url => URL.revokeObjectURL(url)));
    // Reúne los errores del formulario antes del POST; PHP vuelve a validar los datos recibidos.
    document.querySelectorAll('[data-admin-form]').forEach(form => {
        if (!form.querySelector('button[type="submit"], button:not([type])') && !form.id) return;
        const message = form.querySelector('[data-form-error]');
        form.noValidate = true;
        const clear = () => { if (message) { message.textContent = ''; message.classList.remove('show'); } };
        form.addEventListener('input', clear);
        form.addEventListener('change', clear);
        form.addEventListener('submit', event => {
            clear();
            let invalid = null;
            let error = '';
            for (const field of form.querySelectorAll('input:not([type="hidden"]),select,textarea')) {
                if (field.readOnly || field.disabled) continue;
                field.setCustomValidity('');
                if (field.required && !field.value.trim()) {
                    invalid = field;
                    error = t('Complete todos los campos obligatorios.', 'Complete all required fields.');
                    break;
                }
                if (!field.checkValidity()) { invalid = field; error = field.validationMessage; break; }
            }
            const password = form.querySelector('[name="password"]');
            const confirmation = form.querySelector('[name="confirmacion"]');
            if (!invalid && password && confirmation && password.value !== confirmation.value) {
                invalid = confirmation;
                error = t('Las contraseñas deben coincidir.', 'Passwords must match.');
            }
            const start = form.querySelector('[name="inicio"]');
            const end = form.querySelector('[name="fin"]');
            if (!invalid && start && end && end.value < start.value) {
                invalid = end;
                error = t('La finalización no puede ser anterior al inicio de la oferta.', 'The end date cannot precede the offer start date.');
            }
            if (invalid) {
                event.preventDefault();
                if (message) { message.textContent = error; message.classList.add('show'); }
                invalid.focus();
            }
        });
    });
    // Actualiza la vista previa del descuento; el precio definitivo se calcula en el servidor.
    const offer = document.querySelector('[data-price-base]');
    if (offer) {
        const discount = offer.querySelector('[name="descuento"]');
        const preview = document.getElementById('precioOfertaPreview');
        const update = () => {
            const price = Number(offer.dataset.priceBase) * (1 - Number(discount.value || 0) / 100);
            preview.textContent = new Intl.NumberFormat(offer.dataset.locale || 'es-CR', { style: 'currency', currency: 'CRC', maximumFractionDigits: 0 }).format(Math.max(0, price));
        };
        discount.addEventListener('input', update);
        update();
    }
    // Lleva el foco al diálogo y permite recorrer sus controles con Tab o salir con Escape.
    const modal = document.querySelector('[data-modal-layer]');
    if (modal) {
        document.body.classList.add('admin-modal-open');
        const title = modal.querySelector('h2');
        title?.setAttribute('tabindex', '-1');
        title?.focus({ preventScroll: true });
        modal.addEventListener('keydown', event => {
            const controls = Array.from(modal.querySelectorAll('a[href],button,input,select,textarea,[tabindex="0"]')).filter(control => !control.disabled && control.type !== 'hidden' && control.getClientRects().length);
            const close = modal.querySelector('.offer-modal-close, .inventory-modal-actions a, .users-modal-actions a');
            if (event.key === 'Escape' && close) { event.preventDefault(); window.location.assign(close.href); }
            if (event.key === 'Tab' && controls.length) {
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && (document.activeElement === first || document.activeElement === title)) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
    }
})();

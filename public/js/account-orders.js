// Valida los formularios de cuenta y controla la edición y confirmación de pedidos.
// Marca como inválida la confirmación cuando no coincide con la contraseña escrita.
document.querySelectorAll('form[data-password-match]').forEach((form) => {
    const password = form.querySelector('input[name="password"]');
    const confirmation = form.querySelector('input[name="confirmacion"]');
    const helper = form.querySelector('[data-confirmation-error]');
    if (!password || !confirmation) return;

    const validate = () => {
        const mismatch = password.value !== confirmation.value;
        confirmation.setCustomValidity(mismatch ? form.dataset.passwordMatch : '');
        if (helper) helper.hidden = !mismatch || confirmation.value === '';
    };
    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
    form.addEventListener('submit', (event) => {
        validate();
        if (!form.reportValidity()) event.preventDefault();
    });
});

// Permite revisar la fotografía antes del envío y libera la URL temporal cuando deja de usarse.
const profileImageInput = document.querySelector('#profile-image-input');
if (profileImageInput) {
    const box = document.querySelector('[data-profile-image-box]');
    const fallback = box?.querySelector('[data-profile-fallback]');
    let temporaryImageUrl;
    profileImageInput.addEventListener('change', () => {
        const file = profileImageInput.files?.[0];
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || !box) return;
        let preview = box.querySelector('[data-profile-preview]');
        if (!preview) {
            preview = document.createElement('img');
            preview.dataset.profilePreview = '';
            preview.alt = document.documentElement.lang === 'en' ? 'Profile preview' : 'Vista previa del perfil';
            box.prepend(preview);
        }
        if (temporaryImageUrl) URL.revokeObjectURL(temporaryImageUrl);
        temporaryImageUrl = URL.createObjectURL(file);
        preview.src = temporaryImageUrl;
        preview.hidden = false;
        if (fallback) fallback.hidden = true;
    });
    window.addEventListener('pagehide', () => {
        if (temporaryImageUrl) URL.revokeObjectURL(temporaryImageUrl);
    });
}

// Permite seleccionar un solo pedido para cambiar su estado.
const orderChecks = document.querySelectorAll('input[name="idPedidoSeleccionado"]');
orderChecks.forEach((selected) => selected.addEventListener('change', () => {
    if (selected.checked) orderChecks.forEach((check) => {
        if (check !== selected) check.checked = false;
    });
}));

document.querySelector('[data-edit-order]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const selected = document.querySelector('input[name="idPedidoSeleccionado"]:checked');
    if (!selected) {
        window.alert(button.dataset.message);
        return;
    }
    const target = new URL(button.dataset.url, window.location.href);
    target.searchParams.set('id', selected.value);
    window.location.assign(target.toString());
});

// Rechaza una selección vacía o equivalente al estado visible antes de enviar la actualización.
document.querySelectorAll('form[data-order-state]').forEach((form) => {
    const selector = form.querySelector('select[name="estado"]');
    const error = form.querySelector('[data-order-error]');
    if (!selector || !error) return;
    selector.addEventListener('change', () => {
        error.textContent = '';
        selector.setCustomValidity('');
    });
    form.addEventListener('submit', (event) => {
        const selectedCode = selector.selectedOptions[0]?.dataset.code;
        const message = !selector.value ? form.dataset.stateRequired
            : selectedCode === form.dataset.orderState ? form.dataset.stateEqual : '';
        selector.setCustomValidity(message);
        error.textContent = message;
        if (message) {
            event.preventDefault();
            selector.reportValidity();
        }
    });
});

// Mantiene la navegación con Tab dentro del diálogo abierto para no enfocar controles del fondo.
const accountDialog = document.querySelector('[role="dialog"][aria-modal="true"]');
if (accountDialog) {
    const controls = () => Array.from(accountDialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex="0"]'))
        .filter((element) => !element.disabled && element.getClientRects().length > 0);
    const title = accountDialog.querySelector('h2');
    if (title) {
        title.tabIndex = -1;
        title.focus({preventScroll: true});
    }
    accountDialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const list = controls();
        const firstControl = list[0];
        const lastControl = list[list.length - 1];
        if (event.shiftKey && document.activeElement === firstControl) {
            event.preventDefault(); lastControl?.focus();
        } else if (!event.shiftKey && document.activeElement === lastControl) {
            event.preventDefault(); firstControl?.focus();
        }
    });
}

// Mantiene el aviso disponible mientras se lee con el puntero o se navega con el teclado.
function showNotice(notice) {
    let timeout;
    const pause = () => window.clearTimeout(timeout);
    const close = () => {
        pause();
        notice.hidden = true;
    };
    const schedule = () => {
        pause();
        if (!notice.hidden && !notice.matches(':hover, :focus-within')) {
            timeout = window.setTimeout(close, 4500);
        }
    };
    notice.hidden = false;
    notice.querySelector('[data-close-notice]').addEventListener('click', close);
    notice.addEventListener('mouseenter', pause);
    notice.addEventListener('mouseleave', schedule);
    notice.addEventListener('focusin', pause);
    notice.addEventListener('focusout', () => window.setTimeout(schedule, 0));
    schedule();
}

document.querySelectorAll('[data-bookguest-notice]').forEach(showNotice);

document.querySelectorAll('select[data-navigate]').forEach((select) => {
    select.addEventListener('change', () => window.location.assign(select.value));
});

const accountMenu = document.querySelector('[data-account-menu]');
if (accountMenu) {
    const trigger = accountMenu.querySelector('button[aria-controls]');
    const panel = document.getElementById(trigger.getAttribute('aria-controls'));
    const setOpen = (open) => {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
    };

    trigger.addEventListener('click', () => setOpen(panel.hidden));
    document.addEventListener('click', (event) => {
        if (!accountMenu.contains(event.target)) setOpen(false);
    });
    accountMenu.addEventListener('focusout', (event) => {
        if (!accountMenu.contains(event.relatedTarget)) setOpen(false);
    });
    accountMenu.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || panel.hidden) return;
        event.preventDefault();
        setOpen(false);
        trigger.focus();
    });
}

// Ajusta la cantidad del detalle entre uno y las existencias indicadas por el servidor.
document.querySelectorAll('[data-quantity]').forEach((control) => {
    const input = control.querySelector('input');
    control.querySelectorAll('button[data-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const current = Number.isFinite(input.valueAsNumber) ? input.valueAsNumber : 1;
            input.value = Math.min(Number(input.max), Math.max(Number(input.min), current + Number(button.dataset.step)));
        });
    });
});

document.querySelectorAll('[data-scroll-track]').forEach((button) => {
    button.addEventListener('click', () => {
        const track = document.getElementById(button.dataset.scrollTrack);
        const direction = Number(button.dataset.direction);
        const end = track.scrollWidth - track.clientWidth;
        let target = track.scrollLeft + direction * track.clientWidth;
        if (direction > 0 && track.scrollLeft >= end - 1) {
            target = 0;
        } else if (direction < 0 && track.scrollLeft <= 1) {
            target = end;
        }
        track.scrollTo({ left: target, behavior: 'smooth' });
    });
});

document.querySelectorAll('[data-countdown]').forEach((timer) => {
    const digits = timer.querySelectorAll('strong');
    const update = () => {
        const remaining = Math.max(0, Math.floor((Number(timer.dataset.countdown) - Date.now()) / 1000));
        const units = [Math.floor(remaining / 86400), Math.floor(remaining / 3600) % 24,
            Math.floor(remaining / 60) % 60, remaining % 60];
        digits.forEach((digit, index) => { digit.textContent = String(units[index]).padStart(2, '0'); });
    };
    update();
    window.setInterval(update, 1000);
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

// Deshabilita el botón al confirmar la compra y lo restaura si se regresa con el navegador.
document.querySelectorAll('form[data-once]').forEach((form) => {
    const button = form.querySelector('button');
    form.addEventListener('submit', () => {
        button.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
    });
});

// Sustituye un avatar que no pudo cargarse por el icono de usuario.
document.querySelectorAll('[data-avatar-image]').forEach((image) => {
    const fallback = () => {
        image.hidden = true;
        const sibling = image.nextElementSibling;
        if (sibling) sibling.hidden = false;
    };
    image.addEventListener('error', fallback);
    if (image.complete && !image.naturalWidth) fallback();
});

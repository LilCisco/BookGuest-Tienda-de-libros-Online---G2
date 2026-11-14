const form = document.querySelector('#login-form');
const submit = form.querySelector('button[type="submit"]');
const csrf = form.elements.csrf;
const message = document.querySelector('#login-error');
const password = document.querySelector('#password');
const toggle = document.querySelector('#btnMostrarPassword');

function updatePasswordButton() {
    const visible = password.type === 'text';
    const icon = toggle.querySelector('i');
    icon.classList.toggle('fa-eye', !visible);
    icon.classList.toggle('fa-eye-slash', visible);
    toggle.setAttribute('aria-pressed', String(visible));

    const english = document.documentElement.lang === 'en';
    const label = english
        ? (visible ? toggle.dataset.hideLabelEn : toggle.dataset.showLabelEn)
        : (visible ? toggle.dataset.hideLabel : toggle.dataset.showLabel);
    toggle.setAttribute('aria-label', label);
    toggle.title = label;
}

toggle.addEventListener('click', () => {
    password.type = password.type === 'password' ? 'text' : 'password';
    updatePasswordButton();
});

function showError(text) {
    const english = document.documentElement.lang === 'en';
    message.textContent = text || (english ? form.dataset.errorEn : form.dataset.error);
    message.hidden = false;
}

// Solicita el idioma y el token de la sesión antes de permitir el envío del formulario HTML.
async function loadAccess() {
    const response = await fetch(form.action, { headers: { Accept: 'application/json' } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error);
    if (data.redirect) {
        window.location.replace(data.redirect);
        return false;
    }

    document.documentElement.lang = data.lang;
    if (data.lang === 'en') {
        document.querySelectorAll('[data-en]').forEach((element) => {
            element.textContent = element.dataset.en;
        });
        const accountLinks = document.querySelector('.auth-links');
        accountLinks.firstChild.textContent = accountLinks.dataset.promptEn + ' ';
        password.placeholder = password.dataset.placeholderEn;
        updatePasswordButton();
    }

    csrf.value = data.csrf;
    if (data.notice) {
        const notice = document.querySelector('#login-notice');
        notice.querySelector('.interface-notice-body p').textContent = data.notice;
        showNotice(notice);
    }
    return true;
}

loadAccess().then((ready) => {
    submit.disabled = !ready;
}).catch(() => {
    showError();
    submit.disabled = false;
});

// Envía los datos por POST y conserva el formulario visible si PHP rechaza el acceso.
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    submit.disabled = true;
    message.hidden = true;
    try {
        if (!csrf.value && !await loadAccess()) return;
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });
        const data = await response.json();
        if (!response.ok) {
            showError(data.error);
            return;
        }
        window.location.assign(data.redirect);
    } catch {
        showError();
    } finally {
        submit.disabled = false;
    }
});

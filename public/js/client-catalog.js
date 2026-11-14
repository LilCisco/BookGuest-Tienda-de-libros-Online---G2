// Actualiza el tiempo restante y recarga al vencer la oferta para volver a consultar su precio.
document.querySelectorAll('[data-offer-end]').forEach((timer) => {
    const digits = timer.querySelectorAll('[data-offer-digit]');
    const end = Number(timer.dataset.offerEnd);
    if (!Number.isFinite(end) || end <= 0) return;
    let expired = false;
    const update = () => {
        const remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
        const units = [Math.floor(remaining / 86400), Math.floor(remaining / 3600) % 24,
            Math.floor(remaining / 60) % 60, remaining % 60];
        digits.forEach((digit, index) => { digit.textContent = String(units[index]).padStart(2, '0'); });
        if (remaining === 0 && !expired) {
            expired = true;
            window.setTimeout(() => window.location.reload(), 1200);
        }
    };
    update();
    window.setInterval(update, 1000);
});

// Muestra el título como respaldo si la descarga de una portada falla.
document.querySelectorAll('.book-cover, .product-image, .favorite-cover').forEach((image) => {
    const showFallback = () => {
        const fallback = document.createElement('div');
        fallback.className = image.classList.contains('product-image') ? 'product-placeholder'
            : (image.classList.contains('favorite-cover') ? 'favorite-cover-empty' : 'book-cover-placeholder visible');
        fallback.textContent = image.alt;
        image.replaceWith(fallback);
    };
    image.addEventListener('error', showFallback, { once: true });
    if (image.complete && image.naturalWidth === 0) showFallback();
});
// Cambia el grupo de ofertas con los botones, las flechas del teclado o un gesto horizontal.
document.querySelectorAll('[data-offers-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('.offers-slides');
    const slides = [...track.children];
    if (slides.length < 2) return;

    let current = 0;
    let moving = false;
    let touchStart;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const move = (direction) => {
        if (moving) return;
        const previous = slides[current];
        current = (current + direction + slides.length) % slides.length;
        const next = slides[current];
        next.hidden = false;
        if (reducedMotion.matches) {
            previous.hidden = true;
            return;
        }

        moving = true;
        previous.inert = true;
        previous.classList.add('offers-slide-leaving');
        const timing = { duration: 600, easing: 'ease-in-out' };
        next.animate([{ transform: `translateX(${direction * 100}%)` }, { transform: 'translateX(0)' }], timing);
        const transition = previous.animate([{ transform: 'translateX(0)' }, { transform: `translateX(${-direction * 100}%)` }], timing);
        transition.onfinish = () => {
            previous.hidden = true;
            previous.inert = false;
            previous.classList.remove('offers-slide-leaving');
            moving = false;
        };
    };

    carousel.querySelectorAll('[data-offers-step]').forEach((button) => {
        button.addEventListener('click', () => move(Number(button.dataset.offersStep)));
    });
    carousel.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        move(event.key === 'ArrowLeft' ? -1 : 1);
    });
    track.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'touch') touchStart = event.clientX;
    });
    track.addEventListener('pointerup', (event) => {
        if (event.pointerType !== 'touch' || touchStart === undefined) return;
        const distance = event.clientX - touchStart;
        touchStart = undefined;
        if (Math.abs(distance) > 40) move(distance > 0 ? -1 : 1);
    });
    track.addEventListener('pointercancel', () => { touchStart = undefined; });
    window.setInterval(() => {
        if (!document.hidden && !carousel.matches(':hover, :focus-within')) move(1);
    }, 5000);
});

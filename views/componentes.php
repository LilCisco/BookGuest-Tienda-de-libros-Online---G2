<?php

function icon(string $name): string
{
    $icons = [
        'brand' => 'solid fa-book-bookmark', 'search' => 'solid fa-magnifying-glass',
        'right' => 'solid fa-arrow-right', 'left' => 'solid fa-arrow-left',
        'heart' => 'solid fa-heart', 'eye' => 'regular fa-eye', 'cart' => 'solid fa-cart-shopping',
        'user' => 'solid fa-user', 'home' => 'solid fa-house', 'inventory' => 'solid fa-dolly',
        'users' => 'regular fa-circle-user', 'orders' => 'regular fa-clipboard',
        'receipt' => 'solid fa-receipt', 'products' => 'regular fa-rectangle-list',
        'layers' => 'solid fa-layer-group', 'stock' => 'solid fa-boxes-stacked',
        'coins' => 'solid fa-coins', 'edit' => 'regular fa-pen-to-square',
        'settings' => 'solid fa-gear', 'logout' => 'solid fa-arrow-right-from-bracket',
    ];
    return '<i class="icon fa-' . ($icons[$name] ?? 'solid fa-circle') . '" aria-hidden="true"></i>';
}

function linkTo(string $page, string $label, array $params = [], string $class = ''): string
{
    return '<a class="' . e($class) . '" href="' . e(url($page, $params)) . '">' . e($label) . '</a>';
}

function hidden(string $name, mixed $value): string
{
    return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
}

/**
 * Incluye el token CSRF, la acción y sus identificadores en los formularios POST.
 */
function actionFields(string $action, array $fields = []): string
{
    $html = token() . hidden('accion', $action);
    foreach ($fields as $key => $value) {
        $html .= hidden($key, $value);
    }
    return $html;
}

function input(string $name, string $label, string $type, mixed $value, int $max = 0, bool $required = true): string
{
    $limit = $max > 0 ? ' maxlength="' . $max . '"' : '';
    return '<label>' . e($label) . '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($value)
        . '"' . $limit . ($required ? ' required' : '') . '></label>';
}

function heading(string $title, string $summary = ''): void
{
    echo '<div class="section-heading"><h1>' . e($title) . '</h1>';
    if ($summary !== '') {
        echo '<p>' . e($summary) . '</p>';
    }
    echo '</div>';
}

/**
 * Presenta el mensaje guardado en sesión como una notificación sobre la vista.
 */
function renderNotice(?array $notice): void
{
    if ($notice) {
        $error = $notice[1] === 'error';
        echo '<div class="bookguest-notices" aria-live="polite" aria-atomic="true">'
            . '<div class="interface-notice" role="' . ($error ? 'alert' : 'status') . '" data-bookguest-notice>'
            . '<div class="interface-notice-header ' . ($error ? 'notice-header-error' : 'notice-header-success') . '">'
            . '<i class="fa-solid ' . ($error ? 'fa-triangle-exclamation' : 'fa-square-check') . '" aria-hidden="true"></i>'
            . '<strong>' . e($error ? tr('Error', 'Error') : tr('Atención', 'Notice')) . '</strong></div>'
            . '<div class="interface-notice-body"><p>' . e($notice[0]) . '</p><div class="interface-notice-actions">'
            . '<button type="button" data-close-notice>' . e(tr('Cerrar', 'Close'))
            . '</button></div></div></div></div>';
    }
}

function accountFieldsHtml(array $account, bool $profile = false): void
{
    echo '<div class="fields">';
    echo input('nombre', tr('Nombre', 'First name'), 'text', $account['nombre'] ?? '', 50);
    echo input('apellidos', tr('Apellidos', 'Last name'), 'text', $account['apellidos'] ?? '', 75);
    echo input('email', tr('Correo electrónico', 'Email address'), 'email', $account['email'] ?? '', 75);
    if ($profile) {
        echo input('telefono', tr('Teléfono', 'Phone'), 'tel', $account['telefono'] ?? '', 25, false);
        echo input('direccion', tr('Dirección', 'Address'), 'text', $account['direccion'] ?? '', 255, false);
    }
    echo '</div>';
}

function passwordFieldsHtml(bool $required): void
{
    echo '<div class="fields">';
    echo input('password', $required ? tr('Contraseña', 'Password') : tr('Nueva contraseña (opcional)', 'New password (optional)'),
        'password', '', 72, $required);
    echo input('confirmacion', tr('Confirmar contraseña', 'Confirm password'), 'password', '', 72, $required);
    echo '</div>';
}

function cover(array $book): void
{
    $image = imageUrl($book['ruta_imagen']);
    if ($image) {
        echo '<img class="cover" loading="lazy" src="' . e($image) . '" alt="' . e($book['titulo']) . '">';
        return;
    }
    echo '<div class="cover cover-placeholder">' . e($book['titulo']) . '</div>';
}

function statusBadge(string $label, bool $active): string
{
    return '<span class="badge ' . ($active ? 'positive' : 'neutral') . '">' . e($label) . '</span>';
}

function searchBox(string $page, string $value, string $label): void
{
    echo '<form method="get" action="index.php" class="search">'
        . hidden('p', $page) . input('q', $label, 'search', $value, 150, false)
        . '<button>' . e(tr('Buscar', 'Search')) . '</button></form>';
}

/**
 * Recorta el listado de la página solicitada y conserva los filtros en los enlaces de paginación.
 */
function pageItems(array $items, string $page, array $filters = [], int $size = 12): array
{
    $last = max(1, (int) ceil(count($items) / $size));
    $current = min($last, max(1, (int) textInput($_GET, 'pagina')));
    $pagination = '';
    if ($last > 1) {
        $pagination = '<nav class="pagination" aria-label="' . e(tr('Paginación', 'Pagination')) . '">';
        if ($current > 1) {
            $pagination .= linkTo($page, tr('Anterior', 'Previous'), ['pagina' => $current - 1] + $filters);
        }
        $pagination .= '<span>' . $current . ' / ' . $last . '</span>';
        if ($current < $last) {
            $pagination .= linkTo($page, tr('Siguiente', 'Next'), ['pagina' => $current + 1] + $filters);
        }
        $pagination .= '</nav>';
    }
    return [array_slice($items, ($current - 1) * $size, $size), $pagination];
}

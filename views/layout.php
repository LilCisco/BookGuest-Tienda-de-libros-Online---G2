<?php
$account = $account ?? null;
$isAdmin = ($account['rol'] ?? null) === ADMIN;
$lang = $_SESSION['lang'] ?? 'es';
$localeParams = array_intersect_key($_GET, array_flip(['id', 'q', 'categoria', 'estado', 'pagina', 'promocion', 'editar', 'filtros', 'seleccionar', 'confirmado', 'rol']));
$avatar = $account ? imageUrl($account['ruta_imagen']) : null;
$navAdmin = [
    'dashboard' => ['home', 'Dashboard'],
    'inventario' => ['inventory', tr('Inventario', 'Inventory')],
    'usuarios' => ['users', tr('Usuarios', 'Users')],
    'pedidos-admin' => ['orders', tr('Pedidos', 'Orders')],
    'ofertas' => ['products', tr('Productos', 'Products')],
];
$activePage = match ($page) {
    'editar-libro' => 'inventario', 'editar-usuario' => 'usuarios',
    'pedido-admin' => 'pedidos-admin', 'pedido' => 'pedidos',
    'factura', 'compra-confirmada' => 'carrito', default => $page,
};
$pageClass = match ($page) {
    'favoritos' => 'favorites-page', 'perfil' => $isAdmin ? 'admin-profile-page' : 'profile-page',
    'pedidos', 'pedido' => 'customer-orders-page', 'factura' => 'checkout-page',
    'compra-confirmada' => 'purchase-page', default => '',
};
// Combina el tema del rol con los estilos de la pantalla; layout.css se aplica después de ambos.
$sharedStyle = $account ? ($isAdmin ? 'admin' : 'cliente') : 'auth';
$pageStyle = in_array($page, ['inicio', 'catalogo', 'libro', 'favoritos'], true) ? 'client-catalog'
    : (in_array($page, ['dashboard', 'inventario', 'editar-libro', 'ofertas', 'usuarios', 'editar-usuario'], true) ? 'admin-panels' : 'account-orders');

/**
 * Añade la fecha de modificación a la URL del recurso para que el navegador cargue sus cambios.
 */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/' . $path;
    return $path . '?v=' . (is_file($file) ? filemtime($file) : '1');
}

function accountAvatar(array $account, ?string $avatar, string $prefix): void
{
    if ($avatar) {
        echo '<img src="' . e($avatar) . '" alt="' . e($account['nombre'] . ' ' . $account['apellidos']) . '" class="' . $prefix . '-avatar-image" data-avatar-image>';
    }
    echo '<span class="' . $prefix . '-avatar-fallback"' . ($avatar ? ' hidden' : '') . '>' . icon('user') . '</span>';
}

/**
 * Genera el menú de perfil y el formulario de cierre de sesión para el rol de la cuenta.
 */
function accountMenu(array $account, ?string $avatar, string $prefix): void
{
    $label = tr('Mi cuenta', 'My account');
    $menuId = $prefix . '-account-menu';
    ?>
    <div class="account-menu" data-account-menu>
        <button type="button" class="<?= $prefix ?>-avatar-link" aria-controls="<?= $menuId ?>" aria-expanded="false" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?php accountAvatar($account, $avatar, $prefix); ?></button>
        <div class="account-menu-panel <?= $prefix ?>-account-menu" id="<?= $menuId ?>" hidden>
            <div class="<?= $prefix ?>-account-summary">
                <?php accountAvatar($account, $avatar, $prefix); ?>
                <div class="<?= $prefix ?>-account-name"><?= e($account['nombre'] . ' ' . $account['apellidos']) ?></div>
            </div>
            <a href="<?= e(url('perfil')) ?>" class="<?= $prefix ?>-account-option"><?= icon('settings') ?><span><?= e(tr('Configuración', 'Settings')) ?></span></a>
            <form action="api/cuenta.php" method="post" class="<?= $prefix ?>-account-logout">
                <?= actionFields('salir') ?>
                <button type="submit" class="<?= $prefix ?>-account-option"><?= icon('logout') ?><span><?= e(tr('Cerrar sesión', 'Sign out')) ?></span></button>
            </form>
        </div>
    </div>
    <?php
}

function languageMenu(string $page, string $lang, array $params, string $prefix): void
{
    ?>
    <select class="<?= $prefix ?>-language" data-navigate aria-label="<?= e(tr('Idioma', 'Language')) ?>">
        <option value="<?= e(url($page, ['lang' => 'es'] + $params)) ?>"<?= $lang === 'es' ? ' selected' : '' ?>>Español</option>
        <option value="<?= e(url($page, ['lang' => 'en'] + $params)) ?>"<?= $lang === 'en' ? ' selected' : '' ?>>English</option>
    </select>
    <?php
}
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · BookGuest</title>
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css"
          integrity="sha384-EXatlQyrOJgDaM9/a74ArMzy7/2bTMSrZj8ID1IPeVmc3GncfCugefCFWSLj8JL/" crossorigin="anonymous">
    <?php if ($account || $page === 'registro-confirmado'): ?><link rel="stylesheet" href="<?= e(asset('css/original-' . $sharedStyle . '.css')) ?>"><?php endif ?>
    <link rel="stylesheet" href="<?= e(asset('css/estilos.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/' . $pageStyle . '.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
    <script defer src="<?= e(asset('js/app.js')) ?>"></script>
    <script defer src="<?= e(asset('js/' . $pageStyle . '.js')) ?>"></script>
</head>
<body class="<?= $account ? ($isAdmin ? 'admin' : 'store') : 'auth' ?> page-<?= e($page) ?>">
<?php if ($isAdmin): ?>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-top">
                <a class="admin-brand" href="<?= e(url('dashboard')) ?>"><?= icon('brand') ?><span>BookGuest</span></a>
                <nav class="admin-menu" aria-label="<?= e(tr('Administración', 'Administration')) ?>">
                    <?php foreach ($navAdmin as $target => [$symbol, $label]): ?>
                        <a href="<?= e(url($target)) ?>"<?= $activePage === $target ? ' class="active" aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span></a>
                    <?php endforeach ?>
                </nav>
            </div>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <?php $searchTarget = in_array($activePage, ['usuarios', 'pedidos-admin', 'ofertas'], true) ? $activePage : 'inventario'; ?>
                <form action="index.php" method="get" class="admin-search">
                    <?= hidden('p', $searchTarget) ?><?= icon('search') ?>
                    <input type="text" name="q" maxlength="150" placeholder="<?= e(tr('Buscar productos, usuarios, pedidos', 'Search products, users, orders')) ?>" aria-label="<?= e(tr('Buscar', 'Search')) ?>">
                </form>
                <div class="admin-top-icons"><?php languageMenu($page, $lang, $localeParams, 'admin'); accountMenu($account, $avatar, 'admin'); ?></div>
            </header>
            <main class="admin-content page-<?= e($page) ?> <?= e($pageClass) ?>" id="contenido">
                <?php renderNotice($notice); ?><?= $content ?>
            </main>
        </div>
    </div>
<?php elseif ($account): ?>
    <header class="client-header">
        <nav class="client-nav" aria-label="<?= e(tr('Navegación principal', 'Main navigation')) ?>">
            <a class="client-brand" href="<?= e(url('inicio')) ?>" aria-label="<?= e(tr('Inicio de BookGuest', 'BookGuest home')) ?>"><?= icon('brand') ?><span>BookGuest</span></a>
            <div class="client-menu"><a href="<?= e(url('inicio')) ?>"<?= $page === 'inicio' ? ' class="active" aria-current="page"' : '' ?>><?= e(tr('Inicio', 'Home')) ?></a></div>
            <?php languageMenu($page, $lang, $localeParams, 'client'); ?>
            <div class="client-icons">
                <?php foreach (['favoritos' => 'heart', 'pedidos' => 'receipt', 'carrito' => 'cart'] as $target => $symbol): ?>
                    <?php $label = tr($routes[$target][2], $routes[$target][3]); ?>
                    <a href="<?= e(url($target)) ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"<?= $page === 'favoritos' && $target === 'favoritos' ? ' class="active" aria-current="page"' : '' ?>><?= icon($symbol) ?></a>
                <?php endforeach ?>
                <?php accountMenu($account, $avatar, 'client'); ?>
            </div>
        </nav>
    </header>
    <main class="client-page page-<?= e($page) ?> <?= e($pageClass) ?>" id="contenido">
        <?php renderNotice($notice); ?><?= $content ?>
    </main>
    <footer class="client-footer"><?= e(tr('©Derechos reservados ', '©All rights reserved ') . date('Y')) ?></footer>
<?php else: ?>
    <main class="<?= match ($page) { 'registro' => 'register-wrapper', 'registro-confirmado' => 'confirmation-layout', default => 'auth-wrapper' } ?>" id="contenido"><?= $content ?></main>
<?php endif ?>
</body>
</html>

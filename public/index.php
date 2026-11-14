<?php
// Selecciona la vista y comprueba la sesión y el rol antes de generar su contenido.


require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/catalogo.php';
require_once dirname(__DIR__) . '/app/pedidos.php';
require_once dirname(__DIR__) . '/views/componentes.php';

// Cada entrada define el rol requerido, la vista PHP y el título en ambos idiomas.
$routes = [
    'login' => [null, null, 'Iniciar sesión', 'Sign in'],
    'registro' => [null, 'cuenta', 'Crear cuenta', 'Create an account'],
    'registro-confirmado' => [null, 'cuenta', 'Cuenta creada', 'Account created'],
    'perfil' => ['account', 'cuenta', 'Mi cuenta', 'My account'],
    'inicio' => [CLIENT, 'catalogo', 'Inicio', 'Home'],
    'catalogo' => [CLIENT, 'catalogo', 'Catálogo', 'Catalog'],
    'libro' => [CLIENT, 'catalogo', 'Detalle del libro', 'Book details'],
    'favoritos' => [CLIENT, 'catalogo', 'Favoritos', 'Favorites'],
    'carrito' => [CLIENT, 'compras', 'Mi carrito', 'My cart'],
    'factura' => [CLIENT, 'compras', 'Confirmar compra', 'Review purchase'],
    'compra-confirmada' => [CLIENT, 'compras', 'Compra confirmada', 'Purchase confirmed'],
    'pedidos' => [CLIENT, 'pedidos', 'Mis pedidos', 'My orders'],
    'pedido' => [CLIENT, 'pedidos', 'Detalle del pedido', 'Order details'],
    'dashboard' => [ADMIN, 'admin', 'Resumen', 'Overview'],
    'inventario' => [ADMIN, 'admin', 'Inventario', 'Inventory'],
    'editar-libro' => [ADMIN, 'admin', 'Ficha de inventario', 'Inventory record'],
    'ofertas' => [ADMIN, 'admin', 'Promociones', 'Promotions'],
    'usuarios' => [ADMIN, 'usuarios', 'Usuarios', 'Users'],
    'editar-usuario' => [ADMIN, 'usuarios', 'Ficha de usuario', 'User record'],
    'pedidos-admin' => [ADMIN, 'pedidos', 'Gestión de pedidos', 'Manage orders'],
    'pedido-admin' => [ADMIN, 'pedidos', 'Detalle del pedido', 'Order details'],
];

$page = textInput($_GET, 'p') ?: 'login';
$notice = $_SESSION['flash'] ?? null;
$language = textInput($_GET, 'lang');
if (in_array($language, ['es', 'en'], true)) {
    $_SESSION['lang'] = $language;
    $params = array_intersect_key($_GET, array_flip(['id', 'q', 'categoria', 'estado', 'pagina', 'promocion', 'editar', 'filtros', 'seleccionar', 'confirmado', 'rol']));
    go(isset($routes[$page]) ? $page : 'login', $params);
}

// El búfer permite reemplazar una vista fallida por un mensaje sin enviar HTML incompleto.
ob_start();
try {
    $account = user();
    if (!isset($routes[$page])) {
        throw new AppError(tr('Página inexistente.', 'Page not found.'), 404);
    }
    [$role, $view, $es, $en] = $routes[$page];
    $title = tr($es, $en);
    if ($role === null && $account) {
        go($account['rol'] === ADMIN ? 'dashboard' : 'inicio');
    }
    if ($role !== null && !$account) {
        go('login');
    }
    if ($role !== null && $role !== 'account') {
        requireRole($role);
    }
    if ($page === 'login') {
        header('Location: login.html', true, 303);
        exit;
    }
    require dirname(__DIR__) . '/views/' . $view . '.php';
    $content = ob_get_clean();
} catch (Throwable $error) {
    ob_end_clean();
    [$status, $message] = problem($error);
    http_response_code($status);
    $title = tr('BookGuest', 'BookGuest');
    $content = '<p class="notice error" role="alert">' . e($message) . '</p>';
}
require dirname(__DIR__) . '/views/layout.php';
unset($_SESSION['flash']);

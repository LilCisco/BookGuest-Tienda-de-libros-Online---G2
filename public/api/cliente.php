<?php
// Procesa favoritos, carrito y compras usando el identificador de la sesión, no un usuario enviado por el formulario.


require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/catalogo.php';
require_once dirname(__DIR__, 2) . '/app/pedidos.php';

$action = textInput($_POST, 'accion');
$id = (int) textInput($_POST, 'id');
$from = textInput($_POST, 'volver');
$page = match ($action) {
    'favorito' => in_array($from, ['favoritos', 'inicio', 'catalogo'], true) ? $from : 'libro',
    'comprar' => 'factura',
    default => 'carrito',
};
$params = match ($page) {
    'libro' => ['id' => max(1, (int) textInput($_POST, 'detalle') ?: $id)],
    'catalogo', 'favoritos' => array_intersect_key($_POST, array_flip(['q', 'categoria', 'pagina'])),
    default => [],
};
finishAction($page, $params, function () use ($action) {
    $account = requireRole(CLIENT);
    $userId = (int) $account['id_usuario'];
    if ($action === 'comprar') {
        $orderId = checkout($userId, $_POST);
        go('compra-confirmada', ['id' => $orderId]);
    }
    $bookId = integer($_POST, 'id');
    if ($action === 'favorito') {
        setFavorite($userId, $bookId, textInput($_POST, 'activo') === '1');
        return tr('Preferencia actualizada.', 'Preference updated.');
    }
    if (in_array($action, ['agregar', 'actualizar', 'quitar'], true)) {
        $quantity = $action === 'quitar' ? 0 : integer($_POST, 'cantidad', 1, 2147483647);
        updateCart($userId, $bookId, $action, $quantity);
        return tr('Carrito actualizado.', 'Cart updated.');
    }
    throw new AppError(tr('Operación inválida.', 'Invalid operation.'), 400);
});

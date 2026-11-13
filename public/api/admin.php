<?php
// Despacha las operaciones administrativas después de validar el POST y exigir ROLE_ADMIN.


require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/catalogo.php';
require_once dirname(__DIR__, 2) . '/app/pedidos.php';

$action = textInput($_POST, 'accion');
$id = max(0, (int) textInput($_POST, 'id'));
$page = match ($action) {
    'libro' => 'editar-libro',
    'oferta', 'finalizar' => 'ofertas',
    'usuario' => 'editar-usuario',
    'estado' => 'pedido-admin',
    default => 'dashboard',
};
finishAction($page, ['id' => $id], function () use ($action) {
    requireRole(ADMIN);
    $id = integer($_POST, 'id', 0);
    switch ($action) {
        case 'libro':
            $savedId = saveWithImage('libros', fn ($image) => saveBook($id, $_POST, $image));
            $_SESSION['admin_confirmation'] = ['kind' => 'libro', 'id' => $savedId, 'created' => $id === 0];
            go('inventario');
        case 'oferta':
            saveOffer($id, $_POST);
            flash(tr('Oferta guardada.', 'Offer saved.'));
            go('ofertas');
        case 'finalizar':
            transaction(function () use ($id) {
                lockBook($id);
                sql('UPDATE oferta SET activo = 0 WHERE id_oferta = ? AND id_libro = ?', [integer($_POST, 'oferta'), $id]);
            });
            flash(tr('Oferta finalizada.', 'Offer ended.'));
            go('ofertas');
        case 'usuario':
            $savedId = saveWithImage('perfiles', fn ($image) => saveAccount($_POST, $id, textInput($_POST, 'rol'), textInput($_POST, 'activo') === '1', $image));
            $_SESSION['admin_confirmation'] = ['kind' => 'usuario', 'id' => $savedId, 'created' => $id === 0];
            go('usuarios');
        case 'estado':
            setOrderState($id, textInput($_POST, 'estado'));
            go('pedido-admin', ['id' => $id, 'confirmado' => 1]);
        default:
            throw new AppError(tr('Operación inválida.', 'Invalid operation.'), 400);
    }
});

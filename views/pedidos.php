<?php
// Muestra las órdenes del cliente o la gestión administrativa; la consulta del cliente se limita a su cuenta.

$isAdminOrder = $account['rol'] === ADMIN;
$detailPage = $isAdminOrder ? 'pedido-admin' : 'pedido';
$selectedOrder = $page === $detailPage ? order(integer($_GET, 'id'), $isAdminOrder ? null : (int)$account['id_usuario']) : null;
/**
 * Agrupa Pendiente y Pagado como Abierto y devuelve la clase visual y la etiqueta traducida.
 */
function displayOrderState(string $state): array
{
    return match ($state) {
        'Enviado' => ['en-entrega', tr('En entrega', 'In delivery')],
        'Entregado' => ['entregado', tr('Entregado', 'Delivered')],
        'Cancelado' => ['cancelado', tr('Cancelado', 'Cancelled')],
        default => ['abierto', tr('Abierto', 'Open')],
    };
}
function orderPaymentText(string $payment): string
{
    return match ($payment) {
        'Tarjeta' => tr('Tarjeta VISA/Mastercard', 'VISA/Mastercard'),
        'Efectivo' => tr('Efectivo contra entrega', 'Cash on delivery'),
        'Sinpe' => tr('SINPE Móvil', 'SINPE Mobile'),
        default => paymentName($payment),
    };
}
function orderDate(?string $date, string $format = 'd/m/Y - H:i'): string
{
    return $date ? (new DateTimeImmutable($date))->format($format) : '-';
}
$query = textInput($_GET, 'q');
$state = textInput($_GET, 'estado');
$filters = [];
$values = [];
if (!$isAdminOrder) {
    $filters[] = 'p.id_usuario = ?';
    $values[] = $account['id_usuario'];
    if ($selectedOrder) {
        $filters[] = 'p.id_pedido = ?';
        $values[] = $selectedOrder['id_pedido'];
    }
} elseif ($query !== '') {
    $filters[] = "(CONCAT(u.nombre, ' ', u.apellidos) LIKE ? OR u.email LIKE ? OR CONCAT('ORD-', LPAD(1000 + p.id_pedido, GREATEST(4, CHAR_LENGTH(1000 + p.id_pedido)), '0')) LIKE ? OR EXISTS (SELECT 1 FROM pedido_detalle d JOIN libro l USING (id_libro) WHERE d.id_pedido = p.id_pedido AND l.titulo LIKE ?))";
    $values = array_fill(0, 4, '%' . $query . '%');
}
$filterStates = ['abierto' => ['Pendiente','Pagado'], 'en-entrega' => ['Enviado'], 'entregado' => ['Entregado'], 'cancelado' => ['Cancelado']];
if ($isAdminOrder && isset($filterStates[$state])) {
    $filters[] = 'p.estado IN (' . implode(',', array_fill(0,count($filterStates[$state]),'?')) . ')';
    $values = array_merge($values,$filterStates[$state]);
} elseif ($isAdminOrder && in_array($state, STATES, true)) {
    $filters[] = 'p.estado = ?';
    $values[] = $state;
}
$where = $filters ? ' WHERE '.implode(' AND ',$filters) : '';
$matchingOrders = sql('SELECT p.*, u.nombre, u.apellidos, u.email FROM pedido p JOIN usuario u USING (id_usuario)'.$where.($isAdminOrder ? ' ORDER BY p.id_pedido' : ' ORDER BY p.fecha DESC, p.id_pedido DESC'),$values)->fetchAll();
$currentPage = max(1,(int)textInput($_GET,'pagina'));
$lastPage = max(1,(int)ceil(count($matchingOrders)/9));
$currentPage = min($currentPage,$lastPage);
$orders = $isAdminOrder ? array_slice($matchingOrders,($currentPage-1)*9,9) : $matchingOrders;
$orderDetails = [];
foreach ($orders as $entry) {
    $orderDetails[(int)$entry['id_pedido']] = orderItems((int)$entry['id_pedido']);
}
if ($selectedOrder && !isset($orderDetails[(int)$selectedOrder['id_pedido']])) {
    $orderDetails[(int)$selectedOrder['id_pedido']] = orderItems((int)$selectedOrder['id_pedido']);
}
if (!$isAdminOrder): ?>
<section class="customer-orders-heading">
    <div><div class="customer-orders-kicker"><?= e(tr('Seguimiento de compras','Purchase tracking')) ?></div><h1 class="customer-orders-title"><?= e(tr('Mis órdenes','My orders')) ?></h1><p class="customer-orders-description"><?= e(tr('Consulte sus compras y el estado actual de cada entrega.','Review your purchases and the current status of each delivery.')) ?></p></div>
    <span class="customer-orders-count"><?= count($matchingOrders) ?> <?= e(tr('Órdenes','Orders')) ?></span>
</section>
<?php if ($orders): ?>
<section class="customer-order-list">
    <?php foreach ($orders as $entry):
        $details = $orderDetails[(int)$entry['id_pedido']];
        [$statusClass,$statusLabel] = displayOrderState($entry['estado']);
    ?>
    <article class="customer-order-card">
        <header class="customer-order-header">
            <div><span class="customer-order-label"><?= e(tr('Número de Pedido','Order number')) ?></span><span class="customer-order-number">N° <?= e(orderNumber((int)$entry['id_pedido'])) ?></span></div>
            <div><span class="customer-order-label"><?= e(tr('Fecha','Date')) ?></span><span class="customer-order-date"><?= e(orderDate($entry['fecha'])) ?></span></div>
            <span class="customer-order-status status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
        </header>
        <div class="customer-order-content">
            <div class="customer-order-products">
                <?php foreach ($details as $item): $image = imageUrl($item['ruta_imagen']); ?>
                <div class="customer-order-product">
                    <?php if ($image): ?><img class="customer-order-image" src="<?= e($image) ?>" alt="<?= e($item['titulo']) ?>">
                    <?php else: ?><span class="customer-order-image-empty"><i class="fa-solid fa-book" aria-hidden="true"></i></span><?php endif ?>
                    <div><div class="customer-order-product-title"><?= e($item['titulo']) ?></div><div class="customer-order-product-detail"><?= e(tr('Cantidad','Quantity')) ?>: <?= e($item['cantidad']) ?> · <?= e(tr('Precio unitario','Unit price')) ?>: <?= e(money($item['precio_historico'])) ?></div></div>
                    <div class="customer-order-product-subtotal"><?= e(money($item['subtotal'])) ?></div>
                </div>
                <?php endforeach ?>
            </div>
            <aside class="customer-order-summary">
                <div class="customer-order-summary-row"><?= e(tr('Método de pago','Payment method')) ?><strong><?= e(orderPaymentText($entry['metodo_pago'])) ?></strong></div>
                <div class="customer-order-summary-row"><?= e(tr('Dirección de entrega','Delivery address')) ?><strong><?= e($entry['direccion_envio'] ?: '-') ?></strong></div>
                <div class="customer-order-summary-row"><?= e(tr('Total de productos','Total products')) ?><strong><?= array_sum(array_column($details,'cantidad')) ?></strong></div>
                <div class="customer-order-total"><span>Total</span><span><?= e(money($entry['total'])) ?></span></div>
            </aside>
        </div>
    </article>
    <?php endforeach ?>
</section>
<?php else: ?>
<section class="customer-orders-empty"><i class="fa-solid fa-receipt" aria-hidden="true"></i><strong><?= e(tr('Aún no tiene órdenes registradas.','You have no orders yet.')) ?></strong><p><?= e(tr('Cuando confirme una compra, podrá consultar su estado desde esta ventana.','After placing an order, you can track its status here.')) ?></p></section>
<?php endif ?>
<div class="customer-orders-actions"><a href="<?= e(url($selectedOrder ? 'pedidos' : 'inicio')) ?>" class="customer-orders-return"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= e($selectedOrder ? tr('Regresar a mis órdenes','Back to my orders') : tr('Regresar al menú principal','Back to the home page')) ?></a></div>
<?php else:
    $selecting = textInput($_GET,'seleccionar') === '1' || $selectedOrder !== null;
    $showFilters = textInput($_GET,'filtros') === '1';
    $confirmed = textInput($_GET,'confirmado') === '1';
    $params = ['q'=>$query,'estado'=>$state,'pagina'=>$currentPage];
    $summary = row("SELECT COUNT(*) AS total, COALESCE(SUM(fecha >= CURRENT_TIMESTAMP - INTERVAL 7 DAY),0) AS recibidos, COALESCE(SUM(estado = 'Entregado' AND fecha >= CURRENT_TIMESTAMP - INTERVAL 7 DAY),0) AS entregados, COALESCE(SUM(estado = 'Enviado'),0) AS enviados FROM pedido");
?>
<section class="admin-card orders-summary-card">
    <h1 class="orders-section-title"><?= e(tr('Resumen de pedidos','Order summary')) ?></h1>
    <div class="orders-summary">
        <?php foreach ([['total','','Total de pedidos','Total orders','Pedidos registrados','Registered orders'],['recibidos','orange','Total de recibidos','Total received','Últimos 7 días','Last 7 days'],['entregados','purple','Total de entregados','Total delivered','Últimos 7 días','Last 7 days'],['enviados','red','En entrega','In delivery','Pedidos','Orders']] as [$key,$color,$es,$en,$captionEs,$captionEn]): ?>
        <article class="orders-summary-item"><div class="orders-summary-label <?= $color ?>"><?= e(tr($es,$en)) ?></div><div class="orders-summary-value"><?= e($summary[$key]) ?></div><div class="orders-summary-caption"><?= e(tr($captionEs,$captionEn)) ?></div></article>
        <?php endforeach ?>
    </div>
</section>
<section class="admin-card orders-list-card">
    <div class="orders-card-header">
        <h2 class="orders-section-title"><?= e(tr('Pedidos','Orders')) ?></h2>
        <div class="orders-actions">
            <?php if (!$selecting): ?>
            <a class="orders-btn-secondary" href="<?= e(url('pedidos-admin',['filtros'=>$showFilters?0:1]+$params)) ?>"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= e(tr('Filtros','Filters')) ?></a>
            <a class="orders-btn-primary" href="<?= e(url('pedidos-admin',['seleccionar'=>1]+$params)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= e(tr('Modificar estado de pedido','Change order status')) ?></a>
            <?php else: ?>
            <a class="orders-btn-secondary" href="<?= e(url('pedidos-admin',$params)) ?>"><i class="fa-regular fa-circle-xmark" aria-hidden="true"></i><?= e(tr('Cancelar','Cancel')) ?></a>
            <button type="button" class="orders-btn-primary" data-edit-order data-url="<?= e(url('pedido-admin',$params)) ?>" data-message="<?= e(tr('Debe seleccionar un pedido para modificar.','Select an order to update.')) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= e(tr('Modificar estado de pedido','Change order status')) ?></button>
            <?php endif ?>
        </div>
    </div>
    <?php if ($showFilters && !$selecting): ?>
    <form action="index.php" method="get" class="orders-filter-box">
        <?= hidden('p','pedidos-admin').hidden('filtros',1) ?>
        <div class="orders-filter-grid">
            <div class="orders-filter-group"><label for="orders-search"><?= e(tr('Buscar pedido','Find order')) ?></label><input id="orders-search" name="q" type="text" maxlength="150" value="<?= e($query) ?>" placeholder="<?= e(tr('Número de orden, cliente, correo o libro','Order number, customer, email or book')) ?>"></div>
            <div class="orders-filter-group"><label for="orders-state"><?= e(tr('Estado','Status')) ?></label><select id="orders-state" name="estado"><option value=""><?= e(tr('Todos','All')) ?></option><?php foreach (['Pendiente','Enviado','Entregado','Cancelado'] as $option): [$code,$label]=displayOrderState($option); ?><option value="<?= e($code) ?>"<?= $state===$code?' selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
            <div class="orders-filter-actions"><?= linkTo('pedidos-admin',tr('Limpiar','Clear'),['filtros'=>1],'orders-btn-secondary') ?><button type="submit" class="orders-btn-primary"><?= e(tr('Aplicar filtros','Apply filters')) ?></button></div>
        </div>
    </form>
    <?php endif ?>
    <div class="table-scroll">
        <table class="orders-table"><thead><tr>
            <?php if ($selecting): ?><th></th><?php endif ?>
            <th><?= e(tr('N° Orden','Order no.')) ?></th><th><?= e(tr('Libro','Book')) ?></th><th><?= e(tr('Precio c/u','Unit price')) ?></th><th><?= e(tr('Cantidad','Quantity')) ?></th><th>Total</th><th><?= e($selecting ? tr('ID Cliente','Customer ID') : tr('Fecha entrega estimada','Estimated delivery date')) ?></th><th><?= e(tr('Estado','Status')) ?></th>
        </tr></thead><tbody>
        <?php foreach ($orders as $entry):
            $details=$orderDetails[(int)$entry['id_pedido']];
            $books=implode(', ',array_column($details,'titulo'));
            [$statusClass,$statusLabel]=displayOrderState($entry['estado']);
        ?>
        <tr>
            <?php if ($selecting): ?><td><input type="checkbox" class="orders-check" name="idPedidoSeleccionado" value="<?= e($entry['id_pedido']) ?>" aria-label="<?= e(tr('Seleccionar','Select').' '.orderNumber((int)$entry['id_pedido'])) ?>"></td><?php endif ?>
            <td><?= e(orderNumber((int)$entry['id_pedido'])) ?></td>
            <td class="orders-book-cell"><?php if (count($details)>1): ?><div class="orders-books-compact"><span class="orders-products-count"><i class="fa-solid fa-book" aria-hidden="true"></i><span><?= count($details) ?></span><span><?= e(tr('productos','products')) ?></span></span><span class="orders-products-preview" title="<?= e($books) ?>"><?= e($books) ?></span></div><?php else: ?><span class="orders-book-single"><?= e($books ?: '-') ?></span><?php endif ?></td>
            <td><?= count($details)>1 ? e(tr('Varios','Various')) : e(isset($details[0]) ? money($details[0]['precio_historico']) : '—') ?></td>
            <td><?= e(str_pad((string)array_sum(array_column($details,'cantidad')),2,'0',STR_PAD_LEFT)) ?></td>
            <td><?= e(money($entry['total'])) ?></td>
            <td><?= e($selecting ? $entry['id_usuario'] : ($entry['fecha'] ? (new DateTimeImmutable($entry['fecha']))->modify('+3 days')->format('d/m/Y') : '-')) ?></td>
            <td><span class="orders-status <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
        </tr>
        <?php endforeach ?>
        </tbody></table>
        <?php if (!$orders): ?><div class="orders-empty"><?= e(tr('No se encontraron pedidos con los criterios indicados.','No orders match the selected criteria.')) ?></div><?php endif ?>
    </div>
    <div class="orders-pagination">
        <a class="orders-btn-secondary<?= $currentPage<=1?' disabled':'' ?>"<?= $currentPage<=1?' aria-disabled="true" tabindex="-1"':'' ?> href="<?= e(url('pedidos-admin',['pagina'=>max(1,$currentPage-1),'seleccionar'=>$selecting?1:0]+$params)) ?>"><?= e(tr('Anterior','Previous')) ?></a>
        <span class="orders-page-info"><?= e(tr('Página','Page')) ?> <?= $currentPage ?> <?= e(tr('de','of')) ?> <?= $lastPage ?></span>
        <a class="orders-btn-secondary<?= $currentPage>=$lastPage?' disabled':'' ?>"<?= $currentPage>=$lastPage?' aria-disabled="true" tabindex="-1"':'' ?> href="<?= e(url('pedidos-admin',['pagina'=>min($lastPage,$currentPage+1),'seleccionar'=>$selecting?1:0]+$params)) ?>"><?= e(tr('Siguiente','Next')) ?></a>
    </div>
</section>
<?php if ($selectedOrder):
    $details=$orderDetails[(int)$selectedOrder['id_pedido']];
    [$selectedCode,$selectedLabel]=displayOrderState($selectedOrder['estado']);
?>
<section class="orders-modal-layer" role="dialog" aria-modal="true" aria-labelledby="orders-modal-title">
    <form action="api/admin.php" method="post" class="orders-modal" data-order-state="<?= e($selectedCode) ?>" data-state-required="<?= e(tr('Seleccione el nuevo estado del pedido.','Select the new order status.')) ?>" data-state-equal="<?= e(tr('El nuevo estado no puede ser igual al anterior.','The new status must differ from the current status.')) ?>">
        <?= actionFields('estado',['id'=>$selectedOrder['id_pedido']]) ?>
        <h2 id="orders-modal-title" class="orders-modal-title"><?= e(tr('Datos del pedido','Order details')) ?></h2>
        <div class="orders-data-grid">
            <?php foreach ([
                [tr('N° Orden','Order no.'),orderNumber((int)$selectedOrder['id_pedido']),''],
                [tr('ID Cliente','Customer ID'),$selectedOrder['id_usuario'],''],
                [tr('Nombre cliente','Customer name'),$selectedOrder['nombre'].' '.$selectedOrder['apellidos'],''],
                ['Email',$selectedOrder['email'],'orders-grid-span-two'],
                [tr('Total del pedido','Order total'),money($selectedOrder['total']),''],
            ] as [$label,$value,$class]): ?><div class="orders-form-group <?= $class ?>"><label><?= e($label) ?></label><input type="text" value="<?= e($value) ?>" readonly></div><?php endforeach ?>
            <div class="orders-form-group orders-products-group"><label><?= e(tr('Productos','Products')) ?></label><div class="orders-products-list">
                <?php foreach ($details as $item): ?><div class="orders-product-row"><i class="fa-solid fa-book orders-product-icon" aria-hidden="true"></i><span class="orders-product-title"><?= e($item['titulo']) ?></span><span class="orders-product-quantity"><?= e(tr('Cantidad','Quantity')) ?>: <?= e($item['cantidad']) ?></span></div><?php endforeach ?>
            </div></div>
        </div>
        <h3 class="orders-modal-subtitle"><?= e(tr('Actualizar estado','Update status')) ?></h3>
        <div class="orders-update-grid">
            <div class="orders-form-group"><label><?= e(tr('Estado actual del pedido','Current order status')) ?></label><input type="text" value="<?= e($selectedLabel) ?>" readonly></div>
            <?php if ($confirmed): ?><div class="orders-success"><i class="fa-solid fa-check" aria-hidden="true"></i> <?= e(tr('Estado actualizado correctamente','Status updated successfully')) ?></div><div class="orders-modal-actions"><?= linkTo('pedidos-admin',tr('Regresar','Back'),$params,'orders-btn-primary') ?></div>
            <?php else: ?>
            <div class="orders-form-group"><label for="new-order-state"><?= e(tr('Nuevo estado de pedido','New order status')) ?></label><select id="new-order-state" name="estado" required><option value=""><?= e(tr('Seleccione un estado','Select a status')) ?></option><?php foreach (['Pendiente','Enviado','Entregado','Cancelado'] as $option): [$code,$label]=displayOrderState($option); ?><option value="<?= e($option) ?>" data-code="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach ?></select><div class="orders-error" role="alert" data-order-error></div></div>
            <div class="orders-modal-actions"><?= linkTo('pedidos-admin',tr('Cancelar','Cancel'),['seleccionar'=>1]+$params,'orders-btn-primary') ?><button type="submit" class="orders-btn-primary"><?= e(tr('Confirmar cambios','Confirm changes')) ?></button></div>
            <?php endif ?>
        </div>
    </form>
</section>
<?php endif; endif ?>


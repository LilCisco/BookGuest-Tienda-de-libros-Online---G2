<?php
/**
 * Renderiza la imagen del producto o un respaldo cuando no hay una URL de Firebase admitida.
 */
function purchaseImage(array $book, string $imageClass, string $placeholderClass): void
{
    $image = imageUrl($book['ruta_imagen'] ?? null);
    if ($image) {
        echo '<img class="' . e($imageClass) . '" src="' . e($image) . '" alt="' . e($book['titulo']) . '">';
    } else {
        echo '<span class="' . e($placeholderClass) . '"><i class="fa-solid fa-book" aria-hidden="true"></i></span>';
    }
}
if ($page === 'compra-confirmada'):
    $order = order(integer($_GET, 'id'), (int) $account['id_usuario']);
    $items = orderItems((int) $order['id_pedido']);
    $recipient = $_SESSION['purchase_recipient'] ?? null;
    $recipientName = is_array($recipient) && (int) ($recipient['order'] ?? 0) === (int) $order['id_pedido']
        ? (string) ($recipient['name'] ?? '') : $order['nombre'] . ' ' . $order['apellidos'];
?>
<h1 class="purchase-title"><?= e(tr('Detalles de la factura', 'Billing details')) ?></h1>
<section class="purchase-receipt" aria-hidden="true">
    <div class="purchase-data">
        <p><strong><?= e(tr('Nombre de quien recibe','Recipient name')) ?></strong><?= e($recipientName) ?></p>
        <p><strong>Email</strong><?= e($order['email']) ?></p>
        <p><strong><?= e(tr('Dirección','Address')) ?></strong><?= e($order['direccion_envio']) ?></p>
        <p><strong><?= e(tr('Teléfono','Phone')) ?></strong><?= e($order['telefono_envio']) ?></p>
    </div>
    <div>
        <?php foreach ($items as $item): ?><div class="purchase-product"><span><?= e($item['titulo']) ?> × <?= e($item['cantidad']) ?></span><span><?= e(money($item['subtotal'])) ?></span></div><?php endforeach ?>
        <div class="purchase-product"><span><?= e(tr('Envío:', 'Shipping:')) ?></span><span><?= e(tr('Gratis','Free')) ?></span></div>
        <div class="purchase-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
    </div>
</section>
<div class="purchase-overlay" role="dialog" aria-modal="true" aria-labelledby="purchase-modal-title">
    <section class="purchase-modal">
        <h2 id="purchase-modal-title"><?= e(tr('Compra confirmada','Purchase confirmed')) ?></h2>
        <p><?= e(tr('Se ha generado la orden de compra','Your purchase order has been generated')) ?> <span class="purchase-order">N° <?= e(orderNumber((int) $order['id_pedido'])) ?></span></p>
        <div class="purchase-check"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
        <?= linkTo('inicio', tr('Regresar','Back'), [], 'purchase-return') ?>
    </section>
</div>
<?php else:
    $cart = activeCart((int) $account['id_usuario']);
    $cartId = (int) ($cart['id_carrito'] ?? 0);
    $purchase = quote($cartId, cartItems($cartId));
    $items = $purchase['items'];
    $total = $purchase['total'] === null ? '—' : money($purchase['total']);
    if ($items && !$purchase['available']): ?>
        <div class="cart-alert" role="alert"><?= e($purchase['total'] === null
            ? tr('El importe supera el límite permitido. Retire libros o reduzca cantidades.', 'The amount exceeds the limit. Remove books or reduce quantities.')
            : tr('Ajuste las cantidades o retire los libros sin existencias.', 'Adjust quantities or remove unavailable books.')) ?></div>
    <?php endif;
    if ($page === 'factura'):
        $checkoutAddress = trim($account['direccion'] ?? '');
        $checkoutSecondary = '';
        $checkoutCity = '';
        $addressParts = preg_split('/\s*,\s*/u', $checkoutAddress);
        $partCount = count($addressParts);
        if ($partCount >= 2 && strcasecmp($addressParts[$partCount - 1], 'Costa Rica') === 0) {
            $checkoutCity = $addressParts[$partCount - 2];
            if ($partCount === 2) {
                $checkoutAddress = $addressParts[0];
            } else {
                $addressEnd = $partCount - 2;
                if ($partCount >= 4) {
                    $checkoutSecondary = $addressParts[$partCount - 3];
                    $addressEnd = $partCount - 3;
                }
                $checkoutAddress = implode(', ', array_slice($addressParts, 0, $addressEnd));
            }
        }
    ?>
<h1 class="checkout-title"><?= e(tr('Detalles de la factura', 'Billing details')) ?></h1>
<?php if (!$purchase['available']): ?>
    <?= linkTo('carrito', tr('Regresar al carrito', 'Back to cart'), [], 'checkout-btn checkout-btn-secondary') ?>
<?php else: ?>
<form action="api/cliente.php" method="post" class="checkout-layout" data-once>
    <?= actionFields('comprar', ['cotizacion' => $purchase['version']]) ?>
    <section class="checkout-fields">
        <div class="checkout-group"><label for="checkout-nombre"><?= e(tr('Nombre de quien recibe','Recipient name')) ?></label><input id="checkout-nombre" name="nombre" type="text" class="checkout-input" maxlength="126" value="<?= e($account['nombre'].' '.$account['apellidos']) ?>" required></div>
        <div class="checkout-group"><label for="checkout-email">Email</label><input id="checkout-email" type="email" class="checkout-input" value="<?= e($account['email']) ?>" readonly></div>
        <div class="checkout-group"><label for="checkout-direccion"><?= e(tr('Dirección','Address')) ?></label><input id="checkout-direccion" name="direccion" type="text" class="checkout-input" maxlength="255" value="<?= e($checkoutAddress) ?>" placeholder="<?= e(tr('Ej. San José, Desamparados','E.g. San José, Desamparados')) ?>" required></div>
        <div class="checkout-group"><label for="checkout-secundaria"><?= e(tr('Dirección secundaria (opcional)','Additional address (optional)')) ?></label><input id="checkout-secundaria" name="direccionSecundaria" type="text" class="checkout-input" maxlength="255" value="<?= e($checkoutSecondary) ?>" placeholder="<?= e(tr('Ej. San Antonio, casa color azul','E.g. San Antonio, blue house')) ?>"></div>
        <div class="checkout-group"><label for="checkout-ciudad"><?= e(tr('Ciudad o cantón','City or canton')) ?></label><input id="checkout-ciudad" name="ciudad" type="text" class="checkout-input" maxlength="100" value="<?= e($checkoutCity) ?>" placeholder="<?= e(tr('Ej. San José','E.g. San José')) ?>" required></div>
        <div class="checkout-group"><label for="checkout-telefono"><?= e(tr('Teléfono','Phone')) ?></label><input id="checkout-telefono" name="telefono" type="tel" class="checkout-input" maxlength="25" value="<?= e($account['telefono'] ?? '') ?>" placeholder="8888-8888" required></div>
        <label class="checkout-save"><input type="checkbox" name="recordar" value="1" checked><span><?= e(tr('Guardar esta información para agilizar futuras compras','Save this information for faster future purchases')) ?></span></label>
    </section>
    <aside class="checkout-summary">
        <div class="checkout-products">
            <?php foreach ($items as $item): ?><article class="checkout-product">
                <?php purchaseImage($item, '', 'checkout-product-placeholder'); ?>
                <div class="checkout-product-name"><span><?= e($item['titulo']) ?></span><small><?= e(tr('Cantidad','Quantity')) ?>: <?= e($item['cantidad']) ?></small></div>
                <div class="checkout-product-total"><?= $item['subtotal'] === null ? '—' : e(money($item['subtotal'])) ?></div>
            </article><?php endforeach ?>
        </div>
        <div class="checkout-total-row"><span>Subtotal</span><span><?= e($total) ?></span></div>
        <div class="checkout-total-row"><span><?= e(tr('Envío:', 'Shipping:')) ?></span><span><?= e(tr('Gratis','Free')) ?></span></div>
        <div class="checkout-total-row checkout-grand-total"><span>Total</span><span><?= e($total) ?></span></div>
        <h2 class="checkout-payment-title"><?= e(tr('Método de pago','Payment method')) ?></h2>
        <div class="checkout-payment-options">
            <label class="checkout-payment-option"><input type="radio" name="metodo" value="Tarjeta" required checked><i class="fa-regular fa-credit-card checkout-payment-icon" aria-hidden="true"></i><span><?= e(tr('Tarjeta','Card')) ?><small class="checkout-card-brands">VISA · Mastercard</small></span></label>
            <label class="checkout-payment-option"><input type="radio" name="metodo" value="Efectivo" required><i class="fa-solid fa-money-bill-wave checkout-payment-icon" aria-hidden="true"></i><span><?= e(tr('Efectivo contra entrega','Cash on delivery')) ?></span></label>
        </div>
        <div class="checkout-actions"><button type="submit" class="checkout-btn checkout-btn-primary"><?= e(tr('Confirmar compra','Confirm purchase')) ?></button><?= linkTo('carrito',tr('Regresar al carrito','Back to cart'),[],'checkout-btn checkout-btn-secondary') ?></div>
    </aside>
</form>
<?php endif; else: ?>
<section class="cart-page">
    <div class="cart-table-header"><div><?= e(tr('Producto','Product')) ?></div><div><?= e(tr('Precio','Price')) ?></div><div><?= e(tr('Cantidad','Quantity')) ?></div><div>Subtotal</div></div>
    <?php if (!$items): ?><div class="cart-empty"><?= e(tr('El carrito se encuentra vacío.','Your cart is empty.')) ?></div><?php endif ?>
    <?php foreach ($items as $item): ?>
    <div class="cart-row">
        <div class="cart-product">
            <form action="api/cliente.php" method="post" class="cart-remove-form"><?= actionFields('quitar',['id'=>$item['id_libro']]) ?><button type="submit" class="cart-remove" aria-label="<?= e(tr('Eliminar','Remove')) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></form>
            <?php purchaseImage($item, 'cart-image', 'cart-image-placeholder'); ?>
            <div class="cart-product-title"><?= e($item['titulo']) ?><?php if (!$item['activo'] || $item['cantidad'] > $item['existencias']): ?><span class="d-block text-danger"><?= e(tr('Disponibilidad insuficiente.','Insufficient availability.')) ?></span><?php endif ?></div>
        </div>
        <div class="cart-price"><?= e(money($item['unitario'])) ?></div>
        <div>
            <form action="api/cliente.php" method="post" class="cart-quantity-form">
                <?= actionFields('actualizar',['id'=>$item['id_libro']]) ?>
                <input type="text" value="<?= e($item['cantidad']) ?>" readonly aria-label="<?= e(tr('Cantidad','Quantity')) ?>">
                <div class="cart-quantity-controls">
                    <button type="submit" name="cantidad" value="<?= (int)$item['cantidad']+1 ?>" aria-label="<?= e(tr('Aumentar cantidad','Increase quantity')) ?>"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
                    <button type="submit" name="cantidad" value="<?= max(1,(int)$item['cantidad']-1) ?>"<?= (int)$item['cantidad'] <= 1 ? ' disabled' : '' ?> aria-label="<?= e(tr('Disminuir cantidad','Decrease quantity')) ?>"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>
        <div class="cart-subtotal"><?= $item['subtotal'] === null ? '—' : e(money($item['subtotal'])) ?></div>
    </div>
    <?php endforeach ?>
    <div class="cart-bottom">
        <div class="cart-actions-left"><?= linkTo('inicio',tr('Regresar al inicio','Back to home'),[],'client-btn-secondary') ?></div>
        <aside class="cart-summary">
            <h2 class="cart-summary-title"><?= e(tr('Total del carrito','Cart total')) ?></h2>
            <div class="cart-summary-line"><span>Subtotal</span><span class="cart-summary-value"><?= e($total) ?></span></div>
            <div class="cart-summary-line"><span><?= e(tr('Envío:', 'Shipping:')) ?></span><span class="cart-summary-value"><?= e(tr('Gratis','Free')) ?></span></div>
            <div class="cart-summary-line"><span>Total</span><span class="cart-summary-value"><?= e($total) ?></span></div>
            <?php if ($purchase['available']): ?><?= linkTo('factura',tr('Proceder al pago','Proceed to checkout'),[],'cart-pay-button') ?>
            <?php else: ?><span class="cart-pay-button disabled" aria-disabled="true"><?= e(tr('Proceder al pago','Proceed to checkout')) ?></span><?php endif ?>
        </aside>
    </div>
</section>
<?php endif; endif ?>


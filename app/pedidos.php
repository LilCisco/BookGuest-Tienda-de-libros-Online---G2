<?php

const STATES = ['Pendiente', 'Pagado', 'Enviado', 'Entregado', 'Cancelado'];
const PAYMENTS = ['Tarjeta', 'Efectivo', 'Sinpe', 'Transferencia'];
const MAX_TOTAL = 999999999999;

function orderState(string $state): string
{
    $english = array_combine(STATES, ['Pending', 'Paid', 'Shipped', 'Delivered', 'Cancelled']);
    return tr($state, $english[$state] ?? $state);
}

function paymentName(string $payment): string
{
    $english = array_combine(PAYMENTS, ['Card', 'Cash', 'SINPE', 'Bank transfer']);
    return tr($payment, $english[$payment] ?? $payment);
}

function activeCart(int $user): ?array
{
    return row('SELECT id_carrito FROM carrito WHERE id_usuario = ? AND activo = 1 ORDER BY id_carrito DESC LIMIT 1', [$user]);
}

function cartItems(int $cart): array
{
    return sql('SELECT d.id_libro, d.cantidad, l.titulo, l.isbn, l.ruta_imagen, l.activo, l.existencias, l.precio
                FROM carrito_detalle d JOIN libro l USING (id_libro) WHERE id_carrito = ? ORDER BY d.id_libro', [$cart])->fetchAll();
}

/**
 * Bloquea la cuenta y el libro para agregar, actualizar o quitar una línea del carrito.
 * Comprueba las existencias y el importe antes de guardar la cantidad solicitada.
 */
function updateCart(int $user, int $id, string $action, int $quantity): void
{
    transaction(function () use ($user, $id, $action, $quantity) {
        row('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE', [$user]);
        $cart = activeCart($user);
        if ($action === 'quitar') {
            if ($cart) {
                sql('DELETE FROM carrito_detalle WHERE id_carrito = ? AND id_libro = ?', [$cart['id_carrito'], $id]);
            }
            return;
        }
        $book = lockBook($id);
        $existing = $cart
            ? row('SELECT cantidad FROM carrito_detalle WHERE id_carrito = ? AND id_libro = ?', [$cart['id_carrito'], $id])
            : null;
        if ($action === 'agregar') {
            $quantity += (int) ($existing['cantidad'] ?? 0);
        } elseif (!$existing) {
            throw new AppError(tr('El libro no está en su carrito.', 'The book is not in your cart.'));
        }
        if (!$book['activo'] || $quantity > $book['existencias']) {
            throw new AppError(tr('No hay suficientes ejemplares disponibles.', 'There are not enough copies available.'));
        }
        $price = sellingPrice($book, bookOffer($id));
        if (lineTotal($price, $quantity) === null) {
            throw new AppError(tr('El importe supera el límite permitido.', 'The amount exceeds the allowed limit.'));
        }
        if (!$cart) {
            sql('INSERT INTO carrito (id_usuario) VALUES (?)', [$user]);
            $cart = ['id_carrito' => (int) db()->lastInsertId()];
        }
        sql('INSERT INTO carrito_detalle (id_carrito, id_libro, cantidad, precio_unitario) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cantidad = ?, precio_unitario = ?',
            [$cart['id_carrito'], $id, $quantity, $price, $quantity, $price]);
        sql('UPDATE carrito SET fecha_modificacion = CURRENT_TIMESTAMP WHERE id_carrito = ?', [$cart['id_carrito']]);
    });
}

/**
 * Calcula el subtotal en centavos y devuelve null si supera el máximo admitido por el pedido.
 */
function lineTotal(string $price, int $quantity): ?int
{
    $unit = cents($price);
    if ($unit > intdiv(MAX_TOTAL, $quantity)) {
        return null;
    }
    return $unit * $quantity;
}

/**
 * Recalcula precios y descuentos del carrito y comprueba la disponibilidad.
 * Firma el contenido de la cotización con HMAC para detectar cambios antes de confirmar la compra.
 */
function quote(int $cart, array $items): array
{
    $total = 0;
    $available = true;
    $overLimit = false;
    $version = [$cart];
    foreach ($items as &$item) {
        $offer = bookOffer((int) $item['id_libro']);
        $item['unitario'] = sellingPrice($item, $offer);
        $item['descuento'] = $offer['porcentaje_descuento'] ?? '0.00';
        $line = lineTotal($item['unitario'], (int) $item['cantidad']);
        $item['subtotal'] = $line === null ? null : amount($line);
        $overLimit = $overLimit || $line === null || $total + $line > MAX_TOTAL;
        if (!$overLimit) {
            $total += $line;
        }
        $available = $available && $item['activo'] && $item['cantidad'] <= $item['existencias'];
        $version[] = [$item['id_libro'], $item['cantidad'], $item['unitario'], $item['descuento']];
    }
    return [
        'items' => $items,
        'total' => $overLimit ? null : amount($total),
        'available' => $available && $items !== [] && !$overLimit,
        'version' => hash_hmac('sha256', serialize($version), csrf()),
    ];
}

/**
 * Valida la entrega y vuelve a calcular la cotización con los libros bloqueados.
 * En una sola transacción crea el pedido, guarda sus precios históricos, descuenta el stock y cierra el carrito.
 */
function checkout(int $user, array $data): int
{
    $recipient = field($data, 'nombre', 126);
    $addressParts = [field($data, 'direccion', 255)];
    $secondary = field($data, 'direccionSecundaria', 255, false);
    if ($secondary !== '') {
        $addressParts[] = $secondary;
    }
    $addressParts[] = field($data, 'ciudad', 100);
    $addressParts[] = 'Costa Rica';
    $address = implode(', ', $addressParts);
    if (mb_strlen($address) > 255) {
        throw new AppError(tr('La dirección completa no puede superar 255 caracteres.', 'The complete address cannot exceed 255 characters.'));
    }
    $phone = field($data, 'telefono', 25);
    $payment = textInput($data, 'metodo');
    if (!in_array($payment, PAYMENTS, true)) {
        throw new AppError(tr('Seleccione un método de pago.', 'Select a payment method.'));
    }
    $orderId = transaction(function () use ($user, $data, $address, $phone, $payment) {
        row('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE', [$user]);
        $cart = activeCart($user);
        $cartId = (int) ($cart['id_carrito'] ?? 0);
        $items = cartItems($cartId);
        foreach ($items as &$item) {
            $locked = lockBook((int) $item['id_libro']);
            $item = array_replace($item, $locked);
        }
        unset($item);
        $purchase = quote($cartId, $items);
        if ($purchase['total'] === null) {
            throw new AppError(tr('El importe supera el límite permitido.', 'The amount exceeds the allowed limit.'));
        }
        if (!$purchase['available']) {
            throw new AppError(tr('Revise el carrito. Está vacío o cambió la disponibilidad.', 'Review your cart. It is empty or availability changed.'));
        }
        if (!hash_equals($purchase['version'], textInput($data, 'cotizacion'))) {
            throw new AppError(tr('Cambió el carrito o el precio. Revise el nuevo total antes de confirmar.', 'The cart or price changed. Review the new total before confirming.'));
        }
        sql('INSERT INTO pedido (id_usuario, subtotal, total, metodo_pago, direccion_envio, telefono_envio)
             VALUES (?, ?, ?, ?, ?, ?)', [$user, $purchase['total'], $purchase['total'], $payment, $address, $phone]);
        $order = (int) db()->lastInsertId();
        foreach ($purchase['items'] as $item) {
            sql('INSERT INTO pedido_detalle (id_pedido, id_libro, precio_historico, descuento_porcentaje, cantidad, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$order, $item['id_libro'], $item['unitario'], $item['descuento'], $item['cantidad'], $item['subtotal']]);
            sql('UPDATE libro SET existencias = existencias - ? WHERE id_libro = ?', [$item['cantidad'], $item['id_libro']]);
        }
        sql('UPDATE carrito SET activo = 0 WHERE id_carrito = ?', [$cartId]);
        if (textInput($data, 'recordar') === '1') {
            sql('UPDATE usuario SET direccion = ?, telefono = ? WHERE id_usuario = ?', [$address, $phone, $user]);
        }
        return $order;
    });
    // El nombre del destinatario se conserva en sesión para la confirmación; la dirección y el teléfono se guardan en el pedido.
    $_SESSION['purchase_recipient'] = ['order' => $orderId, 'name' => $recipient];
    return $orderId;
}

/**
 * Consulta un pedido y, cuando se indica $owner, limita el resultado al cliente propietario.
 */
function order(int $id, ?int $owner = null): array
{
    $order = row('SELECT p.*, u.nombre, u.apellidos, u.email FROM pedido p JOIN usuario u USING (id_usuario)
                  WHERE id_pedido = ?' . ($owner === null ? '' : ' AND p.id_usuario = ?'),
                $owner === null ? [$id] : [$id, $owner]);
    if (!$order) {
        throw new AppError(tr('Pedido inexistente.', 'Order not found.'), 404);
    }
    return $order;
}

function orderItems(int $id): array
{
    return sql('SELECT d.*, l.titulo, l.ruta_imagen FROM pedido_detalle d
                JOIN libro l USING (id_libro) WHERE id_pedido = ? ORDER BY d.id_libro, d.id_pedido_detalle', [$id])->fetchAll();
}

/**
 * Actualiza el estado y ajusta el stock solo al entrar o salir de Cancelado.
 * Bloquea las filas involucradas para evitar devoluciones duplicadas o reaperturas sin existencias.
 */
function setOrderState(int $id, string $state): void
{
    if (!in_array($state, STATES, true)) {
        throw new AppError(tr('Seleccione un estado válido.', 'Select a valid status.'));
    }
    transaction(function () use ($id, $state) {
        $current = row('SELECT estado FROM pedido WHERE id_pedido = ? FOR UPDATE', [$id]);
        if (!$current) {
            throw new AppError(tr('Pedido inexistente.', 'Order not found.'), 404);
        }
        $cancel = $state === 'Cancelado';
        $wasCancelled = $current['estado'] === 'Cancelado';
        if ($cancel !== $wasCancelled) {
            $items = sql('SELECT id_libro, SUM(cantidad) AS cantidad FROM pedido_detalle WHERE id_pedido = ?
                          GROUP BY id_libro ORDER BY id_libro', [$id])->fetchAll();
            foreach ($items as $item) {
                $book = lockBook((int) $item['id_libro']);
                $stock = (int) $book['existencias'] + ($cancel ? (int) $item['cantidad'] : -(int) $item['cantidad']);
                if ($stock < 0 || $stock > 2147483647 || (!$cancel && !$book['activo'])) {
                    throw new AppError(tr('Las existencias o disponibilidad impiden cambiar el estado.', 'Stock or availability prevents this status change.'));
                }
                sql('UPDATE libro SET existencias = ? WHERE id_libro = ?', [$stock, $item['id_libro']]);
            }
        }
        sql('UPDATE pedido SET estado = ? WHERE id_pedido = ?', [$state, $id]);
    });
}

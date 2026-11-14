<?php
// Renderiza el dashboard, el inventario y las ofertas según la ruta administrativa solicitada.


function adminBookImage(array $item, string $imageClass, string $placeholderClass, bool $title = false): void
{
    $image = imageUrl($item['ruta_imagen'] ?? null);
    if ($image) {
        echo '<img class="' . e($imageClass) . '" src="' . e($image) . '" alt="' . e($item['titulo']) . '">';
    } else {
        echo '<div class="' . e($placeholderClass) . '">' . ($title ? e($item['titulo']) : '<i class="fa-solid fa-book" aria-hidden="true"></i>') . '</div>';
    }
}

function adminStockStatus(array $item, string $prefix): void
{
    $stock = (int) $item['existencias'];
    $state = !$item['activo'] || $stock === 0 ? 'empty' : ($stock <= 5 ? 'low' : 'ok');
    $label = !$item['activo'] ? tr('Inactivo', 'Inactive') : match ($state) {
        'empty' => tr('No disponible', 'Unavailable'), 'low' => tr('Pocas unidades', 'Low stock'), default => tr('Disponible', 'Available'),
    };
    echo '<span class="' . e($prefix . '-status-' . $state) . '">' . e($label) . '</span>';
}

/**
 * Genera los enlaces del listado administrativo conservando la búsqueda y los filtros.
 */
function adminPagination(string $target, string $prefix, int $current, int $last, array $params): void
{
    $button = $prefix === 'products' ? 'products-page-button' : $prefix . '-btn-secondary';
    ?>
    <nav class="<?= e($prefix) ?>-pagination" aria-label="<?= e(tr('Paginación', 'Pagination')) ?>">
        <?php foreach ([-1, 1] as $direction): ?>
            <?php if ($direction === 1): ?><span class="<?= e($prefix) ?>-page-info"><?= e(tr('Página', 'Page')) ?> <?= $current ?> <?= e(tr('de', 'of')) ?> <?= $last ?></span><?php endif ?>
            <?php $disabled = $direction < 0 ? $current <= 1 : $current >= $last; ?>
            <a class="<?= e($button . ($disabled ? ' disabled' : '')) ?>" href="<?= e(url($target, ['pagina' => max(1, min($last, $current + $direction))] + $params)) ?>"<?= $disabled ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= e($direction < 0 ? tr('Anterior', 'Previous') : tr('Siguiente', 'Next')) ?></a>
        <?php endforeach ?>
    </nav>
    <?php
}

if ($page === 'dashboard'):
    $metrics = row("SELECT (SELECT COUNT(*) FROM libro WHERE activo = 1) AS libros,
                           (SELECT COUNT(*) FROM categoria WHERE activo = 1) AS categorias,
                           (SELECT COALESCE(SUM(existencias), 0) FROM libro WHERE activo = 1) AS ejemplares,
                           (SELECT COALESCE(SUM(precio * existencias), 0) FROM libro WHERE activo = 1) AS valor,
                           (SELECT COUNT(*) FROM libro WHERE activo = 1 AND existencias BETWEEN 1 AND 5) AS bajo,
                           (SELECT COUNT(*) FROM libro WHERE activo = 1 AND existencias = 0) AS agotados");
    $recent = sql(BOOKS . ' WHERE l.activo = 1 ORDER BY l.id_libro DESC LIMIT 5')->fetchAll();
    $low = sql(BOOKS . ' WHERE l.activo = 1 AND l.existencias BETWEEN 1 AND 5 ORDER BY l.existencias, l.titulo LIMIT 5')->fetchAll();
    $out = sql(BOOKS . ' WHERE l.activo = 1 AND l.existencias = 0 ORDER BY l.titulo LIMIT 5')->fetchAll();
?>
<section class="dashboard-heading"><h1><?= e(tr('Dashboard Administrador', 'Administrator Dashboard')) ?></h1></section>
<section class="dashboard-grid">
    <?php foreach ([
        ['libros', tr('Libros activos', 'Active books'), 'book', tr('Productos registrados en inventario', 'Products registered in inventory')],
        ['categorias', tr('Total Categorías', 'Total Categories'), 'layer-group', tr('Categorías registradas', 'Registered categories')],
        ['ejemplares', tr('Stock total', 'Total stock'), 'boxes-stacked', tr('Copias disponibles para venta', 'Copies available for sale')],
        ['valor', tr('Valor inventario', 'Inventory value'), 'coins', tr('Precio por existencias disponibles', 'Price times available stock')],
    ] as [$key, $label, $symbol, $caption]): ?>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-header"><p class="dashboard-stat-title"><?= e($label) ?></p><div class="dashboard-stat-icon"><i class="fa-solid fa-<?= e($symbol) ?>" aria-hidden="true"></i></div></div>
            <div class="dashboard-stat-value"><?= e($key === 'valor' ? money($metrics[$key]) : $metrics[$key]) ?></div><div class="dashboard-stat-muted"><?= e($caption) ?></div>
        </article>
    <?php endforeach ?>
</section>
<section class="dashboard-section-grid">
    <article class="dashboard-card">
        <div class="dashboard-card-header"><h2 class="dashboard-card-title"><?= e(tr('Últimos libros agregados', 'Recently added books')) ?></h2><?= linkTo('inventario', tr('Ver inventario', 'View inventory'), [], 'dashboard-link') ?></div>
        <div class="table-scroll"><table class="dashboard-table">
            <thead><tr><th><?= e(tr('Libro', 'Book')) ?></th><th><?= e(tr('Autor', 'Author')) ?></th><th><?= e(tr('Precio', 'Price')) ?></th><th>Stock</th><th><?= e(tr('Estado', 'Status')) ?></th></tr></thead>
            <tbody><?php foreach ($recent as $item): ?><tr>
                <td><div class="dashboard-book-cell"><?php adminBookImage($item, 'dashboard-book-image', 'dashboard-book-placeholder'); ?><span><?= e($item['titulo']) ?></span></div></td>
                <td><?= e($item['autor']) ?></td><td><?= e(money($item['precio'])) ?></td><td><?= e($item['existencias']) ?></td><td><?php adminStockStatus($item, 'dashboard'); ?></td>
            </tr><?php endforeach ?></tbody>
        </table><?php if (!$recent): ?><div class="dashboard-empty"><?= e(tr('No hay libros registrados todavía.', 'There are no registered books yet.')) ?></div><?php endif ?></div>
    </article>
    <article class="dashboard-card">
        <div class="dashboard-card-header"><h2 class="dashboard-card-title"><?= e(tr('Alertas de inventario', 'Inventory alerts')) ?></h2><?= linkTo('inventario', tr('Revisar', 'Review'), ['estado' => 'pocas'], 'dashboard-link') ?></div>
        <div class="dashboard-alert-list">
            <?php foreach ([['bajo','low',tr('Pocas unidades','Low stock'),tr('Libros con stock entre 1 y 5 unidades','Books with stock between 1 and 5 units')],['agotados','empty',tr('No disponibles','Unavailable'),tr('Libros sin existencias disponibles','Books with no available stock')]] as [$key,$state,$label,$caption]): ?>
                <div class="dashboard-alert-item"><div><div class="dashboard-alert-title"><?= e($label) ?></div><div class="dashboard-alert-subtitle"><?= e($caption) ?></div></div><strong class="dashboard-status-<?= e($state) ?>"><?= e($metrics[$key]) ?></strong></div>
            <?php endforeach ?>
        </div>
    </article>
</section>
<section class="dashboard-bottom-grid">
    <?php foreach ([[$low,'low','pocas',tr('Libros con pocas unidades','Low-stock books'),tr('No hay libros con pocas unidades.','There are no low-stock books.')],[$out,'empty','sin_stock',tr('Libros no disponibles','Unavailable books'),tr('No hay libros sin stock.','There are no out-of-stock books.')]] as [$items,$state,$filter,$label,$empty]): ?>
        <article class="dashboard-card"><div class="dashboard-card-header"><h2 class="dashboard-card-title"><?= e($label) ?></h2><?= linkTo('inventario', tr('Ver todos','View all'), ['estado'=>$filter], 'dashboard-link') ?></div>
            <div class="table-scroll"><table class="dashboard-table"><thead><tr><th><?= e(tr('Título','Title')) ?></th><th><?= e(tr('Categoría','Category')) ?></th><th>Stock</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td><?= e($item['titulo']) ?></td><td><?= e($item['categoria']) ?></td><td class="dashboard-status-<?= e($state) ?>"><?= e($item['existencias']) ?></td></tr><?php endforeach ?>
            </tbody></table><?php if (!$items): ?><div class="dashboard-empty"><?= e($empty) ?></div><?php endif ?></div>
        </article>
    <?php endforeach ?>
</section>
<?php elseif (in_array($page, ['inventario', 'editar-libro'], true)):
    $query = textInput($_GET, 'q');
    $state = textInput($_GET, 'estado');
    $selecting = textInput($_GET, 'seleccionar') === '1';
    $showFilters = textInput($_GET, 'filtros') === '1';
    $allBooks = array_values(array_filter(filteredBooks($query, 0, true), static fn ($item) => match ($state) {
        'disponibles' => $item['activo'] && $item['existencias'] > 5,
        'pocas' => $item['activo'] && $item['existencias'] > 0 && $item['existencias'] <= 5,
        'sin_stock' => $item['activo'] && (int) $item['existencias'] === 0,
        default => true,
    }));
    $last = max(1, (int) ceil(count($allBooks) / 10));
    $current = min($last, max(1, (int) textInput($_GET, 'pagina')));
    $books = array_slice($allBooks, ($current - 1) * 10, 10);
    $params = ['q'=>$query, 'estado'=>$state, 'seleccionar'=>$selecting ? 1 : 0];
    $inventory = row('SELECT COUNT(DISTINCT id_categoria) AS categorias, COUNT(*) AS productos, COALESCE(SUM(precio * existencias), 0) AS valor, COALESCE(SUM(existencias BETWEEN 1 AND 5), 0) AS bajo, COALESCE(SUM(existencias = 0), 0) AS agotados FROM libro WHERE activo = 1');
    $sales = row("SELECT COALESCE(SUM(d.cantidad), 0) AS ejemplares, COALESCE(SUM(d.subtotal), 0) AS total FROM pedido_detalle d JOIN pedido p USING (id_pedido) WHERE p.estado <> 'Cancelado' AND p.fecha >= CURRENT_DATE - INTERVAL 6 DAY");
?>
<section class="admin-card admin-summary-card"><h2 class="admin-card-title"><?= e(tr('Resumen del inventario', 'Inventory summary')) ?></h2><div class="inventory-summary">
    <div class="inventory-summary-item"><div class="inventory-summary-title inventory-blue"><?= e(tr('Categorías','Categories')) ?></div><div class="inventory-summary-line"><span><?= e($inventory['categorias']) ?></span></div><div class="inventory-summary-muted"><span><?= e(tr('Últimos 7 días','Last 7 days')) ?></span></div></div>
    <div class="inventory-summary-item"><div class="inventory-summary-title inventory-orange"><?= e(tr('Total de productos','Total products')) ?></div><div class="inventory-summary-line"><span><?= e($inventory['productos']) ?></span><span><?= e(money($inventory['valor'])) ?></span></div><div class="inventory-summary-muted"><span><?= e(tr('Productos registrados','Registered products')) ?></span><span><?= e(tr('Valor total del inventario','Total inventory value')) ?></span></div></div>
    <div class="inventory-summary-item"><div class="inventory-summary-title inventory-purple"><?= e(tr('Mejores ventas','Top sales')) ?></div><div class="inventory-summary-line"><span><?= e($sales['ejemplares']) ?></span><span><?= e(money($sales['total'])) ?></span></div><div class="inventory-summary-muted"><span><?= e(tr('Últimos 7 días','Last 7 days')) ?></span><span><?= e(tr('Total','Total')) ?></span></div></div>
    <div class="inventory-summary-item"><div class="inventory-summary-title inventory-red"><?= e(tr('Alertas de inventario','Inventory alerts')) ?></div><div class="inventory-summary-line"><span><?= e($inventory['bajo']) ?></span><span><?= e($inventory['agotados']) ?></span></div><div class="inventory-summary-muted"><span><?= e(tr('Pocas unidades','Low stock')) ?></span><span><?= e(tr('No disponibles','Unavailable')) ?></span></div></div>
</div></section>
<section class="admin-card admin-list-card">
    <div class="inventory-card-header"><h2 class="admin-card-title"><?= e(tr('Inventario de libros','Book inventory')) ?></h2><div class="inventory-actions">
        <?php if (!$selecting): ?>
            <?= linkTo('editar-libro', tr('Añadir producto','Add product'), [], 'inventory-btn-primary') ?>
            <a class="inventory-btn-secondary" href="<?= e(url('inventario', ['filtros'=>$showFilters ? 0 : 1, 'pagina'=>$current] + $params)) ?>"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= e(tr('Filtros','Filters')) ?></a>
            <a class="inventory-btn-secondary" href="<?= e(url('inventario', ['seleccionar'=>1, 'pagina'=>$current] + $params)) ?>"><i class="fa-solid fa-pencil" aria-hidden="true"></i><?= e(tr('Modificar producto','Modify product')) ?></a>
        <?php else: ?>
            <button type="button" class="inventory-btn-primary" data-edit-selection="libro" data-edit-url="<?= e(url('editar-libro')) ?>" data-selection-message="<?= e(tr('Debe seleccionar un producto para modificar.','Select a product to modify.')) ?>"><?= e(tr('Modificar producto','Modify product')) ?></button>
            <a class="inventory-btn-secondary" href="<?= e(url('inventario')) ?>"><i class="fa-regular fa-circle-xmark" aria-hidden="true"></i><?= e(tr('Cancelar','Cancel')) ?></a>
        <?php endif ?>
    </div></div>
    <?php if ($showFilters): ?><div class="inventory-filter-box"><form action="index.php" method="get" class="admin-filter-form">
        <?= hidden('p','inventario') . hidden('filtros',1) . hidden('seleccionar',$selecting ? 1 : 0) ?>
        <div class="inventory-filter-state"><label for="inventory-state" class="filter-label"><?= e(tr('Disponibilidad','Availability')) ?></label><select id="inventory-state" name="estado" class="text-control filter-select">
            <?php foreach ([''=>tr('Todos','All'),'disponibles'=>tr('Disponibles','Available'),'pocas'=>tr('Pocas unidades','Low stock'),'sin_stock'=>tr('No disponibles','Unavailable')] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $state === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        </select></div><?= hidden('q',$query) ?><div class="inventory-filter-action"><button class="inventory-btn-primary"><?= e(tr('Aplicar filtro','Apply filter')) ?></button></div>
    </form></div><?php endif ?>
    <div class="table-scroll"><table class="inventory-table"><thead><tr>
        <?php if ($selecting): ?><th><span class="visually-hidden"><?= e(tr('Seleccionar','Select')) ?></span></th><?php endif ?>
        <?php foreach ([tr('Título','Title'),tr('Autor','Author'),tr('Precio unitario','Unit price'),tr('Editorial','Publisher'),'ISBN',tr('Categoría','Category'),tr('Existencias','Stock'),tr('Valor en inventario','Stock value'),tr('Disponibilidad','Availability')] as $label): ?><th><?= e($label) ?></th><?php endforeach ?>
    </tr></thead><tbody><?php foreach ($books as $item): ?><tr>
        <?php if ($selecting): ?><td><input type="checkbox" data-selection="libro" value="<?= e($item['id_libro']) ?>" aria-label="<?= e(tr('Seleccionar ','Select ') . $item['titulo']) ?>"></td><?php endif ?>
        <td><?= e($item['titulo']) ?></td><td><?= e($item['autor']) ?></td><td><?= e(money($item['precio'])) ?></td><td><?= e($item['editorial']) ?></td><td><?= e($item['isbn']) ?></td><td><?= e($item['categoria']) ?></td><td><?= e($item['existencias']) ?></td><td><?= e(money((string) ((float) $item['precio'] * $item['existencias']))) ?></td><td><?php adminStockStatus($item, 'inventory'); ?></td>
    </tr><?php endforeach ?></tbody></table><?php if (!$books): ?><div class="inventory-empty"><?= e(tr('No hay libros registrados en inventario.','There are no books registered in inventory.')) ?></div><?php endif ?></div>
    <?php adminPagination('inventario','inventory',$current,$last,$params); ?>
</section>
<?php
    $confirmation = $_SESSION['admin_confirmation'] ?? null;
    $confirmed = $page === 'inventario' && ($confirmation['kind'] ?? '') === 'libro';
    if ($page === 'editar-libro' || $confirmed):
        $id = $confirmed ? (int) $confirmation['id'] : (isset($_GET['id']) ? integer($_GET,'id',0) : 0);
        $item = $id ? book($id, false) : [];
        if ($confirmed) { unset($_SESSION['admin_confirmation']); }
        $modalTitle = $confirmed ? (($confirmation['created'] ?? false) ? tr('Producto añadido correctamente','Product added successfully') : tr('El producto se ha modificado correctamente','Product updated successfully')) : ($id ? tr('Modificar producto','Modify product') : tr('Nuevo libro','New book'));
?>
<section class="inventory-modal-layer" role="dialog" aria-modal="true" aria-labelledby="inventory-modal-title" data-modal-layer>
    <form action="api/admin.php" method="post" enctype="multipart/form-data" class="inventory-modal bookguest-inventory-form" data-admin-form>
        <?php if (!$confirmed): ?><?= actionFields('libro',['id'=>$id,'existencias_originales'=>$item['existencias'] ?? 0,'activo'=>$item['activo'] ?? 1]) ?><?php endif ?>
        <h2 id="inventory-modal-title" class="inventory-modal-title"><?= e($modalTitle) ?></h2>
        <?php if (!$confirmed && $notice && $notice[1] === 'error'): ?><div class="inventory-error" role="alert"><?= e($notice[0]) ?></div><?php $notice = null; endif ?>
        <div class="inventory-client-error" data-form-error role="alert"></div>
        <div class="inventory-upload-row"><div class="inventory-image-preview" id="inventory-preview">
            <?php if (!empty($item['ruta_imagen']) && imageUrl($item['ruta_imagen'])): ?><img src="<?= e(imageUrl($item['ruta_imagen'])) ?>" alt="<?= e($item['titulo']) ?>"><?php else: ?><span><?= e(tr('Sin imagen','No image')) ?></span><?php endif ?>
        </div>
        <?php if ($confirmed): ?><i class="fa-solid fa-check inventory-confirm-icon" aria-hidden="true"></i><?php else: ?>
            <label for="inventory-image" class="inventory-image-link"><?= e($id ? tr('Cambiar imagen','Change image') : tr('Seleccione la imagen','Select the image')) ?><?= $id ? '' : ' *' ?></label>
            <input type="file" id="inventory-image" name="imagen" class="inventory-hidden-file" accept="image/jpeg,image/png,image/webp" data-image-preview="inventory-preview"<?= $id ? '' : ' required' ?>>
        <?php endif ?></div>
        <?php foreach ([
            ['titulo',tr('Título','Title'),'text',150,tr('Título del libro','Book title')],
            ['autor',tr('Autor','Author'),'text',120,tr('Autor(a) o autores','Author or authors')],
            ['descripcion',tr('Descripción','Description'),'textarea',1000,tr('Descripción del libro','Book description')],
            ['precio',tr('Precio unitario','Unit price'),'number',0,'15000'],
            ['editorial',tr('Editorial','Publisher'),'text',120,tr('Editorial del libro','Book publisher')],
            ['isbn','ISBN','text',20,'9780000000000'],
            ['ubicacion',tr('Ubicación física','Shelf location'),'text',80,tr('Estante A1 - Sección Literatura','Shelf A1 - Literature section')],
            ['categoria',tr('Categoría','Category'),'text',120,tr('Categoría del libro','Book category')],
            ['existencias',tr('Stock disponible','Available stock'),'number',0,'1'],
        ] as [$name,$label,$type,$max,$placeholder]):
            $value = $item[$name === 'ubicacion' ? 'ubicacion_fisica' : $name] ?? ($name === 'existencias' ? 1 : '');
            if ($confirmed && $name === 'precio') {
                $value = money($value);
            }
        ?>
            <div class="inventory-form-row"><label for="book-<?= e($name) ?>"><?= e($label) ?><?= !$confirmed ? ' *' : '' ?></label>
                <?php if ($type === 'textarea'): ?><textarea id="book-<?= e($name) ?>" name="<?= e($name) ?>" maxlength="<?= $max ?>" placeholder="<?= e($placeholder) ?>"<?= $confirmed ? ' readonly' : ' required' ?>><?= e($value) ?></textarea>
                <?php else: ?><input id="book-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($confirmed ? 'text' : $type) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>"<?= $max ? ' maxlength="' . $max . '"' : '' ?><?= $type === 'number' ? ($name === 'precio' ? ' min="0.01" max="9999999999.99" step="0.01"' : ' min="0" max="2147483647" step="1"') : '' ?><?= $confirmed ? ' readonly' : ' required' ?>><?php endif ?>
            </div>
            <?php if ($name === 'isbn' && !$confirmed): ?><div class="inventory-field-help"><?= e(tr('Ingrese ISBN-13. Puede escribirlo con o sin guiones.','Enter an ISBN-13, with or without hyphens.')) ?></div><?php endif ?>
        <?php endforeach ?>
        <?php if ($confirmed): ?><div class="inventory-form-row"><label for="book-stock-value"><?= e(tr('Valor en inventario','Stock value')) ?></label><input id="book-stock-value" value="<?= e(money((string) ((float) $item['precio'] * $item['existencias']))) ?>" readonly></div><?php endif ?>
        <div class="inventory-modal-actions">
            <?php if ($confirmed): ?><?= linkTo('inventario',tr('Regresar','Back'),[],'inventory-btn-primary') ?>
            <?php else: ?><?= linkTo('inventario',$id ? tr('Cancelar','Cancel') : tr('Regresar','Back'), $id ? ['seleccionar'=>1] : [],'inventory-btn-secondary') ?><button class="inventory-btn-primary"><?= e($id ? tr('Confirmar cambios','Confirm changes') : tr('Añadir producto','Add product')) ?></button><?php endif ?>
        </div>
    </form>
</section>
<?php endif ?>
<?php elseif ($page === 'ofertas'):
    $id = max(0, (int) textInput($_GET,'id'));
    $term = textInput($_GET,'q');
    $allBooks = filteredBooks($term,0,true);
    $last = max(1, (int) ceil(count($allBooks) / 8));
    $current = min($last,max(1,(int) textInput($_GET,'pagina')));
    $books = array_slice($allBooks,($current - 1) * 8,8);
    $backParams = ['q'=>$term,'pagina'=>$current];
?>
<div class="products-admin-page">
    <div class="products-toolbar"><div class="products-heading"><h1><?= e(tr('Productos','Products')) ?></h1><p><?= e(tr('Consulta el catálogo y administra las ofertas que verá el cliente.','Browse the catalog and manage the offers customers will see.')) ?></p></div>
        <form action="index.php" method="get" class="products-search"><?= hidden('p','ofertas') ?><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?= e($term) ?>" placeholder="<?= e(tr('Buscar por libro, autor, categoría o ISBN','Search by book, author, category or ISBN')) ?>" aria-label="<?= e(tr('Buscar productos','Search products')) ?>"><button><?= e(tr('Buscar','Search')) ?></button></form>
    </div>
    <section class="products-panel"><div class="products-grid">
        <?php foreach ($books as $item):
            $offer = row('SELECT * FROM oferta WHERE id_libro = ? ORDER BY id_oferta DESC LIMIT 1',[$item['id_libro']]);
            $today = date('Y-m-d');
            $offerState = !$offer ? 'none' : (!$offer['activo'] ? 'inactive' : ($offer['fecha_fin'] < $today ? 'finished' : ($offer['fecha_inicio'] > $today ? 'scheduled' : 'active')));
            $discounted = in_array($offerState,['active','scheduled'],true);
            $stateClass = match ($offerState) { 'inactive','finished' => 'finished', 'none' => '', default => $offerState };
            $stateLabel = match ($offerState) { 'active'=>tr('Activa','Active'),'scheduled'=>tr('Programada','Scheduled'),'finished'=>tr('Finalizada','Ended'),'inactive'=>tr('Inactiva','Inactive'),default=>tr('Sin oferta','No offer') };
        ?>
            <article class="product-admin-card"><div class="product-cover-box">
                <?php if ($discounted): ?><span class="product-discount-badge">-<?= e(rtrim(rtrim($offer['porcentaje_descuento'],'0'),'.')) ?>%</span><?php endif ?>
                <span class="product-status <?= e($stateClass) ?>"><?= e($stateLabel) ?></span><?php adminBookImage($item,'','product-cover-placeholder',true); ?>
            </div><div class="product-card-body"><h2 class="product-card-title"><?= e($item['titulo']) ?></h2><div class="product-card-author"><?= e($item['autor']) ?></div>
                <div class="product-card-price"><strong><?= e(money(sellingPrice($item,$discounted ? $offer : null))) ?></strong><?php if ($discounted): ?><del><?= e(money($item['precio'])) ?></del><?php endif ?></div>
                <div class="product-card-meta"><span><?= e(tr('Existencias','Stock')) ?>: <?= e($item['existencias']) ?></span><span><?= e($item['categoria']) ?></span></div>
                <a class="product-offer-button" href="<?= e(url('ofertas',['id'=>$item['id_libro']] + $backParams)) ?>"><i class="fa-solid fa-tags" aria-hidden="true"></i><span><?= e($offer ? tr('Gestionar oferta','Manage offer') : tr('Crear oferta','Create offer')) ?></span></a>
            </div></article>
        <?php endforeach ?>
    </div><?php if (!$books): ?><div class="products-empty"><i class="fa-solid fa-book-open fa-2x" aria-hidden="true"></i><div><?= e(tr('No se encontraron productos con ese criterio.','No products matched that search.')) ?></div><?php endif ?>
        <?php adminPagination('ofertas','products',$current,$last,['q'=>$term]); ?>
    </section>
</div>
<?php if ($id):
    $targetBook = book($id,false);
    $selectedOffer = max(0,(int) textInput($_GET,'promocion'));
    $editing = $selectedOffer ? row('SELECT * FROM oferta WHERE id_oferta = ? AND id_libro = ?',[$selectedOffer,$id]) : row('SELECT * FROM oferta WHERE id_libro = ? ORDER BY id_oferta DESC LIMIT 1',[$id]);
    $editable = $editing && $editing['activo'] && $editing['fecha_fin'] >= date('Y-m-d');
?>
<section class="offer-modal-layer" role="dialog" aria-modal="true" aria-labelledby="offer-modal-title" data-modal-layer>
    <div class="offer-modal"><div class="offer-modal-header"><div><h2 id="offer-modal-title"><?= e(tr('Configurar oferta','Configure offer')) ?></h2><p><?= e(tr('El descuento se aplicará automáticamente mientras la oferta esté vigente.','The discount applies automatically while the offer is active.')) ?></p></div><a class="offer-modal-close" href="<?= e(url('ofertas',$backParams)) ?>" aria-label="<?= e(tr('Cerrar','Close')) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a></div>
        <div class="offer-book-summary"><?php adminBookImage($targetBook,'','offer-book-image-placeholder'); ?><div><strong><?= e($targetBook['titulo']) ?></strong><span><?= e($targetBook['autor']) ?></span><span><?= e(tr('Precio regular','Regular price')) ?>: <?= e(money($targetBook['precio'])) ?></span></div></div>
        <form id="guardarOfertaForm" action="api/admin.php" method="post" class="offer-form" data-price-base="<?= e($targetBook['precio']) ?>" data-locale="<?= e(($_SESSION['lang'] ?? 'es') === 'en' ? 'en-US' : 'es-CR') ?>" data-admin-form>
            <?= actionFields('oferta',['id'=>$id,'promocion'=>$editable ? $editing['id_oferta'] : 0]) ?><div class="offer-form-grid">
                <?php if ($notice && $notice[1] === 'error'): ?><div class="offer-form-error show" role="alert"><?= e($notice[0]) ?></div><?php $notice = null; endif ?>
                <div class="offer-form-error" data-form-error role="alert"></div>
                <div class="offer-field full"><label for="descripcionOferta"><?= e(tr('Descripción','Description')) ?></label><textarea id="descripcionOferta" name="descripcion" maxlength="150" required placeholder="<?= e(tr('Ejemplo: Oferta especial de la semana','Example: Weekly special offer')) ?>"><?= e($editing['descripcion'] ?? '') ?></textarea></div>
                <div class="offer-field"><label for="porcentajeOferta"><?= e(tr('Descuento','Discount')) ?> (%)</label><input id="porcentajeOferta" name="descuento" type="number" min="0.01" max="99.99" step="0.01" required value="<?= e($editing['porcentaje_descuento'] ?? 10) ?>"></div>
                <div class="offer-field"><label for="offer-stock"><?= e(tr('Existencias disponibles','Available stock')) ?></label><input id="offer-stock" value="<?= e($targetBook['existencias']) ?>" readonly></div>
                <div class="offer-field"><label for="fechaInicioOferta"><?= e(tr('Fecha de inicio','Start date')) ?></label><input id="fechaInicioOferta" name="inicio" type="date" required value="<?= e($editable ? $editing['fecha_inicio'] : date('Y-m-d')) ?>"></div>
                <div class="offer-field"><label for="fechaFinOferta"><?= e(tr('Fecha de finalización','End date')) ?></label><input id="fechaFinOferta" name="fin" type="date" required value="<?= e($editable ? $editing['fecha_fin'] : date('Y-m-d',strtotime('+7 days'))) ?>"></div>
                <div class="offer-price-preview"><span><?= e(tr('Precio que verá el cliente','Price customers will see')) ?></span><strong id="precioOfertaPreview"><?= e(money(sellingPrice($targetBook,$editing))) ?></strong></div>
            </div>
        </form><div class="offer-modal-actions">
            <?php if ($editable): ?><form action="api/admin.php" method="post" data-confirm="<?= e(tr('¿Finalizar esta promoción?','End this promotion?')) ?>"><?= actionFields('finalizar',['id'=>$id,'oferta'=>$editing['id_oferta']]) ?><button class="offer-action danger"><?= e(tr('Finalizar oferta','End offer')) ?></button></form><?php endif ?>
            <?= linkTo('ofertas',tr('Cancelar','Cancel'),$backParams,'offer-action') ?><button type="submit" form="guardarOfertaForm" class="offer-action primary"><?= e(tr('Guardar oferta','Save offer')) ?></button>
        </div>
    </div>
</section>
<?php endif ?>
<?php endif ?>

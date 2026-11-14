<?php

function sectionMarker(string $label): void
{
    echo '<div class="section-marker">' . e($label) . '</div>';
}

function categoryLinks(array $categories, int $selected = 0, bool $catalog = false): void
{
    echo '<nav class="category-row' . ($catalog ? ' productos-category-row' : '') . '" aria-label="' . e(tr('Categorías', 'Categories')) . '">';
    if ($catalog) {
        echo '<a href="' . e(url('catalogo')) . '" class="category-btn' . ($selected === 0 ? ' active' : '') . '">' . e(tr('Todos', 'All')) . '</a>';
    }
    foreach ($categories as $index => $item) {
        $active = $catalog ? (int) $item['id_categoria'] === $selected : $index === 3;
        echo '<a href="' . e(url('catalogo', ['categoria' => $item['id_categoria']])) . '" class="category-btn'
            . ($active ? ' active' : '') . '">' . e($item['descripcion']) . '</a>';
    }
    echo '</nav>';
}

/**
 * Muestra una portada permitida por imageUrl o un respaldo con el título del libro.
 */
function catalogCover(array $book, string $imageClass = 'book-cover', string $emptyClass = 'book-cover-placeholder visible'): void
{
    $image = imageUrl($book['ruta_imagen']);
    if ($image) {
        echo '<img class="' . e($imageClass) . '" loading="lazy" src="' . e($image) . '" alt="' . e($book['titulo']) . '">';
    } else {
        echo '<div class="' . e($emptyClass) . '"><span>' . e($book['titulo']) . '</span></div>';
    }
}

/**
 * Construye el formulario de favorito y conserva la pantalla y sus filtros al regresar.
 */
function favoriteButton(int $id, bool $marked, string $page, array $filters = [], string $class = 'book-action'): void
{
    $label = $marked ? tr('Quitar de favoritos', 'Remove from favorites') : tr('Añadir a favoritos', 'Add to favorites');
    ?>
    <form action="api/cliente.php" method="post" class="<?= $class === 'product-heart' ? 'product-heart-form' : 'book-favorite-form' ?>">
        <?= actionFields('favorito', ['id' => $id, 'activo' => $marked ? 0 : 1, 'volver' => $page] + $filters) ?>
        <button type="submit" class="<?= e($class . ($marked ? ' is-favorite' : '')) ?>" aria-label="<?= e($label) ?>" title="<?= e($label) ?>">
            <i class="<?= $marked ? 'fa-solid' : 'fa-regular' ?> fa-heart" aria-hidden="true"></i>
        </button>
    </form>
    <?php
}

function catalogRating(int $count, string $class = 'book-rating', bool $block = false): void
{
    // Las estrellas y el contador son elementos de presentación; no consultan reseñas de usuarios.
    ?>
    <<?= $block ? 'div' : 'span' ?> class="<?= e($class) ?>" aria-hidden="true">
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star"></i>
        <i class="fa-solid fa-star-half-stroke"></i>
        <?php if ($count > 0): ?><span> (<span><?= $count ?></span>) </span><?php endif ?>
    </<?= $block ? 'div' : 'span' ?>>
    <?php
}

/**
 * Reutiliza la presentación de un libro en el catálogo y las ofertas, con precio y favorito actualizados.
 */
function bookCard(array $book, bool $marked, string $page, array $filters = [], bool $deal = false): void
{
    $id = (int) $book['id_libro'];
    $offer = bookOffer($id);
    $target = url('libro', ['id' => $id]);
    $view = tr('Ver detalle', 'View details');
    ?>
    <article class="book-card">
        <div class="book-cover-box">
            <?php if ($offer): ?><span class="book-discount">-<?= e(number_format((float) $offer['porcentaje_descuento'], 0)) ?>%</span><?php endif ?>
            <div class="book-actions">
                <?php favoriteButton($id, $marked, $page, $filters); ?>
                <a class="book-action" href="<?= e($target) ?>" aria-label="<?= e($view) ?>" title="<?= e($view) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></a>
            </div>
            <a class="<?= $page === 'inicio' ? 'inicio-book-link' : 'productos-book-link' ?>" href="<?= e($target) ?>"><?php catalogCover($book); ?></a>
            <a class="book-add" href="<?= e($target) ?>"><?= e(tr('Añadir al carrito', 'Add to cart')) ?></a>
        </div>
        <?= linkTo('libro', $book['titulo'], ['id' => $id], 'book-title') ?>
        <div class="book-author"><?= e($book['autor']) ?></div>
        <div>
            <?php if ($page === 'inicio'): ?><small class="inicio-unit-price-label"><?= e(tr('Precio unitario', 'Unit price')) ?></small><?php endif ?>
            <span class="book-price"> <?= e(money(sellingPrice($book, $offer))) ?> </span>
            <?php if ($offer): ?><span class="book-old-price"> <?= e(money($book['precio'])) ?> </span><?php endif ?>
            <?php if (!$deal) { catalogRating($id + ($page === 'inicio' ? 30 : 35)); } ?>
        </div>
        <?php if ($deal) { catalogRating($id + 70, 'book-rating', true); } ?>
    </article>
    <?php
}

/**
 * Entrega al navegador la fecha de fin que utiliza el contador de la oferta.
 */
function catalogTimer(?int $end): void
{
    if ($end === null) {
        return;
    }
    ?>
    <div class="offer-timer" data-offer-end="<?= $end ?>">
        <?php foreach ([tr('Días', 'Days'), tr('Horas', 'Hours'), tr('Minutos', 'Minutes'), tr('Segundos', 'Seconds')] as $index => $label): ?>
            <?php if ($index > 0): ?><strong aria-hidden="true">:</strong><?php endif ?>
            <div class="timer-item"><small><?= e($label) ?></small><strong data-offer-digit>00</strong></div>
        <?php endforeach ?>
    </div>
    <?php
}

function categorySection(array $categories, bool $detail = false): void
{
    $prefix = $detail ? 'detalle' : 'inicio';
    ?>
    <section class="<?= $prefix ?>-section">
        <?php sectionMarker(tr('Categorías', 'Categories')); ?>
        <div class="<?= $prefix ?>-category-header">
            <h2 class="section-title"><?= e(tr('Busca por categoría', 'Search by category')) ?></h2>
            <div class="<?= $prefix ?>-category-arrows">
                <a href="<?= e(url('catalogo')) ?>" class="<?= $prefix ?>-arrow" aria-label="<?= e(tr('Ver categorías anteriores', 'View previous categories')) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                <a href="<?= e(url('catalogo')) ?>" class="<?= $prefix ?>-arrow" aria-label="<?= e(tr('Ver más categorías', 'View more categories')) ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
        <?php categoryLinks($categories); ?>
    </section>
    <?php
}

$customer = (int) $account['id_usuario'];
$favoriteIds = array_map('intval', sql('SELECT id_libro FROM favorito WHERE id_usuario = ?', [$customer])->fetchAll(PDO::FETCH_COLUMN));
$categories = sql('SELECT id_categoria, descripcion FROM categoria WHERE activo = 1 ORDER BY id_categoria')->fetchAll();
$query = textInput($_GET, 'q');
$category = max(0, (int) textInput($_GET, 'categoria'));
$filters = ['q' => $query, 'categoria' => $category];
$deals = [];
$end = null;
if (in_array($page, ['inicio', 'libro'], true)) {
    $today = date('Y-m-d');
    $deals = sql(BOOKS . ' JOIN (
        SELECT id_libro, MIN(fecha_fin) AS fin, MAX(id_oferta) AS ultima FROM oferta
        WHERE activo = 1 AND fecha_inicio <= ? AND fecha_fin >= ? GROUP BY id_libro
    ) ofertas USING (id_libro) WHERE l.activo = 1 AND l.existencias > 0
    ORDER BY ofertas.fin, ofertas.ultima DESC LIMIT 8', [$today, $today])->fetchAll();
    foreach ($deals as $deal) {
        $date = bookOffer((int) $deal['id_libro'])['fecha_fin'] ?? null;
        if ($date !== null) {
            $epoch = (new DateTimeImmutable($date))->modify('+1 day')->getTimestamp() * 1000;
            $end = $end === null ? $epoch : min($end, $epoch);
        }
    }
}

if ($page === 'libro'):
    $id = integer($_GET, 'id');
    $book = book($id);
    $offer = bookOffer($id);
    $available = (int) $book['existencias'] > 0;
?>
<section class="product-section">
    <div class="product-image-box"><?php catalogCover($book, 'product-image', 'product-placeholder'); ?></div>
    <div class="product-info">
        <h1 class="product-title"><?= e($book['titulo']) ?></h1>
        <div class="product-author"><?= e($book['autor']) ?></div>
        <div class="product-meta">
            <?php catalogRating(0, 'product-rating'); ?>
            <span class="product-review-count" aria-hidden="true"> (<span><?= $id + 320 ?></span>) </span>
            <span aria-hidden="true">|</span>
            <span class="product-stock"><?= e($available ? tr('Disponible', 'Available') : tr('No disponible', 'Unavailable')) ?></span>
        </div>
        <div class="product-price">
            <?= e(money(sellingPrice($book, $offer))) ?>
            <?php if ($offer): ?>
                <span class="product-regular-price"><?= e(money($book['precio'])) ?></span>
                <span class="product-offer-label">-<?= e(number_format((float) $offer['porcentaje_descuento'], 0)) ?>%</span>
            <?php endif ?>
        </div>
        <h2 class="product-description-title"><?= e(tr('Descripción', 'Description')) ?></h2>
        <p class="product-description"><?= e($book['descripcion']) ?></p>
        <hr class="product-divider">
        <div class="product-actions">
            <div class="product-quantity" data-quantity>
                <button type="button" data-step="-1" aria-label="<?= e(tr('Disminuir cantidad', 'Decrease quantity')) ?>"<?= !$available ? ' disabled' : '' ?>>−</button>
                <input type="number" name="cantidad" form="comprar-libro" min="1" max="<?= max(1, (int) $book['existencias']) ?>" value="1" aria-label="<?= e(tr('Cantidad', 'Quantity')) ?>" readonly>
                <button type="button" data-step="1" aria-label="<?= e(tr('Aumentar cantidad', 'Increase quantity')) ?>"<?= !$available ? ' disabled' : '' ?>>+</button>
            </div>
            <form id="comprar-libro" action="api/cliente.php" method="post">
                <?= actionFields('agregar', ['id' => $id]) ?>
                <button type="submit" class="product-buy"<?= !$available ? ' disabled' : '' ?>><?= e(tr('Comprar ahora', 'Buy now')) ?></button>
            </form>
            <?php favoriteButton($id, in_array($id, $favoriteIds, true), 'libro', [], 'product-heart'); ?>
        </div>
    </div>
</section>
<?php categorySection($categories, true); ?>
<section class="offers-section detalle-section">
    <?php sectionMarker(tr('Ofertas de la semana', 'Weekly offers')); ?>
    <div class="offers-header"><h2 class="section-title"><?= e(tr('Ofertas rápidas', 'Quick offers')) ?></h2><?php catalogTimer($end); ?></div>
    <?php if ($deals): ?>
        <div class="book-grid detalle-offer-grid">
            <?php foreach ($deals as $item) { bookCard($item, in_array((int) $item['id_libro'], $favoriteIds, true), 'libro', ['detalle' => $id], true); } ?>
        </div>
    <?php else: ?><div class="detalle-empty"><?= e(tr('No hay ofertas disponibles.', 'No offers available.')) ?></div><?php endif ?>
</section>
<?php elseif ($page === 'inicio'):
    $books = array_slice(array_values(array_filter(filteredBooks(''), static fn ($item) => $item['existencias'] > 0)), 0, 8);
?>
<div class="inicio-search-wrap">
    <form action="index.php" method="get" class="inicio-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <?= hidden('p', 'catalogo') ?>
        <input name="q" type="search" maxlength="150" placeholder="<?= e(tr('Buscar cualquier libro', 'Search any book')) ?>" aria-label="<?= e(tr('Buscar cualquier libro', 'Search any book')) ?>">
        <button type="submit" aria-label="<?= e(tr('Buscar', 'Search')) ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
    </form>
</div>
<section class="offers-section inicio-section inicio-top-carousel">
    <?php sectionMarker(tr('Ofertas de la semana', 'Weekly offers')); ?>
    <div class="offers-header"><h2 class="section-title"><?= e(tr('Ofertas rápidas', 'Quick offers')) ?></h2><?php catalogTimer($end); ?></div>
    <?php if ($deals): ?>
        <div id="ofertasInicioCarousel" class="offers-carousel" data-offers-carousel>
            <div class="offers-slides">
                <?php foreach (array_chunk($deals, 4) as $index => $slide): ?>
                    <div class="offers-slide"<?= $index === 0 ? '' : ' hidden' ?>>
                        <div class="book-grid inicio-offer-grid">
                            <?php foreach ($slide as $item) { bookCard($item, in_array((int) $item['id_libro'], $favoriteIds, true), 'inicio', [], true); } ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <button class="offers-prev" type="button" data-offers-step="-1" aria-label="<?= e(tr('Anterior', 'Previous')) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
            <button class="offers-next" type="button" data-offers-step="1" aria-label="<?= e(tr('Siguiente', 'Next')) ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
        </div>
    <?php else: ?><div class="inicio-empty"><?= e(tr('No hay ofertas disponibles.', 'No offers available.')) ?></div><?php endif ?>
</section>
<?php categorySection($categories); ?>
<section class="inicio-section">
    <?php sectionMarker(tr('Nuestros productos', 'Our products')); ?>
    <h2 class="section-title"><?= e(tr('Explora nuestros productos', 'Explore our products')) ?></h2>
    <?php if ($books): ?>
        <div class="book-grid inicio-product-grid">
            <?php foreach ($books as $item) { bookCard($item, in_array((int) $item['id_libro'], $favoriteIds, true), 'inicio'); } ?>
        </div>
    <?php else: ?><div class="inicio-empty"><?= e(tr('No hay productos disponibles.', 'No products available.')) ?></div><?php endif ?>
</section>
<?php elseif ($page === 'favoritos'):
    $saved = favorites($customer);
?>
<section class="favorites-heading">
    <div>
        <div class="favorites-kicker"><?= e(tr('Tu biblioteca personal', 'Your personal library')) ?></div>
        <h1 class="favorites-title"><?= e(tr('Mis favoritos', 'My favorites')) ?></h1>
        <p class="favorites-description"><?= e(tr('Guarda los libros que te interesan y agrégalos al carrito cuando quieras.', 'Save books you are interested in and add them to the cart whenever you want.')) ?></p>
    </div>
    <span class="favorites-count"><?= count($saved) ?> <?= e(tr('favoritos', 'favorites')) ?></span>
</section>
<?php if ($saved): ?>
    <section class="favorites-grid">
        <?php foreach ($saved as $item): $id = (int) $item['id_libro']; $offer = bookOffer($id); ?>
            <article class="favorite-card">
                <a href="<?= e(url('libro', ['id' => $id])) ?>"><?php catalogCover($item, 'favorite-cover', 'favorite-cover-empty'); ?></a>
                <div class="favorite-content">
                    <?= linkTo('libro', $item['titulo'], ['id' => $id], 'favorite-title') ?>
                    <div class="favorite-author"><?= e($item['autor']) ?></div>
                    <div>
                        <span class="favorite-price"> <?= e(money(sellingPrice($item, $offer))) ?> </span>
                        <?php if ($offer): ?><span class="favorite-old-price"> <?= e(money($item['precio'])) ?> </span><?php endif ?>
                    </div>
                    <div class="favorite-stock"><?= e(tr('Existencias', 'Stock')) ?>: <?= (int) $item['existencias'] ?></div>
                    <div class="favorite-actions">
                        <form action="api/cliente.php" method="post">
                            <?= actionFields('agregar', ['id' => $id, 'cantidad' => 1]) ?>
                            <button type="submit" class="favorite-cart"<?= !$item['existencias'] ? ' disabled' : '' ?>><i class="fa-solid fa-cart-plus" aria-hidden="true"></i><span><?= e($item['existencias'] > 0 ? tr('Añadir al carrito', 'Add to cart') : tr('Sin existencias', 'Out of stock')) ?></span></button>
                        </form>
                        <form action="api/cliente.php" method="post">
                            <?= actionFields('favorito', ['id' => $id, 'activo' => 0, 'volver' => 'favoritos']) ?>
                            <button type="submit" class="favorite-remove" title="<?= e(tr('Quitar de favoritos', 'Remove from favorites')) ?>" aria-label="<?= e(tr('Quitar de favoritos', 'Remove from favorites')) ?>"><i class="fa-solid fa-heart-crack" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach ?>
    </section>
<?php else: ?>
    <section class="favorites-empty">
        <i class="fa-regular fa-heart" aria-hidden="true"></i>
        <strong><?= e(tr('Aún no tienes libros favoritos.', 'You do not have any favorite books yet.')) ?></strong>
        <p><?= e(tr('Presiona el corazón de cualquier libro para guardarlo en esta lista.', 'Press the heart on any book to save it to this list.')) ?></p>
    </section>
<?php endif ?>
<a class="favorites-return" href="<?= e(url('inicio')) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><?= e(tr('Regresar al menú principal', 'Back to main menu')) ?></a>
<?php else:
    $books = array_values(array_filter(filteredBooks($query, $category), static fn ($item) => $item['existencias'] > 0));
    $displayQuery = $query;
    if ($category > 0 && $displayQuery === '') {
        foreach ($categories as $item) {
            if ((int) $item['id_categoria'] === $category) { $displayQuery = $item['descripcion']; break; }
        }
    }
?>
<section class="productos-page">
    <div class="productos-toolbar">
        <div class="productos-title-block">
            <?php sectionMarker(tr('Nuestros productos', 'Our products')); ?>
            <h1><?= e(tr('Explora nuestros productos', 'Explore our products')) ?></h1>
            <?php if ($displayQuery === ''): ?>
                <p><?= e(tr('Consulta el catálogo completo de libros disponibles.', 'Browse the full catalog of available books.')) ?></p>
            <?php else: ?><p><?= e(tr('Resultados para:', 'Results for:')) ?> <strong><?= e($displayQuery) ?></strong></p><?php endif ?>
        </div>
        <form action="index.php" method="get" class="productos-filter-form">
            <?= hidden('p', 'catalogo') ?>
            <input name="q" type="search" value="<?= e($query) ?>" maxlength="150" placeholder="<?= e(tr('Buscar por título, autor, categoría o editorial', 'Search by title, author, category or publisher')) ?>" aria-label="<?= e(tr('Buscar libros', 'Search books')) ?>">
            <button type="submit"><?= e(tr('Buscar', 'Search')) ?></button>
        </form>
    </div>
    <?php if ($query === '') { categoryLinks($categories, $category, true); } ?>
    <?php if ($books): ?>
        <div class="book-grid productos-grid">
            <?php foreach ($books as $item) { bookCard($item, in_array((int) $item['id_libro'], $favoriteIds, true), 'catalogo', $filters); } ?>
        </div>
    <?php else: ?><div class="productos-empty"><?= e(tr('No se encontraron libros disponibles con los criterios indicados.', 'No available books were found with the selected criteria.')) ?></div><?php endif ?>
</section>
<?php endif ?>

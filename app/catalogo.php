<?php

const BOOKS = 'SELECT l.*, c.descripcion AS categoria, a.nombre AS autor, e.nombre AS editorial
              FROM libro l JOIN categoria c USING (id_categoria) JOIN autor a USING (id_autor)
              JOIN editorial e USING (id_editorial)';

function book(int $id, bool $active = true): array
{
    $book = row(BOOKS . ' WHERE l.id_libro = ?' . ($active ? ' AND l.activo = 1' : ''), [$id]);
    if (!$book) {
        throw new AppError(tr('Libro inexistente.', 'Book not found.'), 404);
    }
    return $book;
}

/**
 * Bloquea la fila del libro con FOR UPDATE dentro de una transacción.
 * Se utiliza antes de cambiar existencias o validar operaciones simultáneas sobre ese libro.
 */
function lockBook(int $id): array
{
    $book = row('SELECT * FROM libro WHERE id_libro = ? FOR UPDATE', [$id]);
    if (!$book) {
        throw new AppError(tr('Libro inexistente.', 'Book not found.'), 404);
    }
    return $book;
}

/**
 * Busca una oferta activa cuya vigencia incluya la fecha actual de Costa Rica.
 */
function bookOffer(int $id): ?array
{
    $today = date('Y-m-d');
    return row('SELECT * FROM oferta WHERE id_libro = ? AND activo = 1 AND fecha_inicio <= ? AND fecha_fin >= ?
                ORDER BY porcentaje_descuento DESC, id_oferta DESC LIMIT 1', [$id, $today, $today]);
}

/**
 * Aplica el descuento usando centavos enteros y redondea el resultado a dos decimales.
 */
function sellingPrice(array $book, ?array $offer): string
{
    $price = cents($book['precio']);
    $discount = $offer ? cents($offer['porcentaje_descuento']) : 0;
    return amount(max(1, intdiv($price * (10000 - $discount) + 5000, 10000)));
}

/**
 * Construye la búsqueda por título, autor, ISBN, categoría o editorial con parámetros preparados.
 * La tienda consulta libros activos; el administrador puede consultar todo el inventario.
 */
function filteredBooks(string $term, int $category = 0, bool $admin = false): array
{
    $filter = $admin ? '1 = 1' : 'l.activo = 1';
    $params = [];
    if ($term !== '') {
        $filter .= ' AND (l.titulo LIKE ? OR a.nombre LIKE ? OR l.isbn LIKE ? OR c.descripcion LIKE ? OR e.nombre LIKE ?)';
        $params = array_fill(0, 5, '%' . $term . '%');
    }
    if ($category > 0) {
        $filter .= ' AND l.id_categoria = ?';
        $params[] = $category;
    }
    $order = $admin ? 'l.titulo, l.id_libro' : 'l.id_libro';
    return sql(BOOKS . ' WHERE ' . $filter . ' ORDER BY ' . $order, $params)->fetchAll();
}

function favorites(int $user): array
{
    return sql(BOOKS . ' JOIN favorito f USING (id_libro)
                WHERE f.id_usuario = ? AND l.activo = 1 ORDER BY f.id_favorito DESC', [$user])->fetchAll();
}

/**
 * Agrega o retira la relación entre cliente y libro sin duplicar un favorito existente.
 */
function setFavorite(int $user, int $bookId, bool $active): void
{
    transaction(function () use ($user, $bookId, $active) {
        $book = lockBook($bookId);
        if ($active && !$book['activo']) {
            throw new AppError(tr('El libro ya no está disponible.', 'The book is no longer available.'));
        }
        if ($active) {
            sql('INSERT INTO favorito (id_usuario, id_libro) VALUES (?, ?) ON DUPLICATE KEY UPDATE id_favorito = id_favorito',
                [$user, $bookId]);
        } else {
            sql('DELETE FROM favorito WHERE id_usuario = ? AND id_libro = ?', [$user, $bookId]);
        }
    });
}

/**
 * Obtiene o crea la categoría, el autor o la editorial y devuelve su identificador.
 */
function catalogReference(string $table, string $field, string $label): int
{
    // Los nombres de tabla y columna deben venir de las llamadas internas de saveBook; PDO solo parametriza los valores.
    $idField = 'id_' . $table;
    sql("INSERT INTO $table ($field) VALUES (?) ON DUPLICATE KEY UPDATE $idField = LAST_INSERT_ID($idField)", [$label]);
    return (int) db()->lastInsertId();
}

/**
 * Comprueba el dígito de control de un ISBN-13 nuevo.
 * Permite conservar el ISBN existente cuando una edición no modifica ese dato.
 */
function bookIsbn(string $value, ?string $existing): string
{
    if ($value === $existing) {
        return $value;
    }
    $isbn = str_replace(['-', ' '], '', $value);
    $sum = 0;
    if (preg_match('/^97[89][0-9]{10}$/D', $isbn)) {
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        if ($sum % 10 === 0) {
            return $isbn;
        }
    }
    throw new AppError(tr('Ingrese un ISBN-13 válido.', 'Enter a valid ISBN-13.'));
}

/**
 * Valida la ficha y guarda el libro junto con sus referencias bibliográficas.
 * Al editar, compara el stock mostrado en el formulario con el stock bloqueado para no sobrescribir ventas recientes.
 */
function saveBook(int $id, array $data, ?string $image): int
{
    if ($id === 0 && $image === null) {
        throw new AppError(tr('Seleccione la imagen del libro.', 'Select the book cover.'));
    }
    $title = field($data, 'titulo', 150);
    $description = field($data, 'descripcion', 1000);
    $price = decimal($data, 'precio', 10);
    $stock = integer($data, 'existencias', 0, 2147483647);
    $isbn = field($data, 'isbn', 20);
    $location = field($data, 'ubicacion', 80);
    $category = field($data, 'categoria', 120);
    $author = field($data, 'autor', 120);
    $publisher = field($data, 'editorial', 120);
    $active = (int) (textInput($data, 'activo') === '1');
    $original = $id > 0 ? integer($data, 'existencias_originales', 0, 2147483647) : 0;
    return transaction(function () use ($id, $image, $title, $description, $price, $stock, $isbn, $location, $category, $author, $publisher, $active, $original) {
        $book = $id > 0 ? lockBook($id) : null;
        if ($book) {
            $currentStock = (int) $book['existencias'];
            if ($stock !== $original && $currentStock !== $original) {
                throw new AppError(tr('Las existencias cambiaron. Revise el inventario antes de volver a editar.', 'Stock changed. Review inventory before editing again.'));
            }
            if ($stock === $original) {
                $stock = $currentStock;
            }
        }
        $values = [$title, $description, $price, $stock, bookIsbn($isbn, $book['isbn'] ?? null), $location,
            catalogReference('categoria', 'descripcion', $category), catalogReference('autor', 'nombre', $author),
            catalogReference('editorial', 'nombre', $publisher), $active];
        if ($book === null) {
            sql('INSERT INTO libro (titulo, descripcion, precio, existencias, isbn, ubicacion_fisica,
                 id_categoria, id_autor, id_editorial, activo, ruta_imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$values, $image]);
            return (int) db()->lastInsertId();
        } else {
            sql('UPDATE libro SET titulo = ?, descripcion = ?, precio = ?, existencias = ?, isbn = ?,
                 ubicacion_fisica = ?, id_categoria = ?, id_autor = ?, id_editorial = ?, activo = ?,
                 ruta_imagen = COALESCE(?, ruta_imagen) WHERE id_libro = ?', [...$values, $image, $id]);
        }
        return $id;
    });
}

/**
 * Valida fechas, descuento y disponibilidad del libro antes de guardar la oferta.
 * Rechaza periodos que se superpongan con otra oferta activa del mismo libro.
 */
function saveOffer(int $bookId, array $data): void
{
    $offerId = integer($data + ['promocion' => '0'], 'promocion', 0);
    $description = field($data, 'descripcion', 150);
    $discount = decimal($data, 'descuento', 2);
    $start = textInput($data, 'inicio');
    $end = textInput($data, 'fin');
    foreach ([$start, $end] as $date) {
        $parsed = preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/D', $date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new AppError(tr('Seleccione fechas válidas.', 'Select valid dates.'));
        }
    }
    if ($start > $end || $end < date('Y-m-d')) {
        throw new AppError(tr('La fecha de fin debe ser igual o posterior al inicio y a hoy.', 'The end date must be on or after both the start date and today.'));
    }
    transaction(function () use ($bookId, $offerId, $description, $discount, $start, $end) {
        $book = lockBook($bookId);
        if (!$book['activo']) {
            throw new AppError(tr('Active el libro para crear una oferta.', 'Activate the book to create an offer.'));
        }
        if (row('SELECT id_oferta FROM oferta WHERE id_libro = ? AND activo = 1
                 AND fecha_fin >= ? AND fecha_inicio <= ? AND id_oferta <> ? LIMIT 1', [$bookId, $start, $end, $offerId])) {
            throw new AppError(tr('Ya existe una oferta para ese libro en esas fechas.', 'An offer already exists for that book on those dates.'));
        }
        if ($offerId === 0) {
            sql('INSERT INTO oferta (id_libro, descripcion, porcentaje_descuento, fecha_inicio, fecha_fin)
                 VALUES (?, ?, ?, ?, ?)', [$bookId, $description, $discount, $start, $end]);
        } else {
            if (!row('SELECT id_oferta FROM oferta WHERE id_oferta = ? AND id_libro = ? AND activo = 1', [$offerId, $bookId])) {
                throw new AppError(tr('La promoción ya no está disponible para edición.', 'This promotion is no longer available for editing.'));
            }
            sql('UPDATE oferta SET descripcion = ?, porcentaje_descuento = ?, fecha_inicio = ?, fecha_fin = ? WHERE id_oferta = ?',
                [$description, $discount, $start, $end, $offerId]);
        }
    });
}

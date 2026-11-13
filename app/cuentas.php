<?php

const ADMIN = 'ROLE_ADMIN';
const CLIENT = 'ROLE_CLIENTE';

/**
 * Obtiene la cuenta y su rol efectivo; da prioridad al rol administrativo si tiene más de uno.
 */
function findUser(int $id): ?array
{
    return row(
        "SELECT u.*, (SELECT r.nombre FROM usuario_rol ur JOIN rol r USING (id_rol)
         WHERE ur.id_usuario = u.id_usuario ORDER BY (r.nombre = 'ROLE_ADMIN') DESC, r.nombre LIMIT 1) AS rol
         FROM usuario u WHERE u.id_usuario = ?",
        [$id]
    );
}

/**
 * Verifica la contraseña y el estado de la cuenta antes de abrir la sesión.
 * Admite hashes con prefijo {bcrypt}, los actualiza cuando corresponde y devuelve la página del rol.
 */
function authenticate(array $data): string
{
    $email = strtolower(textInput($data, 'email'));
    $password = $data['password'] ?? '';
    $account = row('SELECT id_usuario, password FROM usuario WHERE email = ? AND activo = 1', [$email]);
    $hash = $account ? $account['password'] : '';
    $legacy = str_starts_with($hash, '{bcrypt}');
    $verifiedHash = $legacy ? substr($hash, 8) : $hash;
    if (!$account || !is_string($password) || strlen($password) > 1024 ||
        !password_verify($password, $verifiedHash)) {
        throw new AppError(tr('Correo o contraseña incorrectos.', 'Incorrect email or password.'), 422);
    }
    $accountId = (int) $account['id_usuario'];
    $profile = findUser($accountId);
    if (!$profile || !$profile['activo'] || !in_array($profile['rol'], [ADMIN, CLIENT], true)) {
        throw new AppError(tr('La cuenta no está disponible.', 'The account is unavailable.'), 403);
    }
    if (($legacy || password_needs_rehash($verifiedHash, PASSWORD_DEFAULT)) &&
        strlen($password) <= 72 && !str_contains($password, "\0")) {
        sql('UPDATE usuario SET password = ? WHERE id_usuario = ? AND password = ?',
            [password_hash($password, PASSWORD_DEFAULT), $accountId, $hash]);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $accountId;
    unset($_SESSION['csrf']);
    return $profile['rol'] === ADMIN ? 'dashboard' : 'inicio';
}

function accountFields(array $data): array
{
    $email = strtolower(field($data, 'email', 75));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppError(tr('Ingrese un correo válido.', 'Enter a valid email address.'));
    }
    return [
        field($data, 'nombre', 50),
        field($data, 'apellidos', 75),
        $email,
        field($data, 'telefono', 25, false) ?: null,
        field($data, 'direccion', 255, false) ?: null,
    ];
}

/**
 * Valida la confirmación y crea el hash de la contraseña.
 * En una edición, devuelve null si ambos campos quedan vacíos para conservar el hash existente.
 */
function accountPassword(array $data, bool $required): ?string
{
    $password = $data['password'] ?? '';
    $confirmation = $data['confirmacion'] ?? '';
    if (!$required && $password === '' && $confirmation === '') {
        return null;
    }
    if (!is_string($password) || mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new AppError(tr('Use una contraseña de al menos 8 caracteres y hasta 72 bytes.', 'Use a password of at least 8 characters and up to 72 bytes.'));
    }
    if ($password !== $confirmation) {
        throw new AppError(tr('Las contraseñas no coinciden.', 'The passwords do not match.'));
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Guarda los datos y la asignación de rol en una transacción.
 * Impide que una modificación deje al sistema sin administradores activos.
 */
function saveAccount(array $data, int $id, ?string $role, bool $active, ?string $image = null): int
{
    $values = accountFields($data);
    $hash = accountPassword($data, $id === 0);
    return transaction(function () use ($id, $role, $active, $image, $hash, $values) {
        if ($role !== null) {
            if (!in_array($role, [ADMIN, CLIENT], true)) {
                throw new AppError(tr('Seleccione un rol válido.', 'Select a valid role.'));
            }
            row('SELECT id_rol FROM rol WHERE nombre = ? FOR UPDATE', [ADMIN]);
        }
        $target = $id;
        if ($target === 0) {
            sql('INSERT INTO usuario (nombre, apellidos, email, telefono, direccion, password, activo, ruta_imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [...$values, $hash, (int) $active, $image]);
            $target = (int) db()->lastInsertId();
        } else {
            $locked = row('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE', [$target]);
            if (!$locked) {
                throw new AppError(tr('Usuario inexistente.', 'User not found.'), 404);
            }
            $existing = findUser($target);
            if ($role !== null && $target === (int) ($_SESSION['user'] ?? 0) && ($role !== ADMIN || !$active)) {
                throw new AppError(tr('Su cuenta debe conservar el acceso administrativo.', 'Your account must retain administrator access.'));
            }
            if ($role !== null && $existing['rol'] === ADMIN && $existing['activo'] && ($role !== ADMIN || !$active)) {
                $count = row('SELECT COUNT(*) AS total FROM usuario u JOIN usuario_rol ur USING (id_usuario)
                              JOIN rol r USING (id_rol) WHERE u.activo = 1 AND r.nombre = ?', [ADMIN]);
                if ((int) $count['total'] <= 1) {
                    throw new AppError(tr('Debe quedar al menos un administrador activo.', 'At least one administrator must remain active.'));
                }
            }
            sql('UPDATE usuario SET nombre = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?,
                 password = COALESCE(?, password), ruta_imagen = COALESCE(?, ruta_imagen),
                 activo = COALESCE(?, activo) WHERE id_usuario = ?',
                [...$values, $hash, $image, $role === null ? null : (int) $active, $target]);
        }
        if ($role !== null) {
            sql('DELETE FROM usuario_rol WHERE id_usuario = ?', [$target]);
            sql('INSERT INTO usuario_rol (id_usuario, id_rol) SELECT ?, id_rol FROM rol WHERE nombre = ?', [$target, $role]);
        }
        return $target;
    });
}

/**
 * Revisa la carga temporal, el MIME real, el tamaño y las dimensiones antes de enviarla a Firebase.
 * Devuelve null cuando el formulario no incluye una imagen nueva.
 */
function uploadImage(string $directory): ?array
{
    $file = $_FILES['imagen'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        throw new AppError(tr('Seleccione una imagen de hasta 2 MB.', 'Select an image of up to 2 MB.'));
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $image = $extension ? @getimagesize($file['tmp_name']) : false;
    if (!$image || $image[0] > 5000 || $image[1] > 5000) {
        throw new AppError(tr('Use una imagen JPG, PNG o WebP de hasta 5000 × 5000.', 'Use a JPG, PNG or WebP image up to 5000 × 5000.'));
    }
    try {
        return firebaseUpload($file['tmp_name'], $mime, $extension, $directory);
    } catch (Throwable $error) {
        error_log('Carga de imagen en Firebase: ' . $error::class . ' en ' . basename($error->getFile()) . ':' . $error->getLine());
        throw new AppError(tr('No se pudo guardar la imagen en Firebase. Revise la configuración de Storage.', 'The image could not be saved to Firebase. Check the Storage configuration.'), 502);
    }
}

/**
 * Coordina la carga en Firebase con el guardado en MySQL.
 * Si el guardado falla, intenta retirar únicamente la imagen que acaba de cargar.
 */
function saveWithImage(string $directory, callable $save): mixed
{
    $upload = uploadImage($directory);
    try {
        return $save($upload['url'] ?? null);
    } catch (Throwable $error) {
        if ($upload !== null) {
            try {
                firebaseDiscard($upload);
            } catch (Throwable $cleanupError) {
                error_log('No se pudo retirar la carga fallida de Firebase. ' . $cleanupError::class);
            }
        }
        throw $error;
    }
}

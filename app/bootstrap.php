<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/firebase.php';

date_default_timezone_set('America/Costa_Rica');
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
// Separa las sesiones por servidor, puerto y base para no mezclar cuentas entre instalaciones locales.
$dbConfig = localConfig();
$databaseScope = implode(':', [$dbConfig['host'] ?? '', $dbConfig['port'] ?? '', $dbConfig['database'] ?? '']);
session_name('BOOKGUESTPHP' . substr(hash('sha256', $databaseScope), 0, 12));
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' blob: data: https://firebasestorage.googleapis.com; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

class AppError extends RuntimeException
{
}

function tr(string $es, string $en): string
{
    return ($_SESSION['lang'] ?? 'es') === 'en' ? $en : $es;
}

/**
 * Escapa valores antes de insertarlos en texto o atributos HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construye rutas a index.php desde las vistas o desde public/api, conservando el alias de Apache.
 */
function url(string $page, array $params = []): string
{
    $base = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    if (str_ends_with($base, '/api')) {
        $base = dirname($base);
    }
    return rtrim($base, '/') . '/index.php?' . http_build_query(['p' => $page] + $params);
}

function go(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params), true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [$message, $type];
}

/**
 * Genera un token aleatorio por sesión y lo reutiliza en los formularios de esa sesión.
 */
function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function token(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}

/**
 * Acepta únicamente POST y verifica que el token recibido pertenezca a la sesión activa.
 */
function checkPost(array $data): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        throw new AppError(tr('Método no permitido.', 'Method not allowed.'), 405);
    }
    $sent = $data['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf(), $sent)) {
        throw new AppError(tr('La sesión del formulario venció. Recargue la página.', 'The form expired. Reload the page.'), 403);
    }
}

function textInput(array $data, string $key): string
{
    $value = $data[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * Comprueba que un campo de texto cumpla la longitud máxima y, cuando corresponda, sea obligatorio.
 */
function field(array $data, string $key, int $max, bool $required = true): string
{
    $value = textInput($data, $key);
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new AppError(tr('Revise el campo ', 'Check the field ') . $key . '.');
    }
    return $value;
}

function integer(array $data, string $key, int $min = 1, int $max = PHP_INT_MAX): int
{
    $value = filter_var(textInput($data, $key), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    if ($value === false) {
        throw new AppError(tr('Ingrese un número válido en ', 'Enter a valid number in ') . $key . '.');
    }
    return $value;
}

function decimal(array $data, string $key, int $digits): string
{
    $value = textInput($data, $key);
    if (!preg_match('/^\d{1,' . $digits . '}(?:\.\d{1,2})?$/D', $value) || cents($value) <= 0) {
        throw new AppError(tr('Ingrese un importe válido en ', 'Enter a valid amount in ') . $key . '.');
    }
    return $value;
}

/**
 * Convierte un importe decimal a centavos enteros para realizar cálculos sin redondeos de punto flotante.
 */
function cents(string $decimal): int
{
    [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}

function amount(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function money(string|int $decimal): string
{
    $value = (float) $decimal;
    return '₡' . number_format($value, floor($value) === $value ? 0 : 2, '.', ',');
}

function orderNumber(int $id): string
{
    return sprintf('ORD-%04d', 1000 + $id);
}

/**
 * Carga la cuenta de la sesión una sola vez por solicitud.
 * Si fue eliminada o desactivada, retira su identificador de la sesión.
 */
function user(): ?array
{
    static $loaded = false;
    static $user;
    if (!$loaded && isset($_SESSION['user'])) {
        $user = findUser((int) $_SESSION['user']);
        if (!$user || !$user['activo']) {
            $user = null;
            unset($_SESSION['user']);
        }
    }
    $loaded = true;
    return $user;
}

/**
 * Exige una cuenta activa con el rol indicado antes de ejecutar una operación protegida.
 */
function requireRole(string $role): array
{
    $account = user();
    if (!$account) {
        throw new AppError(tr('Inicie sesión para continuar.', 'Sign in to continue.'), 401);
    }
    if ($account['rol'] !== $role) {
        throw new AppError(tr('Acceso denegado.', 'Access denied.'), 403);
    }
    return $account;
}

/**
 * Traduce los errores de la aplicación y de MySQL a mensajes y códigos HTTP.
 * Los fallos internos se registran sin enviar detalles de conexión al navegador.
 */
function problem(Throwable $error): array
{
    if ($error instanceof AppError) {
        return [$error->getCode() ?: 422, $error->getMessage()];
    }
    if ($error instanceof PDOException) {
        if (($error->errorInfo[1] ?? null) === 1062) {
            return [409, tr('Ya existe un registro con esos datos.', 'A record with those details already exists.')];
        }
        if (in_array($error->errorInfo[1] ?? null, [1205, 1213], true)) {
            return [409, tr('Hubo una operación simultánea. Intente de nuevo.', 'A concurrent operation occurred. Please try again.')];
        }
    }
    error_log($error::class . ' en ' . basename($error->getFile()) . ':' . $error->getLine());
    return [500, tr('No se pudo completar la operación. Revise la configuración de BookGuest.', 'The operation failed. Check the BookGuest configuration.')];
}

function jsonReply(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Valida el POST, ejecuta la operación y redirige a su vista de resultado.
 * Guarda los mensajes en sesión para mostrarlos una sola vez después de la redirección.
 */
function finishAction(string $page, array $params, callable $operation): never
{
    try {
        checkPost($_POST);
        $message = $operation();
        if (is_string($message)) {
            flash($message);
        }
    } catch (Throwable $error) {
        [$status, $message] = problem($error);
        if (in_array($status, [403, 405, 500], true)) {
            http_response_code($status);
            echo e($message);
            exit;
        }
        flash($message, 'error');
        if ($status === 401) {
            go('login');
        }
    }
    go($page, $params);
}

require_once __DIR__ . '/cuentas.php';

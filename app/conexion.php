<?php

/**
 * Lee la configuración privada una vez por solicitud.
 * Devuelve un arreglo vacío si todavía no se ha creado config.local.php.
 */
function localConfig(): array
{
    static $config;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config.local.php';
        $config = is_file($path) ? require $path : [];
    }
    return $config;
}

/**
 * Reutiliza una conexión PDO durante la solicitud y activa las consultas preparadas reales.
 * Configura la hora de Costa Rica y el aislamiento usado por las transacciones.
 */
function db(): PDO
{
    static $pdo;
    if ($pdo === null) {
        $config = localConfig();
        if (!$config) {
            throw new RuntimeException('Falta config.local.php. Consulte el README.');
        }
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '-06:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
    return $pdo;
}

/**
 * Prepara la consulta y enlaza sus valores antes de ejecutarla.
 * Los datos del formulario se pasan en $params, separados del texto SQL.
 */
function sql(string $query, array $params = []): PDOStatement
{
    $statement = db()->prepare($query);
    $statement->execute($params);
    return $statement;
}

function row(string $query, array $params = []): ?array
{
    return sql($query, $params)->fetch() ?: null;
}

/**
 * Confirma la operación completa si termina correctamente.
 * Ante una excepción revierte los cambios pendientes y deja que el controlador maneje el error.
 */
function transaction(callable $operation): mixed
{
    db()->beginTransaction();
    try {
        $result = $operation();
        db()->commit();
        return $result;
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}

<?php

const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const STORAGE_API = 'https://storage.googleapis.com';
const FIREBASE_MEDIA_URL = 'https://firebasestorage.googleapis.com';

function firebaseBucket(): string
{
    $bucket = localConfig()['firebase']['bucket'] ?? '';
    return preg_match('/^[a-z0-9][a-z0-9._-]{2,221}$/D', $bucket) ? $bucket : '';
}

/**
 * Acepta únicamente URL de descarga del bucket configurado, con HTTPS y token válido.
 * Evita insertar en las vistas direcciones de otros servidores o protocolos.
 */
function imageUrl(?string $url): ?string
{
    if (!$url || !($bucket = firebaseBucket())) {
        return null;
    }
    $parts = parse_url($url);
    $basePath = '/v0/b/' . rawurlencode($bucket) . '/o/';
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
        ($parts['host'] ?? '') !== 'firebasestorage.googleapis.com' ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment']) ||
        !str_starts_with($parts['path'] ?? '', $basePath) || strlen($parts['path']) <= strlen($basePath)) {
        return null;
    }
    parse_str($parts['query'] ?? '', $query);
    if (($query['alt'] ?? '') !== 'media' || !is_string($query['token'] ?? null) ||
        !preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $query['token'])) {
        return null;
    }
    return $url;
}

/**
 * Ejecuta una solicitud HTTPS a Google y devuelve su código HTTP y su cuerpo.
 * Limita el tiempo y la lectura de la respuesta, sin seguir redirecciones.
 */
function firebaseRequest(string $method, string $url, array $headers, ?string $body = null): array
{
    $options = [
        'method' => $method,
        'header' => $headers,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 12,
        'protocol_version' => 1.1,
    ];
    if ($body !== null) {
        $options['content'] = $body;
    }
    $context = stream_context_create(['http' => $options]);
    $stream = @fopen($url, 'rb', false, $context);
    if (!$stream) {
        throw new RuntimeException('No se pudo establecer HTTPS con Google.');
    }
    try {
        $response = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $contents = stream_get_contents($stream, 65536);
    } finally {
        fclose($stream);
    }
    preg_match('/^HTTP\/[0-9.]+\s+(\d{3})\b/', $response[0] ?? '', $match);
    return [(int) ($match[1] ?? 0), $contents === false ? '' : $contents];
}

/**
 * Firma un JWT con la cuenta de servicio y lo intercambia por un token OAuth.
 * Las credenciales se leen en el servidor y el token se reutiliza durante la solicitud.
 */
function firebaseAccessToken(): string
{
    static $token;
    if ($token !== null) {
        return $token;
    }
    $path = localConfig()['firebase']['credentials'] ?? '';
    if (!extension_loaded('openssl') || !is_readable($path)) {
        throw new RuntimeException('Faltan OpenSSL o las credenciales de Firebase.');
    }
    $credentials = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($credentials['type'] ?? '') !== 'service_account' ||
        empty($credentials['client_email']) || empty($credentials['private_key'])) {
        throw new RuntimeException('La credencial de Firebase debe ser de una cuenta de servicio.');
    }
    $encode = static fn (string $part): string => rtrim(strtr(base64_encode($part), '+/', '-_'), '=');
    $now = time();
    $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $claims = $encode(json_encode([
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/devstorage.read_write',
        'aud' => GOOGLE_TOKEN_URL,
        'iat' => $now,
        'exp' => $now + 300,
    ], JSON_THROW_ON_ERROR));
    $unsigned = $header . '.' . $claims;
    if (!@openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('La clave privada de Firebase no se pudo utilizar.');
    }
    [$status, $body] = firebaseRequest('POST', GOOGLE_TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $unsigned . '.' . $encode($signature),
    ]));
    $data = json_decode($body, true);
    if ($status !== 200 || !is_string($data['access_token'] ?? null)) {
        throw new RuntimeException('Google rechazó la autenticación de Firebase (HTTP ' . $status . ').');
    }
    return $token = $data['access_token'];
}

/**
 * Carga la imagen con un nombre aleatorio y un token de descarga propio.
 * Devuelve la URL, el nombre del objeto y su generación para poder retirar una carga fallida.
 */
function firebaseUpload(string $file, string $mime, string $extension, string $directory): array
{
    $bucket = firebaseBucket();
    $prefix = localConfig()['firebase']['prefix'] ?? 'bookguest-php';
    if ($bucket === '' || !preg_match('/^[a-zA-Z0-9_-]+$/D', $prefix)) {
        throw new RuntimeException('Configure el bucket y el prefijo de Firebase.');
    }
    $object = $prefix . '/' . $directory . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
    $downloadToken = bin2hex(random_bytes(24));
    $boundary = 'bookguest-' . bin2hex(random_bytes(18));
    $metadata = json_encode([
        'name' => $object,
        'contentType' => $mime,
        'cacheControl' => 'public, max-age=86400',
        // Este metadato permite que la URL de descarga utilice el token asignado a la imagen.
        'metadata' => ['firebaseStorageDownloadTokens' => $downloadToken],
    ], JSON_THROW_ON_ERROR);
    $bytes = file_get_contents($file);
    if ($bytes === false) {
        throw new RuntimeException('No se pudo leer la imagen temporal.');
    }
    $multipart = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadata\r\n"
        . "--$boundary\r\nContent-Type: $mime\r\n\r\n$bytes\r\n--$boundary--\r\n";
    $target = STORAGE_API . '/upload/storage/v1/b/' . rawurlencode($bucket) . '/o?uploadType=multipart&ifGenerationMatch=0';
    [$status, $body] = firebaseRequest('POST', $target, [
        'Authorization: Bearer ' . firebaseAccessToken(),
        'Content-Type: multipart/related; boundary=' . $boundary,
    ], $multipart);
    $data = json_decode($body, true);
    if ($status !== 200 || ($data['name'] ?? '') !== $object || !isset($data['generation'])) {
        throw new RuntimeException('Firebase no confirmó la carga (HTTP ' . $status . ').');
    }
    return [
        'object' => $object,
        'generation' => $data['generation'],
        'url' => FIREBASE_MEDIA_URL . '/v0/b/' . rawurlencode($bucket) . '/o/' . rawurlencode($object)
            . '?alt=media&token=' . $downloadToken,
    ];
}

/**
 * Elimina una carga por nombre y generación.
 * La condición de generación evita borrar un objeto que haya sido reemplazado después de la carga.
 */
function firebaseDiscard(array $upload): void
{
    $target = STORAGE_API . '/storage/v1/b/' . rawurlencode(firebaseBucket()) . '/o/' . rawurlencode($upload['object'])
        . '?ifGenerationMatch=' . rawurlencode((string) $upload['generation']);
    [$status] = firebaseRequest('DELETE', $target, ['Authorization: Bearer ' . firebaseAccessToken()]);
    if (!in_array($status, [204, 404], true)) {
        throw new RuntimeException('Firebase no retiró la carga fallida (HTTP ' . $status . ').');
    }
}

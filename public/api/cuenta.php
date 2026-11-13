<?php
// Atiende el registro público, la actualización de la cuenta propia y el cierre de sesión.


require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$action = textInput($_POST, 'accion');
$page = match ($action) {
    'registro' => 'registro',
    'guardar' => 'perfil',
    default => 'login',
};
finishAction($page, $action === 'guardar' ? ['editar' => 1] : [], function () use ($action) {
    switch ($action) {
        case 'registro':
            saveAccount($_POST, 0, CLIENT, true);
            go('registro-confirmado');
        case 'guardar':
            $account = user();
            if (!$account) {
                throw new AppError(tr('Inicie sesión para continuar.', 'Sign in to continue.'), 401);
            }
            saveWithImage('perfiles', fn ($image) => saveAccount($_POST, (int) $account['id_usuario'], null, true, $image));
            go('perfil', ['confirmado' => 1]);
        case 'salir':
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();
            go('login');
        default:
            throw new AppError(tr('Operación inválida.', 'Invalid operation.'), 400);
    }
});

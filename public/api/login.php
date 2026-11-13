<?php
// Prepara el formulario HTML y valida el acceso por JSON o por POST convencional.


require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $account = user();
        if ($account) {
            jsonReply(['redirect' => url($account['rol'] === ADMIN ? 'dashboard' : 'inicio')]);
        }
        $notice = $_SESSION['flash'][0] ?? null;
        unset($_SESSION['flash']);
        jsonReply(['lang' => tr('es', 'en'), 'csrf' => csrf(), 'notice' => $notice]);
    } catch (Throwable $error) {
        [$status, $message] = problem($error);
        jsonReply(['error' => $message], $status);
    }
}

$asJson = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
if ($asJson) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        checkPost(is_array($data) ? $data : []);
        jsonReply(['redirect' => url(authenticate($data))]);
    } catch (Throwable $error) {
        [$status, $message] = problem($error);
        jsonReply(['error' => $message], $status);
    }
}
finishAction('login', [], function () {
    go(authenticate($_POST));
});

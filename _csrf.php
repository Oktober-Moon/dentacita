<?php
/*
 * CSRF · protección de endpoints contra Cross-Site Request Forgery
 * -----------------------------------------------------------------
 * Cada sesión recibe un token aleatorio único. Para que una petición
 * POST sea aceptada, debe incluir ese token en:
 *   - Header `X-CSRF-Token` (lo añade automáticamente _nav.php para AJAX), o
 *   - Campo `csrf_token` del body (para forms HTML clásicos: login/registro).
 *
 * Los endpoints AJAX (que llaman exigirSesionAjax) ya validan
 * automáticamente. Vistas con submit nativo (login/registro) deben
 * llamar csrfValidar() manualmente y renderizar csrfTokenInput()
 * dentro del <form>.
 *
 * Asume que session_start() ya corrió (lo hace conexion.php).
 */


/**
 * Devuelve el token CSRF de la sesión actual. Si no existe, lo crea.
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


/**
 * HTML para insertar dentro de un <form>: campo hidden con el token.
 */
function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}


/**
 * Compara un token candidato contra el de la sesión en tiempo constante.
 * Devuelve true si coinciden y no están vacíos.
 */
function csrfTokenValido($candidato) {
    if (!is_string($candidato) || $candidato === '') return false;
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($esperado === '') return false;
    return hash_equals($esperado, $candidato);
}


/**
 * Valida CSRF para la request actual. Lee el token de:
 *   - Header X-CSRF-Token (AJAX), o
 *   - $_POST['csrf_token'] (form clásico)
 *
 * Solo se aplica a métodos no idempotentes (POST, PUT, DELETE).
 * GET/HEAD/OPTIONS pasan siempre (no modifican estado).
 *
 * Devuelve true si todo OK; false si el token falta o es inválido.
 */
function csrfValidar() {
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) return true;

    // Prioridad: header → body
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($headerToken !== '') {
        return csrfTokenValido($headerToken);
    }
    $bodyToken = $_POST['csrf_token'] ?? '';
    return csrfTokenValido($bodyToken);
}

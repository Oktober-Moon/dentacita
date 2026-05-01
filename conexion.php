<?php
/*
 * CONEXIÓN A MySQL · cuenta de servicio fija
 * -------------------------------------------------
 * v8 multi-tenant: la app usa siempre la cuenta MySQL `dentista`
 * (creada por instalar.sql). El control de acceso lo hace la
 * tabla `usuarios` + sesión PHP, NO MySQL.
 *
 * Cada request:
 *   1. obtenerConexion()  → devuelve mysqli o null si la BD no responde.
 *   2. exigirSesionVista()/exigirSesionAjax() → corta si no hay $_SESSION['usuario_id'].
 *   3. Las queries de cada módulo filtran por getUsuarioId() para aislar tenants.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/_csrf.php';
csrfToken(); // Asegura que la sesión tiene un token CSRF generado.

mysqli_report(MYSQLI_REPORT_OFF);

// Credenciales de servicio (usuario MySQL con SELECT/INSERT/UPDATE/DELETE).
const DB_HOST     = 'localhost';
const DB_USUARIO  = 'dentista';
const DB_PASSWORD = 'dentista';
const DB_NOMBRE   = 'dentacita';


/*
 * Devuelve un mysqli activo o null si la conexión falla.
 * No depende de la sesión: la app puede conectar incluso antes
 * de que el usuario inicie sesión (registro / login).
 */
function obtenerConexion() {
    $conexion = @new mysqli(DB_HOST, DB_USUARIO, DB_PASSWORD, DB_NOMBRE);
    if ($conexion->connect_error) {
        return null;
    }
    $conexion->set_charset('utf8mb4');
    return $conexion;
}


/*
 * ID del usuario logueado o null si no hay sesión activa.
 */
function getUsuarioId() {
    return isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
}


/*
 * Para vistas (páginas HTML): si no hay sesión válida, redirige al login.
 * Si la BD no responde, también redirige al login.
 */
function exigirSesionVista($conexion, $rutaLogin = '/dentacita/') {
    if ($conexion === null) {
        header('Location: ' . $rutaLogin);
        exit;
    }
    if (getUsuarioId() === null) {
        header('Location: ' . $rutaLogin);
        exit;
    }
}


/*
 * Para endpoints AJAX: si no hay sesión válida, devuelve JSON
 * con redirect=true para que el frontend mande al login.
 */
function exigirSesionAjax($conexion) {
    if ($conexion === null || getUsuarioId() === null) {
        header('Content-Type: application/json');
        echo json_encode([
            "ok"       => false,
            "mensaje"  => "Sesión expirada. Vuelve a iniciar sesión.",
            "redirect" => true
        ]);
        exit;
    }
    // Anti-CSRF: rechazar POST/PUT/DELETE sin token válido.
    if (!csrfValidar()) {
        header('Content-Type: application/json');
        echo json_encode([
            "ok"      => false,
            "mensaje" => "Petición rechazada por seguridad (token inválido). Recarga la página."
        ]);
        exit;
    }
}


/*
 * Bootstrap de un usuario recién registrado:
 *   - Crea fila en perfil_dentista con su nombre y moneda default.
 *
 * Devuelve el perfil_id creado o null si falla algo.
 */
function bootstrapPerfilNuevoUsuario(mysqli $conexion, int $usuarioId, string $nombre) {
    $stmt = @$conexion->prepare(
        "INSERT INTO perfil_dentista
            (usuario_id, nombre_completo, onboarding_completado)
         VALUES (?, ?, 0)"
    );
    if (!$stmt) return null;
    $stmt->bind_param("is", $usuarioId, $nombre);
    if (!@$stmt->execute()) { $stmt->close(); return null; }
    $perfilId = $stmt->insert_id;
    $stmt->close();
    return $perfilId;
}


/*
 * Traduce errores crudos de MySQL a mensajes claros.
 */
function mensajeErrorMysql($errno, $error) {
    if ($errno === 1062) {
        return "Ya existe un registro con esos datos.";
    }
    if ($errno === 1451) {
        return "No se puede eliminar: hay otros registros que dependen de éste.";
    }
    if ($errno === 1452) {
        return "El registro al que haces referencia no existe.";
    }
    if ($errno === 1142 || $errno === 1143 || $errno === 1044) {
        return "La base de datos no permite esta operación. Avisa al administrador.";
    }
    return "No se pudo completar la operación. Inténtalo de nuevo.";
}

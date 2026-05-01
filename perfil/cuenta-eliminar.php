<?php
/*
 * ELIMINAR MI CUENTA · soft delete reversible
 * --------------------------------------------
 * Marca perfil_dentista.cuenta_eliminada = 1 y destruye la sesión. La
 * fila se conserva por integridad histórica (citas, transacciones y
 * archivos del paciente referencian al usuario_id). Para reactivar:
 *
 *   UPDATE perfil_dentista
 *      SET cuenta_eliminada = 0, cuenta_eliminada_en = NULL
 *    WHERE usuario_id = ?;
 *
 * Confirmación: el usuario debe escribir literalmente la palabra
 * "ELIMINAR" en el modal antes de que el frontend habilite el botón.
 * Aquí se vuelve a validar server-side por defensa en profundidad.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);  // valida sesión + CSRF
$usuarioId = getUsuarioId();

$confirmacion = trim((string)($_POST['confirmacion'] ?? ''));
if ($confirmacion !== 'ELIMINAR') {
    echo json_encode(["ok" => false, "mensaje" => "Debes escribir 'ELIMINAR' para confirmar."]);
    $conexion->close();
    exit;
}

$stmt = @$conexion->prepare(
    "UPDATE perfil_dentista
        SET cuenta_eliminada = 1, cuenta_eliminada_en = NOW()
      WHERE usuario_id = ?"
);
if (!$stmt) {
    echo json_encode(["ok" => false, "mensaje" => mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close();
    exit;
}
$stmt->bind_param("i", $usuarioId);
if (!@$stmt->execute()) {
    echo json_encode(["ok" => false, "mensaje" => mensajeErrorMysql($stmt->errno, $stmt->error)]);
    $stmt->close();
    $conexion->close();
    exit;
}
$stmt->close();
$conexion->close();

// Destruir la sesión por completo (variables + cookie + storage server-side).
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]);
}
session_destroy();

echo json_encode([
    "ok"       => true,
    "mensaje"  => "Cuenta eliminada.",
    "redirect" => true   // patrón actual: el JS redirige a ../index.php
]);

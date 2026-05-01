<?php
/*
 * INVENTARIO · citas recientes (para selector en modal de movimiento)
 * ------------------------------------------------------------------
 * Devuelve hasta 50 citas recientes del usuario actual, ordenadas por fecha
 * descendente (las más recientes primero). Filtradas por usuario_id.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$citas = [];
$stmt = @$conexion->prepare(
    "SELECT cita_id, paciente_nombre, titulo, fecha_hora_inicio, estado
     FROM citas
     WHERE usuario_id = ?
       AND fecha_hora_inicio >= DATE_SUB(NOW(), INTERVAL 60 DAY)
     ORDER BY fecha_hora_inicio DESC
     LIMIT 50"
);
if ($stmt) {
    $stmt->bind_param("i", $usuarioId);
    @$stmt->execute();
    $r = $stmt->get_result();
    while ($f = $r->fetch_assoc()) $citas[] = $f;
    $stmt->close();
}

$conexion->close();

echo json_encode(["ok" => true, "citas" => $citas]);

<?php
/*
 * NOTIFICACIONES · listar las del usuario actual
 * -----------------------------------------------
 * GET ?solo_no_leidas=1  → solo no leídas (default: todas)
 * Devuelve hasta 20 más recientes + conteo de no leídas.
 */

header('Content-Type: application/json');
require __DIR__ . '/conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$soloNoLeidas = !empty($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] === '1';

$where = "WHERE usuario_id = ?";
if ($soloNoLeidas) $where .= " AND leida = 0";

$sql = "SELECT notificacion_id, tipo, titulo, mensaje, enlace, leida, fecha
        FROM notificaciones
        $where
        ORDER BY fecha DESC
        LIMIT 20";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("i", $usuarioId);
@$stmt->execute();
$r = $stmt->get_result();
$notificaciones = [];
while ($f = $r->fetch_assoc()) $notificaciones[] = $f;
$stmt->close();

/* Conteo de no leídas */
$noLeidas = 0;
$stmtC = @$conexion->prepare(
    "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0"
);
if ($stmtC) {
    $stmtC->bind_param("i", $usuarioId);
    @$stmtC->execute();
    $noLeidas = (int)$stmtC->get_result()->fetch_array(MYSQLI_NUM)[0];
    $stmtC->close();
}

$conexion->close();

echo json_encode([
    "ok"            => true,
    "notificaciones"=> $notificaciones,
    "no_leidas"     => $noLeidas
]);

<?php
/*
 * NOTIFICACIONES · marcar una o todas como leídas
 * ------------------------------------------------
 * POST notificacion_id=N        → marca esa notificación
 * POST todas=1                  → marca todas las del usuario
 */

header('Content-Type: application/json');
require __DIR__ . '/conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

if (!empty($_POST['todas']) && $_POST['todas'] === '1') {
    $stmt = @$conexion->prepare(
        "UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("i", $usuarioId);
    if (@$stmt->execute()) {
        echo json_encode(["ok"=>true,"mensaje"=>"Todas marcadas como leídas.","afectadas"=>$stmt->affected_rows]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
    $stmt->close();
    $conexion->close();
    exit;
}

$id = $_POST['notificacion_id'] ?? '';
if (!is_numeric($id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
}
$idInt = (int)$id;

$stmt = @$conexion->prepare(
    "UPDATE notificaciones SET leida = 1 WHERE notificacion_id = ? AND usuario_id = ?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ii", $idInt, $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Marcada como leída."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

<?php
/*
 * AGENDA · INSERT o UPDATE memoria personal
 * Si POST contiene memoria_id → UPDATE, sino → INSERT.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones-memorias.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

$memoria_id = $_POST['memoria_id'] ?? '';
$contenido  = trim($_POST['contenido'] ?? '');
$fecha      = trim($_POST['fecha'] ?? date('Y-m-d'));
$color      = trim($_POST['color'] ?? 'amarillo');

$err = validarMemoriaContenido($contenido); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarMemoriaFecha($fecha);         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarMemoriaColor($color);         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

if (is_numeric($memoria_id) && (int)$memoria_id > 0) {
    /* UPDATE */
    $idInt = (int)$memoria_id;
    $stmt = @$conexion->prepare(
        "UPDATE personal_memories SET contenido = ?, fecha = ?, color = ? WHERE memoria_id = ? AND usuario_id = ?"
    );
    $stmt->bind_param("sssii", $contenido, $fecha, $color, $idInt, $usuarioId);
    if (@$stmt->execute()) {
        echo json_encode(["ok"=>true,"mensaje"=>"Memoria actualizada.","memoria_id"=>$idInt]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
    $stmt->close();
} else {
    /* INSERT */
    $stmt = @$conexion->prepare(
        "INSERT INTO personal_memories (usuario_id, contenido, fecha, color) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $usuarioId, $contenido, $fecha, $color);
    if (@$stmt->execute()) {
        echo json_encode(["ok"=>true,"mensaje"=>"Memoria guardada.","memoria_id"=>$stmt->insert_id]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
    $stmt->close();
}
$conexion->close();

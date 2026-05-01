<?php
/*
 * NOTAS · actualizar nota
 * POST nota_id, contenido, fecha
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$nota_id   = $_POST['nota_id']   ?? '';
$contenido = trim($_POST['contenido'] ?? '');
$fecha     = trim($_POST['fecha']     ?? '');

if (!is_numeric($nota_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Nota no válida."]); exit; }
$idInt = (int)$nota_id;

$err = validarNotaContenido($contenido); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarNotaFecha($fecha);         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$stmt = @$conexion->prepare(
    "UPDATE notas_paciente n
     JOIN pacientes p ON n.paciente_id = p.paciente_id
     SET n.contenido = ?, n.fecha = ?
     WHERE n.nota_id = ? AND p.usuario_id = ?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ssii", $contenido, $fecha, $idInt, $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Nota actualizada."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

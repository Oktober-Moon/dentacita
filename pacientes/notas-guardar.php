<?php
/*
 * NOTAS · crear nota nueva
 * POST paciente_id, contenido, fecha
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id'] ?? '';
$contenido   = trim($_POST['contenido'] ?? '');
$fecha       = trim($_POST['fecha'] ?? date('Y-m-d'));

if (!is_numeric($paciente_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit; }
$pacIdInt = (int)$paciente_id;

$err = validarNotaContenido($contenido); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarNotaFecha($fecha);         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* Verificar que el paciente pertenezca al usuario logueado */
$sv = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
$sv->bind_param("ii", $pacIdInt, $usuarioId);
@$sv->execute();
if (!$sv->get_result()->fetch_assoc()) {
    $sv->close();
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
    $conexion->close(); exit;
}
$sv->close();

$stmt = @$conexion->prepare(
    "INSERT INTO notas_paciente (paciente_id, contenido, fecha) VALUES (?, ?, ?)"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("iss", $pacIdInt, $contenido, $fecha);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Nota guardada.","nota_id"=>$stmt->insert_id]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

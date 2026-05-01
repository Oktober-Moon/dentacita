<?php
/*
 * ACUERDOS · crear acuerdo (desde la pestaña Acuerdos del paciente)
 * El dentista registra una propuesta formal con fecha/duración/precio.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones-acuerdos.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id      = $_POST['paciente_id']      ?? '';
$servicio         = trim($_POST['servicio']    ?? '');
$descripcion      = trim($_POST['descripcion'] ?? '');
$fecha_programada = trim($_POST['fecha_programada'] ?? '');
$duracion_minutos = trim($_POST['duracion_minutos'] ?? '60');
$precio           = trim($_POST['precio']      ?? '');

if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit;
}
$pacIdInt = (int)$paciente_id;

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

$err = validarAcuerdoServicio($servicio);                   if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoFechaProgramada($fecha_programada);    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoDuracion($duracion_minutos);           if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoPrecio($precio);                       if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoDescripcion($descripcion);             if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$durInt  = (int)$duracion_minutos;
$precioF = (float)$precio;
$descV   = $descripcion === '' ? null : $descripcion;

$stmt = @$conexion->prepare(
    "INSERT INTO acuerdos_servicio
        (paciente_id, servicio, descripcion, fecha_programada, duracion_minutos, precio, estado)
     VALUES (?, ?, ?, ?, ?, ?, 'pendiente')"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("isssid", $pacIdInt, $servicio, $descV, $fecha_programada, $durInt, $precioF);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Acuerdo creado.","acuerdo_id"=>$stmt->insert_id]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

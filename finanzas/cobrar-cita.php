<?php
/*
 * FINANZAS · cobrar cita completada
 * ----------------------------------
 * Toma una cita en estado 'completada' que NO tenga aún transacción de ingreso
 * y crea la transacción correspondiente. Se invoca desde el botón "Cobrar"
 * en la pestaña de citas pendientes en finanzas.
 *
 * Misma lógica que dispara automáticamente el agenda/actualizar.php cuando
 * el dentista cambia el estado de una cita a 'completada'.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$cita_id     = $_POST['cita_id']     ?? '';
$metodo_pago = trim($_POST['metodo_pago'] ?? 'efectivo');
$monto_custom = trim($_POST['monto'] ?? '');

if (!is_numeric($cita_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Cita no válida."]); exit;
}
$citaIdInt = (int)$cita_id;

$err = validarTransMetodo($metodo_pago);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
// Normalizar vacío a 'efectivo' (validador lo permite vacío pero no queremos '' en BD).
if ($metodo_pago === '') $metodo_pago = 'efectivo';

/* Obtener datos de la cita (validando que pertenezca al usuario, anti-IDOR) */
$s = @$conexion->prepare(
    "SELECT cita_id, paciente_id, paciente_nombre, titulo, fecha_hora_inicio, estado, precio
     FROM citas WHERE cita_id = ? AND usuario_id = ?"
);
$s->bind_param("ii", $citaIdInt, $usuarioId);
@$s->execute();
$cita = $s->get_result()->fetch_assoc();
$s->close();

if (!$cita) {
    echo json_encode(["ok"=>false,"mensaje"=>"Cita no encontrada."]);
    $conexion->close(); exit;
}
if ($cita['estado'] !== 'completada') {
    echo json_encode(["ok"=>false,"mensaje"=>"Solo se cobran citas completadas. La cita está en estado '{$cita['estado']}'."]);
    $conexion->close(); exit;
}

/* ¿Ya hay un cobro activo? */
$s2 = @$conexion->prepare(
    "SELECT 1 FROM transacciones
     WHERE cita_id = ? AND usuario_id = ? AND estado='activa' AND categoria != 'Reembolso' LIMIT 1"
);
$s2->bind_param("ii", $citaIdInt, $usuarioId);
@$s2->execute();
$yaCobrada = (bool)$s2->get_result()->fetch_assoc();
$s2->close();
if ($yaCobrada) {
    echo json_encode(["ok"=>false,"mensaje"=>"Esta cita ya tiene un cobro registrado."]);
    $conexion->close(); exit;
}

/* Determinar monto */
$monto = $monto_custom !== '' ? (float)$monto_custom : (float)$cita['precio'];
if ($monto <= 0) {
    echo json_encode(["ok"=>false,"mensaje"=>"La cita no tiene precio asignado. Edítala antes de cobrar."]);
    $conexion->close(); exit;
}

$descripcion = "Cita: {$cita['titulo']} · {$cita['paciente_nombre']}";
$pacV   = $cita['paciente_id'] !== null ? (int)$cita['paciente_id'] : null;
$fecha  = date('Y-m-d');

$stmt = @$conexion->prepare(
    "INSERT INTO transacciones
       (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
     VALUES (?, 'ingreso', 'Tratamiento', ?, ?, ?, ?, ?, ?, 'activa')"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("idssiis", $usuarioId, $monto, $fecha, $descripcion, $pacV, $citaIdInt, $metodo_pago);

if (@$stmt->execute()) {
    echo json_encode([
        "ok"=>true,
        "mensaje"=>"Ingreso de \$" . number_format($monto, 2) . " registrado por la cita.",
        "transaccion_id"=>$stmt->insert_id
    ]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

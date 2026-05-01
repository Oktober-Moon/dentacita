<?php
/*
 * AGENDA · cambio rápido de estado de cita
 * -----------------------------------------
 * POST cita_id, estado
 *
 * Endpoint ligero para el dropdown inline en la lista de citas del día.
 * Solo cambia el estado (no fecha, paciente, precio, etc.). Si el estado
 * es 'completada' y la cita tiene precio, dispara el mismo flujo cruzado
 * que actualizar.php (genera transacción de ingreso si no existe).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

$cita_id = $_POST['cita_id'] ?? '';
$estado  = $_POST['estado']  ?? '';
$motivoCancelacion = trim($_POST['motivo_cancelacion'] ?? '');

if (!is_numeric($cita_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de cita no válido."]); exit;
}
$idInt = (int)$cita_id;

$err = validarEstadoCita($estado);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$motivoCancV = ($estado === 'cancelada' && $motivoCancelacion !== '')
    ? mb_substr($motivoCancelacion, 0, 200) : null;

/* Cargar la cita primero para tener acceso al precio + datos para auto-cobro */
$s = @$conexion->prepare(
    "SELECT cita_id, paciente_id, paciente_nombre, titulo, precio, estado AS estado_actual
     FROM citas WHERE cita_id = ? AND usuario_id = ?"
);
if (!$s) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$s->bind_param("ii", $idInt, $usuarioId);
@$s->execute();
$cita = $s->get_result()->fetch_assoc();
$s->close();
if (!$cita) {
    echo json_encode(["ok"=>false,"mensaje"=>"Cita no encontrada."]);
    $conexion->close(); exit;
}

if ($cita['estado_actual'] === $estado) {
    echo json_encode(["ok"=>true,"mensaje"=>"La cita ya estaba en ese estado.","sin_cambios"=>true]);
    $conexion->close(); exit;
}

/* UPDATE atómico · si pasa a cancelada se persiste el motivo, si no se limpia */
$stmt = @$conexion->prepare(
    "UPDATE citas SET estado = ?, motivo_cancelacion = ? WHERE cita_id = ? AND usuario_id = ?"
);
$stmt->bind_param("ssii", $estado, $motivoCancV, $idInt, $usuarioId);

if (!@$stmt->execute()) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    $stmt->close(); $conexion->close(); exit;
}
$stmt->close();

/* FLUJO CRUZADO: si pasó a completada y tiene precio > 0, generar ingreso si no existe */
$infoExtra = "";
if ($estado === 'completada' && $cita['precio'] !== null && (float)$cita['precio'] > 0) {
    $sc = @$conexion->prepare(
        "SELECT 1 FROM transacciones
         WHERE cita_id = ? AND usuario_id = ? AND estado='activa' AND categoria != 'Reembolso' LIMIT 1"
    );
    $sc->bind_param("ii", $idInt, $usuarioId);
    @$sc->execute();
    $yaCobrada = (bool)$sc->get_result()->fetch_assoc();
    $sc->close();

    if (!$yaCobrada) {
        $precioFinal = (float)$cita['precio'];
        $descTrans = "Cita: {$cita['titulo']} · {$cita['paciente_nombre']}";
        $hoyStr = date('Y-m-d');
        $pacIdInt = $cita['paciente_id'] !== null ? (int)$cita['paciente_id'] : null;
        $metodo = 'efectivo';
        $st = @$conexion->prepare(
            "INSERT INTO transacciones
               (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
             VALUES (?, 'ingreso', 'Tratamiento', ?, ?, ?, ?, ?, ?, 'activa')"
        );
        if ($st) {
            $st->bind_param("idssiis", $usuarioId, $precioFinal, $hoyStr, $descTrans, $pacIdInt, $idInt, $metodo);
            if (@$st->execute()) {
                $infoExtra = " Se generó un ingreso de \$" . number_format($precioFinal, 2) . " en finanzas.";
            }
            $st->close();
        }
    }
}

echo json_encode(["ok"=>true,"mensaje"=>"Estado actualizado." . $infoExtra]);
$conexion->close();

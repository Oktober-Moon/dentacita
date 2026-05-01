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

/* =============================================================
 * Flujo atómico:
 *   1) BEGIN TRANSACTION
 *   2) SELECT cita FOR UPDATE → bloquea para evitar que dos requests
 *      simultáneos de cobro generen dos ingresos.
 *   3) Verificar que esté completada y no tenga cobro activo.
 *   4) Validar que el monto no supere el precio de la cita * 1.0
 *      (no se cobra de más; los reembolsos parciales tienen su flujo).
 *   5) INSERT ingreso.
 *   6) COMMIT o ROLLBACK.
 * ============================================================= */
@$conexion->begin_transaction();
try {
    $s = @$conexion->prepare(
        "SELECT cita_id, paciente_id, paciente_nombre, titulo, estado, precio
         FROM citas WHERE cita_id = ? AND usuario_id = ? FOR UPDATE"
    );
    if (!$s) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $s->bind_param("ii", $citaIdInt, $usuarioId);
    @$s->execute();
    $cita = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$cita) throw new Exception("Cita no encontrada.");

    if ($cita['estado'] !== 'completada') {
        throw new Exception("Solo se cobran citas completadas. La cita está en estado '{$cita['estado']}'.");
    }

    // ¿Ya hay un cobro activo? (lock incluido para evitar carrera)
    $s2 = @$conexion->prepare(
        "SELECT 1 FROM transacciones
         WHERE cita_id = ? AND usuario_id = ? AND estado='activa' AND categoria != 'Reembolso'
         LIMIT 1
         FOR UPDATE"
    );
    if (!$s2) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $s2->bind_param("ii", $citaIdInt, $usuarioId);
    @$s2->execute();
    $yaCobrada = (bool)$s2->get_result()->fetch_assoc();
    $s2->close();
    if ($yaCobrada) throw new Exception("Esta cita ya tiene un cobro registrado.");

    // Determinar monto + validar contra precio de la cita
    $precioCita = $cita['precio'] !== null ? (float)$cita['precio'] : 0;
    $monto = $monto_custom !== '' ? (float)$monto_custom : $precioCita;
    if ($monto <= 0) {
        throw new Exception("La cita no tiene precio asignado. Edítala antes de cobrar.");
    }
    if ($precioCita > 0 && $monto > $precioCita) {
        throw new Exception("El monto a cobrar (\$" . number_format($monto, 2) .
            ") no puede superar el precio de la cita (\$" . number_format($precioCita, 2) . ").");
    }

    $descripcion = "Cita: {$cita['titulo']} · {$cita['paciente_nombre']}";
    $pacV   = $cita['paciente_id'] !== null ? (int)$cita['paciente_id'] : null;
    $fecha  = date('Y-m-d');

    $stmt = @$conexion->prepare(
        "INSERT INTO transacciones
           (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
         VALUES (?, 'ingreso', 'Tratamiento', ?, ?, ?, ?, ?, ?, 'activa')"
    );
    if (!$stmt) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $stmt->bind_param("idssiis", $usuarioId, $monto, $fecha, $descripcion, $pacV, $citaIdInt, $metodo_pago);
    if (!@$stmt->execute()) {
        $errno = $stmt->errno; $error = $stmt->error; $stmt->close();
        throw new Exception(mensajeErrorMysql($errno, $error));
    }
    $insertId = $stmt->insert_id;
    $stmt->close();

    @$conexion->commit();

    echo json_encode([
        "ok"=>true,
        "mensaje"=>"Ingreso de \$" . number_format($monto, 2) . " registrado por la cita.",
        "transaccion_id"=>$insertId
    ]);
} catch (Exception $e) {
    @$conexion->rollback();
    echo json_encode(["ok"=>false, "mensaje"=>$e->getMessage()]);
}
$conexion->close();

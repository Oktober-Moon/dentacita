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

/* =============================================================
 * Flujo atómico (idéntico al de actualizar.php pero sin tocar
 * fecha/precio/paciente; solo el estado):
 *   1) BEGIN TRANSACTION
 *   2) SELECT estado actual + ingreso vinculado (FOR UPDATE).
 *   3) UPDATE estado.
 *   4) Si pasó a 'completada' con precio > 0 y sin cobro → INSERT ingreso.
 *   5) Si downgrade desde 'completada' con cobro → anular ingreso.
 *   6) COMMIT o ROLLBACK.
 * ============================================================= */
@$conexion->begin_transaction();
try {
    // 2) Estado actual + lock
    $s = @$conexion->prepare(
        "SELECT paciente_id, paciente_nombre, titulo, precio, estado
         FROM citas WHERE cita_id = ? AND usuario_id = ? FOR UPDATE"
    );
    if (!$s) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $s->bind_param("ii", $idInt, $usuarioId);
    @$s->execute();
    $cita = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$cita) throw new Exception("Cita no encontrada.");

    $estadoAnterior = $cita['estado'];

    if ($estadoAnterior === $estado) {
        @$conexion->commit();
        echo json_encode(["ok"=>true,"mensaje"=>"La cita ya estaba en ese estado.","sin_cambios"=>true]);
        $conexion->close(); exit;
    }

    // ¿Hay ingreso activo vinculado a esta cita?
    $sCobro = @$conexion->prepare(
        "SELECT transaccion_id FROM transacciones
         WHERE cita_id = ? AND usuario_id = ?
           AND estado = 'activa' AND categoria != 'Reembolso'
         LIMIT 1
         FOR UPDATE"
    );
    if (!$sCobro) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $sCobro->bind_param("ii", $idInt, $usuarioId);
    @$sCobro->execute();
    $rowCobro = $sCobro->get_result()->fetch_assoc();
    $sCobro->close();
    $transaccionExistente = $rowCobro ? (int)$rowCobro['transaccion_id'] : null;

    // 3) UPDATE estado
    $stmt = @$conexion->prepare(
        "UPDATE citas SET estado = ?, motivo_cancelacion = ? WHERE cita_id = ? AND usuario_id = ?"
    );
    if (!$stmt) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $stmt->bind_param("ssii", $estado, $motivoCancV, $idInt, $usuarioId);
    if (!@$stmt->execute()) {
        $errno = $stmt->errno; $error = $stmt->error; $stmt->close();
        throw new Exception(mensajeErrorMysql($errno, $error));
    }
    $stmt->close();

    $infoExtra = "";

    // 4) Auto-cobro al pasar a 'completada'
    if ($estado === 'completada' && $cita['precio'] !== null && (float)$cita['precio'] > 0
        && $transaccionExistente === null) {
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
        if (!$st) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
        $st->bind_param("idssiis", $usuarioId, $precioFinal, $hoyStr, $descTrans, $pacIdInt, $idInt, $metodo);
        if (!@$st->execute()) {
            $errno = $st->errno; $error = $st->error; $st->close();
            throw new Exception(mensajeErrorMysql($errno, $error));
        }
        $st->close();
        $infoExtra = " Se generó un ingreso de \$" . number_format($precioFinal, 2) . " en finanzas.";
    }

    // 5) Downgrade desde 'completada' → anular ingreso
    if ($estadoAnterior === 'completada' && $estado !== 'completada'
        && $transaccionExistente !== null) {
        $motivoAnul = "Cita revertida a '$estado'";
        $sa = @$conexion->prepare(
            "UPDATE transacciones
             SET estado = 'anulada', anulada_en = NOW(), motivo_anulacion = ?
             WHERE transaccion_id = ? AND usuario_id = ?"
        );
        if (!$sa) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
        $sa->bind_param("sii", $motivoAnul, $transaccionExistente, $usuarioId);
        if (!@$sa->execute()) {
            $errno = $sa->errno; $error = $sa->error; $sa->close();
            throw new Exception(mensajeErrorMysql($errno, $error));
        }
        $sa->close();
        $infoExtra = " El ingreso vinculado fue anulado automáticamente.";
    }

    @$conexion->commit();
    echo json_encode(["ok"=>true,"mensaje"=>"Estado actualizado." . $infoExtra]);
} catch (Exception $e) {
    @$conexion->rollback();
    echo json_encode(["ok"=>false, "mensaje"=>$e->getMessage()]);
}
$conexion->close();

<?php
/*
 * ACTUALIZAR CITA · endpoint AJAX (JSON)
 * ----------------------------------------
 *   GET  ?cita_id=N  → devuelve la cita para precargar el formulario
 *   POST             → ejecuta UPDATE
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

require __DIR__ . '/_validaciones.php';


/* ============================================================
 * GET · leer cita para precargar el formulario
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['cita_id'] ?? '';
    if (!is_numeric($id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]);
        exit;
    }

    $stmt = @$conexion->prepare(
        "SELECT cita_id, paciente_id, paciente_nombre, paciente_telefono,
                titulo, descripcion, fecha_hora_inicio, fecha_hora_fin,
                estado, notas, precio
         FROM citas WHERE cita_id = ? AND usuario_id = ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $idGet = (int)$id;
    $stmt->bind_param("ii", $idGet, $usuarioId);
    @$stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $conexion->close();

    if (!$cita) {
        echo json_encode(["ok"=>false,"mensaje"=>"Cita no encontrada."]);
        exit;
    }
    echo json_encode(["ok"=>true,"cita"=>$cita]);
    exit;
}


/* ============================================================
 * POST · actualizar cita
 * ============================================================ */
$id                 = $_POST['cita_id'] ?? '';
$paciente_id        = trim($_POST['paciente_id'] ?? '');
$paciente_nombre    = preg_replace('/\s+/', ' ', trim($_POST['paciente_nombre'] ?? ''));
$paciente_telefono  = trim($_POST['paciente_telefono'] ?? '');
$titulo             = preg_replace('/\s+/', ' ', trim($_POST['titulo'] ?? ''));
$descripcion        = trim($_POST['descripcion'] ?? '');
$fecha              = $_POST['fecha'] ?? '';
$hora_inicio        = $_POST['hora_inicio'] ?? '';
$duracion           = $_POST['duracion'] ?? '';
$estadoCita         = $_POST['estado'] ?? '';
$notas              = trim($_POST['notas'] ?? '');
$precio             = trim($_POST['precio'] ?? '');
$metodoPagoCobro    = trim($_POST['metodo_pago'] ?? 'efectivo');
$motivoCancelacion  = trim($_POST['motivo_cancelacion'] ?? '');

if (!is_numeric($id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de cita no válido."]);
    exit;
}

/* ----- Validaciones ----- */
$err = validarPacienteNombre($paciente_nombre);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTelefonoOpcional($paciente_telefono);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTitulo($titulo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarFechaHoraDuracion($fecha, $hora_inicio, $duracion);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarEstadoCita($estadoCita);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarPrecioOpcional($precio);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }


/* ----- Cálculo de fecha_hora_inicio y fecha_hora_fin ----- */
$inicio = $fecha . ' ' . $hora_inicio . ':00';
$tsIni  = strtotime($inicio);
$tsFin  = $tsIni + ((int)$duracion) * 60;
$fin    = date('Y-m-d H:i:s', $tsFin);

$pacienteIdInt = is_numeric($paciente_id) ? (int)$paciente_id : null;
$precioFinal   = ($precio === '' || $precio === null) ? null : (float)$precio;

/* ----- Anti-IDOR: validar que el paciente referenciado pertenezca al usuario ----- */
if ($pacienteIdInt !== null) {
    $chkP = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ? LIMIT 1");
    if ($chkP) {
        $chkP->bind_param("ii", $pacienteIdInt, $usuarioId);
        @$chkP->execute();
        $okP = (bool)$chkP->get_result()->fetch_assoc();
        $chkP->close();
        if (!$okP) {
            echo json_encode(["ok"=>false,"mensaje"=>"El paciente no pertenece a este usuario."]);
            $conexion->close(); exit;
        }
    }
}
$descripcionV  = $descripcion === '' ? null : $descripcion;
$telefonoV     = $paciente_telefono === '' ? null : $paciente_telefono;
$notasV        = $notas === '' ? null : $notas;
// Solo persistimos motivo si la cita queda cancelada; si pasa a otro estado, limpiamos.
$motivoCancV   = ($estadoCita === 'cancelada' && $motivoCancelacion !== '')
    ? mb_substr($motivoCancelacion, 0, 200) : null;
$idInt         = (int)$id;

/* ----- Detección de solapamiento (excluyendo la propia cita) ----- */
$chkOverlap = @$conexion->prepare(
    "SELECT cita_id, titulo, fecha_hora_inicio, fecha_hora_fin
     FROM citas
     WHERE usuario_id = ?
       AND cita_id != ?
       AND estado NOT IN ('cancelada', 'no_asistio')
       AND fecha_hora_inicio < ?
       AND fecha_hora_fin    > ?
     LIMIT 1"
);
if ($chkOverlap) {
    $chkOverlap->bind_param("iiss", $usuarioId, $idInt, $fin, $inicio);
    @$chkOverlap->execute();
    $citaSolapada = $chkOverlap->get_result()->fetch_assoc();
    $chkOverlap->close();
    if ($citaSolapada) {
        $hi = substr($citaSolapada['fecha_hora_inicio'], 11, 5);
        $hf = substr($citaSolapada['fecha_hora_fin'],    11, 5);
        echo json_encode([
            "ok" => false,
            "mensaje" => "Hay una cita que se solapa: \"" . $citaSolapada['titulo'] . "\" ($hi–$hf). Cambia el horario."
        ]);
        $conexion->close(); exit;
    }
}


/* =============================================================
 * Flujo atómico:
 *   1) BEGIN TRANSACTION
 *   2) SELECT estado_actual + cita ya cobrada (FOR UPDATE evita
 *      race condition con cobro paralelo).
 *   3) UPDATE cita.
 *   4) Si transición a 'completada' y no había cobro → INSERT ingreso.
 *   5) Si transición DESDE 'completada' (downgrade) → anular ingreso.
 *   6) COMMIT o ROLLBACK si algo falla.
 * ============================================================= */
@$conexion->begin_transaction();
try {
    // 2) Estado actual + lock para evitar cobros simultáneos
    $sEst = @$conexion->prepare(
        "SELECT estado FROM citas WHERE cita_id = ? AND usuario_id = ? FOR UPDATE"
    );
    if (!$sEst) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $sEst->bind_param("ii", $idInt, $usuarioId);
    @$sEst->execute();
    $rowEst = $sEst->get_result()->fetch_assoc();
    $sEst->close();
    if (!$rowEst) throw new Exception("La cita ya no existe.");
    $estadoAnterior = $rowEst['estado'];

    // ¿La cita ya tiene un ingreso activo? (excluye reembolsos)
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

    // 3) UPDATE cita
    $sql = "UPDATE citas
            SET paciente_id        = ?,
                paciente_nombre    = ?,
                paciente_telefono  = ?,
                titulo             = ?,
                descripcion        = ?,
                fecha_hora_inicio  = ?,
                fecha_hora_fin     = ?,
                estado             = ?,
                notas              = ?,
                motivo_cancelacion = ?,
                precio             = ?
            WHERE cita_id = ? AND usuario_id = ?";
    $stmt = @$conexion->prepare($sql);
    if (!$stmt) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $stmt->bind_param("isssssssssdii",
        $pacienteIdInt, $paciente_nombre, $telefonoV, $titulo, $descripcionV,
        $inicio, $fin, $estadoCita, $notasV, $motivoCancV,
        $precioFinal, $idInt, $usuarioId
    );
    if (!@$stmt->execute()) {
        $errno = $stmt->errno; $error = $stmt->error; $stmt->close();
        throw new Exception(mensajeErrorMysql($errno, $error));
    }
    $stmt->close();

    $infoExtra = "";

    // 4) Auto-cobro al pasar a 'completada'
    if ($estadoCita === 'completada' && $precioFinal !== null && $precioFinal > 0
        && $transaccionExistente === null) {
        $descTrans = "Cita: {$titulo} · {$paciente_nombre}";
        $hoyStr = date('Y-m-d');
        $metodosValidos = ['efectivo','tarjeta','transferencia','cheque','otro'];
        $metodoPagoFinal = in_array($metodoPagoCobro, $metodosValidos, true) ? $metodoPagoCobro : 'efectivo';
        $st = @$conexion->prepare(
            "INSERT INTO transacciones
               (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
             VALUES (?, 'ingreso', 'Tratamiento', ?, ?, ?, ?, ?, ?, 'activa')"
        );
        if (!$st) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
        $st->bind_param("idssiis", $usuarioId, $precioFinal, $hoyStr, $descTrans, $pacienteIdInt, $idInt, $metodoPagoFinal);
        if (!@$st->execute()) {
            $errno = $st->errno; $error = $st->error; $st->close();
            throw new Exception(mensajeErrorMysql($errno, $error));
        }
        $st->close();
        $infoExtra = " Se generó un ingreso de \$" . number_format($precioFinal, 2) . " en finanzas (" . $metodoPagoFinal . ").";
    }

    // 5) Downgrade desde 'completada' → anular ingreso huérfano
    if ($estadoAnterior === 'completada' && $estadoCita !== 'completada'
        && $transaccionExistente !== null) {
        $motivoAnul = "Cita revertida a '$estadoCita'";
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

    echo json_encode([
        "ok"      => true,
        "mensaje" => "Cita actualizada correctamente." . $infoExtra,
        "fecha"   => $fecha
    ]);
} catch (Exception $e) {
    @$conexion->rollback();
    echo json_encode(["ok"=>false, "mensaje"=>$e->getMessage()]);
}

$conexion->close();

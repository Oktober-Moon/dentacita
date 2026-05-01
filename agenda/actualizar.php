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


/* ----- Update ----- */
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
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

// Tipos: i=int, s=string, d=double. precio=DECIMAL→'d', cita_id=INT→'i' final.
$stmt->bind_param("isssssssssdii",
    $pacienteIdInt,
    $paciente_nombre,
    $telefonoV,
    $titulo,
    $descripcionV,
    $inicio,
    $fin,
    $estadoCita,
    $notasV,
    $motivoCancV,
    $precioFinal,
    $idInt,
    $usuarioId
);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        $check = @$conexion->prepare("SELECT 1 FROM citas WHERE cita_id = ? AND usuario_id = ?");
        $check->bind_param("ii", $idInt, $usuarioId);
        @$check->execute();
        $existe = $check->get_result()->fetch_row();
        $check->close();
        if (!$existe) {
            echo json_encode(["ok"=>false,"mensaje"=>"La cita ya no existe."]);
            $stmt->close(); $conexion->close(); exit;
        }
    }
    $stmt->close();

    /* =========================================================
     * FLUJO CRUZADO: cita completada con precio → ingreso
     * Si el dentista cambió el estado a 'completada' y hay precio,
     * generamos automáticamente la transacción de ingreso si aún no existe.
     * ========================================================= */
    $infoExtra = "";
    if ($estadoCita === 'completada' && $precioFinal !== null && $precioFinal > 0) {
        // ¿Ya hay transacción activa para esta cita (que no sea reembolso)?
        $sc = @$conexion->prepare(
            "SELECT 1 FROM transacciones
             WHERE cita_id = ? AND usuario_id = ? AND estado='activa' AND categoria != 'Reembolso' LIMIT 1"
        );
        $sc->bind_param("ii", $idInt, $usuarioId);
        @$sc->execute();
        $yaCobrada = (bool)$sc->get_result()->fetch_assoc();
        $sc->close();

        if (!$yaCobrada) {
            $descTrans = "Cita: {$titulo} · {$paciente_nombre}";
            $hoyStr = date('Y-m-d');
            // Validar metodo_pago; si inválido o vacío, default 'efectivo'.
            $metodosValidos = ['efectivo','tarjeta','transferencia','cheque','otro'];
            $metodoPagoFinal = in_array($metodoPagoCobro, $metodosValidos, true) ? $metodoPagoCobro : 'efectivo';
            $st = @$conexion->prepare(
                "INSERT INTO transacciones
                   (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
                 VALUES (?, 'ingreso', 'Tratamiento', ?, ?, ?, ?, ?, ?, 'activa')"
            );
            if ($st) {
                $st->bind_param("idssiis", $usuarioId, $precioFinal, $hoyStr, $descTrans, $pacienteIdInt, $idInt, $metodoPagoFinal);
                if (@$st->execute()) {
                    $infoExtra = " Se generó un ingreso de \$" . number_format($precioFinal, 2) . " en finanzas (" . $metodoPagoFinal . ").";
                }
                $st->close();
            }
        }
    }

    echo json_encode([
        "ok"      => true,
        "mensaje" => "Cita actualizada correctamente." . $infoExtra,
        "fecha"   => $fecha
    ]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    $stmt->close();
}

$conexion->close();

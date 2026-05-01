<?php
/*
 * FINANZAS · reembolsos sobre citas
 * ----------------------------------
 * Endpoints (POST):
 *   accion=crear   · cita_id, monto, motivo  → crea transacción income con monto NEGATIVO
 *   accion=anular  · transaccion_id, motivo → marca la transacción como 'anulada'
 *
 * El reembolso se modela como un INSERT en transacciones con:
 *   tipo='ingreso', categoria='Reembolso', monto = -ABS(monto)
 *   cita_id apuntando a la cita original
 *   estado='activa'
 *
 * Anular un reembolso = UPDATE estado='anulada', anulada_en, motivo_anulacion.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$accion = $_POST['accion'] ?? '';

/* ============================================================
 *  CREAR REEMBOLSO
 * ============================================================ */
if ($accion === 'crear') {
    $cita_id = $_POST['cita_id'] ?? '';
    $monto   = trim($_POST['monto'] ?? '');
    $motivo  = trim($_POST['motivo'] ?? '');

    if (!is_numeric($cita_id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"Cita no válida."]); exit;
    }
    $citaIdInt = (int)$cita_id;

    $err = validarTransMonto($monto, false);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
    $err = validarTransDescripcion($motivo);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $montoF = (float)$monto;

    /* Verificar que la cita exista, pertenezca al usuario, y obtener precio + ya cobrado */
    $s = @$conexion->prepare(
        "SELECT c.titulo, c.paciente_nombre, c.precio,
                (SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = ?
                   AND t.estado='activa' AND t.categoria != 'Reembolso')
                  AS cobrado,
                (SELECT COALESCE(SUM(ABS(t.monto)), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = ?
                   AND t.estado='activa' AND t.categoria = 'Reembolso')
                  AS reembolsado
         FROM citas c WHERE c.cita_id = ? AND c.usuario_id = ?"
    );
    $s->bind_param("iiii", $usuarioId, $usuarioId, $citaIdInt, $usuarioId);
    @$s->execute();
    $cita = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$cita) {
        echo json_encode(["ok"=>false,"mensaje"=>"Cita no encontrada."]);
        $conexion->close(); exit;
    }

    $cobrado     = (float)$cita['cobrado'];
    $reembolsado = (float)$cita['reembolsado'];
    $disponible  = $cobrado - $reembolsado;

    if ($cobrado <= 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"Esta cita no tiene ingresos registrados, no se puede reembolsar."]);
        $conexion->close(); exit;
    }
    if ($montoF > $disponible) {
        echo json_encode(["ok"=>false,"mensaje"=>"El reembolso excede el monto disponible (\$" . number_format($disponible, 2) . ")."]);
        $conexion->close(); exit;
    }

    $descripcion = "Reembolso · {$cita['titulo']} · {$cita['paciente_nombre']} · " . $motivo;
    $montoNegativo = -abs($montoF);
    $fecha = date('Y-m-d');

    $stmt = @$conexion->prepare(
        "INSERT INTO transacciones
            (usuario_id, tipo, categoria, monto, fecha, descripcion, cita_id, estado)
         VALUES (?, 'ingreso', 'Reembolso', ?, ?, ?, ?, 'activa')"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("idssi", $usuarioId, $montoNegativo, $fecha, $descripcion, $citaIdInt);
    if (@$stmt->execute()) {
        echo json_encode([
            "ok"=>true,
            "mensaje"=>"Reembolso de \$" . number_format($montoF, 2) . " registrado.",
            "transaccion_id"=>$stmt->insert_id
        ]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
    $stmt->close();
    $conexion->close();
    exit;
}


/* ============================================================
 *  ANULAR REEMBOLSO
 * ============================================================ */
if ($accion === 'anular') {
    $trans_id = $_POST['transaccion_id'] ?? '';
    $motivo   = trim($_POST['motivo'] ?? '');

    if (!is_numeric($trans_id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
    }
    $idInt = (int)$trans_id;

    $err = validarMotivoAnulacion($motivo);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    /* Verificar que sea reembolso activo y pertenezca al usuario (anti-IDOR) */
    $s = @$conexion->prepare(
        "SELECT estado, categoria FROM transacciones
         WHERE transaccion_id = ? AND usuario_id = ?"
    );
    $s->bind_param("ii", $idInt, $usuarioId);
    @$s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) {
        echo json_encode(["ok"=>false,"mensaje"=>"Transacción no encontrada."]);
        $conexion->close(); exit;
    }
    if ($row['categoria'] !== 'Reembolso') {
        echo json_encode(["ok"=>false,"mensaje"=>"Esta transacción no es un reembolso."]);
        $conexion->close(); exit;
    }
    if ($row['estado'] === 'anulada') {
        echo json_encode(["ok"=>false,"mensaje"=>"El reembolso ya estaba anulado."]);
        $conexion->close(); exit;
    }

    $stmt = @$conexion->prepare(
        "UPDATE transacciones
         SET estado='anulada', anulada_en = NOW(), motivo_anulacion = ?
         WHERE transaccion_id = ? AND usuario_id = ?"
    );
    $stmt->bind_param("sii", $motivo, $idInt, $usuarioId);
    if (@$stmt->execute()) {
        echo json_encode(["ok"=>true,"mensaje"=>"Reembolso anulado."]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
    $stmt->close();
    $conexion->close();
    exit;
}


echo json_encode(["ok"=>false,"mensaje"=>"Acción no reconocida."]);
$conexion->close();

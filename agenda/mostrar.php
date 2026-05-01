<?php
/*
 * MOSTRAR CITAS · endpoint AJAX (JSON)
 * --------------------------------------
 * Dos modos:
 *   GET ?fecha=YYYY-MM-DD  → citas de ese día (con datos completos)
 *   GET ?mes=YYYY-MM       → citas del mes (resumen para los marcadores)
 *
 * El segundo modo es liviano: solo regresa fecha y hora para pintar
 * los puntos del calendario sin traer todo el detalle.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

/* ----- Modo "mes" ----- */
if (isset($_GET['mes'])) {
    $mes = $_GET['mes'];
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        echo json_encode(["ok" => false, "mensaje" => "Mes no válido."]);
        exit;
    }

    $stmt = @$conexion->prepare(
        "SELECT c.cita_id, c.fecha_hora_inicio, c.estado,
                c.titulo, c.paciente_nombre,
                (SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
                   AND t.estado='activa' AND t.categoria != 'Reembolso') AS cobrado_total,
                (SELECT COALESCE(SUM(ABS(t.monto)), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
                   AND t.estado='activa' AND t.categoria = 'Reembolso') AS reembolsado_total
         FROM citas c
         WHERE c.usuario_id = ? AND DATE_FORMAT(c.fecha_hora_inicio, '%Y-%m') = ?
         ORDER BY c.fecha_hora_inicio ASC"
    );
    if (!$stmt) {
        echo json_encode(["ok" => false, "mensaje" => mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("is", $usuarioId, $mes);
    @$stmt->execute();
    $res = $stmt->get_result();

    $citas = [];
    while ($f = $res->fetch_assoc()) $citas[] = $f;

    $stmt->close(); $conexion->close();
    echo json_encode(["ok" => true, "citas" => $citas]);
    exit;
}

/* ----- Modo "rango" (vista semanal: desde/hasta) ----- */
if (isset($_GET['desde']) && isset($_GET['hasta'])) {
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        echo json_encode(["ok" => false, "mensaje" => "Rango no válido."]);
        exit;
    }
    $desdeFull = $desde . ' 00:00:00';
    $hastaFull = $hasta . ' 23:59:59';
    $stmt = @$conexion->prepare(
        "SELECT c.cita_id, c.paciente_id, c.paciente_nombre, c.titulo,
                c.fecha_hora_inicio, c.fecha_hora_fin, c.estado, c.precio,
                (SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
                   AND t.estado='activa' AND t.categoria != 'Reembolso') AS cobrado_total,
                (SELECT COALESCE(SUM(ABS(t.monto)), 0) FROM transacciones t
                 WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
                   AND t.estado='activa' AND t.categoria = 'Reembolso') AS reembolsado_total
         FROM citas c
         WHERE c.usuario_id = ? AND c.fecha_hora_inicio BETWEEN ? AND ?
         ORDER BY c.fecha_hora_inicio ASC"
    );
    if (!$stmt) {
        echo json_encode(["ok" => false, "mensaje" => mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("iss", $usuarioId, $desdeFull, $hastaFull);
    @$stmt->execute();
    $r = $stmt->get_result();
    $citas = [];
    while ($f = $r->fetch_assoc()) $citas[] = $f;
    $stmt->close(); $conexion->close();
    echo json_encode(["ok" => true, "citas" => $citas]);
    exit;
}

/* ----- Modo "fecha" ----- */
$fecha = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(["ok" => false, "mensaje" => "Fecha no válida."]);
    exit;
}

$stmt = @$conexion->prepare(
    "SELECT c.cita_id, c.paciente_id, c.paciente_nombre, c.paciente_telefono,
            c.titulo, c.descripcion, c.fecha_hora_inicio, c.fecha_hora_fin,
            c.estado, c.notas, c.precio,
            (SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t
             WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
               AND t.estado='activa' AND t.categoria != 'Reembolso') AS cobrado_total,
            (SELECT COALESCE(SUM(ABS(t.monto)), 0) FROM transacciones t
             WHERE t.cita_id = c.cita_id AND t.usuario_id = c.usuario_id
               AND t.estado='activa' AND t.categoria = 'Reembolso') AS reembolsado_total
     FROM citas c
     WHERE c.usuario_id = ? AND DATE(c.fecha_hora_inicio) = ?
     ORDER BY c.fecha_hora_inicio ASC"
);
if (!$stmt) {
    echo json_encode(["ok" => false, "mensaje" => mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("is", $usuarioId, $fecha);
@$stmt->execute();
$res = $stmt->get_result();

$citas = [];
while ($f = $res->fetch_assoc()) $citas[] = $f;

$stmt->close(); $conexion->close();
echo json_encode(["ok" => true, "citas" => $citas]);

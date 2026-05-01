<?php
/*
 * ACUERDOS · editar acuerdo existente
 * ------------------------------------
 *  GET  ?acuerdo_id=N → devuelve datos del acuerdo para precarga.
 *  POST → actualiza servicio, descripcion, fecha_programada, duracion, precio.
 *         Solo permitido si estado IN ('pendiente','aceptado').
 *         Si estado='aceptado' y tiene cita_id, sincroniza la cita asociada
 *         (titulo, descripcion, fecha_hora_inicio/fin, precio).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones-acuerdos.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

/* ---------- GET: precargar para el modal ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['acuerdo_id'] ?? '';
    if (!is_numeric($id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
    }
    $idInt = (int)$id;

    $stmt = @$conexion->prepare(
        "SELECT a.acuerdo_id, a.paciente_id, a.servicio, a.descripcion,
                a.fecha_programada, a.duracion_minutos, a.precio, a.estado, a.cita_id
         FROM acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.acuerdo_id = ? AND p.usuario_id = ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("ii", $idInt, $usuarioId);
    @$stmt->execute();
    $acuerdo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conexion->close();

    if (!$acuerdo) {
        echo json_encode(["ok"=>false,"mensaje"=>"Acuerdo no encontrado."]);
        exit;
    }
    echo json_encode(["ok"=>true,"acuerdo"=>$acuerdo]);
    exit;
}

/* ---------- POST: actualizar ---------- */
$acuerdo_id       = $_POST['acuerdo_id']       ?? '';
$servicio         = trim($_POST['servicio']    ?? '');
$descripcion      = trim($_POST['descripcion'] ?? '');
$fecha_programada = trim($_POST['fecha_programada'] ?? '');
$duracion_minutos = trim($_POST['duracion_minutos'] ?? '60');
$precio           = trim($_POST['precio']      ?? '');

if (!is_numeric($acuerdo_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Acuerdo no válido."]); exit;
}
$idInt = (int)$acuerdo_id;

$err = validarAcuerdoServicio($servicio);                if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoFechaProgramada($fecha_programada); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoDuracion($duracion_minutos);        if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoPrecio($precio);                    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarAcuerdoDescripcion($descripcion);          if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$durInt  = (int)$duracion_minutos;
$precioF = (float)$precio;
$descV   = $descripcion === '' ? null : $descripcion;

$conexion->begin_transaction();
try {
    /* Cargar acuerdo + verificar pertenencia y estado editable */
    $sa = @$conexion->prepare(
        "SELECT a.estado, a.cita_id, a.paciente_id
         FROM acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.acuerdo_id = ? AND p.usuario_id = ? FOR UPDATE"
    );
    $sa->bind_param("ii", $idInt, $usuarioId);
    @$sa->execute();
    $acu = $sa->get_result()->fetch_assoc();
    $sa->close();
    if (!$acu) throw new Exception("Acuerdo no encontrado.");
    if (!in_array($acu['estado'], ['pendiente','aceptado'], true)) {
        throw new Exception("Solo se editan acuerdos en estado pendiente o aceptado.");
    }

    /* UPDATE acuerdo */
    $su = @$conexion->prepare(
        "UPDATE acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.servicio=?, a.descripcion=?, a.fecha_programada=?, a.duracion_minutos=?, a.precio=?
         WHERE a.acuerdo_id=? AND p.usuario_id=?"
    );
    $su->bind_param("sssidii", $servicio, $descV, $fecha_programada, $durInt, $precioF, $idInt, $usuarioId);
    if (!@$su->execute()) throw new Exception(mensajeErrorMysql($su->errno, $su->error));
    $su->close();

    /* Si el acuerdo estaba aceptado y tiene cita asociada, sincronizar la cita */
    $infoExtra = "";
    if ($acu['estado'] === 'aceptado' && $acu['cita_id']) {
        $citaIdInt = (int)$acu['cita_id'];
        $tsInicio  = strtotime($fecha_programada);
        $tsFin     = $tsInicio + $durInt * 60;
        $fechaIni  = date('Y-m-d H:i:s', $tsInicio);
        $fechaFin  = date('Y-m-d H:i:s', $tsFin);

        $sc = @$conexion->prepare(
            "UPDATE citas SET titulo=?, descripcion=?, fecha_hora_inicio=?, fecha_hora_fin=?, precio=?
             WHERE cita_id=? AND usuario_id=?"
        );
        $sc->bind_param("ssssdii", $servicio, $descV, $fechaIni, $fechaFin, $precioF, $citaIdInt, $usuarioId);
        if (@$sc->execute()) {
            $infoExtra = " La cita asociada también se actualizó.";
        }
        $sc->close();
    }

    $conexion->commit();
    echo json_encode(["ok"=>true,"mensaje"=>"Acuerdo actualizado." . $infoExtra]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}
$conexion->close();

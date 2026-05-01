<?php
/*
 * ACUERDOS · cambiar estado (rechazar/cancelar/completar)
 * --------------------------------------------------------
 * POST acuerdo_id, nuevo_estado, motivo (opcional)
 *
 * Transiciones permitidas:
 *   pendiente  → rechazado | cancelado
 *   aceptado   → cancelado | completado
 *
 * Si transición a 'cancelado' y tenía cita_id: marca la cita como 'cancelada'.
 * Crea notificación informativa.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$acuerdo_id   = $_POST['acuerdo_id']   ?? '';
$nuevo_estado = $_POST['nuevo_estado'] ?? '';
$motivo       = trim($_POST['motivo']  ?? '');

if (!is_numeric($acuerdo_id) || (int)$acuerdo_id <= 0) {
    echo json_encode(["ok"=>false,"mensaje"=>"Acuerdo no válido."]); exit;
}
$idInt = (int)$acuerdo_id;

if (!in_array($nuevo_estado, ['rechazado','cancelado','completado'], true)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Estado destino no permitido por este endpoint."]);
    exit;
}
if (mb_strlen($motivo) > 200) {
    echo json_encode(["ok"=>false,"mensaje"=>"El motivo no puede pasar de 200 caracteres."]);
    exit;
}

$conexion->begin_transaction();
try {
    /* 1) Leer acuerdo (filtrar por usuario_id del paciente) */
    $sa = @$conexion->prepare(
        "SELECT a.acuerdo_id, a.paciente_id, a.cita_id, a.servicio, a.estado,
                p.nombre_completo AS paciente_nombre
         FROM acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.acuerdo_id = ? AND p.usuario_id = ? FOR UPDATE"
    );
    $sa->bind_param("ii", $idInt, $usuarioId);
    @$sa->execute();
    $acuerdo = $sa->get_result()->fetch_assoc();
    $sa->close();
    if (!$acuerdo) throw new Exception("Acuerdo no encontrado.");

    $estadoActual = $acuerdo['estado'];
    $transicionesPermitidas = [
        'pendiente' => ['rechazado','cancelado'],
        'aceptado'  => ['cancelado','completado'],
    ];
    if (!isset($transicionesPermitidas[$estadoActual])
        || !in_array($nuevo_estado, $transicionesPermitidas[$estadoActual], true)) {
        throw new Exception("No se puede pasar de '$estadoActual' a '$nuevo_estado'.");
    }

    /* 2) UPDATE acuerdo (filtrado por usuario_id via JOIN) */
    $su = @$conexion->prepare(
        "UPDATE acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.estado = ?
         WHERE a.acuerdo_id = ? AND p.usuario_id = ?"
    );
    $su->bind_param("sii", $nuevo_estado, $idInt, $usuarioId);
    if (!@$su->execute()) throw new Exception(mensajeErrorMysql($su->errno, $su->error));
    $su->close();

    /* 3) Si se cancela y había cita, marcar cita como cancelada (también filtrada por usuario) */
    if ($nuevo_estado === 'cancelado' && $acuerdo['cita_id']) {
        $sc = @$conexion->prepare(
            "UPDATE citas SET estado='cancelada' WHERE cita_id = ? AND usuario_id = ?"
        );
        $citaId = (int)$acuerdo['cita_id'];
        $sc->bind_param("ii", $citaId, $usuarioId);
        @$sc->execute();
        $sc->close();
    }

    /* 4) Notificación */
    $tipoN = 'acuerdo';
    $titN = match($nuevo_estado) {
        'rechazado'  => 'Acuerdo rechazado',
        'cancelado'  => 'Acuerdo cancelado',
        'completado' => 'Acuerdo completado',
    };
    $msjN = "{$acuerdo['servicio']} con {$acuerdo['paciente_nombre']}." . ($motivo !== '' ? " · $motivo" : '');
    $enlN = '/pacientes/detalle.php?id=' . (int)$acuerdo['paciente_id'] . '&tab=acuerdos';
    $sn = @$conexion->prepare(
        "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida) VALUES (?, ?, ?, ?, ?, 0)"
    );
    $sn->bind_param("issss", $usuarioId, $tipoN, $titN, $msjN, $enlN);
    @$sn->execute();
    $sn->close();

    $conexion->commit();
    echo json_encode(["ok"=>true,"mensaje"=>"Acuerdo $nuevo_estado."]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}
$conexion->close();

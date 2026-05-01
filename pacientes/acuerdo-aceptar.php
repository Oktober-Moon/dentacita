<?php
/*
 * ACUERDOS · aceptar (flujo cruzado)
 * ===================================
 * Al aceptar un acuerdo:
 *   1) Verificar que esté 'pendiente'
 *   2) INSERT en citas (estado='confirmada' si fecha futura)
 *   3) UPDATE acuerdos_servicio (estado='aceptado', cita_id=N)
 *   4) INSERT notificación tipo='acuerdo' (acuerdo aceptado)
 *   5) INSERT notificación tipo='cita'    (cita programada)
 *
 * Atómico (begin_transaction / commit / rollback).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$acuerdo_id = $_POST['acuerdo_id'] ?? '';
if (!is_numeric($acuerdo_id) || (int)$acuerdo_id <= 0) {
    echo json_encode(["ok"=>false,"mensaje"=>"Acuerdo no válido."]); exit;
}
$idInt = (int)$acuerdo_id;

$conexion->begin_transaction();
try {
    /* 1) Bloquear y leer acuerdo + datos del paciente
     *    (filtra por usuario_id del paciente para evitar IDOR) */
    $sa = @$conexion->prepare(
        "SELECT a.acuerdo_id, a.paciente_id, a.cita_id, a.servicio, a.descripcion,
                a.fecha_programada, a.duracion_minutos, a.precio, a.estado,
                p.nombre_completo AS paciente_nombre, p.telefono AS paciente_telefono
         FROM acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.acuerdo_id = ? AND p.usuario_id = ? FOR UPDATE"
    );
    if (!$sa) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $sa->bind_param("ii", $idInt, $usuarioId);
    @$sa->execute();
    $acuerdo = $sa->get_result()->fetch_assoc();
    $sa->close();
    if (!$acuerdo) throw new Exception("Acuerdo no encontrado.");
    if ($acuerdo['estado'] !== 'pendiente') {
        throw new Exception("Solo se pueden aceptar acuerdos pendientes (estado actual: '{$acuerdo['estado']}').");
    }

    /* 2) Calcular fecha de fin de la cita */
    $tsInicio = strtotime($acuerdo['fecha_programada']);
    $tsFin    = $tsInicio + ((int)$acuerdo['duracion_minutos']) * 60;
    $fechaIni = date('Y-m-d H:i:s', $tsInicio);
    $fechaFin = date('Y-m-d H:i:s', $tsFin);
    $estadoCita = ($tsInicio >= time() - 3600) ? 'confirmada' : 'programada';

    /* 3) INSERT en citas (persistir usuario_id del dentista) */
    $sc = @$conexion->prepare(
        "INSERT INTO citas
            (usuario_id, paciente_id, paciente_nombre, paciente_telefono, titulo, descripcion,
             fecha_hora_inicio, fecha_hora_fin, estado, precio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$sc) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $pacId   = (int)$acuerdo['paciente_id'];
    $titCita = $acuerdo['servicio'];
    $descCita= $acuerdo['descripcion'];
    $tel     = $acuerdo['paciente_telefono'];
    $nom     = $acuerdo['paciente_nombre'];
    $precioF = (float)$acuerdo['precio'];
    $sc->bind_param("iisssssssd",
        $usuarioId, $pacId, $nom, $tel, $titCita, $descCita,
        $fechaIni, $fechaFin, $estadoCita, $precioF
    );
    if (!@$sc->execute()) throw new Exception(mensajeErrorMysql($sc->errno, $sc->error));
    $citaId = $sc->insert_id;
    $sc->close();

    /* 4) UPDATE acuerdo (filtrado por usuario via JOIN) */
    $sup = @$conexion->prepare(
        "UPDATE acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.estado='aceptado', a.cita_id = ?
         WHERE a.acuerdo_id = ? AND p.usuario_id = ?"
    );
    $sup->bind_param("iii", $citaId, $idInt, $usuarioId);
    if (!@$sup->execute()) throw new Exception(mensajeErrorMysql($sup->errno, $sup->error));
    $sup->close();

    /* 5) INSERT notificación tipo='acuerdo' */
    $tipo1 = 'acuerdo';
    $tit1  = 'Acuerdo aceptado';
    $msj1  = "Aceptaste el acuerdo de '{$acuerdo['servicio']}' con {$acuerdo['paciente_nombre']}.";
    $enl1  = '/pacientes/detalle.php?id=' . $pacId . '&tab=acuerdos';
    $sn1 = @$conexion->prepare(
        "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida) VALUES (?, ?, ?, ?, ?, 0)"
    );
    $sn1->bind_param("issss", $usuarioId, $tipo1, $tit1, $msj1, $enl1);
    @$sn1->execute();
    $sn1->close();

    /* 6) INSERT notificación tipo='cita' */
    $tipo2 = 'cita';
    $tit2  = 'Cita programada';
    $msj2  = "Cita '{$acuerdo['servicio']}' agendada con {$acuerdo['paciente_nombre']} para " . date('d/m/Y H:i', $tsInicio) . ".";
    $enl2  = '/agenda/';
    $sn2 = @$conexion->prepare(
        "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida) VALUES (?, ?, ?, ?, ?, 0)"
    );
    $sn2->bind_param("issss", $usuarioId, $tipo2, $tit2, $msj2, $enl2);
    @$sn2->execute();
    $sn2->close();

    $conexion->commit();
    echo json_encode([
        "ok"      => true,
        "mensaje" => "Acuerdo aceptado. Se creó la cita correspondiente.",
        "cita_id" => $citaId
    ]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}
$conexion->close();

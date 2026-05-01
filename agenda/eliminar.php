<?php
/*
 * ELIMINAR CITA · endpoint AJAX (JSON)
 * --------------------------------------
 * Borra una cita por ID. No borra el paciente; solo la cita.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

$id = $_POST['cita_id'] ?? '';

if (!is_numeric($id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]);
    exit;
}

$stmt = @$conexion->prepare("DELETE FROM citas WHERE cita_id = ? AND usuario_id = ?");
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

$idInt = (int)$id;

/* Contar cuántas transacciones financieras quedan vinculadas a esta cita
   (se preservan automáticamente por ON DELETE SET NULL, pero avisamos al dentista) */
$cnt = 0;
if ($sc = @$conexion->prepare("SELECT COUNT(*) FROM transacciones WHERE cita_id = ? AND usuario_id = ? AND estado='activa'")) {
    $sc->bind_param("ii", $idInt, $usuarioId);
    @$sc->execute();
    $cnt = (int)$sc->get_result()->fetch_array(MYSQLI_NUM)[0];
    $sc->close();
}

$stmt->bind_param("ii", $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"La cita ya no existe."]);
    } else {
        $msj = "Cita eliminada correctamente.";
        if ($cnt > 0) {
            $msj .= " Se preservaron $cnt transacción(es) financieras (ya no están vinculadas a la cita).";
        }
        echo json_encode(["ok"=>true,"mensaje"=>$msj]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

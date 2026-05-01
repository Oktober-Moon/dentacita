<?php
/*
 * FINANZAS · anular transacción regular (no reembolso)
 * -----------------------------------------------------
 * POST transaccion_id, motivo
 *
 * UPDATE estado='anulada' + anulada_en + motivo_anulacion. Anti-IDOR
 * por usuario_id. No permite anular si ya está anulada o si es reembolso
 * (los reembolsos se anulan desde reembolso.php?accion=anular).
 *
 * No toca el monto ni borra la fila — preserva auditoría.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$trans_id = $_POST['transaccion_id'] ?? '';
$motivo   = trim($_POST['motivo'] ?? '');

if (!is_numeric($trans_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
}
$idInt = (int)$trans_id;

$err = validarMotivoAnulacion($motivo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* Verificar pertenencia, estado activo y que NO sea reembolso */
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
if ($row['estado'] === 'anulada') {
    echo json_encode(["ok"=>false,"mensaje"=>"La transacción ya estaba anulada."]);
    $conexion->close(); exit;
}
if ($row['categoria'] === 'Reembolso') {
    echo json_encode(["ok"=>false,"mensaje"=>"Los reembolsos se anulan desde su propio botón."]);
    $conexion->close(); exit;
}

$stmt = @$conexion->prepare(
    "UPDATE transacciones
     SET estado='anulada', anulada_en = NOW(), motivo_anulacion = ?
     WHERE transaccion_id = ? AND usuario_id = ?"
);
$stmt->bind_param("sii", $motivo, $idInt, $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Transacción anulada."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

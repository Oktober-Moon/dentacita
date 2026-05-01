<?php
/*
 * FINANZAS · eliminar transacción manual
 * Solo se pueden eliminar transacciones manuales (sin vínculo a inventario/cita).
 * Para reembolsos, usar el endpoint de anulación.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$transaccion_id = $_POST['transaccion_id'] ?? '';
if (!is_numeric($transaccion_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
}
$idInt = (int)$transaccion_id;

$check = @$conexion->prepare(
    "SELECT inventario_movimiento_id, cita_id, categoria FROM transacciones
     WHERE transaccion_id=? AND usuario_id=?"
);
$check->bind_param("ii", $idInt, $usuarioId);
@$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if (!$row) {
    echo json_encode(["ok"=>false,"mensaje"=>"Transacción no encontrada."]); exit;
}
if ($row['inventario_movimiento_id'] !== null) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se puede eliminar: esta transacción proviene de un movimiento de inventario."]);
    $conexion->close(); exit;
}
if ($row['categoria'] === 'Reembolso') {
    echo json_encode(["ok"=>false,"mensaje"=>"Los reembolsos no se eliminan, se anulan."]);
    $conexion->close(); exit;
}
if ($row['cita_id'] !== null) {
    echo json_encode(["ok"=>false,"mensaje"=>"Esta transacción está vinculada a una cita. Elimínala desactivando la cita o márcala como anulada."]);
    $conexion->close(); exit;
}

$stmt = @$conexion->prepare("DELETE FROM transacciones WHERE transaccion_id=? AND usuario_id=?");
$stmt->bind_param("ii", $idInt, $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Transacción eliminada."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

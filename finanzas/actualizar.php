<?php
/*
 * FINANZAS · actualizar transacción manual
 * Solo se pueden editar transacciones que NO estén vinculadas a citas/inventario,
 * para no romper la auditoría. Si está vinculada se rechaza la edición.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$transaccion_id = $_POST['transaccion_id'] ?? '';
$tipo        = trim($_POST['tipo'] ?? '');
$categoria   = trim($_POST['categoria'] ?? '');
$monto       = trim($_POST['monto'] ?? '');
$fecha       = trim($_POST['fecha'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$metodo_pago = trim($_POST['metodo_pago'] ?? 'efectivo');

if (!is_numeric($transaccion_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit;
}
$idInt = (int)$transaccion_id;

$err = validarTransTipo($tipo);                 if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransCategoria($categoria, $tipo);if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransMonto($monto, true);  if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransFecha($fecha);        if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransDescripcion($descripcion); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransMetodo($metodo_pago); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

// Verificar que no esté vinculada a inventario (esas son auditadas, no editables)
// y que pertenezca al usuario actual (anti-IDOR)
$check = @$conexion->prepare(
    "SELECT inventario_movimiento_id, categoria FROM transacciones
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
    echo json_encode(["ok"=>false,"mensaje"=>"Esta transacción nació de un movimiento de inventario y no se puede editar manualmente. Modifica el movimiento original."]);
    $conexion->close(); exit;
}
if ($row['categoria'] === 'Reembolso') {
    echo json_encode(["ok"=>false,"mensaje"=>"Los reembolsos no se editan: anúlalo y crea uno nuevo."]);
    $conexion->close(); exit;
}

$descV  = $descripcion === '' ? null : $descripcion;
$metV   = $metodo_pago === '' ? 'efectivo' : $metodo_pago;
$montoF = (float)$monto;

$stmt = @$conexion->prepare(
    "UPDATE transacciones SET
        tipo=?, categoria=?, monto=?, fecha=?, descripcion=?, metodo_pago=?
     WHERE transaccion_id=? AND usuario_id=?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ssdsssii",
    $tipo, $categoria, $montoF, $fecha, $descV, $metV, $idInt, $usuarioId
);

if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Transacción actualizada."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

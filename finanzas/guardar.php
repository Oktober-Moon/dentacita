<?php
/*
 * FINANZAS · registrar transacción manual
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$tipo        = trim($_POST['tipo'] ?? '');
$categoria   = trim($_POST['categoria'] ?? '');
$monto       = trim($_POST['monto'] ?? '');
$fecha       = trim($_POST['fecha'] ?? date('Y-m-d'));
$descripcion = trim($_POST['descripcion'] ?? '');
$metodo_pago = trim($_POST['metodo_pago'] ?? 'efectivo');
$paciente_id = trim($_POST['paciente_id'] ?? '');
$cita_id     = trim($_POST['cita_id'] ?? '');

$err = validarTransTipo($tipo);                 if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransCategoria($categoria, $tipo);if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransMonto($monto, false); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransFecha($fecha);        if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransDescripcion($descripcion); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTransMetodo($metodo_pago); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$pacV   = ($paciente_id !== '' && is_numeric($paciente_id)) ? (int)$paciente_id : null;
$citaV  = ($cita_id     !== '' && is_numeric($cita_id))     ? (int)$cita_id     : null;
$descV  = $descripcion === '' ? null : $descripcion;
$metV   = $metodo_pago === '' ? 'efectivo' : $metodo_pago;
$montoF = (float)$monto;

// Validar que el paciente referenciado pertenezca al usuario
if ($pacV !== null) {
    $chkP = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ? LIMIT 1");
    if ($chkP) {
        $chkP->bind_param("ii", $pacV, $usuarioId);
        @$chkP->execute();
        $okP = (bool)$chkP->get_result()->fetch_assoc();
        $chkP->close();
        if (!$okP) {
            echo json_encode(["ok"=>false,"mensaje"=>"El paciente no pertenece a este usuario."]);
            $conexion->close(); exit;
        }
    }
}

// Validar que la cita referenciada pertenezca al usuario
if ($citaV !== null) {
    $chkC = @$conexion->prepare("SELECT 1 FROM citas WHERE cita_id = ? AND usuario_id = ? LIMIT 1");
    if ($chkC) {
        $chkC->bind_param("ii", $citaV, $usuarioId);
        @$chkC->execute();
        $okC = (bool)$chkC->get_result()->fetch_assoc();
        $chkC->close();
        if (!$okC) {
            echo json_encode(["ok"=>false,"mensaje"=>"La cita no pertenece a este usuario."]);
            $conexion->close(); exit;
        }
    }
}

$stmt = @$conexion->prepare(
    "INSERT INTO transacciones
       (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("issdssiis",
    $usuarioId, $tipo, $categoria, $montoF, $fecha, $descV, $pacV, $citaV, $metV
);

if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Transacción registrada.","transaccion_id"=>$stmt->insert_id]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

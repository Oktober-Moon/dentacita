<?php
/*
 * FINANZAS · listar transacciones + KPIs
 * GET parámetros opcionales:
 *   tipo=ingreso|egreso
 *   estado=activa|anulada|todas (default: activa)
 *   desde=YYYY-MM-DD
 *   hasta=YYYY-MM-DD
 *   categoria=texto
 *   paciente_id=N
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$tipo       = $_GET['tipo']        ?? '';
$estado     = $_GET['estado']      ?? 'activa';
$desde      = $_GET['desde']       ?? '';
$hasta      = $_GET['hasta']       ?? '';
$categoria  = trim($_GET['categoria']  ?? '');
$pacienteId = $_GET['paciente_id'] ?? '';
$metodo     = $_GET['metodo']      ?? '';

$where  = [];
$params = [];
$types  = '';

// Filtro fijo de tenant
$where[] = "t.usuario_id = ?";
$params[] = $usuarioId;
$types  .= 'i';

if ($tipo !== '' && in_array($tipo, ['ingreso','egreso'], true)) {
    $where[] = "t.tipo = ?";  $params[] = $tipo; $types .= 's';
}
if ($estado !== 'todas') {
    if (in_array($estado, ['activa','anulada'], true)) {
        $where[] = "t.estado = ?"; $params[] = $estado; $types .= 's';
    }
}
if ($desde !== '' && strtotime($desde)) { $where[] = "t.fecha >= ?"; $params[] = $desde; $types .= 's'; }
if ($hasta !== '' && strtotime($hasta)) { $where[] = "t.fecha <= ?"; $params[] = $hasta; $types .= 's'; }
if ($categoria !== '' && mb_strlen($categoria) <= 50) {
    $where[] = "t.categoria = ?"; $params[] = $categoria; $types .= 's';
}
if ($metodo !== '' && in_array($metodo, ['efectivo','tarjeta','transferencia','cheque','otro'], true)) {
    $where[] = "t.metodo_pago = ?"; $params[] = $metodo; $types .= 's';
}
if ($pacienteId !== '' && is_numeric($pacienteId)) {
    // Validar que el paciente pertenezca al usuario antes de aplicar el filtro
    $pidInt = (int)$pacienteId;
    $chkP = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ? LIMIT 1");
    if ($chkP) {
        $chkP->bind_param("ii", $pidInt, $usuarioId);
        @$chkP->execute();
        $perteneceP = (bool)$chkP->get_result()->fetch_assoc();
        $chkP->close();
        if ($perteneceP) {
            $where[] = "t.paciente_id = ?"; $params[] = $pidInt; $types .= 'i';
        } else {
            // Forzar resultado vacío sin filtrar (paciente ajeno)
            $where[] = "1 = 0";
        }
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT
            t.transaccion_id, t.tipo, t.categoria, t.monto, t.fecha, t.descripcion,
            t.paciente_id, t.cita_id, t.inventario_item_id, t.inventario_movimiento_id,
            t.metodo_pago, t.estado, t.anulada_en, t.motivo_anulacion, t.creado_en,
            p.nombre_completo AS paciente_nombre,
            c.titulo          AS cita_titulo,
            i.nombre          AS item_nombre
        FROM transacciones t
        LEFT JOIN pacientes p          ON t.paciente_id        = p.paciente_id
        LEFT JOIN citas c              ON t.cita_id            = c.cita_id
        LEFT JOIN inventario_items i   ON t.inventario_item_id = i.item_id
        $whereSql
        ORDER BY t.fecha DESC, t.transaccion_id DESC
        LIMIT 200";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param($types, ...$params);
@$stmt->execute();
$r = $stmt->get_result();
$transacciones = [];
while ($f = $r->fetch_assoc()) $transacciones[] = $f;
$stmt->close();

/* ----- KPIs del mes actual (solo activas) ----- */
$ingresosMes = $egresosMes = 0;
$stmtKM = @$conexion->prepare(
    "SELECT tipo, SUM(monto) AS total FROM transacciones
     WHERE usuario_id = ? AND estado='activa'
       AND YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())
     GROUP BY tipo");
if ($stmtKM) {
    $stmtKM->bind_param("i", $usuarioId);
    @$stmtKM->execute();
    $rk = $stmtKM->get_result();
    while ($f = $rk->fetch_assoc()) {
        if ($f['tipo'] === 'ingreso') $ingresosMes = (float)$f['total'];
        if ($f['tipo'] === 'egreso')  $egresosMes  = (float)$f['total'];
    }
    $stmtKM->close();
}

/* ----- Promedio por transacción del mes (solo activas, valor absoluto) ----- */
$promedioMes = 0;
$stmtAvg = @$conexion->prepare(
    "SELECT AVG(ABS(monto)) AS prom FROM transacciones
     WHERE usuario_id = ? AND estado='activa'
       AND YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())");
if ($stmtAvg) {
    $stmtAvg->bind_param("i", $usuarioId);
    @$stmtAvg->execute();
    $rp = $stmtAvg->get_result()->fetch_assoc();
    if ($rp && $rp['prom'] !== null) $promedioMes = (float)$rp['prom'];
    $stmtAvg->close();
}

$ingresosAno = $egresosAno = 0;
$stmtKA = @$conexion->prepare(
    "SELECT tipo, SUM(monto) AS total FROM transacciones
     WHERE usuario_id = ? AND estado='activa' AND YEAR(fecha)=YEAR(CURDATE())
     GROUP BY tipo");
if ($stmtKA) {
    $stmtKA->bind_param("i", $usuarioId);
    @$stmtKA->execute();
    $ra = $stmtKA->get_result();
    while ($f = $ra->fetch_assoc()) {
        if ($f['tipo'] === 'ingreso') $ingresosAno = (float)$f['total'];
        if ($f['tipo'] === 'egreso')  $egresosAno  = (float)$f['total'];
    }
    $stmtKA->close();
}

/* ----- Citas completadas SIN ingreso registrado (para "Cobrar cita") ----- */
$citasCobrables = [];
$stmtCC = @$conexion->prepare(
    "SELECT c.cita_id, c.titulo, c.paciente_nombre, c.fecha_hora_inicio, c.precio
     FROM citas c
     WHERE c.usuario_id = ?
       AND c.estado = 'completada'
       AND c.precio IS NOT NULL AND c.precio > 0
       AND NOT EXISTS (
           SELECT 1 FROM transacciones t
           WHERE t.cita_id = c.cita_id AND t.usuario_id = ?
             AND t.estado='activa' AND t.categoria != 'Reembolso'
       )
     ORDER BY c.fecha_hora_inicio DESC LIMIT 20");
if ($stmtCC) {
    $stmtCC->bind_param("ii", $usuarioId, $usuarioId);
    @$stmtCC->execute();
    $rc = $stmtCC->get_result();
    while ($f = $rc->fetch_assoc()) $citasCobrables[] = $f;
    $stmtCC->close();
}

$conexion->close();

echo json_encode([
    "ok"             => true,
    "transacciones"  => $transacciones,
    "kpis" => [
        "ingresos_mes"  => $ingresosMes,
        "egresos_mes"   => $egresosMes,
        "balance_mes"   => $ingresosMes - $egresosMes,
        "promedio_mes"  => $promedioMes,
        "ingresos_ano"  => $ingresosAno,
        "egresos_ano"   => $egresosAno,
        "balance_ano"   => $ingresosAno - $egresosAno
    ],
    "citas_cobrables" => $citasCobrables
]);

<?php
/*
 * INVENTARIO · BITÁCORA · listar movimientos paginados
 * -----------------------------------------------------
 * GET parámetros opcionales:
 *   item_id=N
 *   tipo=entrada|salida|ajuste
 *   desde=YYYY-MM-DD
 *   hasta=YYYY-MM-DD
 *   pag=N (default 1)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$itemId = $_GET['item_id'] ?? '';
$tipo   = $_GET['tipo']    ?? '';
$motivo = $_GET['motivo']  ?? '';
$desde  = $_GET['desde']   ?? '';
$hasta  = $_GET['hasta']   ?? '';
$pag    = isset($_GET['pag']) && is_numeric($_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;

$porPagina = 25;
$offset    = ($pag - 1) * $porPagina;

$where  = ["i.usuario_id = ?"];
$params = [$usuarioId];
$types  = 'i';

if ($itemId !== '' && is_numeric($itemId)) {
    $where[]  = "m.item_id = ?";
    $params[] = (int)$itemId;
    $types   .= 'i';
}
if (in_array($tipo, ['entrada','salida','ajuste'], true)) {
    $where[]  = "m.tipo = ?";
    $params[] = $tipo;
    $types   .= 's';
}
$motivosValidos = ['compra','donacion','ajuste_inicial','devolucion_proveedor',
                   'venta','uso_consulta','vencimiento','perdida','ajuste_inventario','otro'];
if (in_array($motivo, $motivosValidos, true)) {
    $where[]  = "m.motivo = ?";
    $params[] = $motivo;
    $types   .= 's';
}
if ($desde !== '' && strtotime($desde)) {
    $where[]  = "m.fecha >= ?";
    $params[] = $desde;
    $types   .= 's';
}
if ($hasta !== '' && strtotime($hasta)) {
    $where[]  = "m.fecha <= ?";
    $params[] = $hasta;
    $types   .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ----- Conteo total (con JOIN para filtrar por usuario_id) ----- */
$sqlCount = "SELECT COUNT(*)
             FROM inventario_movimientos m
             JOIN inventario_items i ON m.item_id = i.item_id
             $whereSql";
$stmtCount = @$conexion->prepare($sqlCount);
if (!$stmtCount) {
    echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
if ($params) $stmtCount->bind_param($types, ...$params);
@$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_array(MYSQLI_NUM)[0];
$stmtCount->close();

/* ----- Listado paginado ----- */
$sql = "SELECT m.movimiento_id, m.item_id, m.tipo, m.motivo, m.cantidad,
               m.cita_id, m.costo_unitario, m.precio_venta_unitario,
               m.tiene_costo, m.notas, m.fecha, m.creado_en,
               i.nombre AS item_nombre, i.unidad
        FROM inventario_movimientos m
        JOIN inventario_items i ON m.item_id = i.item_id
        $whereSql
        ORDER BY m.fecha DESC, m.movimiento_id DESC
        LIMIT ? OFFSET ?";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

$paramsFinal = $params;
$typesFinal  = $types;
$paramsFinal[] = $porPagina;
$paramsFinal[] = $offset;
$typesFinal   .= 'ii';
$stmt->bind_param($typesFinal, ...$paramsFinal);
@$stmt->execute();
$r = $stmt->get_result();
$movimientos = [];
while ($f = $r->fetch_assoc()) $movimientos[] = $f;
$stmt->close();

$conexion->close();

echo json_encode([
    "ok"            => true,
    "movimientos"   => $movimientos,
    "total"         => $total,
    "pagina"        => $pag,
    "por_pagina"    => $porPagina,
    "total_paginas" => (int)ceil($total / $porPagina)
]);

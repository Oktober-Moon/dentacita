<?php
/*
 * INVENTARIO · listar movimientos de un producto
 * ------------------------------------------------
 * GET ?item_id=N → todos los movimientos de ese item, ordenados DESC por fecha.
 * GET sin item_id → últimos 50 movimientos en general.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$itemId = $_GET['item_id'] ?? '';

$movimientos = [];

if ($itemId !== '') {
    if (!is_numeric($itemId)) {
        echo json_encode(["ok"=>false, "mensaje"=>"ID de producto no válido."]);
        $conexion->close(); exit;
    }
    $idInt = (int)$itemId;
    $stmt = @$conexion->prepare(
        "SELECT m.movimiento_id, m.item_id, m.tipo, m.motivo, m.cantidad,
                m.cita_id, m.costo_unitario, m.precio_venta_unitario,
                m.tiene_costo, m.notas, m.fecha, m.creado_en,
                i.nombre AS item_nombre, i.unidad
         FROM inventario_movimientos m
         JOIN inventario_items i ON m.item_id = i.item_id
         WHERE m.item_id = ? AND i.usuario_id = ?
         ORDER BY m.fecha DESC, m.movimiento_id DESC"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("ii", $idInt, $usuarioId);
} else {
    $stmt = @$conexion->prepare(
        "SELECT m.movimiento_id, m.item_id, m.tipo, m.motivo, m.cantidad,
                m.cita_id, m.costo_unitario, m.precio_venta_unitario,
                m.tiene_costo, m.notas, m.fecha, m.creado_en,
                i.nombre AS item_nombre, i.unidad
         FROM inventario_movimientos m
         JOIN inventario_items i ON m.item_id = i.item_id
         WHERE i.usuario_id = ?
         ORDER BY m.fecha DESC, m.movimiento_id DESC
         LIMIT 50"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("i", $usuarioId);
}

@$stmt->execute();
$r = $stmt->get_result();
while ($f = $r->fetch_assoc()) $movimientos[] = $f;
$stmt->close();

$conexion->close();

echo json_encode([
    "ok"           => true,
    "movimientos"  => $movimientos
]);

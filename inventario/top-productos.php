<?php
/*
 * INVENTARIO · top 5 productos más usados
 * ----------------------------------------
 * GET parámetros opcionales:
 *   motivo=compra|donacion|...|todos (default: 'uso_consulta')
 *   dias=N (default 30)
 *
 * Devuelve los 5 productos con mayor cantidad acumulada en movimientos
 * de SALIDA del usuario actual, para el motivo y rango de días dado.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$motivo = trim($_GET['motivo'] ?? 'uso_consulta');
$dias   = isset($_GET['dias']) && is_numeric($_GET['dias']) ? max(1, min(365, (int)$_GET['dias'])) : 30;

$motivosValidosSalida = ['venta','uso_consulta','vencimiento','perdida','ajuste_inventario','otro','todos'];
if (!in_array($motivo, $motivosValidosSalida, true)) {
    $motivo = 'uso_consulta';
}

if ($motivo === 'todos') {
    $sql = "SELECT i.item_id, i.nombre, i.unidad, SUM(m.cantidad) AS total
            FROM inventario_movimientos m
            JOIN inventario_items i ON m.item_id = i.item_id
            WHERE i.usuario_id = ? AND m.tipo = 'salida'
              AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY i.item_id, i.nombre, i.unidad
            ORDER BY total DESC
            LIMIT 5";
    $stmt = @$conexion->prepare($sql);
    $stmt->bind_param("ii", $usuarioId, $dias);
} else {
    $sql = "SELECT i.item_id, i.nombre, i.unidad, SUM(m.cantidad) AS total
            FROM inventario_movimientos m
            JOIN inventario_items i ON m.item_id = i.item_id
            WHERE i.usuario_id = ? AND m.tipo = 'salida' AND m.motivo = ?
              AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY i.item_id, i.nombre, i.unidad
            ORDER BY total DESC
            LIMIT 5";
    $stmt = @$conexion->prepare($sql);
    $stmt->bind_param("isi", $usuarioId, $motivo, $dias);
}

if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

@$stmt->execute();
$r = $stmt->get_result();
$productos = [];
while ($f = $r->fetch_assoc()) {
    $productos[] = [
        'item_id' => (int)$f['item_id'],
        'nombre'  => $f['nombre'],
        'unidad'  => $f['unidad'],
        'total'   => (int)$f['total']
    ];
}
$stmt->close();
$conexion->close();

echo json_encode([
    "ok"        => true,
    "motivo"    => $motivo,
    "dias"      => $dias,
    "productos" => $productos
]);

<?php
/*
 * INVENTARIO · listar productos
 * ------------------------------
 * Devuelve los items con su stock actual + KPIs de inventario.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$items = [];
$stmt = @$conexion->prepare(
    "SELECT item_id, nombre, categoria, cantidad_actual, cantidad_minima, unidad,
            costo_unitario, precio_venta, proveedor, notas, creado_en, actualizado_en
     FROM inventario_items
     WHERE usuario_id = ?
     ORDER BY nombre ASC"
);
if (!$stmt) {
    echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("i", $usuarioId);
@$stmt->execute();
$r = $stmt->get_result();
while ($f = $r->fetch_assoc()) {
    $stockActual = (int)$f['cantidad_actual'];
    $stockMin    = (int)$f['cantidad_minima'];
    if ($stockActual <= 0)              $f['estado_stock'] = 'agotado';
    elseif ($stockActual <= $stockMin)  $f['estado_stock'] = 'bajo';
    else                                $f['estado_stock'] = 'ok';
    $items[] = $f;
}
$stmt->close();

/* ----- KPIs ----- */
$totalItems  = count($items);
$itemsBajos  = 0;
$itemsAgotados = 0;
$valorInventario = 0.0;
foreach ($items as $i) {
    if ($i['estado_stock'] === 'bajo')      $itemsBajos++;
    if ($i['estado_stock'] === 'agotado')   $itemsAgotados++;
    if ($i['costo_unitario'] !== null) {
        $valorInventario += ((float)$i['costo_unitario']) * ((int)$i['cantidad_actual']);
    }
}

$conexion->close();

echo json_encode([
    "ok"     => true,
    "items"  => $items,
    "kpis"   => [
        "total_items"        => $totalItems,
        "items_bajos"        => $itemsBajos,
        "items_agotados"     => $itemsAgotados,
        "valor_inventario"   => $valorInventario
    ]
]);

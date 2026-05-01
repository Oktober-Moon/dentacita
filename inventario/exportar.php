<?php
/*
 * INVENTARIO · exportar productos + movimientos a CSV
 * ----------------------------------------------------
 * GET ?tab=productos|movimientos (default: productos)
 * Genera un CSV con BOM UTF-8 listo para Excel.
 */

require __DIR__ . '/../conexion.php';
require __DIR__ . '/_catalogos.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');

$usuarioId = getUsuarioId();

$tab = $_GET['tab'] ?? 'productos';
if (!in_array($tab, ['productos','movimientos'], true)) $tab = 'productos';

$nombreArchivo = 'inventario_' . $tab . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

if ($tab === 'productos') {
    fputcsv($out, [
        'Nombre', 'Categoría', 'Stock actual', 'Stock mínimo', 'Unidad',
        'Costo unitario', 'Precio venta', 'Proveedor', 'Notas',
        'Creado en', 'Actualizado en'
    ], ';');

    $stmt = @$conexion->prepare(
        "SELECT nombre, categoria, cantidad_actual, cantidad_minima, unidad,
                costo_unitario, precio_venta, proveedor, notas, creado_en, actualizado_en
         FROM inventario_items
         WHERE usuario_id = ?
         ORDER BY nombre ASC"
    );
    $stmt->bind_param("i", $usuarioId);
    @$stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        fputcsv($out, [
            $row['nombre'], invCategoriaLabel($row['categoria']),
            $row['cantidad_actual'], $row['cantidad_minima'], invUnidadLabel($row['unidad']),
            $row['costo_unitario'] !== null ? number_format((float)$row['costo_unitario'], 2, '.', '') : '',
            $row['precio_venta']   !== null ? number_format((float)$row['precio_venta'],   2, '.', '') : '',
            $row['proveedor'], $row['notas'], $row['creado_en'], $row['actualizado_en']
        ], ';');
    }
    $stmt->close();
} else {
    fputcsv($out, [
        'Fecha', 'Producto', 'Tipo', 'Motivo', 'Cantidad', 'Unidad',
        'Costo unitario', 'Precio venta unitario', 'Notas', 'Creado en'
    ], ';');

    $stmt = @$conexion->prepare(
        "SELECT m.fecha, i.nombre AS item_nombre, m.tipo, m.motivo,
                m.cantidad, i.unidad, m.costo_unitario, m.precio_venta_unitario,
                m.notas, m.creado_en
         FROM inventario_movimientos m
         JOIN inventario_items i ON m.item_id = i.item_id
         WHERE i.usuario_id = ?
         ORDER BY m.fecha DESC, m.movimiento_id DESC"
    );
    $stmt->bind_param("i", $usuarioId);
    @$stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        fputcsv($out, [
            $row['fecha'], $row['item_nombre'], $row['tipo'], $row['motivo'],
            $row['cantidad'], invUnidadLabel($row['unidad']),
            $row['costo_unitario']        !== null ? number_format((float)$row['costo_unitario'],        2, '.', '') : '',
            $row['precio_venta_unitario'] !== null ? number_format((float)$row['precio_venta_unitario'], 2, '.', '') : '',
            $row['notas'], $row['creado_en']
        ], ';');
    }
    $stmt->close();
}

fclose($out);
$conexion->close();

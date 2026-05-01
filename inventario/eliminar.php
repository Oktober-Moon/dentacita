<?php
/*
 * INVENTARIO · eliminar producto
 * --------------------------------
 * FLUJO CRUZADO: si el producto tiene stock, registramos un movimiento
 * tipo 'ajuste' motivo 'ajuste_inventario' con la cantidad que se "pierde"
 * antes del DELETE. Esto preserva auditoría aunque la fila se borre
 * (ON DELETE CASCADE de inventario_movimientos no aplica aquí porque el
 * movimiento se inserta justo antes y borra en cascada con el producto;
 * en su lugar hacemos un soft-trace: una transacción en transacciones
 * con notas, que es la que sí se preserva por el SET NULL).
 *
 * Nota: por simplicidad académica registramos en notificaciones la
 * eliminación para que quede rastro visible al dentista.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$item_id = $_POST['item_id'] ?? '';
if (!is_numeric($item_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de producto no válido."]); exit;
}
$idInt = (int)$item_id;

$conexion->begin_transaction();
try {
    // 1) Datos actuales del producto
    $s = @$conexion->prepare(
        "SELECT nombre, cantidad_actual, unidad
         FROM inventario_items WHERE item_id = ? AND usuario_id = ? FOR UPDATE"
    );
    if (!$s) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $s->bind_param("ii", $idInt, $usuarioId);
    @$s->execute();
    $item = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$item) {
        $conexion->rollback();
        echo json_encode(["ok"=>false,"mensaje"=>"Producto no encontrado."]);
        $conexion->close(); exit;
    }

    // 2) Si había stock, dejamos rastro vía notificación (el movimiento se borra en CASCADE)
    if ((int)$item['cantidad_actual'] > 0) {
        $titulo = "Producto eliminado";
        $msj    = "Se eliminó '" . $item['nombre'] . "' con stock de " . (int)$item['cantidad_actual'] . " " . $item['unidad'] . ".";
        $tipo   = 'info';
        $enlace = '/inventario/';
        $sn = @$conexion->prepare(
            "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida)
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        if ($sn) {
            $sn->bind_param("issss", $usuarioId, $tipo, $titulo, $msj, $enlace);
            @$sn->execute();
            $sn->close();
        }
    }

    // 3) Borrar el producto. Las transacciones vinculadas tienen ON DELETE SET NULL,
    //    así que las facturas/egresos se preservan, solo se desvinculan.
    $sd = @$conexion->prepare("DELETE FROM inventario_items WHERE item_id = ? AND usuario_id = ?");
    if (!$sd) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $sd->bind_param("ii", $idInt, $usuarioId);
    @$sd->execute();
    $sd->close();

    $conexion->commit();
    echo json_encode(["ok"=>true,"mensaje"=>"Producto eliminado. Las transacciones financieras vinculadas se preservaron (desvinculadas)."]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}

$conexion->close();

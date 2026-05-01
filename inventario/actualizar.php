<?php
/*
 * INVENTARIO · actualizar producto
 * Cambia los metadatos del producto. NO altera la cantidad_actual
 * (los ajustes a stock se hacen mediante movimientos).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$item_id         = $_POST['item_id'] ?? '';
$nombre          = trim($_POST['nombre'] ?? '');
$categoria       = trim($_POST['categoria'] ?? '');
$cantidad_minima = trim($_POST['cantidad_minima'] ?? '0');
$unidad          = trim($_POST['unidad'] ?? '');
$costo_unitario  = trim($_POST['costo_unitario'] ?? '');
$precio_venta    = trim($_POST['precio_venta'] ?? '');
$proveedor       = trim($_POST['proveedor'] ?? '');
$notas           = trim($_POST['notas'] ?? '');

if (!is_numeric($item_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de producto no válido."]); exit;
}
$idInt = (int)$item_id;

if ($categoria === '') $categoria = INV_CATEGORIA_DEFAULT;
if ($unidad    === '') $unidad    = INV_UNIDAD_DEFAULT;

$err = validarItemNombre($nombre);                                if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemCategoria($categoria);                          if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemCantidadEntera($cantidad_minima, "La cantidad mínima"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemUnidad($unidad);                                if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemPrecioOpcional($costo_unitario, "El costo unitario"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemPrecioOpcional($precio_venta,   "El precio de venta"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemTexto($proveedor, 100, "El proveedor");         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemTexto($notas, 500, "Las notas");                if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$catV = $categoria;
$uniV = $unidad;
$cosV = $costo_unitario === '' ? null : (float)$costo_unitario;
$pveV = $precio_venta   === '' ? null : (float)$precio_venta;
$proV = $proveedor === '' ? null : $proveedor;
$notV = $notas     === '' ? null : $notas;
$canM = (int)$cantidad_minima;

$stmt = @$conexion->prepare(
    "UPDATE inventario_items SET
        nombre=?, categoria=?, cantidad_minima=?, unidad=?,
        costo_unitario=?, precio_venta=?, proveedor=?, notas=?
     WHERE item_id=? AND usuario_id=?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ssisddssii",
    $nombre, $catV, $canM, $uniV,
    $cosV, $pveV, $proV, $notV,
    $idInt, $usuarioId
);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        // puede ser que no cambió nada o que no existe
        $check = @$conexion->prepare("SELECT 1 FROM inventario_items WHERE item_id=? AND usuario_id=?");
        $check->bind_param("ii", $idInt, $usuarioId);
        @$check->execute();
        $existe = (bool)$check->get_result()->fetch_assoc();
        $check->close();
        if (!$existe) { echo json_encode(["ok"=>false,"mensaje"=>"Producto no encontrado."]); exit; }
    }
    echo json_encode(["ok"=>true,"mensaje"=>"Producto actualizado."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

<?php
/*
 * INVENTARIO · crear producto nuevo
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$nombre          = trim($_POST['nombre'] ?? '');
$categoria       = trim($_POST['categoria'] ?? '');
$cantidad_actual = trim($_POST['cantidad_actual'] ?? '0');
$cantidad_minima = trim($_POST['cantidad_minima'] ?? '0');
$unidad          = trim($_POST['unidad'] ?? '');
$costo_unitario  = trim($_POST['costo_unitario'] ?? '');
$precio_venta    = trim($_POST['precio_venta'] ?? '');
$proveedor       = trim($_POST['proveedor'] ?? '');
$notas           = trim($_POST['notas'] ?? '');

if ($categoria === '') $categoria = INV_CATEGORIA_DEFAULT;
if ($unidad    === '') $unidad    = INV_UNIDAD_DEFAULT;

$err = validarItemNombre($nombre);                                if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemCategoria($categoria);                          if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemCantidadEntera($cantidad_actual, "La cantidad actual"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
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
$canA = (int)$cantidad_actual;
$canM = (int)$cantidad_minima;

$stmt = @$conexion->prepare(
    "INSERT INTO inventario_items
        (usuario_id, nombre, categoria, cantidad_actual, cantidad_minima, unidad,
         costo_unitario, precio_venta, proveedor, notas)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("issiisddss",
    $usuarioId, $nombre, $catV, $canA, $canM, $uniV,
    $cosV, $pveV, $proV, $notV
);

if (@$stmt->execute()) {
    $newId = $stmt->insert_id;
    $stmt->close();

    // Si entra con stock inicial > 0 y costo unitario, registramos movimiento "ajuste_inicial"
    // (no genera transacción a menos que el dentista marque "tiene_costo" en el modal de movimientos,
    // así que lo dejamos registrado pero sin asiento contable inicial)
    if ($canA > 0) {
        $sql2 = @$conexion->prepare(
            "INSERT INTO inventario_movimientos (item_id, tipo, motivo, cantidad, costo_unitario, fecha)
             VALUES (?, 'entrada', 'ajuste_inicial', ?, ?, CURDATE())"
        );
        if ($sql2) {
            $sql2->bind_param("iid", $newId, $canA, $cosV);
            @$sql2->execute();
            $sql2->close();
        }
    }
    echo json_encode(["ok"=>true,"mensaje"=>"Producto creado.","item_id"=>$newId]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    $stmt->close();
}

$conexion->close();

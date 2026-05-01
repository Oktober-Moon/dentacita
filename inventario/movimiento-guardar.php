<?php
/*
 * INVENTARIO · registrar movimiento (entrada/salida/ajuste)
 * ============================================================
 * 🔥 ENDPOINT CON FLUJOS CRUZADOS 🔥
 *
 * Acciones que dispara según el tipo y motivo:
 *
 *   ENTRADA con motivo='compra'                      → INSERT en transacciones (egreso, categoría 'Materiales')
 *   ENTRADA con motivo='devolucion_proveedor'        → no genera transacción
 *   ENTRADA con motivo='donacion'                    → no genera transacción
 *   ENTRADA con motivo='ajuste_inicial'              → no genera transacción
 *   ENTRADA con motivo='otro' y tiene_costo=1        → INSERT egreso
 *
 *   SALIDA con motivo='venta' y precio_venta_unitario → INSERT en transacciones (ingreso, categoría 'Producto')
 *   SALIDA con motivo='uso_consulta'                 → no genera transacción (consumo interno)
 *   SALIDA con motivo='vencimiento'/'perdida'        → no genera transacción
 *
 *   AJUSTE                                            → no genera transacción
 *
 *   Si después de SALIDA el stock final ≤ cantidad_minima → INSERT en notificaciones
 *   Si stock final = 0                                    → notificación tipo 'stock_agotado'
 *
 * Todo en una transacción atómica · si algo falla, se hace rollback completo.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$item_id        = $_POST['item_id'] ?? '';
$tipo           = trim($_POST['tipo'] ?? '');
$motivo         = trim($_POST['motivo'] ?? '');
$cantidad       = trim($_POST['cantidad'] ?? '');
$fecha          = trim($_POST['fecha'] ?? date('Y-m-d'));
$cita_id        = trim($_POST['cita_id'] ?? '');
$tiene_costo    = isset($_POST['tiene_costo']) ? 1 : 0;
$costo_unitario = trim($_POST['costo_unitario'] ?? '');
$precio_venta_unitario = trim($_POST['precio_venta_unitario'] ?? '');
$notas          = trim($_POST['notas'] ?? '');

/* ----- Validaciones ----- */
if (!is_numeric($item_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Producto no válido."]); exit; }
$itemIdInt = (int)$item_id;

$err = validarMovimientoTipo($tipo);                         if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarMovimientoMotivo($motivo, $tipo);              if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
// En 'ajuste' la cantidad es el stock objetivo; permitir 0 (producto agotado intencional).
$permiteCero = ($tipo === 'ajuste');
$err = validarItemCantidadEntera($cantidad, ($tipo === 'ajuste' ? "El stock objetivo" : "La cantidad"), $permiteCero); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarMovimientoFecha($fecha);                       if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemPrecioOpcional($costo_unitario, "El costo unitario"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemPrecioOpcional($precio_venta_unitario, "El precio de venta"); if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarItemTexto($notas, 500, "Las notas");           if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$canInt   = (int)$cantidad;
$citaIdV  = ($cita_id !== '' && is_numeric($cita_id)) ? (int)$cita_id : null;
$cosV     = $costo_unitario === '' ? null : (float)$costo_unitario;
$pveV     = $precio_venta_unitario === '' ? null : (float)$precio_venta_unitario;
$notV     = $notas === '' ? null : $notas;

/* ----- Anti-IDOR: validar que la cita referenciada pertenezca al usuario ----- */
if ($citaIdV !== null) {
    $chkC = @$conexion->prepare("SELECT 1 FROM citas WHERE cita_id = ? AND usuario_id = ? LIMIT 1");
    if ($chkC) {
        $chkC->bind_param("ii", $citaIdV, $usuarioId);
        @$chkC->execute();
        $okC = (bool)$chkC->get_result()->fetch_assoc();
        $chkC->close();
        if (!$okC) {
            echo json_encode(["ok"=>false,"mensaje"=>"La cita no pertenece a este usuario."]);
            $conexion->close(); exit;
        }
    }
}

// Validación cruzada: si motivo=venta, debe haber precio
if ($tipo === 'salida' && $motivo === 'venta' && $pveV === null) {
    echo json_encode(["ok"=>false,"mensaje"=>"Para una venta debes indicar el precio de venta unitario."]);
    exit;
}
// Si motivo=compra, debe haber costo
if ($tipo === 'entrada' && $motivo === 'compra' && $cosV === null) {
    echo json_encode(["ok"=>false,"mensaje"=>"Para una compra debes indicar el costo unitario."]);
    exit;
}


/* ----- Iniciar transacción ----- */
$conexion->begin_transaction();
try {
    /* 1) Bloquear y obtener producto (anti-IDOR: filtrar por usuario_id) */
    $s = @$conexion->prepare(
        "SELECT nombre, cantidad_actual, cantidad_minima, unidad,
                costo_unitario AS costo_default, precio_venta AS precio_default
         FROM inventario_items WHERE item_id = ? AND usuario_id = ? FOR UPDATE"
    );
    if (!$s) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $s->bind_param("ii", $itemIdInt, $usuarioId);
    @$s->execute();
    $item = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$item) throw new Exception("Producto no encontrado.");

    $stockActual = (int)$item['cantidad_actual'];
    $stockMin    = (int)$item['cantidad_minima'];

    /* 2) Calcular nuevo stock */
    $nuevoStock = $stockActual;
    if ($tipo === 'entrada')      $nuevoStock = $stockActual + $canInt;
    elseif ($tipo === 'salida') {
        $err = validarMovimientoStockSuficiente($stockActual, $canInt);
        if ($err) throw new Exception($err);
        $nuevoStock = $stockActual - $canInt;
    } elseif ($tipo === 'ajuste') {
        // En 'ajuste' la "cantidad" es el stock objetivo final
        if ($canInt < 0) throw new Exception("El stock objetivo no puede ser negativo.");
        $nuevoStock = $canInt;
    }

    /* 3) Insertar movimiento */
    $sm = @$conexion->prepare(
        "INSERT INTO inventario_movimientos
            (item_id, tipo, motivo, cantidad, cita_id, costo_unitario,
             precio_venta_unitario, tiene_costo, notas, fecha)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$sm) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $sm->bind_param("issiidiiss",
        $itemIdInt, $tipo, $motivo, $canInt, $citaIdV, $cosV,
        $pveV, $tiene_costo, $notV, $fecha
    );
    if (!@$sm->execute()) throw new Exception(mensajeErrorMysql($sm->errno, $sm->error));
    $movimientoId = $sm->insert_id;
    $sm->close();

    /* 4) Actualizar stock en producto */
    $su = @$conexion->prepare("UPDATE inventario_items SET cantidad_actual = ? WHERE item_id = ?");
    if (!$su) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
    $su->bind_param("ii", $nuevoStock, $itemIdInt);
    if (!@$su->execute()) throw new Exception(mensajeErrorMysql($su->errno, $su->error));
    $su->close();

    /* 5) FLUJO CRUZADO: ¿debe crear transacción financiera? */
    $transaccionId   = null;
    $crearEgreso     = false;
    $crearIngreso    = false;
    $monto           = 0.0;
    $categoriaTrans  = '';
    $descTrans       = '';

    if ($tipo === 'entrada') {
        $debeCrear = false;
        if ($motivo === 'compra')                                         $debeCrear = true;
        if ($motivo === 'otro' && $tiene_costo === 1)                     $debeCrear = true;
        // (devolucion_proveedor, donacion, ajuste_inicial → no crean)
        if ($debeCrear && $cosV !== null && $cosV > 0) {
            $monto          = $cosV * $canInt;
            $categoriaTrans = 'Materiales';
            $motivoLabel    = ['compra'=>'Compra','otro'=>'Entrada con costo'][$motivo] ?? $motivo;
            $descTrans      = "Inventario: {$item['nombre']} x {$canInt} ({$motivoLabel})";
            $crearEgreso    = true;
        }
    } elseif ($tipo === 'salida') {
        if ($motivo === 'venta' && $pveV !== null && $pveV > 0) {
            $monto          = $pveV * $canInt;
            $categoriaTrans = 'Producto';
            $descTrans      = "Venta inventario: {$item['nombre']} x {$canInt} @ \${$pveV}/u";
            $crearIngreso   = true;
        }
    }
    // ajuste no crea transacciones

    if ($crearEgreso || $crearIngreso) {
        $tipoTrans = $crearEgreso ? 'egreso' : 'ingreso';
        $st = @$conexion->prepare(
            "INSERT INTO transacciones
                (usuario_id, tipo, categoria, monto, fecha, descripcion,
                 inventario_item_id, inventario_movimiento_id, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activa')"
        );
        if (!$st) throw new Exception(mensajeErrorMysql($conexion->errno, $conexion->error));
        $st->bind_param("issdssii",
            $usuarioId, $tipoTrans, $categoriaTrans, $monto, $fecha, $descTrans,
            $itemIdInt, $movimientoId
        );
        if (!@$st->execute()) throw new Exception(mensajeErrorMysql($st->errno, $st->error));
        $transaccionId = $st->insert_id;
        $st->close();
    }

    /* 6) FLUJO CRUZADO: ¿debe crear notificación de stock bajo? */
    $notificacionId = null;
    if ($tipo === 'salida' || $tipo === 'ajuste') {
        if ($nuevoStock <= 0) {
            // Stock agotado
            $tipoN  = 'stock_agotado';
            $titN   = 'Producto agotado';
            $msjN   = "El producto '{$item['nombre']}' se ha agotado.";
            $enlN   = '/inventario/';
            $sn = @$conexion->prepare(
                "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida)
                 VALUES (?, ?, ?, ?, ?, 0)"
            );
            $sn->bind_param("issss", $usuarioId, $tipoN, $titN, $msjN, $enlN);
            @$sn->execute();
            $notificacionId = $sn->insert_id;
            $sn->close();
        } elseif ($nuevoStock <= $stockMin) {
            // Stock bajo (pero no agotado)
            $tipoN  = 'stock_bajo';
            $titN   = 'Stock bajo';
            $msjN   = "El producto '{$item['nombre']}' tiene stock bajo: {$nuevoStock}/{$stockMin} {$item['unidad']}.";
            $enlN   = '/inventario/';
            $sn = @$conexion->prepare(
                "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida)
                 VALUES (?, ?, ?, ?, ?, 0)"
            );
            $sn->bind_param("issss", $usuarioId, $tipoN, $titN, $msjN, $enlN);
            @$sn->execute();
            $notificacionId = $sn->insert_id;
            $sn->close();
        }
    }

    $conexion->commit();

    /* 7) Construir mensaje informativo según los flujos disparados */
    $mensajePartes = ["Movimiento registrado."];
    if ($crearEgreso)      $mensajePartes[] = "Se generó un egreso de \$" . number_format($monto, 2) . " en finanzas.";
    if ($crearIngreso)     $mensajePartes[] = "Se generó un ingreso de \$" . number_format($monto, 2) . " en finanzas.";
    if ($notificacionId)   $mensajePartes[] = "Se notificó stock bajo.";

    echo json_encode([
        "ok"             => true,
        "mensaje"        => implode(' ', $mensajePartes),
        "movimiento_id"  => $movimientoId,
        "transaccion_id" => $transaccionId,
        "notificacion_id"=> $notificacionId,
        "stock_anterior" => $stockActual,
        "stock_nuevo"    => $nuevoStock
    ]);

} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}

$conexion->close();

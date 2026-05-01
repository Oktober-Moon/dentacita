<?php
/*
 * VALIDACIONES · módulo INVENTARIO
 * --------------------------------
 * Funciones puras que devuelven null si todo OK, o un mensaje de error en español.
 */

require_once __DIR__ . '/_catalogos.php';

function validarItemNombre($nombre) {
    if ($nombre === '') return "El nombre del producto es obligatorio.";
    if (mb_strlen($nombre) < 2)   return "El nombre debe tener al menos 2 caracteres.";
    if (mb_strlen($nombre) > 120) return "El nombre no puede pasar de 120 caracteres.";
    return null;
}

function validarItemCategoria($cat) {
    if (!array_key_exists($cat, INV_CATEGORIAS)) {
        return "Categoría no válida.";
    }
    return null;
}

function validarItemUnidad($u) {
    if (!array_key_exists($u, INV_UNIDADES)) {
        return "Unidad no válida.";
    }
    return null;
}

function validarItemCantidadEntera($v, $nombre, $permiteCero = true) {
    if ($v === '' || $v === null) return "$nombre es obligatorio.";
    if (!is_numeric($v) || (int)$v != (float)$v) return "$nombre debe ser un número entero.";
    $i = (int)$v;
    if (!$permiteCero && $i <= 0) return "$nombre debe ser mayor a 0.";
    if ($i < 0)        return "$nombre no puede ser negativo.";
    if ($i > 99999999) return "$nombre es demasiado alto.";
    return null;
}

function validarItemPrecioOpcional($v, $nombre) {
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v))    return "$nombre debe ser un número.";
    $f = (float)$v;
    if ($f < 0)             return "$nombre no puede ser negativo.";
    if ($f > 99999999.99)   return "$nombre es demasiado alto.";
    return null;
}

function validarItemTexto($v, $maxLen, $nombre) {
    if ($v === '' || $v === null) return null;
    if (mb_strlen($v) > $maxLen) return "$nombre no puede pasar de $maxLen caracteres.";
    return null;
}


/* ===== Movimientos ===== */

function validarMovimientoTipo($t) {
    if (!in_array($t, ['entrada','salida','ajuste'], true)) {
        return "Tipo de movimiento no válido.";
    }
    return null;
}

function validarMovimientoMotivo($motivo, $tipo) {
    $entradas = ['compra','donacion','ajuste_inicial','devolucion_proveedor','otro'];
    $salidas  = ['venta','uso_consulta','vencimiento','perdida','danado','ajuste_inventario','otro'];
    $ajustes  = ['ajuste_inventario','otro'];

    if ($tipo === 'entrada' && !in_array($motivo, $entradas, true)) {
        return "Motivo no válido para entrada de inventario.";
    }
    if ($tipo === 'salida'  && !in_array($motivo, $salidas, true)) {
        return "Motivo no válido para salida de inventario.";
    }
    if ($tipo === 'ajuste'  && !in_array($motivo, $ajustes, true)) {
        return "Motivo no válido para ajuste.";
    }
    return null;
}

function validarMovimientoFecha($fecha) {
    if ($fecha === '' || $fecha === null) return "La fecha es obligatoria.";
    $ts = strtotime($fecha);
    if ($ts === false) return "Fecha no válida.";
    if ($ts > strtotime('+1 day')) return "La fecha no puede ser futura.";
    return null;
}

/**
 * Verifica que una salida de cantidad N no deje el stock negativo.
 */
function validarMovimientoStockSuficiente($stockActual, $cantidadSalida) {
    if ($cantidadSalida > $stockActual) {
        return "No hay stock suficiente. Disponible: $stockActual, intentas sacar: $cantidadSalida.";
    }
    return null;
}

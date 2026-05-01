<?php
/*
 * VALIDACIONES · módulo FINANZAS
 */

require_once __DIR__ . '/_catalogos.php';

function validarTransTipo($t) {
    if (!in_array($t, ['ingreso','egreso'], true)) {
        return "Tipo de transacción no válido (debe ser 'ingreso' o 'egreso').";
    }
    return null;
}

function validarTransCategoria($c, $tipo = null) {
    if ($c === '' || $c === null) return "La categoría es obligatoria.";
    if (mb_strlen($c) > 50)       return "La categoría no puede pasar de 50 caracteres.";

    // Reembolso es categoría especial: solo válida en flujo interno (reembolso.php).
    if ($c === FIN_CATEGORIA_REEMBOLSO) return null;

    if ($tipo === 'ingreso' && !in_array($c, FIN_CATEGORIAS_INGRESO, true)) {
        return "Categoría de ingreso no válida.";
    }
    if ($tipo === 'egreso'  && !in_array($c, FIN_CATEGORIAS_EGRESO, true)) {
        return "Categoría de egreso no válida.";
    }
    return null;
}

function validarTransMonto($m, $permiteNegativo = false) {
    if ($m === '' || $m === null) return "El monto es obligatorio.";
    if (!is_numeric($m))          return "El monto debe ser un número.";
    $f = (float)$m;
    if (!$permiteNegativo && $f <= 0) return "El monto debe ser mayor a 0.";
    if (abs($f) > 99999999.99)        return "El monto es demasiado alto.";
    return null;
}

function validarTransFecha($fecha) {
    if ($fecha === '' || $fecha === null) return "La fecha es obligatoria.";
    if (strtotime($fecha) === false)      return "Fecha no válida.";
    return null;
}

function validarTransMetodo($m) {
    if ($m === '' || $m === null) return null;
    if (!in_array($m, ['efectivo','tarjeta','transferencia','cheque','otro'], true)) {
        return "Método de pago no válido.";
    }
    return null;
}

function validarTransDescripcion($d) {
    if ($d === '' || $d === null) return null;
    if (mb_strlen($d) > 200)      return "La descripción no puede pasar de 200 caracteres.";
    return null;
}

function validarMotivoAnulacion($m) {
    if ($m === '' || $m === null) return "Indica el motivo de la anulación.";
    if (mb_strlen($m) > 200)      return "El motivo no puede pasar de 200 caracteres.";
    return null;
}

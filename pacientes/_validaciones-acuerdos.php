<?php
/*
 * VALIDACIONES · acuerdos de servicio (pestaña Acuerdos del paciente)
 */

function validarAcuerdoServicio($s) {
    if ($s === '' || $s === null) return "El servicio es obligatorio.";
    if (mb_strlen($s) < 2)        return "El servicio debe tener al menos 2 caracteres.";
    if (mb_strlen($s) > 150)      return "El servicio no puede pasar de 150 caracteres.";
    return null;
}

function validarAcuerdoFechaProgramada($f) {
    if ($f === '' || $f === null) return "La fecha programada es obligatoria.";
    $ts = strtotime($f);
    if ($ts === false)            return "Fecha programada no válida.";
    if ($ts < strtotime('-1 day')) return "La fecha programada no puede estar en el pasado.";
    return null;
}

function validarAcuerdoDuracion($d) {
    if ($d === '' || $d === null) return "La duración es obligatoria.";
    if (!is_numeric($d))          return "La duración debe ser un número.";
    $i = (int)$d;
    if ($i < 5 || $i > 600)       return "La duración debe estar entre 5 y 600 minutos.";
    return null;
}

function validarAcuerdoPrecio($p) {
    if ($p === '' || $p === null) return "El precio es obligatorio.";
    if (!is_numeric($p))          return "El precio debe ser un número.";
    $f = (float)$p;
    if ($f < 0)                   return "El precio no puede ser negativo.";
    if ($f > 99999999.99)         return "El precio es demasiado alto.";
    return null;
}

function validarAcuerdoDescripcion($d) {
    if ($d === '' || $d === null) return null;
    if (mb_strlen($d) > 500)      return "La descripción no puede pasar de 500 caracteres.";
    return null;
}

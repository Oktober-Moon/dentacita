<?php
/*
 * VALIDACIONES · módulo agenda · personal memories
 */

function validarMemoriaContenido($c) {
    if ($c === '' || $c === null) return "El contenido de la memoria es obligatorio.";
    if (mb_strlen($c) < 1)        return "El contenido no puede estar vacío.";
    if (mb_strlen($c) > 1000)     return "El contenido no puede pasar de 1000 caracteres.";
    return null;
}

function validarMemoriaFecha($f) {
    if ($f === '' || $f === null) return "La fecha es obligatoria.";
    if (strtotime($f) === false)  return "Fecha no válida.";
    return null;
}

function validarMemoriaColor($c) {
    if ($c === '' || $c === null) return null;
    $valid = ['amarillo','azul','verde','rosa','violeta','gris'];
    if (!in_array($c, $valid, true)) return "Color no válido.";
    return null;
}

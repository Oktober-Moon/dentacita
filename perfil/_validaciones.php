<?php
/*
 * VALIDACIONES · módulo perfil v7 (mínimo)
 */

function validarPerfilNombre($nombre) {
    if ($nombre === '') return "El nombre es obligatorio.";
    if (mb_strlen($nombre) < 2)   return "El nombre debe tener al menos 2 caracteres.";
    if (mb_strlen($nombre) > 150) return "El nombre no puede tener más de 150 caracteres.";
    if (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñÜü\.\' \-]+$/u', $nombre)) {
        return "El nombre sólo admite letras, espacios, apóstrofes, guiones y puntos.";
    }
    return null;
}

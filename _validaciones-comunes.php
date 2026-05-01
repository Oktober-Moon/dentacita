<?php
/*
 * VALIDACIONES COMUNES · datos de paciente
 * -----------------------------------------
 * Compartidas por agenda/_validaciones.php y pacientes/_validaciones.php
 * porque ambos módulos pueden crear pacientes:
 *   - El CRUD directo en pacientes/.
 *   - El wizard inline en agenda/ (paso 3 al crear cita).
 *
 * Mantener las reglas en un solo archivo evita drift entre ambos flujos.
 */


/* ----- NOMBRE DEL PACIENTE ----- */
function validarPacienteNombre($nombre) {
    if ($nombre === '') {
        return "El nombre del paciente es obligatorio.";
    }
    if (mb_strlen($nombre) < 2) {
        return "El nombre debe tener al menos 2 caracteres.";
    }
    if (mb_strlen($nombre) > 100) {
        return "El nombre no puede tener más de 100 caracteres.";
    }
    if (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñÜü\' \-\.]+$/u', $nombre)) {
        return "El nombre sólo admite letras, espacios, apóstrofes, guiones y puntos.";
    }
    if (preg_match('/\s{2,}/', $nombre)) {
        return "El nombre no puede tener espacios dobles.";
    }
    return null;
}


/* ----- TELÉFONO (opcional) ----- */
function validarTelefonoOpcional($telefono) {
    if ($telefono === '') return null;
    if (mb_strlen($telefono) > 20) return "El teléfono no puede tener más de 20 caracteres.";
    if (!preg_match('/^[0-9 +\-()]+$/', $telefono)) {
        return "El teléfono sólo admite dígitos, espacios, +, - y paréntesis.";
    }
    $soloDigitos = preg_replace('/\D/', '', $telefono);
    if (strlen($soloDigitos) < 7) {
        return "El teléfono debe tener al menos 7 dígitos.";
    }
    return null;
}


/* ----- EMAIL (opcional) ----- */
function validarEmailOpcional($email) {
    if ($email === '') return null;
    if (strlen($email) > 100) return "El email no puede tener más de 100 caracteres.";
    if (preg_match('/\s/', $email)) return "El email no puede contener espacios.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Email no válido. Usa el formato nombre@dominio.com.";
    }
    return null;
}


/* ----- FECHA DE NACIMIENTO (opcional) ----- */
function validarFechaNacimientoOpcional($fecha) {
    if ($fecha === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return "Fecha de nacimiento no válida.";
    }
    $ts = strtotime($fecha);
    if ($ts === false) return "Fecha de nacimiento no válida.";
    if ($ts > time()) return "La fecha de nacimiento no puede estar en el futuro.";
    if ($ts < strtotime('1900-01-01')) return "Fecha de nacimiento fuera de rango.";
    return null;
}


/* ----- GÉNERO (whitelist · default 'no_especificado') ----- */
function validarGeneroOpcional($genero) {
    $validos = ['femenino', 'masculino', 'otro', 'no_especificado'];
    if (!in_array($genero, $validos, true)) {
        return "Género no válido.";
    }
    return null;
}


/* ----- TEXTO LIBRE OPCIONAL (alergias, padecimientos, dirección, etc.) ----- */
function validarTextoOpcional($valor, $maxLen, $nombreCampo) {
    if ($valor === '' || $valor === null) return null;
    if (mb_strlen($valor) > $maxLen) {
        return "$nombreCampo no puede tener más de $maxLen caracteres.";
    }
    return null;
}

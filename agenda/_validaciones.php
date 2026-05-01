<?php
/*
 * VALIDACIONES COMPARTIDAS · módulo agenda
 * -------------------------------------------
 * El profesor en Login.zip duplica las validaciones en cada endpoint
 * para que sean autocontenidos. Aquí las extraje a un archivo aparte
 * porque las usan 2+ endpoints (guardar.php y actualizar.php) y la
 * agenda tiene más reglas que un CRUD simple.
 *
 * El fichero se incluye con `require` desde cada endpoint AJAX.
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
    // Mínimo 7 dígitos numéricos para que sea un teléfono "real"
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
    if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)) {
        return "El dominio del email no es válido.";
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


/* ----- GÉNERO (opcional, default no_especificado) ----- */
function validarGeneroOpcional($genero) {
    $validos = ['femenino', 'masculino', 'otro', 'no_especificado'];
    if (!in_array($genero, $validos, true)) {
        return "Género no válido.";
    }
    return null;
}


/* ----- TEXTO LIBRE OPCIONAL (alergias, padecimientos, medicamentos, dirección, ocupación) ----- */
function validarLongitudOpcional($valor, $maxLen, $nombreCampo) {
    if ($valor === '' || $valor === null) return null;
    if (mb_strlen($valor) > $maxLen) {
        return "$nombreCampo no puede tener más de $maxLen caracteres.";
    }
    return null;
}


/* ----- TÍTULO DE LA CITA ----- */
function validarTitulo($titulo) {
    if ($titulo === '') return "El motivo de la cita es obligatorio.";
    if (mb_strlen($titulo) < 2) return "El motivo debe tener al menos 2 caracteres.";
    if (mb_strlen($titulo) > 120) return "El motivo no puede tener más de 120 caracteres.";
    return null;
}


/* ----- FECHA + HORA + DURACIÓN ----- */
function validarFechaHoraDuracion($fecha, $hora, $duracion) {
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return "Fecha no válida.";
    }
    if ($hora === '' || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return "Hora no válida.";
    }
    if (!is_numeric($duracion)) return "Duración no válida.";
    $d = (int)$duracion;
    if ($d < 5 || $d > 480) {
        return "La duración debe estar entre 5 y 480 minutos.";
    }
    // Comprueba que la fecha+hora exista (rechaza 30 de febrero, etc.)
    $ts = strtotime($fecha . ' ' . $hora);
    if ($ts === false) return "Fecha y hora no válidas.";
    return null;
}


/* ----- ESTADO DE LA CITA ----- */
function validarEstadoCita($estado) {
    $validos = ['programada', 'confirmada', 'completada', 'cancelada', 'no_asistio'];
    if (!in_array($estado, $validos, true)) {
        return "Estado no válido.";
    }
    return null;
}


/* ----- PRECIO (opcional) ----- */
function validarPrecioOpcional($precio) {
    if ($precio === '' || $precio === null) return null;
    if (!is_numeric($precio)) return "Precio no válido.";
    $p = (float)$precio;
    if ($p < 0) return "El precio no puede ser negativo.";
    if ($p > 99999999.99) return "Precio fuera de rango.";
    return null;
}

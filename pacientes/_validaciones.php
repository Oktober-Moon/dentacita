<?php
/*
 * VALIDACIONES COMPARTIDAS · módulo pacientes
 * ----------------------------------------------
 * Cubre todos los campos extendidos: datos personales, contacto,
 * y datos clínicos.
 */


/* ----- NOMBRE COMPLETO ----- */
function validarPacienteNombreCompleto($nombre) {
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


/* ----- TELÉFONO ----- */
function validarPacienteTelefono($telefono) {
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


/* ----- EMAIL ----- */
function validarPacienteEmail($email) {
    if ($email === '') return null;
    if (strlen($email) > 100) return "El email no puede tener más de 100 caracteres.";
    if (preg_match('/\s/', $email)) return "El email no puede contener espacios.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Email no válido. Usa el formato nombre@dominio.com.";
    }
    return null;
}


/* ----- FECHA DE NACIMIENTO ----- */
function validarFechaNacimiento($fecha) {
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


/* ----- GÉNERO ----- */
function validarGenero($genero) {
    $validos = ['femenino', 'masculino', 'otro', 'no_especificado'];
    if (!in_array($genero, $validos, true)) {
        return "Género no válido.";
    }
    return null;
}


/* ----- TEXTOS LIBRES OPCIONALES (alergias, padecimientos, etc.) ----- */
function validarTextoOpcional($valor, $maxLen, $nombreCampo) {
    if ($valor === '' || $valor === null) return null;
    if (mb_strlen($valor) > $maxLen) {
        return "$nombreCampo no puede tener más de $maxLen caracteres.";
    }
    return null;
}


/* ----- DIRECCIÓN ----- */
function validarDireccionOpcional($direccion) {
    return validarTextoOpcional($direccion, 200, "La dirección");
}


/* ----- OCUPACIÓN ----- */
function validarOcupacionOpcional($ocupacion) {
    return validarTextoOpcional($ocupacion, 100, "La ocupación");
}


/* ============================================================
 * Validaciones · ARCHIVOS, CARPETAS, NOTAS (v4)
 * ============================================================ */

function validarRutaCarpeta($ruta) {
    if ($ruta === '' || $ruta === null) return null;  // raíz
    if (mb_strlen($ruta) > 255)          return "La ruta de carpeta no puede pasar de 255 caracteres.";
    // No permitir caracteres peligrosos
    if (preg_match('/\.\./', $ruta))     return "La ruta no puede contener '..'.";
    if (preg_match('/[<>:"|?*\\\\]/', $ruta)) return "La ruta contiene caracteres no permitidos.";
    if (preg_match('/\/\//', $ruta))     return "La ruta no puede tener '//' consecutivos.";
    if (str_starts_with($ruta, '/') || str_ends_with($ruta, '/')) return "La ruta no debe empezar ni terminar con '/'.";
    return null;
}

function validarNombreCarpeta($nombre) {
    if ($nombre === '' || $nombre === null) return "El nombre de carpeta es obligatorio.";
    if (mb_strlen($nombre) < 1)              return "El nombre de carpeta no puede estar vacío.";
    if (mb_strlen($nombre) > 120)            return "El nombre de carpeta no puede pasar de 120 caracteres.";
    if (preg_match('/[\/<>:"|?*\\\\]/', $nombre)) return "El nombre de carpeta contiene caracteres no permitidos.";
    return null;
}

function validarNombreArchivo($nombre) {
    if ($nombre === '' || $nombre === null) return "El nombre de archivo es obligatorio.";
    if (mb_strlen($nombre) > 150)            return "El nombre del archivo no puede pasar de 150 caracteres.";
    if (preg_match('/[\/<>:"|?*\\\\]/', $nombre)) return "El nombre del archivo contiene caracteres no permitidos.";
    return null;
}

function validarTipoArchivo($t) {
    $valid = ['imagen','pdf','radiografia','documento','laboratorio','otro'];
    if (!in_array($t, $valid, true)) return "Tipo de archivo no válido.";
    return null;
}

function validarDescripcionArchivo($d) {
    if ($d === '' || $d === null) return null;
    if (mb_strlen($d) > 200)      return "La descripción no puede pasar de 200 caracteres.";
    return null;
}

/* Validar el archivo que llega en $_FILES */
function validarArchivoSubido($file) {
    if (!isset($file) || !is_array($file))                  return "No se recibió ningún archivo.";
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return match ($file['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño permitido.",
            UPLOAD_ERR_PARTIAL                        => "El archivo se subió incompleto.",
            UPLOAD_ERR_NO_FILE                        => "No se seleccionó ningún archivo.",
            UPLOAD_ERR_NO_TMP_DIR                     => "Falta el directorio temporal del servidor.",
            UPLOAD_ERR_CANT_WRITE                     => "Error al escribir el archivo en disco.",
            default                                   => "Error desconocido al subir el archivo."
        };
    }
    // 10 MB de límite
    $MAX = 10 * 1024 * 1024;
    if ($file['size'] > $MAX) return "El archivo excede 10 MB.";

    // Validar extensión
    $extPermitidas = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extPermitidas, true)) {
        return "Extensión no permitida: $ext. Solo: " . implode(', ', $extPermitidas) . ".";
    }
    return null;
}


/* ----- Notas ----- */

function validarNotaContenido($c) {
    if ($c === '' || $c === null) return "El contenido es obligatorio.";
    if (mb_strlen($c) > 5000)     return "El contenido no puede pasar de 5000 caracteres.";
    return null;
}

function validarNotaFecha($f) {
    if ($f === '' || $f === null) return "La fecha es obligatoria.";
    if (strtotime($f) === false)  return "Fecha no válida.";
    return null;
}

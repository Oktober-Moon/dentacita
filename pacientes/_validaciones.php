<?php
/*
 * VALIDACIONES · módulo pacientes
 * --------------------------------
 * Solo las reglas específicas de pacientes (archivos, carpetas, notas)
 * más wrappers cómodos para textos opcionales con longitud predefinida.
 *
 * Las validaciones de datos personales (nombre, teléfono, email, fecha
 * nacimiento, género, texto libre) viven en _validaciones-comunes.php
 * porque las comparten con agenda/.
 */

require_once __DIR__ . '/../_validaciones-comunes.php';


/* ----- DIRECCIÓN ----- */
function validarDireccionOpcional($direccion) {
    return validarTextoOpcional($direccion, 200, "La dirección");
}


/* ----- OCUPACIÓN ----- */
function validarOcupacionOpcional($ocupacion) {
    return validarTextoOpcional($ocupacion, 100, "La ocupación");
}


/* ============================================================
 * Validaciones · ARCHIVOS, CARPETAS, NOTAS (v8)
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

    // Validar magic bytes (tipo MIME real del archivo) contra la extensión declarada
    if (function_exists('finfo_open') && is_uploaded_file($file['tmp_name'])) {
        $mimeReal = @mime_content_type($file['tmp_name']) ?: '';
        // Pares ext → MIMEs aceptables (un archivo .jpg legítimo es image/jpeg, etc.)
        $mimePorExt = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'doc'  => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'application/zip', 'application/octet-stream'],
            'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'application/zip', 'application/octet-stream'],
        ];
        $aceptables = $mimePorExt[$ext] ?? [];
        if ($aceptables && $mimeReal && !in_array($mimeReal, $aceptables, true)) {
            return "El contenido del archivo no coincide con la extensión .$ext (detectado: $mimeReal).";
        }
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

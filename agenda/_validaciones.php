<?php
/*
 * VALIDACIONES · módulo agenda
 * -----------------------------
 * Solo las reglas específicas de citas. Las validaciones de paciente
 * (nombre, teléfono, email, fecha-nac, género, textos libres) viven
 * en _validaciones-comunes.php para no duplicarlas con pacientes/.
 */

require_once __DIR__ . '/../_validaciones-comunes.php';


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

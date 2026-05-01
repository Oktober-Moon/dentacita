<?php
/*
 * GUARDAR CITA · endpoint AJAX (JSON)
 * -------------------------------------
 * Crea una nueva cita. Valida en el servidor: nunca confiamos solo
 * en el frontend. Usa prepared statement.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

require __DIR__ . '/_validaciones.php';


/* ----- Datos recibidos ----- */
$paciente_id        = trim($_POST['paciente_id'] ?? '');
$paciente_nombre    = preg_replace('/\s+/', ' ', trim($_POST['paciente_nombre'] ?? ''));
$paciente_telefono  = trim($_POST['paciente_telefono'] ?? '');
$titulo             = preg_replace('/\s+/', ' ', trim($_POST['titulo'] ?? ''));
$descripcion        = trim($_POST['descripcion'] ?? '');
$fecha              = $_POST['fecha'] ?? '';
$hora_inicio        = $_POST['hora_inicio'] ?? '';
$duracion           = $_POST['duracion'] ?? '';
$estadoCita         = $_POST['estado'] ?? '';
$notas              = trim($_POST['notas'] ?? '');
$precio             = trim($_POST['precio'] ?? '');


/* ----- Validaciones (ver _validaciones.php) ----- */
$err = validarPacienteNombre($paciente_nombre);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTelefonoOpcional($paciente_telefono);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTitulo($titulo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarFechaHoraDuracion($fecha, $hora_inicio, $duracion);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarEstadoCita($estadoCita);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarPrecioOpcional($precio);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }


/* ----- Cálculo de fecha_hora_inicio y fecha_hora_fin ----- */
$inicio = $fecha . ' ' . $hora_inicio . ':00';
$tsIni  = strtotime($inicio);
$tsFin  = $tsIni + ((int)$duracion) * 60;
$fin    = date('Y-m-d H:i:s', $tsFin);


/* ----- paciente_id puede ser NULL si la cita es solo "nombre + tel" ----- */
$pacienteIdInt = is_numeric($paciente_id) ? (int)$paciente_id : null;

/* ----- precio puede ser NULL ----- */
$precioFinal = ($precio === '' || $precio === null) ? null : (float)$precio;

/* ----- Insert ----- */
$sql = "INSERT INTO citas
        (usuario_id, paciente_id, paciente_nombre, paciente_telefono, titulo, descripcion,
         fecha_hora_inicio, fecha_hora_fin, estado, notas, precio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

// "issssssss" + "d" para precio. Tipos: i = int, s = string, d = double.
// Para campos NULL usamos send_long_data o variables locales nullables.
// mysqli no permite NULL directo en bind_param salvo por referencia.
// Truco: usar variables y dejarlas null; mysqli los manda como NULL si están en strict.
$descripcionV = $descripcion === '' ? null : $descripcion;
$telefonoV    = $paciente_telefono === '' ? null : $paciente_telefono;
$notasV       = $notas === '' ? null : $notas;

// Tipos: i=int, s=string, d=double. precio es DECIMAL → 'd'.
$stmt->bind_param("iissssssssd",
    $usuarioId,
    $pacienteIdInt,
    $paciente_nombre,
    $telefonoV,
    $titulo,
    $descripcionV,
    $inicio,
    $fin,
    $estadoCita,
    $notasV,
    $precioFinal
);

if (@$stmt->execute()) {
    echo json_encode([
        "ok"      => true,
        "mensaje" => "Cita guardada correctamente.",
        "fecha"   => $fecha,
        "cita_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

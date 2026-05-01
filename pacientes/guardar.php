<?php
/*
 * GUARDAR PACIENTE · endpoint AJAX (JSON)
 * ----------------------------------------
 * Crea un paciente nuevo con todos los campos extendidos
 * (datos personales, contacto y datos clínicos).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

require __DIR__ . '/_validaciones.php';


/* ----- Datos recibidos ----- */
$nombre_completo  = preg_replace('/\s+/', ' ', trim($_POST['nombre_completo'] ?? ''));
$telefono         = trim($_POST['telefono'] ?? '');
$email            = trim($_POST['email'] ?? '');
$fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
$genero           = trim($_POST['genero'] ?? 'no_especificado');
$direccion        = trim($_POST['direccion'] ?? '');
$ocupacion        = trim($_POST['ocupacion'] ?? '');
$alergias         = trim($_POST['alergias'] ?? '');
$padecimientos    = trim($_POST['padecimientos'] ?? '');
$medicamentos     = trim($_POST['medicamentos'] ?? '');
$foto_url         = trim($_POST['foto_url'] ?? '');
$notas            = trim($_POST['notas'] ?? '');


/* ----- Validaciones ----- */
$err = validarPacienteNombreCompleto($nombre_completo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarPacienteTelefono($telefono);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarPacienteEmail($email);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarFechaNacimiento($fecha_nacimiento);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarGenero($genero);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarDireccionOpcional($direccion);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarOcupacionOpcional($ocupacion);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($alergias,      500, "El campo de alergias");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($padecimientos, 500, "El campo de padecimientos");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($medicamentos,  500, "El campo de medicamentos");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($notas, 1000, "El campo de notas");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($foto_url, 255, "La URL de la foto");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }


/* ----- Conversión de strings vacíos a NULL ----- */
$telefonoV         = $telefono         === '' ? null : $telefono;
$emailV            = $email            === '' ? null : $email;
$fechaNacV         = $fecha_nacimiento === '' ? null : $fecha_nacimiento;
$direccionV        = $direccion        === '' ? null : $direccion;
$ocupacionV        = $ocupacion        === '' ? null : $ocupacion;
$alergiasV         = $alergias         === '' ? null : $alergias;
$padecimientosV    = $padecimientos    === '' ? null : $padecimientos;
$medicamentosV     = $medicamentos     === '' ? null : $medicamentos;
$fotoUrlV          = $foto_url         === '' ? null : $foto_url;
$notasV            = $notas            === '' ? null : $notas;


/* ----- INSERT ----- */
$sql = "INSERT INTO pacientes
        (usuario_id, nombre_completo, telefono, email, fecha_nacimiento, genero,
         direccion, ocupacion, alergias, padecimientos, medicamentos,
         foto_url, notas_dentista)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

// 1 int (usuario_id) + 12 strings (todos VARCHAR/DATE/ENUM/TEXT — mysqli los maneja como 's')
$stmt->bind_param("issssssssssss",
    $usuarioId,
    $nombre_completo, $telefonoV, $emailV, $fechaNacV, $genero,
    $direccionV, $ocupacionV, $alergiasV, $padecimientosV, $medicamentosV,
    $fotoUrlV, $notasV
);

if (@$stmt->execute()) {
    echo json_encode([
        "ok"          => true,
        "mensaje"     => "Paciente registrado correctamente.",
        "paciente_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

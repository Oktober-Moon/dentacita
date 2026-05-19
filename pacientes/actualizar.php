<?php
/*
 * ACTUALIZAR PACIENTE · endpoint AJAX (JSON)
 * --------------------------------------------
 *  GET  ?id=N → devuelve datos del paciente para precarga del modal
 *  POST       → aplica el UPDATE (recibe paciente_id + campos)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

require __DIR__ . '/_validaciones.php';


/* ============================================================
 *  MODO GET · precarga
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? '';
    if (!is_numeric($id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]);
        exit;
    }
    $idInt = (int)$id;

    $stmt = @$conexion->prepare(
        "SELECT paciente_id, nombre_completo, telefono, email, fecha_nacimiento,
                genero, direccion, ocupacion, alergias, padecimientos, medicamentos,
                foto_url, notas_dentista AS notas
         FROM pacientes WHERE paciente_id = ? AND usuario_id = ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("ii", $idInt, $usuarioId);
    @$stmt->execute();
    $paciente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conexion->close();

    if (!$paciente) {
        echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
        exit;
    }

    echo json_encode(["ok"=>true,"paciente"=>$paciente]);
    exit;
}


/* ============================================================
 *  MODO POST · update
 * ============================================================ */
$paciente_id      = $_POST['paciente_id'] ?? '';
if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de paciente no válido."]);
    exit;
}
$pacienteIdInt = (int)$paciente_id;

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


/* ----- Validaciones (mismas del guardar) ----- */
$err = validarPacienteNombre($nombre_completo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTelefonoOpcional($telefono);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarEmailOpcional($email);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarFechaNacimientoOpcional($fecha_nacimiento);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarGeneroOpcional($genero);
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

$err = validarTextoOpcional($notas,         1000, "El campo de notas");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$err = validarTextoOpcional($foto_url, 255, "La URL de la foto");
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }


/* ----- Vacíos a NULL ----- */
$telefonoV      = $telefono         === '' ? null : $telefono;
$emailV         = $email            === '' ? null : $email;
$fechaNacV      = $fecha_nacimiento === '' ? null : $fecha_nacimiento;
$direccionV     = $direccion        === '' ? null : $direccion;
$ocupacionV     = $ocupacion        === '' ? null : $ocupacion;
$alergiasV      = $alergias         === '' ? null : $alergias;
$padecimientosV = $padecimientos    === '' ? null : $padecimientos;
$medicamentosV  = $medicamentos     === '' ? null : $medicamentos;
$fotoUrlV       = $foto_url         === '' ? null : $foto_url;
$notasV         = $notas            === '' ? null : $notas;


/* ----- UPDATE -----
 * No hace falta tocar actualizado_en a mano: la columna está definida con
 * ON UPDATE CURRENT_TIMESTAMP, así que MariaDB la refresca sola cuando
 * algún otro campo cambia. Tocarla explícitamente con NOW() rompería la
 * detección "sin cambios" via affected_rows === 0. */
$sql = "UPDATE pacientes SET
            nombre_completo  = ?,
            telefono         = ?,
            email            = ?,
            fecha_nacimiento = ?,
            genero           = ?,
            direccion        = ?,
            ocupacion        = ?,
            alergias         = ?,
            padecimientos    = ?,
            medicamentos     = ?,
            foto_url         = ?,
            notas_dentista   = ?
        WHERE paciente_id = ? AND usuario_id = ?";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}

$stmt->bind_param("ssssssssssssii",
    $nombre_completo, $telefonoV, $emailV, $fechaNacV, $genero,
    $direccionV, $ocupacionV, $alergiasV, $padecimientosV, $medicamentosV,
    $fotoUrlV, $notasV,
    $pacienteIdInt, $usuarioId
);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            "ok"=>true,
            "mensaje"=>"No hubo cambios que guardar.",
            "sin_cambios"=>true
        ]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Paciente actualizado correctamente."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

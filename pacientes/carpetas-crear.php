<?php
/*
 * CARPETAS · crear carpeta
 * POST paciente_id, ruta_padre (opcional), nombre
 *   - Si ruta_padre = '' → carpeta en la raíz: ruta_carpeta = nombre
 *   - Si ruta_padre = 'X' → ruta_carpeta = "X/nombre"
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id']  ?? '';
$ruta_padre  = trim($_POST['ruta_padre'] ?? '');
$nombre      = trim($_POST['nombre']  ?? '');

if (!is_numeric($paciente_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit; }
$pacIdInt = (int)$paciente_id;

$err = validarRutaCarpeta($ruta_padre);   if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarNombreCarpeta($nombre);     if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$ruta_completa = $ruta_padre === '' ? $nombre : $ruta_padre . '/' . $nombre;
$err = validarRutaCarpeta($ruta_completa);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* Verificar que el paciente pertenezca al usuario logueado */
$sv = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
$sv->bind_param("ii", $pacIdInt, $usuarioId);
@$sv->execute();
if (!$sv->get_result()->fetch_assoc()) {
    $sv->close();
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
    $conexion->close(); exit;
}
$sv->close();

/* Si hay ruta_padre, validar que exista (filtrada por usuario via JOIN) */
if ($ruta_padre !== '') {
    $sk = @$conexion->prepare(
        "SELECT 1 FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND c.ruta_carpeta = ? AND p.usuario_id = ?"
    );
    $sk->bind_param("isi", $pacIdInt, $ruta_padre, $usuarioId);
    @$sk->execute();
    if (!$sk->get_result()->fetch_assoc()) {
        $sk->close();
        echo json_encode(["ok"=>false,"mensaje"=>"La carpeta padre no existe."]);
        $conexion->close(); exit;
    }
    $sk->close();
}

$stmt = @$conexion->prepare(
    "INSERT INTO carpetas_paciente (paciente_id, ruta_carpeta, nombre) VALUES (?, ?, ?)"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("iss", $pacIdInt, $ruta_completa, $nombre);
if (@$stmt->execute()) {
    echo json_encode([
        "ok"=>true,
        "mensaje"=>"Carpeta '$ruta_completa' creada.",
        "carpeta_id"=>$stmt->insert_id,
        "ruta_carpeta"=>$ruta_completa
    ]);
} else {
    if ($stmt->errno === 1062) {
        echo json_encode(["ok"=>false,"mensaje"=>"Ya existe una carpeta con esa ruta."]);
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
    }
}
$stmt->close();
$conexion->close();

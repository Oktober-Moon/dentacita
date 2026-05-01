<?php
/*
 * ARCHIVOS · enviar a papelera (soft delete)
 * POST archivo_id
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$archivo_id = $_POST['archivo_id'] ?? '';
if (!is_numeric($archivo_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Archivo no válido."]); exit; }
$idInt = (int)$archivo_id;

$user = $_SESSION['usuario_email'] ?? 'desconocido';

$stmt = @$conexion->prepare(
    "UPDATE archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     SET a.eliminado_en = NOW(), a.eliminado_por = ?
     WHERE a.archivo_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("sii", $user, $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"El archivo no existe o ya estaba en la papelera."]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Archivo enviado a la papelera."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

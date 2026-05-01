<?php
/*
 * AGENDA · eliminar memoria personal
 * POST memoria_id
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

$memoria_id = $_POST['memoria_id'] ?? '';
if (!is_numeric($memoria_id)) { echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]); exit; }
$idInt = (int)$memoria_id;

$stmt = @$conexion->prepare("DELETE FROM personal_memories WHERE memoria_id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $idInt, $usuarioId);
if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"La memoria ya no existe."]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Memoria eliminada."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

<?php
/*
 * NOTAS · eliminar nota
 * POST nota_id
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$nota_id = $_POST['nota_id'] ?? '';
if (!is_numeric($nota_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Nota no válida."]); exit; }
$idInt = (int)$nota_id;

$stmt = @$conexion->prepare(
    "DELETE n FROM notas_paciente n
     JOIN pacientes p ON n.paciente_id = p.paciente_id
     WHERE n.nota_id = ? AND p.usuario_id = ?"
);
$stmt->bind_param("ii", $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"La nota ya no existe."]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Nota eliminada."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

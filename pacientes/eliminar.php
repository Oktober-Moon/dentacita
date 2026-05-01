<?php
/*
 * ELIMINAR PACIENTE · endpoint AJAX (JSON)
 * -----------------------------------------
 * Borra un paciente. Por las FK definidas en instalar.sql:
 *   - archivos_paciente, carpetas_paciente,
 *     notas_paciente → ON DELETE CASCADE (se borran con el paciente)
 *   - citas, transacciones → ON DELETE SET NULL
 *     (la fila queda pero pierde el vínculo al paciente)
 * El frontend ya muestra advertencia antes de llamar este endpoint.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id'] ?? '';
if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de paciente no válido."]);
    exit;
}
$idInt = (int)$paciente_id;


$stmt = @$conexion->prepare("DELETE FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ii", $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"El paciente ya no existe."]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Paciente eliminado."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

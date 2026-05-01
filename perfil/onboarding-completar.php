<?php
/*
 * ONBOARDING · marcar el tour de bienvenida como completado
 * El tour aparece en el dashboard cuando onboarding_completado = 0.
 * Al cerrarlo (botón "Listo, empezar a usar"), se marca como 1.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$stmt = @$conexion->prepare(
    "UPDATE perfil_dentista SET onboarding_completado = 1 WHERE usuario_id = ?"
);
$stmt->bind_param("i", $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Onboarding completado."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

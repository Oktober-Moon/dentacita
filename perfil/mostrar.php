<?php
/*
 * MOSTRAR PERFIL · v7 (mínimo)
 * Devuelve solo nombre, foto, tema y onboarding.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$colsPerfil = "perfil_id, usuario_id, nombre_completo, foto_url,
               variante_tema, onboarding_completado";

$stmt = @$conexion->prepare("SELECT $colsPerfil FROM perfil_dentista WHERE usuario_id = ?");
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("i", $usuarioId);
@$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$perfil) {
    /* Excepción: cuenta antigua sin bootstrap. */
    $nombreFallback = '';
    if ($sn = @$conexion->prepare("SELECT nombre FROM usuarios WHERE usuario_id = ?")) {
        $sn->bind_param("i", $usuarioId);
        @$sn->execute();
        $rn = $sn->get_result()->fetch_assoc();
        $nombreFallback = $rn['nombre'] ?? '';
        $sn->close();
    }
    bootstrapPerfilNuevoUsuario($conexion, (int)$usuarioId, $nombreFallback);

    if ($s2 = @$conexion->prepare("SELECT $colsPerfil FROM perfil_dentista WHERE usuario_id = ?")) {
        $s2->bind_param("i", $usuarioId);
        @$s2->execute();
        $perfil = $s2->get_result()->fetch_assoc();
        $s2->close();
    }
}
if (!$perfil) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo cargar el perfil."]);
    $conexion->close(); exit;
}

$conexion->close();

echo json_encode([
    "ok"     => true,
    "perfil" => $perfil
]);

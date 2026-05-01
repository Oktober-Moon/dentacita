<?php
/*
 * GUARDAR PERFIL · UPDATE de los datos del dentista logueado (v8 mínimo)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$nombre_completo = preg_replace('/\s+/', ' ', trim($_POST['nombre_completo'] ?? ''));

$err = validarPerfilNombre($nombre_completo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* ----- Asegurar que la fila exista (bootstrap fallback) ----- */
$stmtCheck = @$conexion->prepare("SELECT perfil_id FROM perfil_dentista WHERE usuario_id = ?");
$stmtCheck->bind_param("i", $usuarioId);
@$stmtCheck->execute();
$existe = (bool)$stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$existe) {
    bootstrapPerfilNuevoUsuario($conexion, (int)$usuarioId, $nombre_completo);
}

$stmt = @$conexion->prepare(
    "UPDATE perfil_dentista
        SET nombre_completo       = ?,
            onboarding_completado = 1
      WHERE usuario_id = ?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("si", $nombre_completo, $usuarioId);

if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Perfil actualizado correctamente."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

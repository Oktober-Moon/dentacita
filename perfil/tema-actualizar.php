<?php
/*
 * PERFIL · cambiar variante de tema visual
 * POST variante (string) ∈ lista de 8 temas legacy.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$variantesValidas = [
    'cyan-default', 'azul-marino', 'verde-bosque',
    'naranja-citrico', 'violeta-real', 'rosa-coral',
    'ambar-sol', 'grafito'
];

$variante = trim($_POST['variante'] ?? '');
if (!in_array($variante, $variantesValidas, true)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Variante de tema no válida."]);
    $conexion->close(); exit;
}

$stmt = @$conexion->prepare(
    "UPDATE perfil_dentista SET variante_tema = ? WHERE usuario_id = ?"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("si", $variante, $usuarioId);
if (@$stmt->execute()) {
    $_SESSION['variante_tema'] = $variante;
    echo json_encode(["ok"=>true,"mensaje"=>"Tema actualizado.","variante"=>$variante]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

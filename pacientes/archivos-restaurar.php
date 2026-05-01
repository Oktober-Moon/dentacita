<?php
/*
 * ARCHIVOS · restaurar de papelera
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

/* Obtener datos del archivo + verificar que su ruta_carpeta aún exista
 * (filtrado por usuario_id del paciente para evitar IDOR) */
$sa = @$conexion->prepare(
    "SELECT a.paciente_id, a.ruta_carpeta FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.archivo_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NOT NULL"
);
$sa->bind_param("ii", $idInt, $usuarioId);
@$sa->execute();
$row = $sa->get_result()->fetch_assoc();
$sa->close();
if (!$row) {
    echo json_encode(["ok"=>false,"mensaje"=>"Archivo no está en la papelera."]);
    $conexion->close(); exit;
}

$pacIdInt = (int)$row['paciente_id'];
$ruta     = $row['ruta_carpeta'];

/* Si la ruta original ya no existe (carpeta borrada), restaurar a raíz */
$rutaFinal = $ruta;
if ($ruta !== '' && $ruta !== null) {
    $sk = @$conexion->prepare(
        "SELECT 1 FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND c.ruta_carpeta = ? AND p.usuario_id = ?"
    );
    $sk->bind_param("isi", $pacIdInt, $ruta, $usuarioId);
    @$sk->execute();
    if (!$sk->get_result()->fetch_assoc()) {
        $rutaFinal = '';  // a raíz
    }
    $sk->close();
}

$stmt = @$conexion->prepare(
    "UPDATE archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     SET a.eliminado_en = NULL, a.eliminado_por = NULL, a.ruta_carpeta = ?
     WHERE a.archivo_id = ? AND p.usuario_id = ?"
);
$stmt->bind_param("sii", $rutaFinal, $idInt, $usuarioId);

if (@$stmt->execute()) {
    $msj = "Archivo restaurado.";
    if ($rutaFinal !== $ruta) $msj .= " (Su carpeta original ya no existe; quedó en la raíz.)";
    echo json_encode(["ok"=>true,"mensaje"=>$msj]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

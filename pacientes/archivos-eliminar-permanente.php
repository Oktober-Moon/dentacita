<?php
/*
 * ARCHIVOS · eliminar permanentemente
 * Borra el registro de BD y el archivo físico del disco.
 * POST archivo_id (debe estar en papelera)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$archivo_id = $_POST['archivo_id'] ?? '';
if (!is_numeric($archivo_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Archivo no válido."]); exit; }
$idInt = (int)$archivo_id;

/* Obtener URL para borrar archivo físico (filtrado por usuario via JOIN) */
$sa = @$conexion->prepare(
    "SELECT a.archivo_url FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.archivo_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NOT NULL"
);
$sa->bind_param("ii", $idInt, $usuarioId);
@$sa->execute();
$row = $sa->get_result()->fetch_assoc();
$sa->close();
if (!$row) {
    echo json_encode(["ok"=>false,"mensaje"=>"El archivo debe estar en la papelera para eliminarse permanentemente."]);
    $conexion->close(); exit;
}

/* DELETE en BD (filtrado por usuario via JOIN) */
$stmt = @$conexion->prepare(
    "DELETE a FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.archivo_id = ? AND p.usuario_id = ?"
);
$stmt->bind_param("ii", $idInt, $usuarioId);

if (@$stmt->execute()) {
    /* Borrar archivo físico (best-effort) */
    $url = $row['archivo_url'];
    if ($url && str_starts_with($url, '../uploads/')) {
        $rutaFisica = __DIR__ . '/' . $url;
        if (is_file($rutaFisica)) @unlink($rutaFisica);
    }
    echo json_encode(["ok"=>true,"mensaje"=>"Archivo eliminado permanentemente."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

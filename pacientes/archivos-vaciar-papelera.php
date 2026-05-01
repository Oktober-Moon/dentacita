<?php
/*
 * ARCHIVOS · vaciar papelera del paciente
 * Borra de BD y disco TODOS los archivos del paciente con eliminado_en NOT NULL.
 * POST paciente_id
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id'] ?? '';
if (!is_numeric($paciente_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit; }
$pacIdInt = (int)$paciente_id;

/* Verificar que el paciente pertenezca al usuario logueado */
$sv = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
$sv->bind_param("ii", $pacIdInt, $usuarioId);
@$sv->execute();
if (!$sv->get_result()->fetch_assoc()) {
    $sv->close();
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
    $conexion->close(); exit;
}
$sv->close();

/* Obtener todas las URLs (filtrado por usuario via JOIN) */
$urls = [];
$sl = @$conexion->prepare(
    "SELECT a.archivo_url FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NOT NULL"
);
if ($sl) {
    $sl->bind_param("ii", $pacIdInt, $usuarioId);
    @$sl->execute();
    $r = $sl->get_result();
    while ($f = $r->fetch_assoc()) $urls[] = $f['archivo_url'];
    $sl->close();
}

if (!$urls) {
    echo json_encode(["ok"=>true,"mensaje"=>"La papelera ya estaba vacía.","eliminados"=>0]);
    $conexion->close(); exit;
}

/* DELETE masivo (filtrado por usuario via JOIN) */
$stmt = @$conexion->prepare(
    "DELETE a FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NOT NULL"
);
$stmt->bind_param("ii", $pacIdInt, $usuarioId);

if (@$stmt->execute()) {
    $eliminados = $stmt->affected_rows;
    /* Borrar archivos físicos */
    foreach ($urls as $url) {
        if ($url && str_starts_with($url, '../uploads/')) {
            $rutaFisica = __DIR__ . '/' . $url;
            if (is_file($rutaFisica)) @unlink($rutaFisica);
        }
    }
    echo json_encode([
        "ok"          => true,
        "mensaje"     => "Papelera vaciada. Se eliminaron $eliminados archivo(s).",
        "eliminados"  => $eliminados
    ]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

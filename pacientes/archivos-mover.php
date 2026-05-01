<?php
/*
 * ARCHIVOS · mover archivo a otra carpeta
 * POST archivo_id, ruta_carpeta_destino ('' = raíz)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$archivo_id    = $_POST['archivo_id']            ?? '';
$ruta_destino  = trim($_POST['ruta_carpeta_destino'] ?? '');

if (!is_numeric($archivo_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Archivo no válido."]); exit; }
$idInt = (int)$archivo_id;

$err = validarRutaCarpeta($ruta_destino);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* Obtener paciente_id del archivo (filtrado por usuario via JOIN) */
$sa = @$conexion->prepare(
    "SELECT a.paciente_id FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.archivo_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL"
);
$sa->bind_param("ii", $idInt, $usuarioId);
@$sa->execute();
$row = $sa->get_result()->fetch_assoc();
$sa->close();
if (!$row) {
    echo json_encode(["ok"=>false,"mensaje"=>"Archivo no encontrado o en papelera."]);
    $conexion->close(); exit;
}
$pacIdInt = (int)$row['paciente_id'];

/* Si destino no es raíz, validar que la carpeta exista (filtrada por usuario) */
if ($ruta_destino !== '') {
    $sk = @$conexion->prepare(
        "SELECT 1 FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND c.ruta_carpeta = ? AND p.usuario_id = ?"
    );
    $sk->bind_param("isi", $pacIdInt, $ruta_destino, $usuarioId);
    @$sk->execute();
    if (!$sk->get_result()->fetch_assoc()) {
        $sk->close();
        echo json_encode(["ok"=>false,"mensaje"=>"La carpeta destino no existe."]);
        $conexion->close(); exit;
    }
    $sk->close();
}

$stmt = @$conexion->prepare(
    "UPDATE archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     SET a.ruta_carpeta = ?
     WHERE a.archivo_id = ? AND p.usuario_id = ?"
);
$stmt->bind_param("sii", $ruta_destino, $idInt, $usuarioId);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Archivo movido."]);
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

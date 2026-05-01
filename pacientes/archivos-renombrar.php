<?php
/*
 * ARCHIVOS · renombrar archivo (cambia nombre_archivo y/o descripción)
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$archivo_id     = $_POST['archivo_id']     ?? '';
$nombre_archivo = trim($_POST['nombre_archivo'] ?? '');
$descripcion    = trim($_POST['descripcion']    ?? '');
$tipo           = trim($_POST['tipo']           ?? '');
$fecha          = trim($_POST['fecha']          ?? '');

if (!is_numeric($archivo_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Archivo no válido."]); exit; }
$idInt = (int)$archivo_id;

$err = validarNombreArchivo($nombre_archivo);     if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTipoArchivo($tipo);                 if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarDescripcionArchivo($descripcion);   if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
if ($fecha !== '' && strtotime($fecha) === false) {
    echo json_encode(["ok"=>false,"mensaje"=>"Fecha no válida."]); exit;
}

$descV  = $descripcion === '' ? null : $descripcion;
$fechaV = $fecha === '' ? null : $fecha;

$stmt = @$conexion->prepare(
    "UPDATE archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     SET a.nombre_archivo=?, a.descripcion=?, a.tipo=?, a.fecha=?
     WHERE a.archivo_id=? AND p.usuario_id = ? AND a.eliminado_en IS NULL"
);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ssssii", $nombre_archivo, $descV, $tipo, $fechaV, $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"Archivo no encontrado o en papelera."]);
    } else {
        echo json_encode(["ok"=>true,"mensaje"=>"Archivo actualizado."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

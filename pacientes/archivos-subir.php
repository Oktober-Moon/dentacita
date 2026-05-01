<?php
/*
 * ARCHIVOS · subir archivo del paciente
 * --------------------------------------
 * POST multipart:
 *   paciente_id, ruta_carpeta (opcional), nombre_archivo (opcional, sino usa el original),
 *   descripcion, tipo, fecha, archivo (file)
 *
 * Guarda el archivo en /uploads/pacientes/{paciente_id}/{archivo_unico}
 * Inserta registro en archivos_paciente.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id    = $_POST['paciente_id']    ?? '';
$ruta_carpeta   = trim($_POST['ruta_carpeta']  ?? '');
$nombre_archivo = trim($_POST['nombre_archivo'] ?? '');
$descripcion    = trim($_POST['descripcion']   ?? '');
$tipo           = trim($_POST['tipo']          ?? 'documento');
$fecha          = trim($_POST['fecha']         ?? date('Y-m-d'));

if (!is_numeric($paciente_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit; }
$pacIdInt = (int)$paciente_id;

$err = validarRutaCarpeta($ruta_carpeta);          if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarTipoArchivo($tipo);                  if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
$err = validarDescripcionArchivo($descripcion);    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }
if (strtotime($fecha) === false)               { echo json_encode(["ok"=>false,"mensaje"=>"Fecha no válida."]); exit; }
$err = validarArchivoSubido($_FILES['archivo'] ?? null);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$archivo = $_FILES['archivo'];
$nombreOriginal = $archivo['name'];
if ($nombre_archivo === '') $nombre_archivo = $nombreOriginal;
$err = validarNombreArchivo($nombre_archivo);      if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

/* Verificar paciente (debe pertenecer al usuario logueado) */
$sp = @$conexion->prepare("SELECT 1 FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
$sp->bind_param("ii", $pacIdInt, $usuarioId);
@$sp->execute();
if (!$sp->get_result()->fetch_assoc()) {
    $sp->close();
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
    $conexion->close(); exit;
}
$sp->close();

/* Si tiene carpeta, validar que exista (filtrada por usuario via JOIN) */
if ($ruta_carpeta !== '') {
    $sk = @$conexion->prepare(
        "SELECT 1 FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND c.ruta_carpeta = ? AND p.usuario_id = ?"
    );
    $sk->bind_param("isi", $pacIdInt, $ruta_carpeta, $usuarioId);
    @$sk->execute();
    if (!$sk->get_result()->fetch_assoc()) {
        $sk->close();
        echo json_encode(["ok"=>false,"mensaje"=>"La carpeta '$ruta_carpeta' no existe."]);
        $conexion->close(); exit;
    }
    $sk->close();
}

/* Construir ruta destino */
$baseUploads = __DIR__ . '/../uploads/pacientes/' . $pacIdInt;
if (!is_dir($baseUploads)) {
    if (!@mkdir($baseUploads, 0755, true) && !is_dir($baseUploads)) {
        echo json_encode(["ok"=>false,"mensaje"=>"No se pudo crear el directorio de subida."]);
        $conexion->close(); exit;
    }
}

$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
$nombreUnico = uniqid('arch_', true) . '.' . $ext;
$destino     = $baseUploads . '/' . $nombreUnico;

if (!@move_uploaded_file($archivo['tmp_name'], $destino)) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo guardar el archivo en el servidor."]);
    $conexion->close(); exit;
}

/* URL relativa accesible desde el navegador */
$archivo_url = '../uploads/pacientes/' . $pacIdInt . '/' . $nombreUnico;

$descV = $descripcion === '' ? null : $descripcion;

$stmt = @$conexion->prepare(
    "INSERT INTO archivos_paciente
        (paciente_id, nombre_archivo, descripcion, tipo, archivo_url, ruta_carpeta, fecha)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
    @unlink($destino);
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("issssss",
    $pacIdInt, $nombre_archivo, $descV, $tipo, $archivo_url, $ruta_carpeta, $fecha
);
if (@$stmt->execute()) {
    echo json_encode(["ok"=>true,"mensaje"=>"Archivo subido.","archivo_id"=>$stmt->insert_id]);
} else {
    @unlink($destino);
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

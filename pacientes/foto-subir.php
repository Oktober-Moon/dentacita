<?php
/*
 * PACIENTES · subir foto del paciente con crop
 * ---------------------------------------------
 * Idéntico al patrón de perfil/foto-subir.php pero para pacientes.
 * Recibe paciente_id + imagen_b64 (dataURL JPEG/PNG/WEBP).
 * Guarda en /uploads/pacientes/{paciente_id}/foto-{timestamp}.jpg
 * y actualiza pacientes.foto_url.
 *
 * Anti-IDOR: solo permite tocar pacientes del usuario actual.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/../_uploads-helpers.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id'] ?? '';
$dataURL     = $_POST['imagen_b64']  ?? '';

if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit;
}
$pacIdInt = (int)$paciente_id;

if ($dataURL === '') {
    echo json_encode(["ok"=>false,"mensaje"=>"No se recibió la imagen."]); exit;
}
if (!preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $dataURL, $m)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Formato de imagen no válido. Solo JPEG, PNG o WEBP."]);
    exit;
}
$ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
$datos = base64_decode($m[2], true);
if ($datos === false) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo decodificar la imagen."]);
    exit;
}
if (strlen($datos) > 5 * 1024 * 1024) {
    echo json_encode(["ok"=>false,"mensaje"=>"La imagen excede 5 MB después del recorte."]);
    exit;
}

/* Verificar que el paciente pertenezca al usuario logueado */
$sv = @$conexion->prepare("SELECT foto_url FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
$sv->bind_param("ii", $pacIdInt, $usuarioId);
@$sv->execute();
$row = $sv->get_result()->fetch_assoc();
$sv->close();
if (!$row) {
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
    $conexion->close(); exit;
}
$fotoAnterior = $row['foto_url'] ?? null;

/* Carpeta por paciente_id */
$baseDir = __DIR__ . '/../uploads/pacientes/' . $pacIdInt;
if (!is_dir($baseDir)) {
    if (!@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
        echo json_encode(["ok"=>false,"mensaje"=>"No se pudo crear el directorio de subida."]);
        $conexion->close(); exit;
    }
}

$nombreNuevo = uniqid('foto_', true) . '.' . $ext;
$rutaFisica  = $baseDir . '/' . $nombreNuevo;
$urlRelativa = '../uploads/pacientes/' . $pacIdInt . '/' . $nombreNuevo;

if (file_put_contents($rutaFisica, $datos) === false) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo guardar la imagen."]);
    $conexion->close(); exit;
}

$stmt = @$conexion->prepare(
    "UPDATE pacientes SET foto_url = ? WHERE paciente_id = ? AND usuario_id = ?"
);
$stmt->bind_param("sii", $urlRelativa, $pacIdInt, $usuarioId);
if (@$stmt->execute()) {
    /* Borrar foto anterior (best-effort, confinado a uploads/) */
    borrarArchivoUploadSeguro($fotoAnterior, __DIR__);
    echo json_encode(["ok"=>true,"mensaje"=>"Foto guardada.","foto_url"=>$urlRelativa]);
} else {
    @unlink($rutaFisica);
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

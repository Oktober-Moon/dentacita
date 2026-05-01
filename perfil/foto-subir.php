<?php
/*
 * PERFIL · subir foto de perfil con crop
 * ---------------------------------------
 * El crop se hace en el cliente (canvas → toDataURL("image/jpeg", 0.9)).
 * Aquí recibimos el dataURL en POST imagen_b64 y lo guardamos.
 *
 * Guarda en /uploads/perfil/{usuario}/foto-{timestamp}.jpg
 * Borra la foto anterior si existía.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/../_uploads-helpers.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$dataURL = $_POST['imagen_b64'] ?? '';
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

/* Carpeta por usuario_id */
$userClean = 'u' . (int)$usuarioId;
$baseDir = __DIR__ . '/../uploads/perfil/' . $userClean;
if (!is_dir($baseDir)) {
    if (!@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
        echo json_encode(["ok"=>false,"mensaje"=>"No se pudo crear el directorio de subida."]);
        exit;
    }
}

$nombreNuevo = uniqid('foto_', true) . '.' . $ext;
$rutaFisica  = $baseDir . '/' . $nombreNuevo;
$urlRelativa = '../uploads/perfil/' . $userClean . '/' . $nombreNuevo;

if (file_put_contents($rutaFisica, $datos) === false) {
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo guardar la imagen."]);
    exit;
}

/* Buscar foto anterior para borrarla */
$so = @$conexion->prepare("SELECT foto_url FROM perfil_dentista WHERE usuario_id = ?");
$so->bind_param("i", $usuarioId);
@$so->execute();
$fotoAnterior = $so->get_result()->fetch_assoc()['foto_url'] ?? null;
$so->close();

/* UPDATE */
$stmt = @$conexion->prepare("UPDATE perfil_dentista SET foto_url = ? WHERE usuario_id = ?");
$stmt->bind_param("si", $urlRelativa, $usuarioId);
if (@$stmt->execute()) {
    /* Borrar foto anterior (best-effort, confinado a uploads/) */
    borrarArchivoUploadSeguro($fotoAnterior, __DIR__);
    echo json_encode(["ok"=>true,"mensaje"=>"Foto actualizada.","foto_url"=>$urlRelativa]);
} else {
    @unlink($rutaFisica);
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}
$stmt->close();
$conexion->close();

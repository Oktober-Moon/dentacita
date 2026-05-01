<?php
/*
 * ELIMINAR PACIENTE · endpoint AJAX (JSON)
 * -----------------------------------------
 * Borra un paciente. Por las FK definidas en instalar.sql:
 *   - archivos_paciente, carpetas_paciente,
 *     notas_paciente → ON DELETE CASCADE (se borran con el paciente)
 *   - citas, transacciones → ON DELETE SET NULL
 *     (la fila queda pero pierde el vínculo al paciente)
 * El frontend ya muestra advertencia antes de llamar este endpoint.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/../_uploads-helpers.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_POST['paciente_id'] ?? '';
if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"ID de paciente no válido."]);
    exit;
}
$idInt = (int)$paciente_id;


/* Verificar pertenencia y recolectar URLs físicas ANTES del DELETE
   (después del cascade ya no podemos consultarlas).
   Incluye archivos activos, en papelera y la foto del paciente. */
$urlsAEliminar = [];

$sFoto = @$conexion->prepare(
    "SELECT foto_url FROM pacientes WHERE paciente_id = ? AND usuario_id = ?"
);
if ($sFoto) {
    $sFoto->bind_param("ii", $idInt, $usuarioId);
    @$sFoto->execute();
    $rowFoto = $sFoto->get_result()->fetch_assoc();
    $sFoto->close();
    if (!$rowFoto) {
        echo json_encode(["ok"=>false,"mensaje"=>"El paciente ya no existe."]);
        $conexion->close(); exit;
    }
    if (!empty($rowFoto['foto_url'])) $urlsAEliminar[] = $rowFoto['foto_url'];
}

$sArc = @$conexion->prepare(
    "SELECT a.archivo_url FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ?"
);
if ($sArc) {
    $sArc->bind_param("ii", $idInt, $usuarioId);
    @$sArc->execute();
    $r = $sArc->get_result();
    while ($f = $r->fetch_assoc()) {
        if (!empty($f['archivo_url'])) $urlsAEliminar[] = $f['archivo_url'];
    }
    $sArc->close();
}

$stmt = @$conexion->prepare("DELETE FROM pacientes WHERE paciente_id = ? AND usuario_id = ?");
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("ii", $idInt, $usuarioId);

if (@$stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["ok"=>false,"mensaje"=>"El paciente ya no existe."]);
    } else {
        // Borrar archivos físicos (best-effort, confinado a uploads/)
        foreach ($urlsAEliminar as $url) {
            borrarArchivoUploadSeguro($url, __DIR__);
        }
        // Borrar la carpeta del paciente si quedó vacía
        $carpeta = __DIR__ . '/../uploads/pacientes/' . $idInt;
        if (is_dir($carpeta)) @rmdir($carpeta);

        echo json_encode(["ok"=>true,"mensaje"=>"Paciente eliminado."]);
    }
} else {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
}

$stmt->close();
$conexion->close();

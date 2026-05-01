<?php
/*
 * ARCHIVOS · listar archivos + carpetas + papelera
 * --------------------------------------------------
 * GET ?paciente_id=N
 * Devuelve:
 *   - carpetas: lista de carpetas con su ruta_carpeta y nombre
 *   - archivos: lista de archivos NO eliminados, agrupados por ruta_carpeta
 *   - papelera: lista de archivos con eliminado_en NOT NULL
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/../_uploads-helpers.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$paciente_id = $_GET['paciente_id'] ?? '';
if (!is_numeric($paciente_id)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Paciente no válido."]); exit;
}
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

/* ============================================================
 * AUTO-PURGA · S3 (Fase 4)
 * Eliminar archivos en papelera con más de 30 días.
 * Se borra del disco antes del DELETE para no dejar archivos huérfanos.
 * ============================================================ */
$purgados = 0;
$sp = @$conexion->prepare(
    "SELECT a.archivo_id, a.archivo_url
     FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ?
       AND a.eliminado_en IS NOT NULL
       AND a.eliminado_en < DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
if ($sp) {
    $sp->bind_param("ii", $pacIdInt, $usuarioId);
    @$sp->execute();
    $rp = $sp->get_result();
    $idsAPurgar = [];
    while ($f = $rp->fetch_assoc()) {
        $idsAPurgar[] = (int)$f['archivo_id'];
        borrarArchivoUploadSeguro($f['archivo_url'], __DIR__);
    }
    $sp->close();
    if (!empty($idsAPurgar)) {
        $placeholders = implode(',', array_fill(0, count($idsAPurgar), '?'));
        $sqlDel = "DELETE a FROM archivos_paciente a
                   JOIN pacientes p ON a.paciente_id = p.paciente_id
                   WHERE p.usuario_id = ? AND a.archivo_id IN ($placeholders)";
        $sd = @$conexion->prepare($sqlDel);
        if ($sd) {
            $tipos = 'i' . str_repeat('i', count($idsAPurgar));
            $sd->bind_param($tipos, $usuarioId, ...$idsAPurgar);
            if (@$sd->execute()) $purgados = $sd->affected_rows;
            $sd->close();
        }
    }
}

/* Carpetas */
$carpetas = [];
$sc = @$conexion->prepare(
    "SELECT c.carpeta_id, c.ruta_carpeta, c.nombre, c.creado_en
     FROM carpetas_paciente c
     JOIN pacientes p ON c.paciente_id = p.paciente_id
     WHERE c.paciente_id = ? AND p.usuario_id = ?
     ORDER BY c.ruta_carpeta ASC"
);
if ($sc) {
    $sc->bind_param("ii", $pacIdInt, $usuarioId);
    @$sc->execute();
    $r = $sc->get_result();
    while ($f = $r->fetch_assoc()) $carpetas[] = $f;
    $sc->close();
}

/* Archivos activos */
$archivos = [];
$sa = @$conexion->prepare(
    "SELECT a.archivo_id, a.nombre_archivo, a.descripcion, a.tipo, a.archivo_url,
            a.ruta_carpeta, a.fecha, a.creado_en
     FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL
     ORDER BY a.ruta_carpeta ASC, a.creado_en DESC"
);
if ($sa) {
    $sa->bind_param("ii", $pacIdInt, $usuarioId);
    @$sa->execute();
    $r = $sa->get_result();
    while ($f = $r->fetch_assoc()) $archivos[] = $f;
    $sa->close();
}

/* Papelera · incluye dias_restantes (30 - días desde eliminado_en) */
$papelera = [];
$sp2 = @$conexion->prepare(
    "SELECT a.archivo_id, a.nombre_archivo, a.descripcion, a.tipo, a.archivo_url,
            a.ruta_carpeta, a.fecha, a.eliminado_en, a.eliminado_por,
            GREATEST(0, 30 - DATEDIFF(NOW(), a.eliminado_en)) AS dias_restantes
     FROM archivos_paciente a
     JOIN pacientes p ON a.paciente_id = p.paciente_id
     WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NOT NULL
     ORDER BY a.eliminado_en DESC"
);
if ($sp2) {
    $sp2->bind_param("ii", $pacIdInt, $usuarioId);
    @$sp2->execute();
    $r = $sp2->get_result();
    while ($f = $r->fetch_assoc()) {
        $f['dias_restantes'] = (int)$f['dias_restantes'];
        $papelera[] = $f;
    }
    $sp2->close();
}

$conexion->close();

/* ----- Storage stats: suma del filesize() de archivos en disco ----- */
function tamanoBytesArchivo($urlRelativa) {
    $ruta = rutaFisicaUploadSegura($urlRelativa, __DIR__);
    return $ruta && is_file($ruta) ? (int)@filesize($ruta) : 0;
}
$totalActivos  = 0;
$totalPapelera = 0;
foreach ($archivos as $a) $totalActivos  += tamanoBytesArchivo($a['archivo_url'] ?? '');
foreach ($papelera as $a) $totalPapelera += tamanoBytesArchivo($a['archivo_url'] ?? '');

echo json_encode([
    "ok"           => true,
    "carpetas"     => $carpetas,
    "archivos"     => $archivos,
    "papelera"     => $papelera,
    "storage_stats"=> [
        "activos_bytes"  => $totalActivos,
        "activos_count"  => count($archivos),
        "papelera_bytes" => $totalPapelera,
        "papelera_count" => count($papelera)
    ],
    "auto_purga"   => [
        "ttl_dias"   => 30,
        "purgados"   => $purgados
    ]
]);

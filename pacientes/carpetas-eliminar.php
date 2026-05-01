<?php
/*
 * CARPETAS · eliminar carpeta
 * --------------------------------
 * POST carpeta_id, modo (papelera|forzar)
 *   - papelera (default): manda los archivos hijos a la papelera (soft) y borra carpeta
 *   - forzar: rechaza si hay archivos NO en papelera (más seguro)
 *
 * Cascadea a subcarpetas (también las elimina).
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$carpeta_id = $_POST['carpeta_id'] ?? '';
$modo       = $_POST['modo'] ?? 'papelera';

if (!is_numeric($carpeta_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Carpeta no válida."]); exit; }
$idInt = (int)$carpeta_id;

if (!in_array($modo, ['papelera','forzar'], true)) {
    echo json_encode(["ok"=>false,"mensaje"=>"Modo no válido."]); exit;
}

$user = $_SESSION['usuario_email'] ?? 'desconocido';

$conexion->begin_transaction();
try {
    /* 1) Leer carpeta (filtrada por usuario_id del paciente para evitar IDOR) */
    $sa = @$conexion->prepare(
        "SELECT c.paciente_id, c.ruta_carpeta FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.carpeta_id = ? AND p.usuario_id = ? FOR UPDATE"
    );
    $sa->bind_param("ii", $idInt, $usuarioId);
    @$sa->execute();
    $cp = $sa->get_result()->fetch_assoc();
    $sa->close();
    if (!$cp) throw new Exception("Carpeta no encontrada.");

    $pacIdInt = (int)$cp['paciente_id'];
    $ruta     = $cp['ruta_carpeta'];
    $patron   = $ruta . '/%';

    /* 2) En modo 'forzar', rechazar si hay archivos no eliminados */
    if ($modo === 'forzar') {
        $sc = @$conexion->prepare(
            "SELECT COUNT(*) AS n FROM archivos_paciente a
             JOIN pacientes p ON a.paciente_id = p.paciente_id
             WHERE a.paciente_id = ? AND p.usuario_id = ?
               AND (a.ruta_carpeta = ? OR a.ruta_carpeta LIKE ?)
               AND a.eliminado_en IS NULL"
        );
        $sc->bind_param("iiss", $pacIdInt, $usuarioId, $ruta, $patron);
        @$sc->execute();
        $n = (int)$sc->get_result()->fetch_assoc()['n'];
        $sc->close();
        if ($n > 0) {
            throw new Exception("La carpeta tiene $n archivo(s). Bórralos primero o usa 'enviar a papelera'.");
        }
    }

    /* 3) Soft-delete archivos en esta carpeta y subcarpetas (filtrado por usuario via JOIN) */
    $s2 = @$conexion->prepare(
        "UPDATE archivos_paciente a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.eliminado_en = NOW(), a.eliminado_por = ?
         WHERE a.paciente_id = ? AND p.usuario_id = ?
           AND (a.ruta_carpeta = ? OR a.ruta_carpeta LIKE ?)
           AND a.eliminado_en IS NULL"
    );
    $s2->bind_param("siiss", $user, $pacIdInt, $usuarioId, $ruta, $patron);
    if (!@$s2->execute()) throw new Exception(mensajeErrorMysql($s2->errno, $s2->error));
    $archivosMovidos = $s2->affected_rows;
    $s2->close();

    /* 4) DELETE carpetas hijas + esta (filtrado por usuario via JOIN) */
    $s3 = @$conexion->prepare(
        "DELETE c FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND p.usuario_id = ?
           AND (c.ruta_carpeta = ? OR c.ruta_carpeta LIKE ?)"
    );
    $s3->bind_param("iiss", $pacIdInt, $usuarioId, $ruta, $patron);
    if (!@$s3->execute()) throw new Exception(mensajeErrorMysql($s3->errno, $s3->error));
    $carpetasBorradas = $s3->affected_rows;
    $s3->close();

    $conexion->commit();
    echo json_encode([
        "ok"      => true,
        "mensaje" => "Carpeta eliminada ($carpetasBorradas carpeta[s], $archivosMovidos archivo[s] enviado[s] a papelera)."
    ]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}

$conexion->close();

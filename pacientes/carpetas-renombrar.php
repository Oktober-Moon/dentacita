<?php
/*
 * CARPETAS · renombrar carpeta
 * --------------------------------
 * Renombrar el último segmento del path. Cascadea:
 *   1. UPDATE en la propia carpeta (ruta_carpeta y nombre)
 *   2. UPDATE en carpetas hijas: REPLACE prefijo de ruta_carpeta
 *   3. UPDATE en archivos del paciente con esa ruta_carpeta o subruta
 *
 * Todo en una transacción atómica.
 *
 * POST carpeta_id, nombre_nuevo
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
require __DIR__ . '/_validaciones.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$carpeta_id   = $_POST['carpeta_id']   ?? '';
$nombre_nuevo = trim($_POST['nombre_nuevo'] ?? '');

if (!is_numeric($carpeta_id)) { echo json_encode(["ok"=>false,"mensaje"=>"Carpeta no válida."]); exit; }
$idInt = (int)$carpeta_id;

$err = validarNombreCarpeta($nombre_nuevo);
if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

$conexion->begin_transaction();
try {
    /* 1) Leer carpeta original (filtrada por usuario_id del paciente para evitar IDOR) */
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

    $pacIdInt   = (int)$cp['paciente_id'];
    $rutaVieja  = $cp['ruta_carpeta'];

    // Calcular ruta nueva: reemplaza solo el último segmento
    $partes = explode('/', $rutaVieja);
    array_pop($partes);
    $partes[] = $nombre_nuevo;
    $rutaNueva = implode('/', $partes);

    if ($rutaVieja === $rutaNueva) {
        $conexion->rollback();
        echo json_encode(["ok"=>true,"mensaje"=>"Sin cambios."]);
        $conexion->close(); exit;
    }

    /* Verificar conflicto (filtrado por usuario via JOIN) */
    $sk = @$conexion->prepare(
        "SELECT 1 FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND c.ruta_carpeta = ? AND p.usuario_id = ?"
    );
    $sk->bind_param("isi", $pacIdInt, $rutaNueva, $usuarioId);
    @$sk->execute();
    if ($sk->get_result()->fetch_assoc()) {
        $sk->close();
        throw new Exception("Ya existe una carpeta con esa ruta.");
    }
    $sk->close();

    /* 2) UPDATE carpeta principal (filtrado por usuario via JOIN) */
    $s1 = @$conexion->prepare(
        "UPDATE carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         SET c.ruta_carpeta = ?, c.nombre = ?
         WHERE c.carpeta_id = ? AND p.usuario_id = ?"
    );
    $s1->bind_param("ssii", $rutaNueva, $nombre_nuevo, $idInt, $usuarioId);
    if (!@$s1->execute()) throw new Exception(mensajeErrorMysql($s1->errno, $s1->error));
    $s1->close();

    /* 3) UPDATE en carpetas hijas: prefijo (filtrado por usuario via JOIN) */
    $patron = $rutaVieja . '/%';
    $longVieja = mb_strlen($rutaVieja);
    $s2 = @$conexion->prepare(
        "UPDATE carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         SET c.ruta_carpeta = CONCAT(?, SUBSTRING(c.ruta_carpeta, ?))
         WHERE c.paciente_id = ? AND p.usuario_id = ? AND c.ruta_carpeta LIKE ?"
    );
    $startAt = $longVieja + 1;
    $s2->bind_param("siiis", $rutaNueva, $startAt, $pacIdInt, $usuarioId, $patron);
    if (!@$s2->execute()) throw new Exception(mensajeErrorMysql($s2->errno, $s2->error));
    $hijasCambiadas = $s2->affected_rows;
    $s2->close();

    /* 4) UPDATE en archivos: directos en esta carpeta (filtrado por usuario via JOIN) */
    $s3 = @$conexion->prepare(
        "UPDATE archivos_paciente a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.ruta_carpeta = ?
         WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.ruta_carpeta = ?"
    );
    $s3->bind_param("siis", $rutaNueva, $pacIdInt, $usuarioId, $rutaVieja);
    if (!@$s3->execute()) throw new Exception(mensajeErrorMysql($s3->errno, $s3->error));
    $arDir = $s3->affected_rows;
    $s3->close();

    /* 5) UPDATE en archivos: en subrutas (filtrado por usuario via JOIN) */
    $s4 = @$conexion->prepare(
        "UPDATE archivos_paciente a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         SET a.ruta_carpeta = CONCAT(?, SUBSTRING(a.ruta_carpeta, ?))
         WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.ruta_carpeta LIKE ?"
    );
    $s4->bind_param("siiis", $rutaNueva, $startAt, $pacIdInt, $usuarioId, $patron);
    if (!@$s4->execute()) throw new Exception(mensajeErrorMysql($s4->errno, $s4->error));
    $arSub = $s4->affected_rows;
    $s4->close();

    $conexion->commit();
    echo json_encode([
        "ok"      => true,
        "mensaje" => "Carpeta renombrada. " . ($hijasCambiadas + $arDir + $arSub) . " elemento(s) actualizado(s).",
        "ruta_nueva" => $rutaNueva
    ]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["ok"=>false,"mensaje"=>$e->getMessage()]);
}

$conexion->close();

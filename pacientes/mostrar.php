<?php
/*
 * MOSTRAR PACIENTES · endpoint AJAX (JSON)
 * ------------------------------------------
 *   GET ?id=N              → un paciente con datos extendidos
 *                            (incluye conteos: citas, planes, mensajes)
 *   GET ?q=texto&pag=1     → lista paginada con filtro
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();


/* ----- Modo "detalle" ----- */
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if (!is_numeric($id)) {
        echo json_encode(["ok"=>false,"mensaje"=>"ID no válido."]);
        exit;
    }
    $idInt = (int)$id;

    $stmt = @$conexion->prepare(
        "SELECT paciente_id, nombre_completo, telefono, email, fecha_nacimiento,
                genero, direccion, ocupacion, alergias, padecimientos, medicamentos,
                foto_url, notas_dentista AS notas, creado_en, actualizado_en
         FROM pacientes WHERE paciente_id = ? AND usuario_id = ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("ii", $idInt, $usuarioId);
    @$stmt->execute();
    $paciente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$paciente) {
        echo json_encode(["ok"=>false,"mensaje"=>"Paciente no encontrado."]);
        $conexion->close(); exit;
    }

    // Contadores relacionados v5
    $extra = [];
    $sqlsContadores = [
        'citas'    => "SELECT COUNT(*) FROM citas              WHERE paciente_id = ? AND usuario_id = ? AND estado NOT IN ('cancelada','no_asistio')",
        'archivos' => "SELECT COUNT(*) FROM archivos_paciente a
                       JOIN pacientes p ON a.paciente_id = p.paciente_id
                       WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL",
        'notas'    => "SELECT COUNT(*) FROM notas_paciente n
                       JOIN pacientes p ON n.paciente_id = p.paciente_id
                       WHERE n.paciente_id = ? AND p.usuario_id = ?"
    ];
    foreach ($sqlsContadores as $clave => $sql) {
        if ($s = @$conexion->prepare($sql)) {
            $s->bind_param("ii", $idInt, $usuarioId);
            @$s->execute();
            $r = $s->get_result()->fetch_array(MYSQLI_NUM);
            $extra[$clave] = $r ? (int)$r[0] : 0;
            $s->close();
        } else {
            $extra[$clave] = 0;
        }
    }
    $paciente['contadores'] = $extra;

    $conexion->close();
    echo json_encode(["ok"=>true,"paciente"=>$paciente]);
    exit;
}


/* ----- Modo "lista" ----- */
$q   = trim($_GET['q']   ?? '');
$pag = isset($_GET['pag']) && is_numeric($_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;
$porPagina = 20;
$offset = ($pag - 1) * $porPagina;

if (mb_strlen($q) > 100) {
    echo json_encode(["ok"=>false,"mensaje"=>"Búsqueda demasiado larga."]);
    exit;
}

$colsBase = "p.paciente_id, p.nombre_completo, p.telefono, p.email, p.fecha_nacimiento,
             p.genero, p.foto_url, p.creado_en,
             (SELECT COUNT(*) FROM citas c WHERE c.paciente_id = p.paciente_id AND c.usuario_id = p.usuario_id AND c.estado NOT IN ('cancelada','no_asistio')) AS total_citas,
             (SELECT MAX(c.fecha_hora_inicio) FROM citas c WHERE c.paciente_id = p.paciente_id AND c.usuario_id = p.usuario_id AND c.estado NOT IN ('cancelada','no_asistio')) AS ultima_cita";

if ($q === '') {
    // Listado completo paginado
    $stmtTotal = @$conexion->prepare("SELECT COUNT(*) FROM pacientes WHERE usuario_id = ?");
    $stmtTotal->bind_param("i", $usuarioId);
    @$stmtTotal->execute();
    $total = (int)$stmtTotal->get_result()->fetch_array(MYSQLI_NUM)[0];
    $stmtTotal->close();

    $stmt = @$conexion->prepare(
        "SELECT $colsBase
         FROM pacientes p
         WHERE p.usuario_id = ?
         ORDER BY p.nombre_completo ASC
         LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("iii", $usuarioId, $porPagina, $offset);
} else {
    // Listado filtrado (por nombre, telefono o email)
    $like = '%' . $q . '%';
    $stmtTotal = @$conexion->prepare(
        "SELECT COUNT(*) FROM pacientes
         WHERE usuario_id = ?
           AND (nombre_completo LIKE ? OR telefono LIKE ? OR email LIKE ?)"
    );
    $stmtTotal->bind_param("isss", $usuarioId, $like, $like, $like);
    @$stmtTotal->execute();
    $total = (int)$stmtTotal->get_result()->fetch_array(MYSQLI_NUM)[0];
    $stmtTotal->close();

    $stmt = @$conexion->prepare(
        "SELECT $colsBase
         FROM pacientes p
         WHERE p.usuario_id = ?
           AND (p.nombre_completo LIKE ? OR p.telefono LIKE ? OR p.email LIKE ?)
         ORDER BY p.nombre_completo ASC
         LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("isssii", $usuarioId, $like, $like, $like, $porPagina, $offset);
}

@$stmt->execute();
$res = $stmt->get_result();
$pacientes = [];
while ($f = $res->fetch_assoc()) $pacientes[] = $f;

$stmt->close();
$conexion->close();

echo json_encode([
    "ok"           => true,
    "pacientes"    => $pacientes,
    "total"        => $total,
    "pagina"       => $pag,
    "por_pagina"   => $porPagina,
    "total_paginas"=> (int)ceil($total / $porPagina)
]);

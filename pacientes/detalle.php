<?php
/*
 * PACIENTES · ficha de detalle con 5 pestañas
 * --------------------------------------------
 * Replica funcional de ClientHistory.tsx del original.
 *
 * Pestañas (parciales en _detalle/):
 *   1. Datos      — datos personales y clínicos
 *   2. Citas      — historial de citas con esta persona
 *   3. Acuerdos   — acuerdos de servicio (≈ planes de tratamiento)
 *   4. Archivos   — archivos del paciente organizados en carpetas
 *   5. Notas      — bitácora libre del dentista
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');

$usuarioId = getUsuarioId();

$id = $_GET['id'] ?? '';
if (!is_numeric($id)) { $conexion->close(); header('Location: ./'); exit; }
$idInt = (int)$id;

// Pestaña activa por query string (default: datos)
$tabValidas = ['datos','citas','acuerdos','archivos','notas'];
$tab = $_GET['tab'] ?? 'datos';
if (!in_array($tab, $tabValidas, true)) $tab = 'datos';

/* ----- Datos del paciente ----- */
$stmt = @$conexion->prepare(
    "SELECT paciente_id, nombre_completo, telefono, email, fecha_nacimiento,
            genero, direccion, ocupacion, alergias, padecimientos, medicamentos,
            foto_url, creado_en, actualizado_en
     FROM pacientes WHERE paciente_id = ? AND usuario_id = ?"
);
$stmt->bind_param("ii", $idInt, $usuarioId);
@$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$paciente) { $conexion->close(); header('Location: ./'); exit; }

/* ----- Datos por pestaña ----- */
$citas = $acuerdos = $archivosPorCarpeta = $archivosSinCarpeta = $notas = [];
$carpetas = [];

if ($tab === 'citas' || $tab === 'datos') {
    if ($s = @$conexion->prepare(
        "SELECT cita_id, titulo, fecha_hora_inicio, fecha_hora_fin, estado, precio
         FROM citas WHERE paciente_id = ? AND usuario_id = ?
         ORDER BY fecha_hora_inicio DESC LIMIT 50"
    )) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) $citas[] = $f;
        $s->close();
    }
}

if ($tab === 'acuerdos') {
    if ($s = @$conexion->prepare(
        "SELECT a.acuerdo_id, a.servicio, a.descripcion, a.fecha_programada, a.duracion_minutos,
                a.precio, a.estado, a.cita_id, a.creado_en
         FROM acuerdos_servicio a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.paciente_id = ? AND p.usuario_id = ?
         ORDER BY a.creado_en DESC"
    )) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) $acuerdos[] = $f;
        $s->close();
    }
}

if ($tab === 'archivos') {
    if ($s = @$conexion->prepare(
        "SELECT c.carpeta_id, c.ruta_carpeta, c.nombre
         FROM carpetas_paciente c
         JOIN pacientes p ON c.paciente_id = p.paciente_id
         WHERE c.paciente_id = ? AND p.usuario_id = ?
         ORDER BY c.ruta_carpeta ASC"
    )) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) {
            $rk = $f['ruta_carpeta'];
            $carpetas[$rk] = $f;
            $archivosPorCarpeta[$rk] = [];
        }
        $s->close();
    }
    if ($s = @$conexion->prepare(
        "SELECT a.archivo_id, a.ruta_carpeta, a.nombre_archivo, a.descripcion, a.tipo, a.archivo_url, a.fecha
         FROM archivos_paciente a
         JOIN pacientes p ON a.paciente_id = p.paciente_id
         WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL
         ORDER BY a.fecha DESC, a.archivo_id DESC"
    )) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) {
            $rk = $f['ruta_carpeta'] ?? '';
            if ($rk === '' || $rk === null) {
                $archivosSinCarpeta[] = $f;
            } else {
                if (!isset($archivosPorCarpeta[$rk])) $archivosPorCarpeta[$rk] = [];
                $archivosPorCarpeta[$rk][] = $f;
            }
        }
        $s->close();
    }
}

if ($tab === 'notas') {
    if ($s = @$conexion->prepare(
        "SELECT n.nota_id, n.contenido, n.fecha, n.creado_en, n.actualizado_en
         FROM notas_paciente n
         JOIN pacientes p ON n.paciente_id = p.paciente_id
         WHERE n.paciente_id = ? AND p.usuario_id = ?
         ORDER BY n.fecha DESC, n.nota_id DESC"
    )) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) $notas[] = $f;
        $s->close();
    }
}

/* ----- Conteos para badges en las pestañas ----- */
$cnt = [];
$sqlsCnt = [
    'citas'    => "SELECT COUNT(*) FROM citas
                   WHERE paciente_id = ? AND usuario_id = ?",
    'acuerdos' => "SELECT COUNT(*) FROM acuerdos_servicio a
                   JOIN pacientes p ON a.paciente_id = p.paciente_id
                   WHERE a.paciente_id = ? AND p.usuario_id = ?",
    'archivos' => "SELECT COUNT(*) FROM archivos_paciente a
                   JOIN pacientes p ON a.paciente_id = p.paciente_id
                   WHERE a.paciente_id = ? AND p.usuario_id = ? AND a.eliminado_en IS NULL",
    'notas'    => "SELECT COUNT(*) FROM notas_paciente n
                   JOIN pacientes p ON n.paciente_id = p.paciente_id
                   WHERE n.paciente_id = ? AND p.usuario_id = ?"
];
foreach ($sqlsCnt as $k => $sql) {
    if ($s = @$conexion->prepare($sql)) {
        $s->bind_param("ii", $idInt, $usuarioId); @$s->execute();
        $cnt[$k] = (int)$s->get_result()->fetch_array(MYSQLI_NUM)[0];
        $s->close();
    } else { $cnt[$k] = 0; }
}

$conexion->close();

/* ----- Helpers ----- */
function fmtFecha($iso) {
    if (!$iso) return '—';
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : $iso;
}
function fmtFechaHora($iso) {
    if (!$iso) return '—';
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y H:i', $ts) : $iso;
}
function fmtDinero($n) {
    return $n === null ? '—' : '$' . number_format((float)$n, 2, '.', ',');
}
function calcularEdad($fechaNac) {
    if (!$fechaNac) return null;
    $ts = strtotime($fechaNac);
    if ($ts === false) return null;
    $edad = date('Y') - date('Y', $ts);
    if (date('md') < date('md', $ts)) $edad--;
    return $edad >= 0 && $edad < 150 ? $edad : null;
}
function generoBonito($g) {
    return ['femenino'=>'Femenino','masculino'=>'Masculino',
            'otro'=>'Otro','no_especificado'=>'No especificado'][$g] ?? $g;
}
$edad = calcularEdad($paciente['fecha_nacimiento']);

$nav_actual   = 'pacientes';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · <?php echo htmlspecialchars($paciente['nombre_completo']); ?></title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>

<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div class="paciente-cabecera">
                <a href="./" class="link-volver">‹ Volver a pacientes</a>
                <div class="paciente-cabecera-fila">
                    <?php if (!empty($paciente['foto_url'])): ?>
                        <img class="paciente-avatar-grande" src="<?php echo htmlspecialchars($paciente['foto_url']); ?>" alt="" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="paciente-avatar-grande paciente-avatar-placeholder">
                            <?php echo htmlspecialchars(mb_strtoupper(mb_substr($paciente['nombre_completo'], 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                    <div class="paciente-cabecera-info">
                        <h1 class="page-titulo"><?php echo htmlspecialchars($paciente['nombre_completo']); ?></h1>
                        <div class="page-subtitulo">
                            <?php
                            $partes = [];
                            if ($edad !== null) $partes[] = $edad . ' años';
                            if ($paciente['genero'] && $paciente['genero'] !== 'no_especificado') $partes[] = generoBonito($paciente['genero']);
                            if ($paciente['ocupacion']) $partes[] = htmlspecialchars($paciente['ocupacion']);
                            echo $partes ? implode(' · ', $partes) : 'Sin datos demográficos.';
                            ?>
                        </div>
                        <div class="paciente-cabecera-meta">
                            <strong><?php echo $cnt['citas']; ?></strong> cita<?php echo $cnt['citas'] === 1 ? '' : 's'; ?>
                            <?php if (!empty($citas)):
                                $ultima = $citas[0]['fecha_hora_inicio'];
                                $primera = end($citas)['fecha_hora_inicio'];
                            ?>
                                <span class="texto-atenuado"> · última: <?php echo fmtFecha($ultima); ?></span>
                                <?php if ($primera !== $ultima): ?>
                                    <span class="texto-atenuado"> · primera: <?php echo fmtFecha($primera); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Pestañas ===== -->
        <div class="tabs-pacientes">
            <a href="?id=<?php echo $idInt; ?>&tab=datos"     class="tab-link <?php echo $tab==='datos' ? 'activo':''; ?>">Datos</a>
            <a href="?id=<?php echo $idInt; ?>&tab=citas"     class="tab-link <?php echo $tab==='citas' ? 'activo':''; ?>">
                Citas <span class="tab-badge"><?php echo $cnt['citas']; ?></span>
            </a>
            <a href="?id=<?php echo $idInt; ?>&tab=acuerdos"  class="tab-link <?php echo $tab==='acuerdos' ? 'activo':''; ?>">
                Acuerdos <span class="tab-badge"><?php echo $cnt['acuerdos']; ?></span>
            </a>
            <a href="?id=<?php echo $idInt; ?>&tab=archivos"  class="tab-link <?php echo $tab==='archivos' ? 'activo':''; ?>">
                Archivos <span class="tab-badge"><?php echo $cnt['archivos']; ?></span>
            </a>
            <a href="?id=<?php echo $idInt; ?>&tab=notas"     class="tab-link <?php echo $tab==='notas' ? 'activo':''; ?>">
                Notas <span class="tab-badge"><?php echo $cnt['notas']; ?></span>
            </a>
        </div>

        <!-- ===== Contenido de la pestaña activa ===== -->
        <?php require __DIR__ . '/_detalle/' . $tab . '.php'; ?>

    </main>

</div>

</body>
</html>

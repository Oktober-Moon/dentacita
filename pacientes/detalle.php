<?php
/*
 * PACIENTES · ficha de detalle con 5 pestañas
 * --------------------------------------------
 * Replica funcional de ClientHistory.tsx del original.
 *
 * Pestañas:
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
    // Carpetas (modelo path-based v4)
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
    // Archivos NO eliminados (papelera aparte, en modal)
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
function iconoArchivo($tipo) {
    $iconos = [
        'imagen'      => '🖼',
        'pdf'         => '📕',
        'radiografia' => '🦷',
        'documento'   => '📄',
        'laboratorio' => '🧪',
        'otro'        => '📎',
    ];
    return $iconos[$tipo] ?? '📎';
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

        <!-- ===== Contenido de la pestaña ===== -->

        <?php if ($tab === 'datos'): ?>
        <div class="ficha-grid">
            <div class="ficha-col">
                <div class="ficha-card">
                    <div class="ficha-card-titulo">Datos personales</div>
                    <dl class="ficha-dl">
                        <dt>Nombre</dt> <dd><?php echo htmlspecialchars($paciente['nombre_completo']); ?></dd>
                        <dt>Nacimiento</dt> <dd><?php echo fmtFecha($paciente['fecha_nacimiento']); ?>
                            <?php if ($edad !== null): ?> · <?php echo $edad; ?> años<?php endif; ?>
                        </dd>
                        <dt>Género</dt> <dd><?php echo htmlspecialchars(generoBonito($paciente['genero'])); ?></dd>
                        <dt>Ocupación</dt> <dd><?php echo $paciente['ocupacion'] ? htmlspecialchars($paciente['ocupacion']) : '—'; ?></dd>
                    </dl>
                </div>

                <div class="ficha-card">
                    <div class="ficha-card-titulo">Contacto</div>
                    <dl class="ficha-dl">
                        <dt>Teléfono</dt><dd><?php echo $paciente['telefono'] ? htmlspecialchars($paciente['telefono']) : '—'; ?></dd>
                        <dt>Email</dt>   <dd><?php echo $paciente['email']    ? htmlspecialchars($paciente['email'])    : '—'; ?></dd>
                        <dt>Dirección</dt><dd><?php echo $paciente['direccion'] ? htmlspecialchars($paciente['direccion']) : '—'; ?></dd>
                    </dl>
                </div>
            </div>

            <div class="ficha-col">
                <div class="ficha-card">
                    <div class="ficha-card-titulo">Datos clínicos</div>
                    <div class="ficha-bloque-clinico">
                        <div class="ficha-bloque-titulo">Alergias</div>
                        <div class="ficha-bloque-contenido">
                            <?php echo $paciente['alergias']
                                ? nl2br(htmlspecialchars($paciente['alergias']))
                                : '<span class="texto-atenuado">Sin alergias registradas.</span>'; ?>
                        </div>
                    </div>
                    <div class="ficha-bloque-clinico">
                        <div class="ficha-bloque-titulo">Padecimientos</div>
                        <div class="ficha-bloque-contenido">
                            <?php echo $paciente['padecimientos']
                                ? nl2br(htmlspecialchars($paciente['padecimientos']))
                                : '<span class="texto-atenuado">Sin padecimientos registrados.</span>'; ?>
                        </div>
                    </div>
                    <div class="ficha-bloque-clinico">
                        <div class="ficha-bloque-titulo">Medicamentos</div>
                        <div class="ficha-bloque-contenido">
                            <?php echo $paciente['medicamentos']
                                ? nl2br(htmlspecialchars($paciente['medicamentos']))
                                : '<span class="texto-atenuado">Sin medicamentos registrados.</span>'; ?>
                        </div>
                    </div>
                </div>

                <div class="ficha-card">
                    <div class="ficha-card-titulo">Resumen</div>
                    <ul class="ficha-lista">
                        <li><strong><?php echo $cnt['citas']; ?></strong> cita(s) registradas</li>
                        <li><strong><?php echo $cnt['acuerdos']; ?></strong> acuerdo(s) de servicio</li>
                        <li><strong><?php echo $cnt['archivos']; ?></strong> archivo(s)</li>
                        <li><strong><?php echo $cnt['notas']; ?></strong> nota(s) clínicas</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="ficha-meta">
            Registrado el <?php echo fmtFecha($paciente['creado_en']); ?> ·
            Última actualización: <?php echo fmtFecha($paciente['actualizado_en']); ?>
        </div>
        <?php endif; ?>


        <?php if ($tab === 'citas'): ?>
        <div class="ficha-card">
            <div class="ficha-card-titulo">Historial de citas</div>
            <?php if (empty($citas)): ?>
                <div class="texto-atenuado">No hay citas registradas con este paciente.</div>
            <?php else: ?>
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Motivo</th><th>Estado</th><th style="text-align:right">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $c): ?>
                        <tr>
                            <td><?php echo fmtFechaHora($c['fecha_hora_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>"><?php echo htmlspecialchars($c['estado']); ?></span></td>
                            <td style="text-align:right" class="mono"><?php echo fmtDinero($c['precio']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="ficha-card-pie">
                <a href="../agenda/" class="btn btn-secundario btn-sm">Ir a la agenda completa</a>
            </div>
        </div>
        <?php endif; ?>


        <?php if ($tab === 'acuerdos'): ?>
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Acuerdos de servicio
                <button type="button" class="btn btn-primario btn-sm" id="btnNuevoAcuerdo" style="margin-left:auto">+ Nuevo acuerdo</button>
            </div>
            <?php if (empty($acuerdos)): ?>
                <div class="placeholder-card-inline">
                    <p>Aún no hay acuerdos de servicio con este paciente.</p>
                    <p class="texto-atenuado">Los acuerdos representan servicios propuestos al paciente. Cuando se aceptan, generan automáticamente una cita en la agenda.</p>
                </div>
            <?php else: ?>
                <ul class="lista-acuerdos">
                    <?php foreach ($acuerdos as $a): ?>
                    <li class="acuerdo-card">
                        <div class="acuerdo-header">
                            <h3><?php echo htmlspecialchars($a['servicio']); ?></h3>
                            <span class="badge badge-acuerdo-<?php echo htmlspecialchars($a['estado']); ?>">
                                <?php echo htmlspecialchars($a['estado']); ?>
                            </span>
                        </div>
                        <?php if ($a['descripcion']): ?>
                            <p><?php echo nl2br(htmlspecialchars($a['descripcion'])); ?></p>
                        <?php endif; ?>
                        <div class="acuerdo-detalles">
                            <div><span class="texto-atenuado">Fecha programada:</span> <?php echo fmtFechaHora($a['fecha_programada']); ?></div>
                            <div><span class="texto-atenuado">Duración:</span> <?php echo (int)$a['duracion_minutos']; ?> min</div>
                            <div><span class="texto-atenuado">Precio:</span> <?php echo fmtDinero($a['precio']); ?></div>
                            <?php if ($a['cita_id']): ?>
                                <div><span class="texto-atenuado">Cita generada:</span>
                                    <a href="../agenda/?cita=<?php echo (int)$a['cita_id']; ?>">#<?php echo (int)$a['cita_id']; ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="acuerdo-acciones">
                            <?php if ($a['estado'] === 'pendiente'): ?>
                                <button type="button" class="btn btn-primario btn-sm" data-ac-accion="aceptar"  data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Aceptar</button>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="editar"   data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Editar</button>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="rechazar" data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Rechazar</button>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="cancelar" data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Cancelar</button>
                            <?php elseif ($a['estado'] === 'aceptado'): ?>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="editar"     data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Editar</button>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="completar"  data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Marcar completado</button>
                                <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="cancelar"   data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Cancelar</button>
                            <?php endif; ?>
                            <?php if ($a['estado'] === 'aceptado' && $a['cita_id']): ?>
                                <a href="../agenda/?cita=<?php echo (int)$a['cita_id']; ?>" class="btn-link">Ver cita</a>
                            <?php endif; ?>
                        </div>
                        <div class="texto-atenuado texto-pequeno">Creado: <?php echo fmtFechaHora($a['creado_en']); ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>


        <!-- Modal nuevo / editar acuerdo -->
        <div id="modalAcuerdoPaciente" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-acuerdo aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo" id="acuerdoModalTitulo">Nuevo acuerdo</h2>
                <form id="formAcuerdoPaciente" autocomplete="off">
                    <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
                    <input type="hidden" name="acuerdo_id" id="acuerdoModalId" value="">

                    <div class="campo">
                        <label for="acp_servicio">Servicio *</label>
                        <input type="text" name="servicio" id="acp_servicio" maxlength="150" required
                               placeholder="Ej. Limpieza dental">
                    </div>

                    <div class="campo">
                        <label for="acp_descripcion">Descripción</label>
                        <textarea name="descripcion" id="acp_descripcion" rows="2" maxlength="500"></textarea>
                    </div>

                    <div class="campo-grid-2">
                        <div class="campo">
                            <label for="acp_fecha">Fecha y hora *</label>
                            <input type="datetime-local" name="fecha_programada" id="acp_fecha" required>
                        </div>
                        <div class="campo">
                            <label for="acp_duracion">Duración (min) *</label>
                            <input type="number" name="duracion_minutos" id="acp_duracion"
                                   min="5" max="600" step="5" value="60" required>
                        </div>
                    </div>

                    <div class="campo">
                        <label for="acp_precio">Precio *</label>
                        <input type="number" name="precio" id="acp_precio" min="0" step="0.01" required>
                    </div>

                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-acuerdo>Cancelar</button>
                        <button type="submit" class="btn btn-primario">Crear acuerdo</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Acciones de acuerdos en pestaña paciente
        (function() {
            const modal = document.getElementById('modalAcuerdoPaciente');
            const btnNuevo = document.getElementById('btnNuevoAcuerdo');
            const form = document.getElementById('formAcuerdoPaciente');
            const toastEl = document.getElementById('toast');
            function toast(m, t='ok') {
                if (!toastEl) { alert(m); return; }
                toastEl.textContent = m;
                toastEl.className = 'toast ' + (t==='error' ? 'toast-error' : 'toast-ok') + ' visible';
                setTimeout(() => toastEl.classList.remove('visible'), 2800);
            }
            const tituloModal = document.getElementById('acuerdoModalTitulo');
            const inputAcuerdoId = document.getElementById('acuerdoModalId');

            if (btnNuevo) {
                btnNuevo.addEventListener('click', () => {
                    form.reset();
                    inputAcuerdoId.value = '';
                    tituloModal.textContent = 'Nuevo acuerdo';
                    const d = new Date();
                    d.setDate(d.getDate() + 7); d.setHours(10, 0, 0, 0);
                    document.getElementById('acp_fecha').value = d.toISOString().slice(0, 16);
                    modal.classList.add('visible');
                });
            }

            async function abrirEditarAcuerdo(acuerdoId) {
                form.reset();
                tituloModal.textContent = 'Editar acuerdo';
                inputAcuerdoId.value = acuerdoId;
                modal.classList.add('visible');
                try {
                    const r = await fetch('acuerdo-actualizar.php?acuerdo_id=' + encodeURIComponent(acuerdoId));
                    const json = await r.json();
                    if (json.redirect) { window.location.href = '../index.php'; return; }
                    if (!json.ok) { toast(json.mensaje, 'error'); modal.classList.remove('visible'); return; }
                    const a = json.acuerdo;
                    document.getElementById('acp_servicio').value    = a.servicio || '';
                    document.getElementById('acp_descripcion').value = a.descripcion || '';
                    // datetime-local quiere "YYYY-MM-DDTHH:MM"
                    let fp = (a.fecha_programada || '').replace(' ', 'T').slice(0, 16);
                    document.getElementById('acp_fecha').value       = fp;
                    document.getElementById('acp_duracion').value    = a.duracion_minutos || 60;
                    document.getElementById('acp_precio').value      = a.precio || '';
                } catch (e) {
                    toast('Error de conexión.', 'error');
                    modal.classList.remove('visible');
                }
            }
            document.querySelectorAll('[data-cerrar-acuerdo]').forEach(b =>
                b.addEventListener('click', () => modal.classList.remove('visible'))
            );
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('visible');
            });

            if (form) form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const datos = new FormData(form);
                const fecha = datos.get('fecha_programada');
                if (fecha) datos.set('fecha_programada', fecha.replace('T', ' ') + ':00');
                const acuerdoId = inputAcuerdoId.value;
                const url = acuerdoId ? 'acuerdo-actualizar.php' : 'acuerdo-crear.php';
                try {
                    const resp = await fetch(url, { method: 'POST', body: datos });
                    const json = await resp.json();
                    if (json.redirect) { window.location.href = '../index.php'; return; }
                    if (!json.ok) { toast(json.mensaje, 'error'); return; }
                    toast(json.mensaje);
                    setTimeout(() => window.location.reload(), 800);
                } catch (e) { toast('No se pudo conectar.', 'error'); }
            });

            document.querySelectorAll('[data-ac-accion]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.acId;
                    const accion = btn.dataset.acAccion;
                    if (accion === 'editar') {
                        await abrirEditarAcuerdo(id);
                        return;
                    }
                    let url, datos = new FormData();
                    datos.append('acuerdo_id', id);
                    if (accion === 'aceptar') {
                        if (!confirm('¿Aceptar este acuerdo? Se creará la cita correspondiente.')) return;
                        url = 'acuerdo-aceptar.php';
                    } else {
                        const motivo = (accion !== 'completar') ? prompt('Motivo (opcional):', '') : '';
                        if (motivo === null) return;
                        const map = { rechazar:'rechazado', cancelar:'cancelado', completar:'completado' };
                        datos.append('nuevo_estado', map[accion]);
                        if (motivo) datos.append('motivo', motivo);
                        url = 'acuerdo-cambiar-estado.php';
                    }
                    try {
                        const resp = await fetch(url, { method: 'POST', body: datos });
                        const json = await resp.json();
                        if (json.redirect) { window.location.href = '../index.php'; return; }
                        if (!json.ok) { toast(json.mensaje, 'error'); return; }
                        toast(json.mensaje);
                        setTimeout(() => window.location.reload(), 800);
                    } catch (e) { toast('No se pudo conectar.', 'error'); }
                });
            });
        })();
        </script>
        <?php endif; ?>


        <?php if ($tab === 'archivos'): ?>
        <div class="ficha-card" id="archivosCard">
            <div class="ficha-card-titulo">
                Archivos del paciente
                <span id="archivosStorageStats" class="texto-pequeno texto-atenuado" style="margin-left:auto; margin-right: var(--espacio-md)"></span>
                <div class="filtros-inline">
                    <input type="search" id="archivosBusqueda" placeholder="Buscar…" class="campo-busqueda" style="max-width:180px">
                    <button type="button" class="btn btn-secundario btn-sm" id="btnNuevaCarpeta">+ Carpeta</button>
                    <button type="button" class="btn btn-primario btn-sm"   id="btnSubirArchivo">+ Subir archivo</button>
                    <button type="button" class="btn btn-secundario btn-sm" id="btnVerPapelera">Papelera</button>
                </div>
            </div>

            <!-- Container que JS llena vía cargarArchivos(). El render PHP de abajo
                 sigue funcional como fallback inicial pero se reemplaza al cargar. -->
            <div id="archivosLista">
                <!-- placeholder · JS lo llena -->
            </div>

            <!-- Drag-drop overlay (oculto por default; se activa al arrastrar) -->
            <div id="archivosDragOverlay" class="archivos-drag-overlay">
                <div class="archivos-drag-mensaje">
                    <div style="font-size: 48px">📁</div>
                    <div>Suelta los archivos aquí para subirlos</div>
                </div>
            </div>

            <!-- Modal preview · imagen / pdf -->
            <div id="modalPreviewArchivo" class="modal-fondo">
                <div class="modal-caja modal-caja-grande">
                    <button type="button" class="modal-cerrar" data-cerrar-modal="modalPreviewArchivo" aria-label="Cerrar">&times;</button>
                    <h2 class="modal-titulo" id="previewTitulo">Vista previa</h2>
                    <div id="previewContenido" style="margin-top: var(--espacio-md); text-align:center"></div>
                    <div class="modal-acciones">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-secundario" id="previewDescargar">Abrir en nueva pestaña</a>
                    </div>
                </div>
            </div>
        </div>


        <!-- Modal subir archivo -->
        <div id="modalSubir" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalSubir" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo">Subir archivo</h2>
                <form id="formSubir" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
                    <div class="campo">
                        <label for="sub_archivo">Archivo *</label>
                        <input type="file" name="archivo" id="sub_archivo" required>
                    </div>
                    <div class="campo">
                        <label for="sub_nombre">Nombre (opcional)</label>
                        <input type="text" name="nombre_archivo" id="sub_nombre" maxlength="150" placeholder="Si lo dejas vacío, se usa el original">
                    </div>
                    <div class="campo-grid-2">
                        <div class="campo">
                            <label for="sub_tipo">Tipo</label>
                            <select name="tipo" id="sub_tipo">
                                <option value="documento">Documento</option>
                                <option value="imagen">Imagen</option>
                                <option value="pdf">PDF</option>
                                <option value="radiografia">Radiografía</option>
                                <option value="laboratorio">Laboratorio</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="sub_fecha">Fecha</label>
                            <input type="date" name="fecha" id="sub_fecha" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="sub_carpeta">Carpeta destino</label>
                        <select name="ruta_carpeta" id="sub_carpeta">
                            <option value="">— Raíz —</option>
                            <?php foreach ($carpetas as $rk => $car): ?>
                                <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="sub_descripcion">Descripción</label>
                        <textarea name="descripcion" id="sub_descripcion" rows="2" maxlength="200"></textarea>
                    </div>
                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-modal="modalSubir">Cancelar</button>
                        <button type="submit" class="btn btn-primario">Subir</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Modal nueva carpeta -->
        <div id="modalCarpeta" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalCarpeta" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo">Nueva carpeta</h2>
                <form id="formCarpeta" autocomplete="off">
                    <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
                    <div class="campo">
                        <label for="car_padre">Carpeta padre</label>
                        <select name="ruta_padre" id="car_padre">
                            <option value="">— Raíz —</option>
                            <?php foreach ($carpetas as $rk => $car): ?>
                                <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="car_nombre">Nombre *</label>
                        <input type="text" name="nombre" id="car_nombre" maxlength="120" required placeholder="Ej. Ortodoncia, Radiografías…">
                    </div>
                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-modal="modalCarpeta">Cancelar</button>
                        <button type="submit" class="btn btn-primario">Crear</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Modal editar archivo -->
        <div id="modalEditarArch" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalEditarArch" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo">Editar archivo</h2>
                <form id="formEditarArch" autocomplete="off">
                    <input type="hidden" name="archivo_id" id="ed_arch_id">
                    <div class="campo">
                        <label for="ed_arch_nombre">Nombre *</label>
                        <input type="text" name="nombre_archivo" id="ed_arch_nombre" maxlength="150" required>
                    </div>
                    <div class="campo-grid-2">
                        <div class="campo">
                            <label for="ed_arch_tipo">Tipo</label>
                            <select name="tipo" id="ed_arch_tipo">
                                <option value="documento">Documento</option>
                                <option value="imagen">Imagen</option>
                                <option value="pdf">PDF</option>
                                <option value="radiografia">Radiografía</option>
                                <option value="laboratorio">Laboratorio</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="ed_arch_fecha">Fecha</label>
                            <input type="date" name="fecha" id="ed_arch_fecha">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="ed_arch_desc">Descripción</label>
                        <textarea name="descripcion" id="ed_arch_desc" rows="2" maxlength="200"></textarea>
                    </div>
                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-modal="modalEditarArch">Cancelar</button>
                        <button type="submit" class="btn btn-primario">Guardar</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Modal mover archivo -->
        <div id="modalMover" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalMover" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo">Mover archivo</h2>
                <form id="formMover" autocomplete="off">
                    <input type="hidden" name="archivo_id" id="mv_arch_id">
                    <div class="campo">
                        <label for="mv_destino">Carpeta destino</label>
                        <select name="ruta_carpeta_destino" id="mv_destino">
                            <option value="">— Raíz —</option>
                            <?php foreach ($carpetas as $rk => $car): ?>
                                <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-modal="modalMover">Cancelar</button>
                        <button type="submit" class="btn btn-primario">Mover</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Modal papelera -->
        <div id="modalPapelera" class="modal-fondo">
            <div class="modal-caja modal-caja-grande">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalPapelera" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo">Papelera</h2>
                <p class="texto-atenuado" style="margin-top:0">Archivos eliminados que aún se pueden restaurar.</p>
                <div id="papeleraLista" class="historial-mov">
                    <div class="tabla-cargando">Cargando…</div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-secundario" data-cerrar-modal="modalPapelera">Cerrar</button>
                    <button type="button" class="btn btn-link btn-link-peligro" id="btnVaciarPapelera">Vaciar papelera</button>
                </div>
            </div>
        </div>


        <script src="archivos.js?v=<?php echo @filemtime(__DIR__ . '/archivos.js'); ?>"></script>
        <script>window.PACIENTE_ID = <?php echo $idInt; ?>;</script>
        <?php endif; ?>


        <?php if ($tab === 'notas'): ?>
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Notas clínicas
                <button type="button" class="btn btn-primario btn-sm" id="btnNuevaNota" style="margin-left:auto">+ Nueva nota</button>
            </div>
            <?php if (empty($notas)): ?>
                <div class="placeholder-card-inline">
                    <p>Aún no hay notas para este paciente.</p>
                    <p class="texto-atenuado">Las notas son tu bitácora libre: observaciones, diagnósticos, recordatorios para la próxima sesión.</p>
                </div>
            <?php else: ?>
                <ul class="lista-notas">
                    <?php foreach ($notas as $n): ?>
                    <li class="nota-card" data-nota-id="<?php echo (int)$n['nota_id']; ?>">
                        <div class="nota-fecha">
                            <?php echo fmtFecha($n['fecha']); ?>
                            <span class="nota-autosave-indicador texto-pequeno texto-atenuado" data-indicador-id="<?php echo (int)$n['nota_id']; ?>"></span>
                        </div>
                        <textarea class="nota-contenido-editable"
                                  data-edit-id="<?php echo (int)$n['nota_id']; ?>"
                                  data-fecha="<?php echo htmlspecialchars($n['fecha']); ?>"
                                  rows="4" maxlength="5000"><?php echo htmlspecialchars($n['contenido']); ?></textarea>
                        <?php if ($n['actualizado_en'] !== $n['creado_en']): ?>
                            <div class="texto-atenuado texto-pequeno">Editada el <?php echo fmtFecha($n['actualizado_en']); ?></div>
                        <?php endif; ?>
                        <div class="nota-acciones">
                            <button type="button" class="btn-link btn-link-peligro"
                                    data-nota-accion="eliminar"
                                    data-nota-id="<?php echo (int)$n['nota_id']; ?>">Eliminar</button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>


        <!-- Modal nueva/editar nota -->
        <div id="modalNota" class="modal-fondo">
            <div class="modal-caja">
                <button type="button" class="modal-cerrar" data-cerrar-modal="modalNota" aria-label="Cerrar">&times;</button>
                <h2 class="modal-titulo" id="tituloModalNota">Nueva nota</h2>
                <form id="formNota" autocomplete="off">
                    <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
                    <input type="hidden" name="nota_id" id="nota_id">

                    <div class="campo">
                        <label for="nota_fecha">Fecha *</label>
                        <input type="date" name="fecha" id="nota_fecha" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="campo">
                        <label for="nota_contenido">Contenido *</label>
                        <textarea name="contenido" id="nota_contenido" rows="6" maxlength="5000" required></textarea>
                    </div>
                    <div class="modal-acciones">
                        <button type="button" class="btn btn-secundario" data-cerrar-modal="modalNota">Cancelar</button>
                        <button type="submit" class="btn btn-primario">Guardar</button>
                    </div>
                </form>
            </div>
        </div>


        <script>
        (function() {
            const PACIENTE_ID = <?php echo $idInt; ?>;
            const modal = document.getElementById('modalNota');
            const form = document.getElementById('formNota');
            const titulo = document.getElementById('tituloModalNota');
            const toastEl = document.getElementById('toast');
            function toast(m, t='ok') {
                if (!toastEl) { alert(m); return; }
                toastEl.textContent = m;
                toastEl.className = 'toast ' + (t==='error' ? 'toast-error' : 'toast-ok') + ' visible';
                setTimeout(() => toastEl.classList.remove('visible'), 2800);
            }
            function abrir(nota) {
                form.reset();
                if (nota) {
                    titulo.textContent = 'Editar nota';
                    document.getElementById('nota_id').value = nota.id;
                    document.getElementById('nota_fecha').value = nota.fecha;
                    document.getElementById('nota_contenido').value = nota.contenido;
                } else {
                    titulo.textContent = 'Nueva nota';
                    document.getElementById('nota_id').value = '';
                    document.getElementById('nota_fecha').value = new Date().toISOString().slice(0, 10);
                }
                modal.classList.add('visible');
            }
            document.getElementById('btnNuevaNota').addEventListener('click', () => abrir(null));
            modal.querySelectorAll('[data-cerrar-modal="modalNota"]').forEach(b =>
                b.addEventListener('click', () => modal.classList.remove('visible'))
            );
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('visible'); });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const datos = new FormData(form);
                const id = document.getElementById('nota_id').value;
                const url = id ? 'notas-actualizar.php' : 'notas-guardar.php';
                try {
                    const resp = await fetch(url, { method: 'POST', body: datos });
                    const json = await resp.json();
                    if (json.redirect) { window.location.href = '../index.php'; return; }
                    if (!json.ok) { toast(json.mensaje, 'error'); return; }
                    toast(json.mensaje);
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) { toast('No se pudo conectar.', 'error'); }
            });

            /* Auto-save inline en cada nota existente.
               Debounce de 1500ms desde el último keystroke; muestra estado en
               .nota-autosave-indicador. */
            const timersAutosave = {};
            document.querySelectorAll('.nota-contenido-editable').forEach(ta => {
                const id = ta.dataset.editId;
                const fechaOriginal = ta.dataset.fecha;
                const indic = document.querySelector(`[data-indicador-id="${id}"]`);

                ta.addEventListener('input', () => {
                    if (indic) indic.textContent = '· editando…';
                    clearTimeout(timersAutosave[id]);
                    timersAutosave[id] = setTimeout(async () => {
                        if (indic) indic.textContent = '· guardando…';
                        const datos = new FormData();
                        datos.append('nota_id', id);
                        datos.append('paciente_id', PACIENTE_ID);
                        datos.append('fecha', fechaOriginal);
                        datos.append('contenido', ta.value);
                        try {
                            const resp = await fetch('notas-actualizar.php', { method: 'POST', body: datos });
                            const json = await resp.json();
                            if (json.redirect) { window.location.href = '../index.php'; return; }
                            if (!json.ok) {
                                if (indic) indic.textContent = '· ' + (json.mensaje || 'error');
                                if (indic) indic.style.color = 'var(--color-peligro-texto)';
                                return;
                            }
                            if (indic) {
                                indic.textContent = '· guardado';
                                indic.style.color = 'var(--color-exito-texto)';
                                setTimeout(() => { if (indic) indic.textContent = ''; }, 2000);
                            }
                        } catch (e) {
                            if (indic) {
                                indic.textContent = '· sin conexión, reintentando…';
                                indic.style.color = 'var(--color-advertencia-texto)';
                            }
                        }
                    }, 1500);
                });
            });

            document.querySelectorAll('[data-nota-accion]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const accion = btn.dataset.notaAccion;
                    const id     = btn.dataset.notaId;
                    if (accion === 'editar') {
                        abrir({ id, fecha: btn.dataset.notaFecha, contenido: btn.dataset.notaContenido });
                    }
                    if (accion === 'eliminar') {
                        if (!confirm('¿Eliminar esta nota?')) return;
                        const datos = new FormData();
                        datos.append('nota_id', id);
                        try {
                            const resp = await fetch('notas-eliminar.php', { method: 'POST', body: datos });
                            const json = await resp.json();
                            if (!json.ok) { toast(json.mensaje, 'error'); return; }
                            toast(json.mensaje);
                            setTimeout(() => window.location.reload(), 600);
                        } catch (e) { toast('No se pudo conectar.', 'error'); }
                    }
                });
            });
        })();
        </script>
        <?php endif; ?>

    </main>

</div>

</body>
</html>

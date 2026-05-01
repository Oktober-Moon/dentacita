<?php
/*
 * DASHBOARD · panel principal (v3, 1:1 dentista)
 * ----------------------------------------------
 * Replica funcional de DashboardOverview.tsx + DashboardInventoryAlert
 * del original, limitado a las cosas que el dentista realmente ve.
 */

require __DIR__ . '/conexion.php';

$conexion = obtenerConexion();
exigirSesionVista($conexion);

$uid = getUsuarioId();

function escalarUid($conexion, $sql, $uid) {
    $stmt = @$conexion->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $uid);
    if (!@$stmt->execute()) { $stmt->close(); return null; }
    $f = $stmt->get_result()->fetch_array(MYSQLI_NUM);
    $stmt->close();
    return $f ? $f[0] : null;
}

/* ----- KPIs operativos ----- */
$totalPacientes = escalarUid($conexion,
    "SELECT COUNT(*) FROM pacientes WHERE usuario_id = ?", $uid);
$citasHoy = escalarUid($conexion,
    "SELECT COUNT(*) FROM citas
     WHERE usuario_id = ?
       AND fecha_hora_inicio >= CURDATE()
       AND fecha_hora_inicio <  CURDATE() + INTERVAL 1 DAY
       AND estado IN ('programada','confirmada')", $uid);
$citasSemana = escalarUid($conexion,
    "SELECT COUNT(*) FROM citas
     WHERE usuario_id = ? AND fecha_hora_inicio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
       AND estado IN ('programada','confirmada')", $uid);
$completadasMes = escalarUid($conexion,
    "SELECT COUNT(*) FROM citas
     WHERE usuario_id = ? AND estado = 'completada'
       AND fecha_hora_inicio >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND fecha_hora_inicio <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH", $uid);

/* ----- KPIs financieros ----- */
$ingresosMes = escalarUid($conexion,
    "SELECT COALESCE(SUM(monto), 0) FROM transacciones
     WHERE usuario_id = ? AND tipo='ingreso' AND estado='activa'
       AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH", $uid);
$egresosMes = escalarUid($conexion,
    "SELECT COALESCE(SUM(monto), 0) FROM transacciones
     WHERE usuario_id = ? AND tipo='egreso'  AND estado='activa'
       AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH", $uid);
$balanceMes = (float)$ingresosMes - (float)$egresosMes;

/* ----- Sub-textos contextuales para los KPIs ----- */
$citasMesCobradas = (int)escalarUid($conexion,
    "SELECT COUNT(DISTINCT cita_id) FROM transacciones
     WHERE usuario_id = ? AND tipo='ingreso' AND estado='activa' AND cita_id IS NOT NULL
       AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH", $uid);

/* ----- Alertas/banners ----- */
$inventarioBajo = (int)escalarUid($conexion,
    "SELECT COUNT(*) FROM inventario_items
     WHERE usuario_id = ? AND cantidad_actual < cantidad_minima", $uid);
$citasAtrasadas = (int)escalarUid($conexion,
    "SELECT COUNT(*) FROM citas
     WHERE usuario_id = ? AND estado IN ('programada','confirmada') AND fecha_hora_inicio < NOW()", $uid);

/* ----- Citas de hoy ----- */
$citasDeHoy = [];
$stmt = @$conexion->prepare(
    "SELECT cita_id, paciente_nombre, titulo, fecha_hora_inicio, fecha_hora_fin, estado
     FROM citas
     WHERE usuario_id = ?
       AND fecha_hora_inicio >= CURDATE()
       AND fecha_hora_inicio <  CURDATE() + INTERVAL 1 DAY
     ORDER BY fecha_hora_inicio ASC");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    @$stmt->execute();
    $r = $stmt->get_result();
    while ($f = $r->fetch_assoc()) $citasDeHoy[] = $f;
    $stmt->close();
}

/* ----- Próximas citas (de mañana hasta 7 días) ----- */
$citasProximas = [];
$stmt = @$conexion->prepare(
    "SELECT cita_id, paciente_nombre, titulo, fecha_hora_inicio, estado
     FROM citas
     WHERE usuario_id = ?
       AND fecha_hora_inicio >= CURDATE() + INTERVAL 1 DAY
       AND fecha_hora_inicio <  CURDATE() + INTERVAL 8 DAY
       AND estado IN ('programada','confirmada')
     ORDER BY fecha_hora_inicio ASC
     LIMIT 8");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    @$stmt->execute();
    $r = $stmt->get_result();
    while ($f = $r->fetch_assoc()) $citasProximas[] = $f;
    $stmt->close();
}

/* ----- Nombre y onboarding ----- */
$nombreDentista = $_SESSION['usuario_nombre'] ?? 'dentista';
$onboardingCompletado = 1;
$stmt = @$conexion->prepare(
    "SELECT nombre_completo, onboarding_completado
     FROM perfil_dentista WHERE usuario_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    @$stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    if ($f) {
        if ($f['nombre_completo']) $nombreDentista = $f['nombre_completo'];
        $onboardingCompletado = (int)$f['onboarding_completado'];
    }
    $stmt->close();
}

$conexion->close();


$nombresEstado = [
    'programada' => 'Programada', 'confirmada' => 'Confirmada',
    'completada' => 'Completada', 'cancelada'  => 'Cancelada',
    'no_asistio' => 'No asistió',
];
function fmtDinero($n) { return '$' . number_format((float)$n, 2, '.', ','); }
function fmtDiaCorto($iso) {
    $ts = strtotime($iso);
    if (!$ts) return $iso;
    $dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    return $dias[(int)date('w', $ts)] . ' ' . date('d/m', $ts);
}

$nav_actual   = 'dashboard';
$nav_base_url = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Inicio</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo @filemtime(__DIR__ . '/styles.css'); ?>">
</head>
<body>

<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <h1 class="page-titulo">Hola, <?php echo htmlspecialchars($nombreDentista); ?></h1>
                <div class="page-subtitulo">Resumen de tu actividad de hoy.</div>
            </div>
            <div class="page-acciones">
                <a href="agenda/" class="btn btn-primario">Ir a la agenda →</a>
            </div>
        </div>

        <!-- ===== Banners de alerta ===== -->
        <?php if ($citasAtrasadas + $inventarioBajo > 0): ?>
        <div class="banners-alerta">
            <?php if ($citasAtrasadas > 0): ?>
                <a href="agenda/" class="banner banner-naranja">
                    <strong><?php echo $citasAtrasadas; ?></strong> cita<?php echo $citasAtrasadas === 1 ? '' : 's'; ?> atrasada<?php echo $citasAtrasadas === 1 ? '' : 's'; ?>
                </a>
            <?php endif; ?>

            <?php if ($inventarioBajo > 0): ?>
                <a href="inventario/" class="banner banner-rojo">
                    <strong><?php echo $inventarioBajo; ?></strong> ítem<?php echo $inventarioBajo === 1 ? '' : 's'; ?> con stock bajo
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===== KPIs operativos ===== -->
        <h2 class="seccion-titulo seccion-titulo-pequeno">Actividad clínica</h2>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-card-titulo">Citas hoy</div>
                <div class="kpi-card-valor"><?php echo $citasHoy === null ? '—' : $citasHoy; ?></div>
                <div class="kpi-card-desc"><?php echo (int)$citasSemana; ?> esta semana</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-titulo">Próximos 7 días</div>
                <div class="kpi-card-valor"><?php echo $citasSemana === null ? '—' : $citasSemana; ?></div>
                <div class="kpi-card-desc">programadas o confirmadas</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-titulo">Completadas este mes</div>
                <div class="kpi-card-valor"><?php echo $completadasMes === null ? '—' : $completadasMes; ?></div>
                <div class="kpi-card-desc"><?php echo $citasMesCobradas; ?> con cobro registrado</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-titulo">Pacientes</div>
                <div class="kpi-card-valor"><?php echo $totalPacientes === null ? '—' : $totalPacientes; ?></div>
                <div class="kpi-card-desc">en tu base</div>
            </div>
        </div>

        <!-- ===== KPIs financieros ===== -->
        <h2 class="seccion-titulo seccion-titulo-pequeno">Finanzas del mes</h2>
        <div class="kpi-grid">
            <div class="kpi-card kpi-card-verde">
                <div class="kpi-card-titulo">Ingresos del mes</div>
                <div class="kpi-card-valor"><?php echo fmtDinero($ingresosMes); ?></div>
                <div class="kpi-card-desc"><?php echo $citasMesCobradas; ?> citas cobradas</div>
            </div>
            <div class="kpi-card kpi-card-rojo">
                <div class="kpi-card-titulo">Egresos del mes</div>
                <div class="kpi-card-valor"><?php echo fmtDinero($egresosMes); ?></div>
                <div class="kpi-card-desc">materiales y otros gastos</div>
            </div>
            <div class="kpi-card <?php echo $balanceMes >= 0 ? 'kpi-card-verde' : 'kpi-card-rojo'; ?>">
                <div class="kpi-card-titulo">Balance</div>
                <div class="kpi-card-valor"><?php echo fmtDinero($balanceMes); ?></div>
                <div class="kpi-card-desc">Egresos: <?php echo fmtDinero($egresosMes); ?></div>
            </div>
        </div>

        <!-- ===== Gráfico de finanzas (últimos 3 meses) ===== -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Tendencia financiera
                <span class="texto-pequeno texto-atenuado">últimos 3 meses</span>
            </div>
            <div class="reporte-grafico">
                <canvas id="dashReporteCanvas" height="180"></canvas>
                <div class="reporte-leyenda">
                    <span class="reporte-pill reporte-pill-ingreso">Ingresos</span>
                    <span class="reporte-pill reporte-pill-egreso">Egresos</span>
                </div>
            </div>
        </div>

        <!-- ===== Próximas citas (próximos 7 días) ===== -->
        <?php if (!empty($citasProximas)): ?>
        <div class="seccion">
            <h2 class="seccion-titulo">Próximas citas</h2>
            <div class="tabla-contenedor">
                <table class="tabla-datos tabla-citas-proximas">
                    <thead>
                        <tr>
                            <th class="col-dia">Día</th>
                            <th class="col-hora">Hora</th>
                            <th>Paciente</th>
                            <th>Motivo</th>
                            <th class="col-estado">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citasProximas as $c): ?>
                            <tr>
                                <td class="mono"><?php echo fmtDiaCorto($c['fecha_hora_inicio']); ?></td>
                                <td class="mono"><?php echo date('H:i', strtotime($c['fecha_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($c['paciente_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>">
                                        <?php echo htmlspecialchars($nombresEstado[$c['estado']] ?? $c['estado']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Citas de hoy ===== -->
        <div class="seccion">
            <h2 class="seccion-titulo">Citas de hoy</h2>

            <div class="tabla-contenedor">
                <?php if (empty($citasDeHoy)): ?>
                    <div class="estado-vacio">
                        <div class="estado-vacio-titulo">Sin citas para hoy</div>
                        <div class="estado-vacio-desc">Disfruta tu día libre o agenda una nueva.</div>
                        <a href="agenda/" class="btn btn-primario btn-sm">Agendar cita</a>
                    </div>
                <?php else: ?>
                    <table class="tabla-datos tabla-citas-hoy">
                        <thead>
                            <tr>
                                <th class="col-hora-rango">Hora</th>
                                <th>Paciente</th>
                                <th>Motivo</th>
                                <th class="col-estado">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($citasDeHoy as $c): ?>
                                <tr>
                                    <td class="mono">
                                        <?php echo date('H:i', strtotime($c['fecha_hora_inicio'])); ?>
                                        <span class="texto-atenuado"> – <?php echo date('H:i', strtotime($c['fecha_hora_fin'])); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['paciente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>">
                                            <?php echo htmlspecialchars($nombresEstado[$c['estado']] ?? $c['estado']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </main>

</div>


<!-- ===== Gráfico finanzas en dashboard ===== -->
<script src="dashboard-grafico.js?v=<?php echo @filemtime(__DIR__ . '/dashboard-grafico.js'); ?>"></script>

<?php if (!$onboardingCompletado): ?>
<!-- Tour de onboarding (primera visita) -->
<div class="onboarding-overlay" id="onboardingOverlay">
    <div class="onboarding-panel">
        <div class="onboarding-titulo" id="obTitulo">¡Bienvenido a DentaCita!</div>
        <div class="onboarding-paso-info" id="obPasoInfo">Paso 1 de 5</div>

        <div class="onboarding-cuerpo" id="obCuerpo">
            <p>Hola <?php echo htmlspecialchars($nombreDentista); ?> 👋</p>
            <p>Esta app es tu centro de operaciones diario. En 5 pasos rápidos te muestro qué puedes hacer.</p>
        </div>

        <div class="onboarding-acciones">
            <div class="onboarding-progreso" id="obProgreso">
                <span class="activo"></span><span></span><span></span><span></span><span></span>
            </div>
            <div>
                <button type="button" class="btn btn-secundario" id="obSaltar">Saltar</button>
                <button type="button" class="btn btn-primario" id="obSiguiente">Siguiente →</button>
            </div>
        </div>
    </div>
</div>

<script>window.DC_NOMBRE = <?php echo json_encode(htmlspecialchars($nombreDentista, ENT_QUOTES)); ?>;</script>
<script src="dashboard-onboarding.js?v=<?php echo @filemtime(__DIR__ . '/dashboard-onboarding.js'); ?>"></script>
<?php endif; ?>


</body>
</html>

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
/* Pre-titulo del dashboard · "Resumen · Jueves 30 de abril" */
$diasLargos  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$mesesLargos = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$preTituloHoy = $diasLargos[(int)date('w')] . ' ' . (int)date('j') . ' de ' . $mesesLargos[(int)date('n') - 1];
$semanaAnio   = (int)date('W');
$horaLocal    = date('H:i');

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
                <div class="page-pre-titulo">Resumen · <?php echo htmlspecialchars($preTituloHoy); ?></div>
                <h1 class="page-titulo">Hola, <?php echo htmlspecialchars($nombreDentista); ?></h1>
                <div class="page-subtitulo">
                    Tienes <strong><?php echo (int)$citasHoy . ' cita' . ($citasHoy == 1 ? '' : 's') . ' hoy'; ?></strong>
                    <span class="sep"></span>
                    <span class="mono">SEMANA <?php echo $semanaAnio; ?> · <?php echo htmlspecialchars($horaLocal); ?></span>
                </div>
            </div>
            <div class="page-acciones">
                <a href="agenda/" class="btn btn-primario">
                    Ir a la agenda
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        <!-- ===== Banners de alerta ===== -->
        <?php if ($citasAtrasadas + $inventarioBajo > 0): ?>
        <div class="banners-alerta">
            <?php if ($citasAtrasadas > 0): ?>
                <a href="agenda/" class="banner banner-naranja">
                    <span class="banner-num"><?php echo $citasAtrasadas; ?></span>
                    cita<?php echo $citasAtrasadas === 1 ? '' : 's'; ?> atrasada<?php echo $citasAtrasadas === 1 ? '' : 's'; ?> sin atender
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
            <?php endif; ?>

            <?php if ($inventarioBajo > 0): ?>
                <a href="inventario/" class="banner banner-rojo">
                    <span class="banner-num"><?php echo $inventarioBajo; ?></span>
                    ítem<?php echo $inventarioBajo === 1 ? '' : 's'; ?> con stock bajo
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===== KPIs operativos · stats strip ===== -->
        <div class="stats-strip">
            <div class="stat-cell">
                <div class="stat-label">Citas hoy</div>
                <div class="stat-valor"><?php echo $citasHoy === null ? '—' : $citasHoy; ?></div>
                <div class="stat-sub"><?php echo (int)$citasSemana; ?> esta semana</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Próximos 7 días</div>
                <div class="stat-valor"><?php echo $citasSemana === null ? '—' : $citasSemana; ?></div>
                <div class="stat-sub">programadas o confirmadas</div>
            </div>
            <div class="stat-cell acento">
                <div class="stat-label">Completadas mes</div>
                <div class="stat-valor"><?php echo $completadasMes === null ? '—' : $completadasMes; ?></div>
                <div class="stat-sub"><?php echo $citasMesCobradas; ?> con cobro registrado</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Pacientes</div>
                <div class="stat-valor"><?php echo $totalPacientes === null ? '—' : $totalPacientes; ?></div>
                <div class="stat-sub">en tu base</div>
            </div>
        </div>

        <!-- ===== KPIs financieros · stats strip de 3 cells ===== -->
        <div class="stats-strip stats-strip-3">
            <div class="stat-cell exito">
                <div class="stat-label">Ingresos del mes</div>
                <div class="stat-valor"><?php echo fmtDinero($ingresosMes); ?></div>
                <div class="stat-sub"><?php echo $citasMesCobradas; ?> citas cobradas</div>
            </div>
            <div class="stat-cell peligro">
                <div class="stat-label">Egresos del mes</div>
                <div class="stat-valor"><?php echo fmtDinero($egresosMes); ?></div>
                <div class="stat-sub">materiales y otros</div>
            </div>
            <div class="stat-cell <?php echo $balanceMes >= 0 ? 'exito' : 'peligro'; ?>">
                <div class="stat-label">Balance</div>
                <div class="stat-valor"><?php echo fmtDinero($balanceMes); ?></div>
                <div class="stat-sub">ingresos − egresos</div>
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

        <!-- ===== Citas de hoy (timeline) + Próximas citas (lista) ===== -->
        <div class="dual-grid">

            <!-- Timeline de citas de hoy -->
            <div class="ficha-card ficha-card-flush">
                <div class="ficha-card-titulo">
                    Citas de hoy
                    <span class="texto-pequeno texto-atenuado"><?php echo count($citasDeHoy); ?></span>
                </div>
                <?php if (empty($citasDeHoy)): ?>
                    <div class="estado-vacio">
                        <div class="estado-vacio-titulo">Sin citas para hoy</div>
                        <div class="estado-vacio-desc">Disfruta tu día libre o agenda una nueva.</div>
                        <a href="agenda/" class="btn btn-primario btn-sm">Agendar cita</a>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php
                        $tsAhora = time();
                        foreach ($citasDeHoy as $c):
                            $iniTs = strtotime($c['fecha_hora_inicio']);
                            $finTs = strtotime($c['fecha_hora_fin']);
                            $esAhora      = ($iniTs <= $tsAhora && $tsAhora < $finTs)
                                            && in_array($c['estado'], ['programada','confirmada'], true);
                            $esCompletada = ($c['estado'] === 'completada');
                            $clases = 'tl-cita';
                            if ($esAhora)      $clases .= ' ahora';
                            if ($esCompletada) $clases .= ' completada';
                        ?>
                        <a href="agenda/" class="<?php echo $clases; ?>">
                            <div class="tl-cita-hora">
                                <?php echo date('H:i', $iniTs); ?>
                                <span class="tl-cita-hora-fin"><?php echo date('H:i', $finTs); ?></span>
                            </div>
                            <div class="tl-cita-fila">
                                <div class="tl-cita-info">
                                    <div class="tl-cita-titulo"><?php echo htmlspecialchars($c['titulo']); ?></div>
                                    <div class="tl-cita-paciente"><?php echo htmlspecialchars($c['paciente_nombre']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>">
                                    <?php echo htmlspecialchars($nombresEstado[$c['estado']] ?? $c['estado']); ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Próximas citas · próximos 7 días -->
            <div class="ficha-card ficha-card-flush">
                <div class="ficha-card-titulo">
                    Próximas citas
                    <span class="texto-pequeno texto-atenuado">7 días</span>
                </div>
                <?php if (empty($citasProximas)): ?>
                    <div class="placeholder-card-inline">
                        <p>Sin citas en los próximos 7 días.</p>
                    </div>
                <?php else: ?>
                    <ul class="prox-lista">
                        <?php
                        $diasCorto = ['DOM','LUN','MAR','MIÉ','JUE','VIE','SÁB'];
                        foreach ($citasProximas as $c):
                            $ts = strtotime($c['fecha_hora_inicio']);
                        ?>
                        <li>
                            <a href="agenda/" class="prox-item">
                                <div class="prox-fecha">
                                    <div class="prox-fecha-dia"><?php echo $diasCorto[(int)date('w', $ts)]; ?></div>
                                    <div class="prox-fecha-num"><?php echo (int)date('j', $ts); ?></div>
                                </div>
                                <div class="prox-cuerpo">
                                    <div class="prox-titulo"><?php echo htmlspecialchars($c['titulo']); ?></div>
                                    <div class="prox-meta">
                                        <span><?php echo htmlspecialchars($c['paciente_nombre']); ?></span>
                                        <span class="dot"></span>
                                        <span class="mono"><?php echo date('H:i', $ts); ?></span>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>">
                                    <?php echo htmlspecialchars($nombresEstado[$c['estado']] ?? $c['estado']); ?>
                                </span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
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

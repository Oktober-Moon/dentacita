<?php
/*
 * FINANZAS · vista principal
 */

require __DIR__ . '/../conexion.php';
require __DIR__ . '/_catalogos.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');

$usuarioId = getUsuarioId();

// Lista de pacientes para el selector de filtro (solo del usuario actual)
$pacientesFiltro = [];
$stmtP = @$conexion->prepare(
    "SELECT paciente_id, nombre_completo FROM pacientes
     WHERE usuario_id = ? ORDER BY nombre_completo ASC"
);
if ($stmtP) {
    $stmtP->bind_param("i", $usuarioId);
    @$stmtP->execute();
    $rp = $stmtP->get_result();
    while ($f = $rp->fetch_assoc()) $pacientesFiltro[] = $f;
    $stmtP->close();
}

$conexion->close();

$nav_actual   = 'finanzas';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Finanzas</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>


<!-- Modal · transacción manual -->
<div id="modalTrans" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalTrans" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo" id="tituloModalTrans">Nueva transacción</h2>
        <form id="formTrans" autocomplete="off">
            <input type="hidden" name="transaccion_id" id="trans_id">

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="trans_tipo">Tipo *</label>
                    <select name="tipo" id="trans_tipo" required>
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="trans_categoria">Categoría *</label>
                    <select name="categoria" id="trans_categoria" required>
                        <?php foreach (FIN_CATEGORIAS_INGRESO as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>" data-tipo="ingreso"><?= htmlspecialchars($c, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="trans_monto">Monto *</label>
                    <input type="number" name="monto" id="trans_monto" min="0.01" step="0.01" required>
                </div>
                <div class="campo">
                    <label for="trans_fecha">Fecha *</label>
                    <input type="date" name="fecha" id="trans_fecha" required>
                </div>
            </div>

            <div class="campo">
                <label for="trans_metodo">Método de pago</label>
                <select name="metodo_pago" id="trans_metodo">
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="cheque">Cheque</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="campo">
                <label for="trans_descripcion">Descripción</label>
                <textarea name="descripcion" id="trans_descripcion" rows="2" maxlength="200"></textarea>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarTrans">Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal · cobrar cita -->
<div id="modalCobro" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalCobro" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Cobrar cita</h2>
        <p id="cobroDetalle" class="texto-atenuado" style="margin-top:0">—</p>
        <form id="formCobro" autocomplete="off">
            <input type="hidden" name="cita_id" id="cobro_cita_id">

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="cobro_monto">Monto a cobrar *</label>
                    <input type="number" name="monto" id="cobro_monto" min="0.01" step="0.01" required>
                </div>
                <div class="campo">
                    <label for="cobro_metodo">Método de pago</label>
                    <select name="metodo_pago" id="cobro_metodo">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarCobro">Cancelar</button>
                <button type="submit" class="btn btn-primario">Registrar cobro</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal · reembolso -->
<div id="modalReembolso" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalReembolso" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Registrar reembolso</h2>
        <p id="reembolsoDetalle" class="texto-atenuado" style="margin-top:0">—</p>
        <form id="formReembolso" autocomplete="off">
            <input type="hidden" name="cita_id" id="reembolso_cita_id">

            <div class="campo">
                <label for="reembolso_monto">Monto a reembolsar *</label>
                <input type="number" name="monto" id="reembolso_monto" min="0.01" step="0.01" required>
            </div>

            <div class="campo">
                <label for="reembolso_motivo">Motivo</label>
                <textarea name="motivo" id="reembolso_motivo" rows="2" maxlength="200"
                          placeholder="Ej. Paciente solicitó reembolso por insatisfacción."></textarea>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarReembolso">Cancelar</button>
                <button type="submit" class="btn btn-primario">Registrar reembolso</button>
            </div>
        </form>
    </div>
</div>


<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <div class="page-pre-titulo">Negocio · Finanzas</div>
                <h1 class="page-titulo">Finanzas</h1>
                <div class="page-subtitulo">
                    Ingresos, egresos, reembolsos y cobros pendientes
                    <span class="sep"></span>
                    <span class="mono"><?php echo strtoupper(date('M Y')); ?></span>
                </div>
            </div>
            <div class="page-acciones">
                <a href="exportar.php" class="btn btn-secundario">
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Exportar CSV
                </a>
                <button type="button" class="btn btn-primario" id="btnNuevaTrans">
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nueva transacción
                </button>
            </div>
        </div>

        <!-- KPIs del mes · stats strip -->
        <div class="stats-strip">
            <div class="stat-cell exito">
                <div class="stat-label">Ingresos</div>
                <div class="stat-valor" id="kpiIngresoMes">—</div>
                <div class="stat-sub">este mes</div>
            </div>
            <div class="stat-cell peligro">
                <div class="stat-label">Egresos</div>
                <div class="stat-valor" id="kpiEgresoMes">—</div>
                <div class="stat-sub">este mes</div>
            </div>
            <div class="stat-cell" id="kpiBalanceMesCard">
                <div class="stat-label">Balance</div>
                <div class="stat-valor" id="kpiBalanceMes">—</div>
                <div class="stat-sub">ingresos − egresos</div>
            </div>
            <div class="stat-cell acento">
                <div class="stat-label">Promedio</div>
                <div class="stat-valor" id="kpiPromedioMes">—</div>
                <div class="stat-sub">por transacción</div>
            </div>
        </div>

        <!-- KPIs del año · stats strip de 3 cells -->
        <div class="stats-strip stats-strip-3">
            <div class="stat-cell exito">
                <div class="stat-label">Ingresos</div>
                <div class="stat-valor" id="kpiIngresoAno">—</div>
                <div class="stat-sub">año en curso</div>
            </div>
            <div class="stat-cell peligro">
                <div class="stat-label">Egresos</div>
                <div class="stat-valor" id="kpiEgresoAno">—</div>
                <div class="stat-sub">año en curso</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Balance</div>
                <div class="stat-valor" id="kpiBalanceAno">—</div>
                <div class="stat-sub">año en curso</div>
            </div>
        </div>

        <!-- Gráfico + Top categorías -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                <span id="reporteTitulo">Últimos 6 meses</span>
                <div class="filtros-inline" style="margin-left:auto">
                    <button type="button" class="btn btn-secundario btn-sm" data-reporte-modo="mensual" id="btnReporteMensual">Mensual</button>
                    <button type="button" class="btn btn-secundario btn-sm" data-reporte-modo="diario" id="btnReporteDiario">Últimos 30 días</button>
                </div>
                <span class="texto-pequeno texto-atenuado" id="reporteEstado"></span>
            </div>
            <div class="reporte-grid">
                <div class="reporte-grafico">
                    <canvas id="reporteCanvas" height="220"></canvas>
                    <div class="reporte-leyenda">
                        <span class="reporte-pill reporte-pill-ingreso">Ingresos</span>
                        <span class="reporte-pill reporte-pill-egreso">Egresos</span>
                    </div>
                </div>
                <div class="reporte-top">
                    <div class="reporte-top-titulo">Top egresos por categoría</div>
                    <div id="reporteTopLista" class="reporte-top-lista">—</div>
                </div>
            </div>
        </div>

        <!-- Ingresos por categoría · toggle bar/pie -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Ingresos por categoría
                <div class="filtros-inline" style="margin-left:auto">
                    <button type="button" class="btn btn-secundario btn-sm" data-grafico-modo="bar" id="btnGraficoBar">Barras</button>
                    <button type="button" class="btn btn-secundario btn-sm" data-grafico-modo="pie" id="btnGraficoPie">Pie</button>
                </div>
            </div>
            <div class="reporte-grafico">
                <canvas id="ingresosCategoriaCanvas" height="220"></canvas>
                <div id="ingresosCategoriaLeyenda" class="reporte-leyenda" style="flex-wrap:wrap; gap:6px"></div>
            </div>
        </div>

        <!-- Citas pendientes de cobro -->
        <div class="ficha-card" id="cobrablesCard" style="display:none">
            <div class="ficha-card-titulo">Citas completadas pendientes de cobro</div>
            <div id="cobrablesLista"></div>
        </div>

        <!-- Filtros + Tabla -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Movimientos
                <span class="tabla-conteo" id="conteoTrans"></span>
            </div>

            <!-- Presets de fecha -->
            <div class="filtros-presets" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:var(--espacio-md)">
                <button type="button" class="btn btn-secundario btn-sm" data-preset="hoy">Hoy</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="7d">Últimos 7 días</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="30d">Últimos 30 días</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="3m">Últimos 3 meses</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="ano">Este año</button>
            </div>

            <div class="campo-grid-2 filtros-finanzas">
                <div class="campo">
                    <label for="filtroTipo">Tipo</label>
                    <select id="filtroTipo">
                        <option value="">Todos</option>
                        <option value="ingreso">Ingresos</option>
                        <option value="egreso">Egresos</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtroEstado">Estado</label>
                    <select id="filtroEstado">
                        <option value="activa">Solo activas</option>
                        <option value="anulada">Solo anuladas</option>
                        <option value="todas">Todas</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtroDesde">Desde</label>
                    <input type="date" id="filtroDesde">
                </div>
                <div class="campo">
                    <label for="filtroHasta">Hasta</label>
                    <input type="date" id="filtroHasta">
                </div>
                <div class="campo">
                    <label for="filtroCategoria">Categoría</label>
                    <select id="filtroCategoria">
                        <option value="">— Todas —</option>
                        <?php foreach (finCategoriasUnion() as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>"><?= htmlspecialchars($c, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                        <option value="<?= htmlspecialchars(FIN_CATEGORIA_REEMBOLSO, ENT_QUOTES) ?>"><?= htmlspecialchars(FIN_CATEGORIA_REEMBOLSO, ENT_QUOTES) ?></option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtroMetodo">Método de pago</label>
                    <select id="filtroMetodo">
                        <option value="">— Todos —</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtroPaciente">Paciente</label>
                    <select id="filtroPaciente">
                        <option value="">— Todos —</option>
                        <?php foreach ($pacientesFiltro as $p): ?>
                            <option value="<?php echo (int)$p['paciente_id']; ?>">
                                <?php echo htmlspecialchars($p['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-acciones" style="border-top:none;margin-top:0;padding-top:0">
                <button type="button" class="btn btn-secundario btn-sm" id="btnLimpiarFiltros">Limpiar filtros</button>
            </div>

            <div id="tablaContenedor">
                <div class="tabla-cargando">Cargando…</div>
            </div>
        </div>

    </main>

</div>

<script src="finanzas-ajax.js?v=<?php echo @filemtime(__DIR__ . '/finanzas-ajax.js'); ?>"></script>
<script src="finanzas-dashboard.js?v=<?php echo @filemtime(__DIR__ . '/finanzas-dashboard.js'); ?>"></script>
<script src="finanzas-tabla.js?v=<?php echo @filemtime(__DIR__ . '/finanzas-tabla.js'); ?>"></script>
<script src="finanzas-modal.js?v=<?php echo @filemtime(__DIR__ . '/finanzas-modal.js'); ?>"></script>

</body>
</html>

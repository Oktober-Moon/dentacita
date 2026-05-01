<?php
/*
 * INVENTARIO · BITÁCORA DE MOVIMIENTOS
 * -------------------------------------
 * Vista paginada y filtrable de TODOS los movimientos del inventario.
 * Filtros: item, tipo, motivo, rango de fechas.
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');

$usuarioId = getUsuarioId();

$itemsCatalogo = [];
$stmtCat = @$conexion->prepare(
    "SELECT item_id, nombre FROM inventario_items WHERE usuario_id = ? ORDER BY nombre ASC"
);
if ($stmtCat) {
    $stmtCat->bind_param("i", $usuarioId);
    @$stmtCat->execute();
    $r = $stmtCat->get_result();
    while ($f = $r->fetch_assoc()) $itemsCatalogo[] = $f;
    $stmtCat->close();
}

$conexion->close();

$nav_actual   = 'inventario';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Bitácora de inventario</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>

<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <a href="./" class="link-volver">‹ Volver a inventario</a>

        <div class="page-header">
            <div>
                <h1 class="page-titulo">Bitácora de movimientos</h1>
                <div class="page-subtitulo">Histórico cronológico de entradas, salidas y ajustes.</div>
            </div>
        </div>

        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Filtros
            </div>

            <!-- Presets de fecha -->
            <div class="filtros-presets" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:var(--espacio-md)">
                <button type="button" class="btn btn-secundario btn-sm" data-preset="hoy">Hoy</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="7d">Últimos 7 días</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="30d">Últimos 30 días</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="3m">Últimos 3 meses</button>
                <button type="button" class="btn btn-secundario btn-sm" data-preset="ano">Este año</button>
            </div>

            <div class="campo-grid-2" style="gap:12px">
                <div class="campo">
                    <label for="filtro_item">Producto</label>
                    <select id="filtro_item">
                        <option value="">— Todos —</option>
                        <?php foreach ($itemsCatalogo as $it): ?>
                            <option value="<?php echo (int)$it['item_id']; ?>">
                                <?php echo htmlspecialchars($it['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtro_tipo">Tipo</label>
                    <select id="filtro_tipo">
                        <option value="">— Todos —</option>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtro_motivo">Motivo</label>
                    <select id="filtro_motivo">
                        <option value="">— Todos —</option>
                        <option value="compra">Compra</option>
                        <option value="donacion">Donación</option>
                        <option value="ajuste_inicial">Ajuste inicial</option>
                        <option value="devolucion_proveedor">Devolución proveedor</option>
                        <option value="venta">Venta</option>
                        <option value="uso_consulta">Uso en consulta</option>
                        <option value="vencimiento">Vencimiento</option>
                        <option value="perdida">Pérdida</option>
                        <option value="ajuste_inventario">Ajuste inventario</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtro_desde">Desde</label>
                    <input type="date" id="filtro_desde">
                </div>
                <div class="campo">
                    <label for="filtro_hasta">Hasta</label>
                    <input type="date" id="filtro_hasta">
                </div>
            </div>

            <div class="modal-acciones" style="border-top:none;margin-top:0;padding-top:0">
                <button type="button" class="btn btn-secundario" id="btnLimpiar">Limpiar</button>
                <button type="button" class="btn btn-primario" id="btnAplicar">Aplicar</button>
            </div>
        </div>

        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Movimientos
                <span class="tabla-conteo" id="conteoMovs"></span>
            </div>
            <div id="bitacoraTabla">
                <div class="tabla-cargando">Cargando…</div>
            </div>
            <div class="tabla-paginacion" id="bitacoraPag"></div>
        </div>

    </main>

</div>

<script src="bitacora.js?v=<?php echo @filemtime(__DIR__ . '/bitacora.js'); ?>"></script>

</body>
</html>

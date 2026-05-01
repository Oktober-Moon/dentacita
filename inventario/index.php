<?php
/*
 * INVENTARIO · vista principal
 */

require __DIR__ . '/../conexion.php';
require __DIR__ . '/_catalogos.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');
$conexion->close();

$nav_actual   = 'inventario';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Inventario</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>


<!-- Modal · producto -->
<div id="modalProducto" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalProducto" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo" id="tituloModalProducto">Nuevo producto</h2>
        <form id="formProducto" autocomplete="off">
            <input type="hidden" name="item_id" id="prod_id">

            <div class="campo">
                <label for="prod_nombre">Nombre *</label>
                <input type="text" name="nombre" id="prod_nombre" maxlength="120" required>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="prod_categoria">Categoría *</label>
                    <select name="categoria" id="prod_categoria" required>
                        <?php foreach (INV_CATEGORIAS as $val => $lbl): ?>
                            <option value="<?= htmlspecialchars($val, ENT_QUOTES) ?>"<?= $val === INV_CATEGORIA_DEFAULT ? ' selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="prod_unidad">Unidad *</label>
                    <select name="unidad" id="prod_unidad" required>
                        <?php foreach (INV_UNIDADES as $val => $lbl): ?>
                            <option value="<?= htmlspecialchars($val, ENT_QUOTES) ?>"<?= $val === INV_UNIDAD_DEFAULT ? ' selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="campo-grid-2" id="campos-cantidades">
                <div class="campo">
                    <label for="prod_cantidad_actual">Stock inicial</label>
                    <input type="number" name="cantidad_actual" id="prod_cantidad_actual" min="0" step="1" value="0">
                </div>
                <div class="campo">
                    <label for="prod_cantidad_minima">Stock mínimo *</label>
                    <input type="number" name="cantidad_minima" id="prod_cantidad_minima" min="0" step="1" value="0" required>
                </div>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="prod_costo">Costo unitario (compra)</label>
                    <input type="number" name="costo_unitario" id="prod_costo" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="campo">
                    <label for="prod_precio">Precio de venta</label>
                    <input type="number" name="precio_venta" id="prod_precio" min="0" step="0.01" placeholder="(opcional)">
                </div>
            </div>

            <div class="campo">
                <label for="prod_proveedor">Proveedor</label>
                <input type="text" name="proveedor" id="prod_proveedor" maxlength="100">
            </div>

            <div class="campo">
                <label for="prod_notas">Notas</label>
                <textarea name="notas" id="prod_notas" rows="2" maxlength="500"></textarea>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarModalProducto">Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal · movimiento de stock -->
<div id="modalMovimiento" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalMov" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Registrar movimiento</h2>
        <p class="texto-atenuado" id="movProductoNombre" style="margin-top:0">—</p>

        <form id="formMovimiento" autocomplete="off">
            <input type="hidden" name="item_id" id="mov_item_id">

            <!-- Selector de producto (visible solo cuando se abre desde el botón global) -->
            <div class="campo" id="mov_selector_producto" style="display:none">
                <label for="mov_selector_select">Producto *</label>
                <select id="mov_selector_select">
                    <option value="">— Selecciona un producto —</option>
                </select>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="mov_tipo">Tipo *</label>
                    <select name="tipo" id="mov_tipo" required>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                        <option value="ajuste">Ajuste de inventario</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="mov_motivo">Motivo *</label>
                    <select name="motivo" id="mov_motivo" required></select>
                </div>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="mov_cantidad" id="mov_cantidad_label">Cantidad *</label>
                    <input type="number" name="cantidad" id="mov_cantidad" min="1" step="1" required>
                </div>
                <div class="campo">
                    <label for="mov_fecha">Fecha *</label>
                    <input type="date" name="fecha" id="mov_fecha" required>
                </div>
            </div>

            <div class="campo">
                <label for="mov_cita">Cita asociada (opcional)</label>
                <select name="cita_id" id="mov_cita">
                    <option value="">— Ninguna —</option>
                </select>
                <div class="campo-ayuda">Útil para vincular insumos usados a una cita específica.</div>
            </div>

            <!-- Campos contextuales según tipo+motivo -->
            <div id="mov_costo_box" class="campo" style="display:none">
                <label for="mov_costo">Costo unitario *</label>
                <input type="number" name="costo_unitario" id="mov_costo" min="0" step="0.01" placeholder="0.00">
                <div class="campo-ayuda">Esto generará un <strong>egreso</strong> en finanzas.</div>
            </div>

            <div id="mov_tiene_costo_box" class="campo campo-checkbox" style="display:none">
                <label>
                    <input type="checkbox" name="tiene_costo" id="mov_tiene_costo">
                    Esta entrada lleva costo (genera egreso en finanzas)
                </label>
            </div>

            <div id="mov_precio_box" class="campo" style="display:none">
                <label for="mov_precio">Precio de venta unitario *</label>
                <input type="number" name="precio_venta_unitario" id="mov_precio" min="0" step="0.01" placeholder="0.00">
                <div class="campo-ayuda">Esto generará un <strong>ingreso</strong> en finanzas.</div>
            </div>

            <div class="campo">
                <label for="mov_notas">Notas</label>
                <textarea name="notas" id="mov_notas" rows="2" maxlength="500"></textarea>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarMov">Cancelar</button>
                <button type="submit" class="btn btn-primario">Registrar</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal · historial de movimientos -->
<div id="modalHistorial" class="modal-fondo">
    <div class="modal-caja modal-caja-grande">
        <button type="button" class="modal-cerrar" id="cerrarModalHist" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Historial de movimientos</h2>
        <p class="texto-atenuado" id="histProductoNombre" style="margin-top:0">—</p>
        <div id="histLista" class="historial-mov"></div>
    </div>
</div>


<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <h1 class="page-titulo">Inventario</h1>
                <div class="page-subtitulo" id="pageSubtitulo">Cargando…</div>
            </div>
            <div class="page-acciones">
                <a href="bitacora.php" class="btn btn-secundario">Bitácora</a>
                <a href="exportar.php?tab=productos" class="btn btn-secundario">↓ CSV</a>
                <button type="button" class="btn btn-secundario" id="btnNuevoMovimientoGlobal">+ Movimiento</button>
                <button type="button" class="btn btn-primario" id="btnNuevo">+ Nuevo producto</button>
            </div>
        </div>

        <!-- Banner de stock bajo (visible solo si hay items bajo el mínimo) -->
        <div id="lowStockBanner" class="ficha-card ficha-card-peligro" hidden>
            <div class="ficha-card-titulo">
                ⚠ Productos por debajo del mínimo
                <span class="texto-pequeno texto-atenuado" id="lowStockConteo"></span>
            </div>
            <ul id="lowStockLista" class="ficha-lista"></ul>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid kpi-grid-4" id="kpisInventario">
            <div class="kpi-card">
                <div class="kpi-card-titulo">Productos totales</div>
                <div class="kpi-card-valor" id="kpiTotal">—</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-titulo">Stock bajo</div>
                <div class="kpi-card-valor" id="kpiBajos">—</div>
            </div>
            <div class="kpi-card kpi-card-rojo">
                <div class="kpi-card-titulo">Agotados</div>
                <div class="kpi-card-valor" id="kpiAgotados">—</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-titulo">Valor del inventario</div>
                <div class="kpi-card-valor" id="kpiValor">—</div>
            </div>
        </div>

        <!-- Top productos más usados -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Productos más utilizados
                <select id="topProductosMotivo" class="campo-busqueda" style="max-width:200px; min-width:160px">
                    <option value="uso_consulta" selected>Uso en consulta</option>
                    <option value="venta">Ventas</option>
                    <option value="vencimiento">Vencidos</option>
                    <option value="perdida">Pérdidas</option>
                    <option value="todos">Todas las salidas</option>
                </select>
            </div>
            <div class="reporte-grafico" style="height:160px">
                <canvas id="topProductosCanvas" height="160"></canvas>
                <div id="topProductosVacio" class="texto-atenuado texto-pequeno" style="display:none; text-align:center; padding-top: var(--espacio-3xl)">
                    Sin datos para este motivo.
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="ficha-card">
            <div class="ficha-card-titulo">
                Productos
                <input type="search" class="campo-busqueda" id="busquedaInv" placeholder="Buscar…">
            </div>
            <div id="tablaContenedor">
                <div class="tabla-cargando">Cargando…</div>
            </div>
        </div>

    </main>

</div>

<script src="inventario.js?v=<?php echo @filemtime(__DIR__ . '/inventario.js'); ?>"></script>

</body>
</html>

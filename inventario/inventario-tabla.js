/* =====================================================================
 * INVENTARIO · render de tabla, banner de stock bajo y gráfico top
 * ---------------------------------------------------------------------
 * Depende de: inventario-ajax.js (helpers, cacheItems, categoriaLabel,
 * unidadLabel). Las acciones de la tabla (editar, movimiento, etc.)
 * llaman a funciones definidas en inventario-modal.js.
 * ====================================================================*/

/* ---------- Banner de stock bajo / agotado ---------- */
function renderLowStockBanner() {
    const banner = $('#lowStockBanner');
    const lista  = $('#lowStockLista');
    const conteo = $('#lowStockConteo');
    const bajos  = cacheItems.filter(i => i.estado_stock === 'bajo' || i.estado_stock === 'agotado');
    if (!bajos.length) {
        banner.hidden = true;
        return;
    }
    banner.hidden = false;
    conteo.textContent = `(${bajos.length})`;
    lista.innerHTML = bajos.map(i => `
        <li>
            <strong>${escapar(i.nombre)}</strong>
            <span class="texto-atenuado">·</span>
            <span class="${i.estado_stock === 'agotado' ? 'estado-no-disponible' : 'badge-warn'} mono">
                ${i.cantidad_actual} de ${i.cantidad_minima} ${escapar(unidadLabel[i.unidad] || i.unidad || '')}
            </span>
        </li>
    `).join('');
}


/* ---------- Tabla ---------- */
function renderTabla(filtro = '') {
    const cont = $('#tablaContenedor');
    let lista = cacheItems;
    if (filtro) {
        const q = filtro.toLowerCase();
        lista = lista.filter(i =>
            (i.nombre || '').toLowerCase().includes(q) ||
            (i.categoria || '').toLowerCase().includes(q) ||
            (categoriaLabel[i.categoria] || '').toLowerCase().includes(q) ||
            (i.proveedor || '').toLowerCase().includes(q)
        );
    }
    if (!lista.length) {
        cont.innerHTML = '<div class="estado-vacio"><div class="estado-vacio-titulo">No hay productos</div><div class="estado-vacio-desc">Crea uno con el botón "+ Nuevo producto".</div></div>';
        return;
    }

    /* Wrapper con scroll horizontal: cuando la viewport es estrecha o
       el sidebar está abierto la tabla excede el ancho del card y
       provoca que las celdas se compriman y los 4 botones de acción
       se desborden. El wrapper permite scroll lateral sin romper el
       layout. min-width:640px en la tabla asegura que el scroll se
       activa en lugar de aplastar las columnas. */
    cont.innerHTML = `
    <div class="tabla-scroll" style="overflow-x:auto; width:100%;">
        <table class="tabla-datos" style="min-width:640px;">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th style="text-align:right">Stock</th>
                    <th style="text-align:right">Mín.</th>
                    <th style="text-align:right">Costo</th>
                    <th style="text-align:right">Venta</th>
                    <th style="width:240px">Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${lista.map(i => `
                <tr>
                    <td>
                        <strong>${escapar(i.nombre)}</strong>
                        ${i.proveedor ? `<div class="texto-atenuado texto-pequeno">${escapar(i.proveedor)}</div>` : ''}
                    </td>
                    <td>${escapar(categoriaLabel[i.categoria] || i.categoria || '—')}</td>
                    <td style="text-align:right" class="mono">
                        <strong class="${i.estado_stock === 'agotado' ? 'estado-no-disponible' : (i.estado_stock === 'bajo' ? 'badge-warn' : '')}">${i.cantidad_actual}</strong>
                        <span class="texto-atenuado"> ${escapar(unidadLabel[i.unidad] || i.unidad || '')}</span>
                    </td>
                    <td style="text-align:right" class="mono">${i.cantidad_minima}</td>
                    <td style="text-align:right" class="mono">${fmtDinero(i.costo_unitario)}</td>
                    <td style="text-align:right" class="mono">${fmtDinero(i.precio_venta)}</td>
                    <td>
                        <!-- Botones envueltos en flex con wrap: si la columna se aprieta
                             los botones bajan a la siguiente línea en vez de desbordar. -->
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            <button type="button" class="btn-link" data-accion="movimiento" data-id="${i.item_id}">Movimiento</button>
                            <button type="button" class="btn-link" data-accion="historial" data-id="${i.item_id}">Historial</button>
                            <button type="button" class="btn-link" data-accion="editar" data-id="${i.item_id}">Editar</button>
                            <button type="button" class="btn-link btn-link-peligro" data-accion="eliminar" data-id="${i.item_id}">Eliminar</button>
                        </div>
                    </td>
                </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
    `;
}

$('#busquedaInv').addEventListener('input', (e) => renderTabla(e.target.value));


/* ---------- Acciones de la tabla ---------- */
$('#tablaContenedor').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    const id = btn.dataset.id;
    const accion = btn.dataset.accion;
    const item = cacheItems.find(x => String(x.item_id) === String(id));
    if (!item) return;

    if (accion === 'editar')     abrirModalProducto(item);
    if (accion === 'movimiento') abrirModalMovimiento(item);
    if (accion === 'historial')  await abrirHistorial(item);
    if (accion === 'eliminar') {
        if (!confirm(`¿Eliminar el producto "${item.nombre}"?\nLas transacciones financieras vinculadas se preservarán.`)) return;
        try {
            const datos = new FormData();
            datos.append('item_id', id);
            const resp = await fetch('eliminar.php', { method: 'POST', body: datos });
            const json = await resp.json();
            if (!manejarRedirect(json)) return;
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            toast(json.mensaje);
            cargar();
        } catch (e) { toast('No se pudo conectar.', 'error'); }
    }
});


/* ---------- Top 5 productos más usados ---------- */
async function cargarTopProductos() {
    const motivo = $('#topProductosMotivo').value;
    const cv = $('#topProductosCanvas');
    const vacio = $('#topProductosVacio');
    if (!cv) return;

    try {
        const resp = await fetch('top-productos.php?motivo=' + encodeURIComponent(motivo) + '&dias=30');
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) return;

        const productos = json.productos || [];
        const ctx = cv.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const cssW = cv.clientWidth || 600;
        const cssH = cv.clientHeight || 160;
        cv.width  = cssW * dpr;
        cv.height = cssH * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, cssW, cssH);

        if (!productos.length) {
            vacio.style.display = '';
            return;
        }
        vacio.style.display = 'none';

        const root = getComputedStyle(document.documentElement);
        const colAcento = root.getPropertyValue('--color-acento').trim() || '#14b8a6';
        const colTexto  = root.getPropertyValue('--texto-atenuado').trim() || 'rgba(255,255,255,.42)';

        const padding = { top: 8, right: 12, bottom: 8, left: 130 };
        const w = cssW - padding.left - padding.right;
        const h = cssH - padding.top - padding.bottom;
        const max = Math.max(1, ...productos.map(p => p.total));
        const altoBarra = Math.min(22, h / productos.length - 4);
        ctx.font = '11px "Inter", sans-serif';
        ctx.textBaseline = 'middle';

        productos.forEach((p, i) => {
            const y = padding.top + (i * (altoBarra + 4));
            const ancho = (p.total / max) * w;
            const alpha = 1 - (i * 0.15);
            ctx.fillStyle = colAcento;
            ctx.globalAlpha = Math.max(0.4, alpha);
            ctx.fillRect(padding.left, y, ancho, altoBarra);
            ctx.globalAlpha = 1;
            ctx.fillStyle = colTexto;
            ctx.textAlign = 'right';
            const nombreCorto = p.nombre.length > 16 ? p.nombre.substring(0, 15) + '…' : p.nombre;
            ctx.fillText(nombreCorto, padding.left - 8, y + altoBarra / 2);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#fff';
            ctx.fillText(p.total + ' ' + (p.unidad || ''), padding.left + ancho + 6, y + altoBarra / 2);
        });
    } catch (e) {
        // silencioso · no crítico
    }
}

$('#topProductosMotivo').addEventListener('change', cargarTopProductos);
window.addEventListener('resize', () => {
    clearTimeout(window.__resizeTopProd);
    window.__resizeTopProd = setTimeout(cargarTopProductos, 200);
});

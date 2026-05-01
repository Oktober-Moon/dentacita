/* =====================================================================
 * INVENTARIO · lógica frontend
 * ====================================================================*/
(() => {

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');

let cacheItems = [];

/* ---------- Catálogos ---------- */
const motivosPorTipo = {
    entrada: [
        ['compra',               'Compra (genera egreso)'],
        ['donacion',             'Donación'],
        ['ajuste_inicial',       'Ajuste inicial'],
        ['devolucion_proveedor', 'Devolución de proveedor'],
        ['otro',                 'Otro']
    ],
    salida: [
        ['venta',             'Venta (genera ingreso)'],
        ['uso_consulta',      'Uso en consulta'],
        ['vencimiento',       'Vencimiento'],
        ['perdida',           'Pérdida'],
        ['danado',            'Dañado'],
        ['ajuste_inventario', 'Ajuste por merma'],
        ['otro',              'Otro']
    ],
    ajuste: [
        ['ajuste_inventario', 'Ajuste de inventario (stock objetivo)'],
        ['otro',              'Otro']
    ]
};

const motivosLabel = {
    compra: 'Compra', donacion: 'Donación', ajuste_inicial: 'Ajuste inicial',
    devolucion_proveedor: 'Devolución a proveedor', venta: 'Venta',
    uso_consulta: 'Uso en consulta', vencimiento: 'Vencimiento',
    perdida: 'Pérdida', danado: 'Dañado',
    ajuste_inventario: 'Ajuste de inventario', otro: 'Otro'
};

const categoriaLabel = {
    consumibles:  'Consumibles',
    instrumentos: 'Instrumentos',
    materiales:   'Materiales',
    equipamiento: 'Equipamiento',
    medicamentos: 'Medicamentos',
    general:      'General'
};

const unidadLabel = {
    unidad:  'Unidad',
    caja:    'Caja',
    paquete: 'Paquete',
    ml:      'Mililitros',
    gr:      'Gramos',
    par:     'Par'
};


/* ---------- Helpers ---------- */
function toast(msj, tipo = 'ok') {
    const tiposValidos = { ok: 'toast-ok', error: 'toast-error', info: 'toast-info', advertencia: 'toast-advertencia' };
    const cls = tiposValidos[tipo] || 'toast-ok';
    toastEl.textContent = msj;
    toastEl.className = 'toast ' + cls + ' visible';
    setTimeout(() => toastEl.classList.remove('visible'), 2800);
}

function escapar(t) {
    if (t === null || t === undefined) return '';
    return String(t)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function fmtDinero(n) {
    if (n === null || n === undefined || n === '') return '—';
    return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return iso;
    return d.toLocaleDateString('es-MX');
}

function manejarRedirect(json) {
    if (json && json.redirect) { window.location.href = '../index.php'; return false; }
    return true;
}


/* ---------- Modales ---------- */
function abrirModal(m)  { m.classList.add('visible'); }
function cerrarModal(m) { m.classList.remove('visible'); }

const modalProducto  = $('#modalProducto');
const modalMovimiento= $('#modalMovimiento');
const modalHistorial = $('#modalHistorial');

[
  ['#cerrarModalProducto', modalProducto],   ['#cancelarModalProducto', modalProducto],
  ['#cerrarModalMov',     modalMovimiento],  ['#cancelarMov',           modalMovimiento],
  ['#cerrarModalHist',    modalHistorial]
].forEach(([sel, m]) => {
    const el = $(sel);
    if (el) el.addEventListener('click', () => cerrarModal(m));
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    [modalProducto, modalMovimiento, modalHistorial].forEach(m => {
        if (m.classList.contains('visible')) cerrarModal(m);
    });
});

[modalProducto, modalMovimiento, modalHistorial].forEach(m => {
    m.addEventListener('click', (e) => { if (e.target === m) cerrarModal(m); });
});


/* ---------- Cargar productos + KPIs ---------- */
async function cargar() {
    try {
        const resp = await fetch('mostrar.php');
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje || 'Error al cargar.', 'error'); return; }

        cacheItems = json.items || [];
        $('#kpiTotal').textContent    = json.kpis.total_items;
        $('#kpiBajos').textContent    = json.kpis.items_bajos;
        $('#kpiAgotados').textContent = json.kpis.items_agotados;
        $('#kpiValor').textContent    = fmtDinero(json.kpis.valor_inventario);
        $('#pageSubtitulo').textContent = `${cacheItems.length} producto(s) registrados.`;
        renderTabla();
        renderLowStockBanner();
    } catch (e) {
        toast('No se pudo conectar.', 'error');
    }
}

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

    cont.innerHTML = `
    <table class="tabla-datos">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th style="text-align:right">Stock</th>
                <th style="text-align:right">Mín.</th>
                <th style="text-align:right">Costo</th>
                <th style="text-align:right">Venta</th>
                <th style="width:200px">Acciones</th>
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
                    <button type="button" class="btn-link" data-accion="movimiento" data-id="${i.item_id}">Movimiento</button>
                    <button type="button" class="btn-link" data-accion="historial" data-id="${i.item_id}">Historial</button>
                    <button type="button" class="btn-link" data-accion="editar" data-id="${i.item_id}">Editar</button>
                    <button type="button" class="btn-link btn-link-peligro" data-accion="eliminar" data-id="${i.item_id}">Eliminar</button>
                </td>
            </tr>
            `).join('')}
        </tbody>
    </table>
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

    if (accion === 'editar') {
        abrirModalProducto(item);
    }
    if (accion === 'movimiento') {
        abrirModalMovimiento(item);
    }
    if (accion === 'historial') {
        await abrirHistorial(item);
    }
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


/* ---------- Modal producto ---------- */
$('#btnNuevo').addEventListener('click', () => abrirModalProducto(null));

function abrirModalProducto(item) {
    $('#formProducto').reset();
    if (item) {
        $('#tituloModalProducto').textContent = 'Editar producto';
        $('#prod_id').value             = item.item_id;
        $('#prod_nombre').value         = item.nombre || '';
        $('#prod_categoria').value      = categoriaLabel[item.categoria] ? item.categoria : 'general';
        $('#prod_unidad').value         = unidadLabel[item.unidad] ? item.unidad : 'unidad';
        $('#prod_cantidad_minima').value= item.cantidad_minima ?? 0;
        $('#prod_costo').value          = item.costo_unitario ?? '';
        $('#prod_precio').value         = item.precio_venta ?? '';
        $('#prod_proveedor').value      = item.proveedor || '';
        $('#prod_notas').value          = item.notas || '';
        $('#campos-cantidades').querySelector('#prod_cantidad_actual').parentElement.style.display = 'none';
    } else {
        $('#tituloModalProducto').textContent = 'Nuevo producto';
        $('#prod_id').value = '';
        $('#prod_categoria').value = 'general';
        $('#prod_unidad').value    = 'unidad';
        $('#campos-cantidades').querySelector('#prod_cantidad_actual').parentElement.style.display = '';
    }
    abrirModal(modalProducto);
}

$('#formProducto').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formProducto'));
    const id    = $('#prod_id').value;
    const url   = id ? 'actualizar.php' : 'guardar.php';
    try {
        const resp = await fetch(url, { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal(modalProducto);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Modal movimiento ---------- */
function actualizarMotivos() {
    const tipo = $('#mov_tipo').value;
    const sel  = $('#mov_motivo');
    sel.innerHTML = motivosPorTipo[tipo].map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
    actualizarCamposContextuales();
}

function actualizarCamposContextuales() {
    const tipo   = $('#mov_tipo').value;
    const motivo = $('#mov_motivo').value;

    // Reset
    $('#mov_costo_box').style.display       = 'none';
    $('#mov_tiene_costo_box').style.display = 'none';
    $('#mov_precio_box').style.display      = 'none';
    $('#mov_costo').required  = false;
    $('#mov_precio').required = false;

    // Etiqueta de cantidad
    if (tipo === 'ajuste') {
        $('#mov_cantidad_label').textContent = 'Stock objetivo *';
        $('#mov_cantidad').min = 0;
    } else {
        $('#mov_cantidad_label').textContent = 'Cantidad *';
        $('#mov_cantidad').min = 1;
    }

    if (tipo === 'entrada' && motivo === 'compra') {
        $('#mov_costo_box').style.display = '';
        $('#mov_costo').required = true;
    }
    if (tipo === 'entrada' && motivo === 'otro') {
        $('#mov_tiene_costo_box').style.display = '';
        $('#mov_costo_box').style.display       = '';
    }
    if (tipo === 'salida' && motivo === 'venta') {
        $('#mov_precio_box').style.display = '';
        $('#mov_precio').required = true;
    }
}

$('#mov_tipo').addEventListener('change', actualizarMotivos);
$('#mov_motivo').addEventListener('change', actualizarCamposContextuales);

async function cargarCitasParaSelect() {
    const sel = $('#mov_cita');
    if (!sel) return;
    // Reiniciamos el select cada vez por si hay nuevas citas
    sel.innerHTML = '<option value="">— Ninguna —</option>';
    try {
        const resp = await fetch('citas-recientes.php');
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) return;
        (json.citas || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.cita_id;
            const fecha = (c.fecha_hora_inicio || '').substring(0, 10);
            opt.textContent = `${fecha} · ${c.paciente_nombre} · ${c.titulo}`;
            sel.appendChild(opt);
        });
    } catch (e) {
        // silencioso · el campo es opcional
    }
}

async function abrirModalMovimiento(item) {
    $('#formMovimiento').reset();
    $('#mov_selector_producto').style.display = 'none';
    $('#mov_item_id').value      = item.item_id;
    $('#movProductoNombre').textContent = `${item.nombre} · stock actual: ${item.cantidad_actual} ${unidadLabel[item.unidad] || item.unidad}`;
    $('#mov_tipo').value          = 'entrada';
    $('#mov_fecha').value         = new Date().toISOString().slice(0, 10);
    // pre-rellenar costo/precio sugeridos
    if (item.costo_unitario) $('#mov_costo').value  = item.costo_unitario;
    if (item.precio_venta)   $('#mov_precio').value = item.precio_venta;
    actualizarMotivos();
    await cargarCitasParaSelect();
    abrirModal(modalMovimiento);
}

/* ---------- Botón global "+ Movimiento" (sin item preseleccionado) ---------- */
$('#btnNuevoMovimientoGlobal').addEventListener('click', async () => {
    if (!cacheItems.length) {
        toast('Primero crea al menos un producto.', 'advertencia');
        return;
    }
    $('#formMovimiento').reset();
    $('#mov_item_id').value = '';
    $('#movProductoNombre').textContent = '—';
    $('#mov_tipo').value  = 'entrada';
    $('#mov_fecha').value = new Date().toISOString().slice(0, 10);

    // Mostrar selector de producto y rellenarlo
    const selProd = $('#mov_selector_select');
    selProd.innerHTML = '<option value="">— Selecciona un producto —</option>' +
        cacheItems.map(i => `<option value="${i.item_id}" data-costo="${i.costo_unitario || ''}" data-precio="${i.precio_venta || ''}" data-stock="${i.cantidad_actual}" data-unidad="${escapar(i.unidad || '')}" data-nombre="${escapar(i.nombre)}">${escapar(i.nombre)}</option>`).join('');
    $('#mov_selector_producto').style.display = '';

    actualizarMotivos();
    await cargarCitasParaSelect();
    abrirModal(modalMovimiento);
});

/* Al cambiar producto en el selector global, sincronizar item_id + sugerencias */
$('#mov_selector_select').addEventListener('change', (e) => {
    const opt = e.target.selectedOptions[0];
    if (!opt || !opt.value) {
        $('#mov_item_id').value = '';
        $('#movProductoNombre').textContent = '—';
        return;
    }
    $('#mov_item_id').value = opt.value;
    $('#movProductoNombre').textContent =
        `${opt.dataset.nombre} · stock actual: ${opt.dataset.stock} ${unidadLabel[opt.dataset.unidad] || opt.dataset.unidad}`;
    if (opt.dataset.costo)  $('#mov_costo').value  = opt.dataset.costo;
    if (opt.dataset.precio) $('#mov_precio').value = opt.dataset.precio;
});

$('#formMovimiento').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formMovimiento'));
    try {
        const resp = await fetch('movimiento-guardar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal(modalMovimiento);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Modal historial ---------- */
async function abrirHistorial(item) {
    $('#histProductoNombre').textContent = item.nombre;
    $('#histLista').innerHTML = '<div class="tabla-cargando">Cargando…</div>';
    abrirModal(modalHistorial);

    try {
        const resp = await fetch('movimientos.php?item_id=' + item.item_id);
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { $('#histLista').innerHTML = '<div class="texto-atenuado">Error al cargar.</div>'; return; }

        const movs = json.movimientos || [];
        if (!movs.length) {
            $('#histLista').innerHTML = '<div class="texto-atenuado">Sin movimientos registrados.</div>';
            return;
        }

        $('#histLista').innerHTML = `
        <table class="tabla-datos">
            <thead><tr>
                <th>Fecha</th><th>Tipo</th><th>Motivo</th><th style="text-align:right">Cantidad</th>
                <th style="text-align:right">Costo/Precio</th><th>Notas</th>
            </tr></thead>
            <tbody>
                ${movs.map(m => `
                <tr>
                    <td class="mono">${fmtFecha(m.fecha)}</td>
                    <td><span class="badge badge-${m.tipo}">${escapar(m.tipo)}</span></td>
                    <td>${escapar(motivosLabel[m.motivo] || m.motivo)}</td>
                    <td style="text-align:right" class="mono">${m.cantidad} ${escapar(unidadLabel[m.unidad] || m.unidad || '')}</td>
                    <td style="text-align:right" class="mono">${
                        m.costo_unitario ? '↓ ' + fmtDinero(m.costo_unitario) :
                        (m.precio_venta_unitario ? '↑ ' + fmtDinero(m.precio_venta_unitario) : '—')
                    }</td>
                    <td>${escapar(m.notas || '')}</td>
                </tr>`).join('')}
            </tbody>
        </table>
        `;
    } catch (e) {
        $('#histLista').innerHTML = '<div class="texto-atenuado">Error al cargar.</div>';
    }
}


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
            // Opacidad descendente para indicar ranking visual
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


cargar();
cargarTopProductos();

})();

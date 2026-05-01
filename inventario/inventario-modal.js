/* =====================================================================
 * INVENTARIO · modales (producto, movimiento, historial)
 * ---------------------------------------------------------------------
 * Depende de: inventario-ajax.js (helpers, cacheItems, categoriaLabel,
 * unidadLabel, modalProducto/Movimiento/Historial, abrirModal/cerrarModal,
 * cargar) e inventario-tabla.js (renderTabla / renderLowStockBanner se
 * disparan vía cargar()).
 *
 * Último archivo en cargar; al final dispara la carga inicial.
 * ====================================================================*/

/* ---------- Catálogos de motivos ---------- */
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

    $('#mov_costo_box').style.display       = 'none';
    $('#mov_tiene_costo_box').style.display = 'none';
    $('#mov_precio_box').style.display      = 'none';
    $('#mov_costo').required  = false;
    $('#mov_precio').required = false;

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
    if (item.costo_unitario) $('#mov_costo').value  = item.costo_unitario;
    if (item.precio_venta)   $('#mov_precio').value = item.precio_venta;
    actualizarMotivos();
    await cargarCitasParaSelect();
    abrirModal(modalMovimiento);
}

/* Botón global "+ Movimiento" (sin item preseleccionado) */
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

    const selProd = $('#mov_selector_select');
    selProd.innerHTML = '<option value="">— Selecciona un producto —</option>' +
        cacheItems.map(i => `<option value="${i.item_id}" data-costo="${i.costo_unitario || ''}" data-precio="${i.precio_venta || ''}" data-stock="${i.cantidad_actual}" data-unidad="${escapar(i.unidad || '')}" data-nombre="${escapar(i.nombre)}">${escapar(i.nombre)}</option>`).join('');
    $('#mov_selector_producto').style.display = '';

    actualizarMotivos();
    await cargarCitasParaSelect();
    abrirModal(modalMovimiento);
});

/* Sincronizar item_id + sugerencias al cambiar producto en selector global */
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


/* ---------- Inicialización ---------- */
cargar();
cargarTopProductos();

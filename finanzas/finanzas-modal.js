/* =====================================================================
 * FINANZAS · modales (transacción, cobro, reembolso) + anulaciones
 * ---------------------------------------------------------------------
 * Último archivo en cargar. Al final dispara la carga inicial:
 *   cargar()        → tabla + KPIs + cobrables (finanzas-ajax)
 *   cargarReporte() → gráficos del dashboard (finanzas-dashboard)
 * ====================================================================*/

/* ---------- Catálogos cerrados de categorías (espejo de _catalogos.php) ---------- */
const FIN_CATEGORIAS = {
    ingreso: ['Consulta', 'Tratamiento', 'Procedimiento', 'Producto', 'Otro'],
    egreso:  ['Materiales', 'Equipo', 'Alquiler', 'Servicios', 'Marketing', 'Software', 'Transporte', 'Otro']
};

function repoblarCategoriasTrans(tipo, valorActual) {
    const sel = $('#trans_categoria');
    if (!sel) return;
    const opciones = FIN_CATEGORIAS[tipo] || [];
    sel.innerHTML = opciones.map(c =>
        `<option value="${escapar(c)}">${escapar(c)}</option>`
    ).join('');
    if (valorActual && opciones.includes(valorActual)) {
        sel.value = valorActual;
    }
}


/* ---------- Modal Cobrar cita ---------- */
$('#formCobro').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formCobro'));
    try {
        const resp = await fetch('cobrar-cita.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal(modalCobro);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Modal transacción manual (crear/editar) ---------- */
$('#btnNuevaTrans').addEventListener('click', () => abrirModalTrans(null));

function abrirModalTrans(t) {
    $('#formTrans').reset();
    if (t) {
        $('#tituloModalTrans').textContent = 'Editar transacción';
        $('#trans_id').value         = t.transaccion_id;
        $('#trans_tipo').value       = t.tipo;
        repoblarCategoriasTrans(t.tipo, t.categoria);
        $('#trans_monto').value      = t.monto;
        $('#trans_fecha').value      = t.fecha;
        $('#trans_metodo').value     = t.metodo_pago || 'efectivo';
        $('#trans_descripcion').value= t.descripcion || '';
    } else {
        $('#tituloModalTrans').textContent = 'Nueva transacción';
        $('#trans_id').value = '';
        $('#trans_tipo').value = 'ingreso';
        repoblarCategoriasTrans('ingreso');
        $('#trans_fecha').value = new Date().toISOString().slice(0, 10);
    }
    abrirModal(modalTrans);
}

$('#trans_tipo').addEventListener('change', (e) => {
    repoblarCategoriasTrans(e.target.value);
});

$('#formTrans').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formTrans'));
    const id    = $('#trans_id').value;
    const url   = id ? 'actualizar.php' : 'guardar.php';
    try {
        const resp = await fetch(url, { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal(modalTrans);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});

async function eliminarTrans(id) {
    if (!confirm('¿Eliminar esta transacción?')) return;
    const datos = new FormData();
    datos.append('transaccion_id', id);
    try {
        const resp = await fetch('eliminar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
}

async function anularTransaccion(id) {
    const motivo = prompt('Motivo de la anulación:');
    if (!motivo) return;
    const datos = new FormData();
    datos.append('transaccion_id', id);
    datos.append('motivo', motivo);
    try {
        const resp = await fetch('anular.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje, 'advertencia');
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
}


/* ---------- Reembolsos ---------- */
function abrirModalReembolso(trans) {
    $('#formReembolso').reset();
    $('#reembolso_cita_id').value = trans.cita_id;
    $('#reembolsoDetalle').textContent = `Cita: ${trans.cita_titulo || '—'} · Cobro original: ${fmtDinero(trans.monto)}`;
    abrirModal(modalReembolso);
}

$('#formReembolso').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formReembolso'));
    datos.append('accion', 'crear');
    try {
        const resp = await fetch('reembolso.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal(modalReembolso);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});

async function anularReembolso(id) {
    const motivo = prompt('Motivo de la anulación:');
    if (!motivo) return;
    const datos = new FormData();
    datos.append('accion', 'anular');
    datos.append('transaccion_id', id);
    datos.append('motivo', motivo);
    try {
        const resp = await fetch('reembolso.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
}


/* ---------- Inicialización ---------- */
cargar();
cargarReporte();

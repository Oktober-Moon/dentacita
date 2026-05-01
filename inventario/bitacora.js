/* =====================================================================
 * INVENTARIO · BITÁCORA · lógica frontend (vainilla JS)
 * ====================================================================*/
(() => {

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');

const motivosLabel = {
    compra: 'Compra', donacion: 'Donación', ajuste_inicial: 'Ajuste inicial',
    devolucion_proveedor: 'Devolución a proveedor', venta: 'Venta',
    uso_consulta: 'Uso en consulta', vencimiento: 'Vencimiento',
    perdida: 'Pérdida', ajuste_inventario: 'Ajuste de inventario', otro: 'Otro'
};

const estado = { pagina: 1 };


function toast(msj, tipo = 'ok') {
    const tiposValidos = { ok: 'toast-ok', error: 'toast-error', info: 'toast-info', advertencia: 'toast-advertencia' };
    const cls = tiposValidos[tipo] || 'toast-ok';
    toastEl.textContent = msj;
    toastEl.className = 'toast ' + cls + ' visible';
    setTimeout(() => toastEl.classList.remove('visible'), 2400);
}

function escapar(t) {
    if (t === null || t === undefined) return '';
    return String(t)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function fmtFecha(iso) {
    if (!iso) return '—';
    const partes = String(iso).split(' ')[0].split('-');
    if (partes.length !== 3) return iso;
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function fmtDinero(n) {
    if (n === null || n === undefined || n === '') return '—';
    return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function manejarRedirect(json) {
    if (json && json.redirect) { window.location.href = '../index.php'; return false; }
    return true;
}


async function cargar() {
    const params = new URLSearchParams();
    const item   = $('#filtro_item').value;
    const tipo   = $('#filtro_tipo').value;
    const motivo = $('#filtro_motivo').value;
    const desde  = $('#filtro_desde').value;
    const hasta  = $('#filtro_hasta').value;
    if (item)   params.set('item_id', item);
    if (tipo)   params.set('tipo', tipo);
    if (motivo) params.set('motivo', motivo);
    if (desde)  params.set('desde', desde);
    if (hasta)  params.set('hasta', hasta);
    params.set('pag', estado.pagina);

    $('#bitacoraTabla').innerHTML = '<div class="tabla-cargando">Cargando…</div>';

    try {
        const resp = await fetch('bitacora-listar.php?' + params.toString());
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) {
            $('#bitacoraTabla').innerHTML = '<div class="tabla-vacio">' + escapar(json.mensaje || 'Error.') + '</div>';
            return;
        }
        renderTabla(json.movimientos);
        renderConteo(json.total, json.pagina, json.por_pagina);
        renderPaginacion(json.pagina, json.total_paginas);
    } catch (e) {
        $('#bitacoraTabla').innerHTML = '<div class="tabla-vacio">No se pudo conectar.</div>';
    }
}

function renderTabla(movs) {
    if (!movs || movs.length === 0) {
        $('#bitacoraTabla').innerHTML =
            '<div class="estado-vacio">'
          + '<div class="estado-vacio-titulo">Sin movimientos</div>'
          + '<div class="estado-vacio-desc">Ajusta los filtros o registra movimientos desde el inventario.</div>'
          + '</div>';
        return;
    }

    const filas = movs.map(m => {
        const motivo = motivosLabel[m.motivo] || m.motivo;
        let signo = '', claseSigno = '';
        if (m.tipo === 'entrada') { signo = '+'; claseSigno = 'texto-verde'; }
        else if (m.tipo === 'salida') { signo = '−'; claseSigno = 'texto-rojo'; }
        let monto = '—';
        if (m.tipo === 'entrada' && m.tiene_costo == 1 && m.costo_unitario)
            monto = fmtDinero(Number(m.costo_unitario) * Number(m.cantidad));
        else if (m.tipo === 'salida' && m.precio_venta_unitario)
            monto = fmtDinero(Number(m.precio_venta_unitario) * Number(m.cantidad));
        return `
            <tr>
                <td class="mono">${fmtFecha(m.fecha)}</td>
                <td>${escapar(m.item_nombre)}</td>
                <td><span class="badge badge-${escapar(m.tipo)}">${escapar(m.tipo)}</span></td>
                <td>${escapar(motivo)}</td>
                <td style="text-align:right" class="mono ${claseSigno}">${signo}${m.cantidad} ${escapar(m.unidad || '')}</td>
                <td style="text-align:right" class="mono">${monto}</td>
                <td class="texto-pequeno">${escapar(m.notas || '')}</td>
            </tr>`;
    }).join('');

    $('#bitacoraTabla').innerHTML = `
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <th style="text-align:right">Cantidad</th>
                    <th style="text-align:right">Importe</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>${filas}</tbody>
        </table>`;
}

function renderConteo(total, pagina, porPag) {
    if (!total) { $('#conteoMovs').textContent = ''; return; }
    const desde = (pagina - 1) * porPag + 1;
    const hasta = Math.min(pagina * porPag, total);
    $('#conteoMovs').textContent = `${desde}–${hasta} de ${total}`;
}

function renderPaginacion(pagina, totalPaginas) {
    const cont = $('#bitacoraPag');
    if (totalPaginas <= 1) { cont.innerHTML = ''; return; }
    cont.innerHTML = `
        <button class="btn-pag" data-pag="${pagina - 1}" ${pagina <= 1 ? 'disabled' : ''}>‹ Anterior</button>
        <span class="texto-atenuado">Página ${pagina} de ${totalPaginas}</span>
        <button class="btn-pag" data-pag="${pagina + 1}" ${pagina >= totalPaginas ? 'disabled' : ''}>Siguiente ›</button>`;
}

$('#bitacoraPag').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-pag]');
    if (!btn || btn.disabled) return;
    estado.pagina = parseInt(btn.dataset.pag, 10);
    cargar();
});

$('#btnAplicar').addEventListener('click', () => { estado.pagina = 1; cargar(); });
$('#btnLimpiar').addEventListener('click', () => {
    $('#filtro_item').value   = '';
    $('#filtro_tipo').value   = '';
    $('#filtro_motivo').value = '';
    $('#filtro_desde').value  = '';
    $('#filtro_hasta').value  = '';
    estado.pagina = 1;
    cargar();
});

/* Presets de fecha (idéntico a finanzas) */
function aplicarPreset(preset) {
    const hoy = new Date();
    const fmt = (d) => d.toISOString().slice(0, 10);
    let desde = '', hasta = fmt(hoy);
    if (preset === 'hoy')   desde = fmt(hoy);
    else if (preset === '7d')  { const d = new Date(hoy); d.setDate(d.getDate() - 6);  desde = fmt(d); }
    else if (preset === '30d') { const d = new Date(hoy); d.setDate(d.getDate() - 29); desde = fmt(d); }
    else if (preset === '3m')  { const d = new Date(hoy); d.setMonth(d.getMonth() - 3); desde = fmt(d); }
    else if (preset === 'ano') { desde = `${hoy.getFullYear()}-01-01`; }
    $('#filtro_desde').value = desde;
    $('#filtro_hasta').value = hasta;
    estado.pagina = 1;
    cargar();
}
document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => aplicarPreset(btn.dataset.preset));
});

cargar();

})();

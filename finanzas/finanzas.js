/* =====================================================================
 * FINANZAS · lógica frontend
 * ====================================================================*/
(() => {

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');
let cacheTrans = [];
let cacheCobrables = [];

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

function fmtDinero(n, mostrarSigno = false) {
    if (n === null || n === undefined || n === '') return '—';
    const num = Number(n);
    const sigPrefix = (mostrarSigno && num > 0) ? '+' : '';
    return sigPrefix + '$' + num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return iso;
    return d.toLocaleDateString('es-MX');
}

function fmtFechaHora(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return iso;
    return d.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
}

function manejarRedirect(json) {
    if (json && json.redirect) { window.location.href = '../index.php'; return false; }
    return true;
}


/* ---------- Modales ---------- */
function abrirModal(m)  { m.classList.add('visible'); }
function cerrarModal(m) { m.classList.remove('visible'); }

const modalTrans     = $('#modalTrans');
const modalCobro     = $('#modalCobro');
const modalReembolso = $('#modalReembolso');

[
  ['#cerrarModalTrans',     modalTrans],     ['#cancelarTrans',     modalTrans],
  ['#cerrarModalCobro',     modalCobro],     ['#cancelarCobro',     modalCobro],
  ['#cerrarModalReembolso', modalReembolso], ['#cancelarReembolso', modalReembolso]
].forEach(([sel, m]) => $(sel).addEventListener('click', () => cerrarModal(m)));

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    [modalTrans, modalCobro, modalReembolso].forEach(m => {
        if (m.classList.contains('visible')) cerrarModal(m);
    });
});
[modalTrans, modalCobro, modalReembolso].forEach(m => {
    m.addEventListener('click', (e) => { if (e.target === m) cerrarModal(m); });
});


/* ---------- Catálogos cerrados de categorías (espejo de finanzas/_catalogos.php) ---------- */
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

/* ---------- Cargar datos ---------- */
async function cargar() {
    const tipo       = $('#filtroTipo').value;
    const estado     = $('#filtroEstado').value;
    const desde      = $('#filtroDesde').value;
    const hasta      = $('#filtroHasta').value;
    const categoria  = $('#filtroCategoria').value;
    const paciente   = $('#filtroPaciente').value;
    const metodo     = $('#filtroMetodo').value;
    const params = new URLSearchParams();
    if (tipo)      params.set('tipo', tipo);
    if (estado)    params.set('estado', estado);
    if (desde)     params.set('desde', desde);
    if (hasta)     params.set('hasta', hasta);
    if (categoria) params.set('categoria', categoria);
    if (paciente)  params.set('paciente_id', paciente);
    if (metodo)    params.set('metodo', metodo);

    try {
        const resp = await fetch('mostrar.php?' + params.toString());
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje || 'Error al cargar.', 'error'); return; }

        cacheTrans = json.transacciones || [];
        cacheCobrables = json.citas_cobrables || [];

        const k = json.kpis;
        $('#kpiIngresoMes').textContent = fmtDinero(k.ingresos_mes);
        $('#kpiEgresoMes').textContent  = fmtDinero(k.egresos_mes);
        $('#kpiBalanceMes').textContent = fmtDinero(k.balance_mes);
        $('#kpiBalanceMesCard').classList.toggle('kpi-card-verde', k.balance_mes >= 0);
        $('#kpiBalanceMesCard').classList.toggle('kpi-card-rojo',  k.balance_mes < 0);

        const kpiProm = $('#kpiPromedioMes');
        if (kpiProm) kpiProm.textContent = fmtDinero(k.promedio_mes);

        $('#kpiIngresoAno').textContent = fmtDinero(k.ingresos_ano);
        $('#kpiEgresoAno').textContent  = fmtDinero(k.egresos_ano);
        $('#kpiBalanceAno').textContent = fmtDinero(k.balance_ano);

        renderCobrables();
        renderTabla();
        const total = cacheTrans.length;
        const conteo = $('#conteoTrans');
        if (conteo) conteo.textContent = total ? (total + ' resultado' + (total === 1 ? '' : 's')) : '';
    } catch (e) {
        toast('No se pudo conectar.', 'error');
    }
}

['#filtroTipo','#filtroEstado','#filtroDesde','#filtroHasta','#filtroPaciente','#filtroMetodo','#filtroCategoria']
    .forEach(sel => $(sel).addEventListener('change', cargar));

/* ---------- Presets de rango de fecha ---------- */
function aplicarPreset(preset) {
    const hoy = new Date();
    const fmt = (d) => d.toISOString().slice(0, 10);
    let desde = '', hasta = fmt(hoy);
    if (preset === 'hoy')   desde = fmt(hoy);
    else if (preset === '7d')  { const d = new Date(hoy); d.setDate(d.getDate() - 6);  desde = fmt(d); }
    else if (preset === '30d') { const d = new Date(hoy); d.setDate(d.getDate() - 29); desde = fmt(d); }
    else if (preset === '3m')  { const d = new Date(hoy); d.setMonth(d.getMonth() - 3); desde = fmt(d); }
    else if (preset === 'ano') { desde = `${hoy.getFullYear()}-01-01`; }
    $('#filtroDesde').value = desde;
    $('#filtroHasta').value = hasta;
    cargar();
}
document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => aplicarPreset(btn.dataset.preset));
});

$('#btnLimpiarFiltros').addEventListener('click', () => {
    $('#filtroTipo').value = '';
    $('#filtroEstado').value = 'activa';
    $('#filtroDesde').value = '';
    $('#filtroHasta').value = '';
    $('#filtroCategoria').value = '';
    $('#filtroPaciente').value = '';
    $('#filtroMetodo').value = '';
    cargar();
});


/* ---------- Citas pendientes de cobro ---------- */
function renderCobrables() {
    const card = $('#cobrablesCard');
    const cont = $('#cobrablesLista');
    if (!cacheCobrables.length) {
        card.style.display = 'none';
        return;
    }
    card.style.display = '';
    cont.innerHTML = `
    <table class="tabla-datos">
        <thead><tr>
            <th>Fecha</th><th>Paciente</th><th>Concepto</th>
            <th style="text-align:right">Monto</th><th></th>
        </tr></thead>
        <tbody>
            ${cacheCobrables.map(c => `
            <tr>
                <td class="mono">${fmtFecha(c.fecha_hora_inicio)}</td>
                <td>${escapar(c.paciente_nombre)}</td>
                <td>${escapar(c.titulo)}</td>
                <td style="text-align:right" class="mono">${fmtDinero(c.precio)}</td>
                <td><button type="button" class="btn btn-primario btn-sm" data-cobrar="${c.cita_id}">Cobrar</button></td>
            </tr>`).join('')}
        </tbody>
    </table>`;
}

$('#cobrablesLista').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-cobrar]');
    if (!btn) return;
    const id   = btn.dataset.cobrar;
    const cita = cacheCobrables.find(c => String(c.cita_id) === String(id));
    if (!cita) return;
    $('#cobro_cita_id').value     = id;
    $('#cobro_monto').value       = cita.precio;
    $('#cobroDetalle').textContent = `${cita.titulo} · ${cita.paciente_nombre} · ${fmtFecha(cita.fecha_hora_inicio)}`;
    abrirModal(modalCobro);
});

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


/* ---------- Tabla principal ---------- */
function renderTabla() {
    const cont = $('#tablaContenedor');
    if (!cacheTrans.length) {
        cont.innerHTML = '<div class="estado-vacio"><div class="estado-vacio-titulo">Sin movimientos</div><div class="estado-vacio-desc">Registra una transacción con el botón "+ Nueva transacción".</div></div>';
        return;
    }

    cont.innerHTML = `
    <table class="tabla-datos">
        <thead>
            <tr>
                <th>Fecha</th><th>Tipo</th><th>Categoría</th><th>Descripción</th>
                <th>Vínculo</th><th style="text-align:right">Monto</th>
                <th style="width:160px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            ${cacheTrans.map(t => {
                const auditada = t.inventario_movimiento_id || t.cita_id;
                const reembolso = t.categoria === 'Reembolso';
                const anulada   = t.estado === 'anulada';
                let vinculo = '—';
                if (t.cita_titulo)         vinculo = `<span class="texto-atenuado">Cita:</span> ${escapar(t.cita_titulo)}`;
                else if (t.item_nombre)    vinculo = `<span class="texto-atenuado">Inventario:</span> ${escapar(t.item_nombre)}`;
                else if (t.paciente_nombre)vinculo = escapar(t.paciente_nombre);
                return `
                <tr class="${anulada ? 'fila-anulada' : ''}">
                    <td class="mono">${fmtFecha(t.fecha)}</td>
                    <td><span class="badge badge-${t.tipo}">${escapar(t.tipo)}</span>${reembolso ? ' <span class="badge badge-reembolso">reembolso</span>' : ''}</td>
                    <td>${escapar(t.categoria)}</td>
                    <td>
                        ${escapar(t.descripcion || '')}
                        ${anulada ? `<div class="texto-atenuado texto-pequeno">Anulada: ${escapar(t.motivo_anulacion || '')}</div>` : ''}
                    </td>
                    <td>${vinculo}</td>
                    <td style="text-align:right" class="mono ${t.tipo === 'ingreso' ? 'texto-verde' : 'texto-rojo'}">
                        ${fmtDinero(t.monto)}
                    </td>
                    <td>
                        ${t.cita_id && !reembolso && !anulada
                            ? `<button type="button" class="btn-link" data-accion="reembolsar" data-id="${t.transaccion_id}">Reembolsar</button>`
                            : ''}
                        ${reembolso && !anulada
                            ? `<button type="button" class="btn-link btn-link-peligro" data-accion="anular-reembolso" data-id="${t.transaccion_id}">Anular</button>`
                            : ''}
                        ${!reembolso && !anulada
                            ? `<button type="button" class="btn-link btn-link-peligro" data-accion="anular-trans" data-id="${t.transaccion_id}">Anular</button>`
                            : ''}
                        ${!auditada && !reembolso && !anulada
                            ? `<button type="button" class="btn-link" data-accion="editar" data-id="${t.transaccion_id}">Editar</button>
                               <button type="button" class="btn-link btn-link-peligro" data-accion="eliminar" data-id="${t.transaccion_id}">Eliminar</button>`
                            : ''}
                    </td>
                </tr>`;
            }).join('')}
        </tbody>
    </table>`;
}

$('#tablaContenedor').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    const id = btn.dataset.id;
    const accion = btn.dataset.accion;
    const trans = cacheTrans.find(t => String(t.transaccion_id) === String(id));
    if (!trans) return;

    if (accion === 'editar')           abrirModalTrans(trans);
    if (accion === 'eliminar')         await eliminarTrans(id);
    if (accion === 'reembolsar')       abrirModalReembolso(trans);
    if (accion === 'anular-reembolso') await anularReembolso(id);
    if (accion === 'anular-trans')     await anularTransaccion(id);
});

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


/* ---------- Crear / editar transacción manual ---------- */
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


/* ---------- Reporte mensual (canvas) ---------- */
let cacheIngresosCat = [];
let modoGraficoCat = 'bar'; // bar | pie
let modoReporte = 'mensual'; // mensual | diario

async function cargarReporte() {
    try {
        const params = modoReporte === 'diario'
            ? 'modo=diario&dias=30'
            : 'modo=mensual&meses=6';
        const resp = await fetch('reporte.php?' + params);
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) {
            $('#reporteEstado').textContent = 'Error al cargar reporte.';
            return;
        }
        renderGrafico(json.serie || []);
        renderTopCategorias(json.top_categorias_egreso || []);
        cacheIngresosCat = json.ingresos_por_categoria || [];
        renderIngresosCategoria();
        // Actualizar título según modo
        const titulo = $('#reporteTitulo');
        if (titulo) titulo.textContent = (modoReporte === 'diario')
            ? 'Últimos 30 días'
            : 'Últimos 6 meses';
        // Estado activo de los toggles
        document.querySelectorAll('[data-reporte-modo]').forEach(b => {
            b.classList.toggle('btn-primario', b.dataset.reporteModo === modoReporte);
            b.classList.toggle('btn-secundario', b.dataset.reporteModo !== modoReporte);
        });
    } catch (e) {
        $('#reporteEstado').textContent = 'Sin conexión.';
    }
}

document.querySelectorAll('[data-reporte-modo]').forEach(btn => {
    btn.addEventListener('click', () => {
        modoReporte = btn.dataset.reporteModo;
        cargarReporte();
    });
});

function renderGrafico(serie) {
    const cv = document.getElementById('reporteCanvas');
    if (!cv || !serie.length) return;

    const dpr = window.devicePixelRatio || 1;
    const cssW = cv.clientWidth || 600;
    const cssH = cv.clientHeight || 220;
    cv.width  = cssW * dpr;
    cv.height = cssH * dpr;
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    const padding = { top: 16, right: 12, bottom: 30, left: 50 };
    const w = cssW - padding.left - padding.right;
    const h = cssH - padding.top  - padding.bottom;

    // Tokens (leídos del DOM para respetar la paleta)
    const root = getComputedStyle(document.documentElement);
    const colExito  = root.getPropertyValue('--color-exito').trim()      || '#2ECC8A';
    const colPelig  = root.getPropertyValue('--color-peligro').trim()    || '#E84545';
    const colTexto  = root.getPropertyValue('--texto-atenuado').trim()   || 'rgba(255,255,255,.42)';
    const colBorde  = root.getPropertyValue('--vidrio-borde').trim()     || 'rgba(255,255,255,.09)';

    const max = Math.max(1, ...serie.flatMap(s => [s.ingresos, s.egresos]));
    const yTicks = 4;

    // Ejes / grid horizontal
    ctx.strokeStyle = colBorde; ctx.lineWidth = 1;
    ctx.fillStyle = colTexto; ctx.font = '10px "JetBrains Mono", monospace';
    ctx.textBaseline = 'middle';
    for (let i = 0; i <= yTicks; i++) {
        const y = padding.top + (h * i / yTicks);
        ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(padding.left + w, y); ctx.stroke();
        const v = max * (1 - i / yTicks);
        ctx.textAlign = 'right';
        ctx.fillText('$' + Math.round(v / 1000) + 'k', padding.left - 6, y);
    }

    // Barras
    const grupos = serie.length;
    const grupoW = w / grupos;
    const barW   = Math.min(22, (grupoW - 14) / 2);
    serie.forEach((s, i) => {
        const x0 = padding.left + grupoW * i + grupoW / 2;
        const hI = (s.ingresos / max) * h;
        const hE = (s.egresos  / max) * h;

        ctx.fillStyle = colExito;
        ctx.fillRect(x0 - barW - 2, padding.top + h - hI, barW, hI);
        ctx.fillStyle = colPelig;
        ctx.fillRect(x0 + 2, padding.top + h - hE, barW, hE);

        ctx.fillStyle = colTexto;
        ctx.textAlign = 'center';
        ctx.fillText(s.etiqueta, x0, padding.top + h + 14);
    });
}

function renderTopCategorias(lista) {
    const cont = $('#reporteTopLista');
    if (!cont) return;
    if (!lista.length) {
        cont.innerHTML = '<div class="texto-atenuado texto-pequeno">Sin egresos registrados aún.</div>';
        return;
    }
    const max = lista[0].total;
    cont.innerHTML = lista.map(c => {
        const pct = max ? Math.round((c.total / max) * 100) : 0;
        return `
            <div class="reporte-top-item">
                <div class="reporte-top-fila">
                    <span class="reporte-top-cat">${escapar(c.categoria)}</span>
                    <span class="reporte-top-monto mono">${fmtDinero(c.total)}</span>
                </div>
                <div class="progreso"><div class="progreso-llenado" style="width:${pct}%"></div></div>
            </div>`;
    }).join('');
}

/* ---------- Gráfico ingresos por categoría · toggle bar/pie ---------- */
function renderIngresosCategoria() {
    const cv = document.getElementById('ingresosCategoriaCanvas');
    const leyendaEl = document.getElementById('ingresosCategoriaLeyenda');
    if (!cv) return;
    if (!cacheIngresosCat.length) {
        const ctx = cv.getContext('2d');
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, cv.width, cv.height);
        if (leyendaEl) leyendaEl.innerHTML = '<span class="texto-atenuado texto-pequeno">Sin ingresos en el período.</span>';
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const cssW = cv.clientWidth || 600;
    const cssH = cv.clientHeight || 220;
    cv.width  = cssW * dpr;
    cv.height = cssH * dpr;
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    const root = getComputedStyle(document.documentElement);
    const colExito  = root.getPropertyValue('--color-exito').trim()      || '#2ECC8A';
    const colInfo   = root.getPropertyValue('--color-info').trim()       || '#4A8EBC';
    const colPurp   = root.getPropertyValue('--color-purpura').trim()    || '#8E4DD9';
    const colAdver  = root.getPropertyValue('--color-advertencia').trim()|| '#E8972A';
    const colPelig  = root.getPropertyValue('--color-peligro').trim()    || '#E84545';
    const colAcento = root.getPropertyValue('--color-acento').trim()     || '#14b8a6';
    const paleta = [colExito, colInfo, colPurp, colAdver, colPelig, colAcento];
    const colTexto = root.getPropertyValue('--texto-atenuado').trim()    || 'rgba(255,255,255,.42)';
    const colBorde = root.getPropertyValue('--vidrio-borde').trim()      || 'rgba(255,255,255,.09)';

    // Leyenda HTML compartida.
    if (leyendaEl) {
        leyendaEl.innerHTML = cacheIngresosCat.map((c, i) => {
            const color = paleta[i % paleta.length];
            return `<span class="reporte-pill" style="background:${color}33;border-color:${color}66;color:${color}">
                ${escapar(c.categoria)}: ${fmtDinero(c.total)}
            </span>`;
        }).join('');
    }

    if (modoGraficoCat === 'bar') {
        // Horizontal bar
        const padding = { top: 12, right: 12, bottom: 12, left: 130 };
        const w = cssW - padding.left - padding.right;
        const h = cssH - padding.top - padding.bottom;
        const max = Math.max(1, ...cacheIngresosCat.map(c => c.total));
        const altoBarra = Math.min(28, h / cacheIngresosCat.length - 6);
        ctx.font = '11px "Inter", sans-serif';
        ctx.textBaseline = 'middle';
        cacheIngresosCat.forEach((c, i) => {
            const y = padding.top + (i * (altoBarra + 6));
            const ancho = (c.total / max) * w;
            ctx.fillStyle = paleta[i % paleta.length];
            ctx.fillRect(padding.left, y, ancho, altoBarra);
            ctx.fillStyle = colTexto;
            ctx.textAlign = 'right';
            ctx.fillText(c.categoria.length > 14 ? c.categoria.substring(0, 13) + '…' : c.categoria, padding.left - 8, y + altoBarra / 2);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#fff';
            ctx.fillText('$' + Math.round(c.total).toLocaleString('es-MX'), padding.left + ancho + 6, y + altoBarra / 2);
        });
    } else {
        // Pie chart
        const cx = cssW / 2;
        const cy = cssH / 2;
        const r  = Math.min(cssW, cssH) / 2 - 14;
        const total = cacheIngresosCat.reduce((sum, c) => sum + c.total, 0);
        let inicio = -Math.PI / 2;
        cacheIngresosCat.forEach((c, i) => {
            const fraccion = c.total / total;
            const fin = inicio + fraccion * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, inicio, fin);
            ctx.closePath();
            ctx.fillStyle = paleta[i % paleta.length];
            ctx.fill();
            ctx.strokeStyle = colBorde;
            ctx.lineWidth = 2;
            ctx.stroke();
            inicio = fin;
        });
    }
}

document.querySelectorAll('[data-grafico-modo]').forEach(btn => {
    btn.addEventListener('click', () => {
        modoGraficoCat = btn.dataset.graficoModo;
        document.querySelectorAll('[data-grafico-modo]').forEach(b =>
            b.classList.toggle('btn-primario', b.dataset.graficoModo === modoGraficoCat)
        );
        renderIngresosCategoria();
    });
});


cargar();
cargarReporte();
window.addEventListener('resize', () => { /* ligero debounce visual */
    clearTimeout(window.__resizeRep);
    window.__resizeRep = setTimeout(() => { cargarReporte(); }, 200);
});

})();

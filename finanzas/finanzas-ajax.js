/* =====================================================================
 * FINANZAS · core (helpers, estado, modales, fetch principal)
 * ---------------------------------------------------------------------
 * Primero en cargar. Declara los símbolos globales que usan los demás
 * archivos (finanzas-dashboard.js, finanzas-tabla.js, finanzas-modal.js).
 * ====================================================================*/

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


/* ---------- Cargar datos (transacciones + KPIs + cobrables) ---------- */
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

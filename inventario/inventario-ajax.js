/* =====================================================================
 * INVENTARIO · core (helpers, estado, modales, fetch principal)
 * ---------------------------------------------------------------------
 * Primero en cargar. Declara los símbolos globales que usan los demás
 * archivos (inventario-tabla.js, inventario-modal.js).
 * ====================================================================*/

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');

let cacheItems = [];

/* ---------- Catálogos de etiquetas (usados por tabla y modal) ---------- */
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

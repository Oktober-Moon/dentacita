/* =====================================================================
 * ARCHIVOS · core (helpers, estado, fetch, listeners de modales)
 * ---------------------------------------------------------------------
 * Primero en cargar. Declara los símbolos globales que usan los demás
 * archivos (archivos-render.js, archivos-acciones.js).
 * ====================================================================*/

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');
const PACIENTE_ID = window.PACIENTE_ID;

let cacheData = { carpetas: [], archivos: [], papelera: [], storage_stats: null };
let busquedaQuery = '';
let carpetasColapsadas = new Set();

function toast(msj, tipo = 'ok') {
    if (!toastEl) { alert(msj); return; }
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

function fmtFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return iso;
    return d.toLocaleDateString('es-MX');
}

function fmtBytes(b) {
    if (!b || b < 0) return '0 B';
    if (b < 1024) return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
    if (b < 1024 * 1024 * 1024) return (b / (1024 * 1024)).toFixed(1) + ' MB';
    return (b / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

function manejarRedirect(json) {
    if (json && json.redirect) { window.location.href = '../index.php'; return false; }
    return true;
}

function iconoTipo(tipo) {
    const map = { imagen:'🖼', pdf:'📕', radiografia:'🦷', documento:'📄', laboratorio:'🧪', otro:'📎' };
    return map[tipo] || '📎';
}

function esImagen(arc) {
    if (arc.tipo === 'imagen' || arc.tipo === 'radiografia') return true;
    return /\.(jpe?g|png|webp|gif|bmp)$/i.test(arc.nombre_archivo || '');
}
function esPDF(arc) {
    if (arc.tipo === 'pdf') return true;
    return /\.pdf$/i.test(arc.nombre_archivo || '');
}


/* ---------- Cargar y renderizar ---------- */
async function cargarArchivos() {
    const cont = $('#archivosLista');
    if (!cont) return;
    if (!cont.innerHTML.trim()) cont.innerHTML = '<div class="tabla-cargando">Cargando archivos…</div>';

    try {
        const resp = await fetch('archivos-listar.php?paciente_id=' + PACIENTE_ID);
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { cont.innerHTML = '<div class="tabla-vacio">' + escapar(json.mensaje || 'Error al cargar.') + '</div>'; return; }
        cacheData = json;
        renderArchivos();
        renderStorageStats();
        actualizarSelectoresCarpeta();
        if (json.auto_purga && json.auto_purga.purgados > 0) {
            toast(`Se eliminaron ${json.auto_purga.purgados} archivo(s) de la papelera con más de ${json.auto_purga.ttl_dias} días.`, 'advertencia');
        }
    } catch (e) {
        cont.innerHTML = '<div class="tabla-vacio">No se pudo conectar.</div>';
    }
}

function renderStorageStats() {
    const el = $('#archivosStorageStats');
    if (!el || !cacheData.storage_stats) return;
    const s = cacheData.storage_stats;
    let txt = `${s.activos_count} archivo(s) · ${fmtBytes(s.activos_bytes)}`;
    if (s.papelera_count > 0) {
        txt += ` · papelera: ${s.papelera_count} (${fmtBytes(s.papelera_bytes)})`;
    }
    el.textContent = txt;
}

function actualizarSelectoresCarpeta() {
    const opts = ['<option value="">— Raíz —</option>']
        .concat(cacheData.carpetas.map(c => `<option value="${escapar(c.ruta_carpeta)}">${escapar(c.ruta_carpeta)}</option>`));
    const selSubir = $('#sub_carpeta');
    const selMover = $('#mv_destino');
    if (selSubir) selSubir.innerHTML = opts.join('');
    if (selMover) selMover.innerHTML = opts.join('');
}


/* ---------- Listeners de cierre de modales ---------- */
const idsModales = ['modalSubir','modalCarpeta','modalEditarArch','modalMover','modalPapelera','modalPreviewArchivo'];
const modales = idsModales.map(id => document.getElementById(id)).filter(Boolean);

document.querySelectorAll('[data-cerrar-modal]').forEach(b => {
    b.addEventListener('click', () => {
        const id = b.dataset.cerrarModal;
        const m = document.getElementById(id);
        if (m) m.classList.remove('visible');
    });
});
modales.forEach(m => m.addEventListener('click', (e) => {
    if (e.target === m) m.classList.remove('visible');
}));
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    modales.forEach(m => m.classList.contains('visible') && m.classList.remove('visible'));
});

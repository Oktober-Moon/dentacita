/* =====================================================================
 * ARCHIVOS · file manager refactorizado (R1)
 * ----------------------------------------------------------------------
 * Renderiza el tab "Archivos" del paciente desde JSON (archivos-listar.php).
 * Reemplaza al render PHP previo. Mejoras incluidas:
 *   - Drag-drop con overlay
 *   - Búsqueda en vivo (debounce 250ms)
 *   - Iconos por tipo de archivo
 *   - Tree de carpetas expandible
 *   - Preview inline (imagen + PDF)
 *   - Storage stats (X MB en activos / Y MB en papelera)
 *   - Sin window.location.reload — todo es re-fetch + re-render
 * ====================================================================*/
(() => {

const $ = (s) => document.querySelector(s);
const toastEl = $('#toast');
const PACIENTE_ID = window.PACIENTE_ID;

let cacheData = { carpetas: [], archivos: [], papelera: [], storage_stats: null };
let busquedaQuery = '';
let carpetasColapsadas = new Set(); // rutas colapsadas

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
        // Notificar si hubo purga automática (S3)
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

/* Reescribe los <select> de carpeta destino en los modales con la lista actualizada. */
function actualizarSelectoresCarpeta() {
    const opts = ['<option value="">— Raíz —</option>']
        .concat(cacheData.carpetas.map(c => `<option value="${escapar(c.ruta_carpeta)}">${escapar(c.ruta_carpeta)}</option>`));
    const selSubir = $('#sub_carpeta');
    const selMover = $('#mv_destino');
    if (selSubir) selSubir.innerHTML = opts.join('');
    if (selMover) selMover.innerHTML = opts.join('');
}

function archivoCoincideQuery(a, q) {
    if (!q) return true;
    const ql = q.toLowerCase();
    return (a.nombre_archivo || '').toLowerCase().includes(ql)
        || (a.descripcion || '').toLowerCase().includes(ql)
        || (a.tipo || '').toLowerCase().includes(ql);
}

function renderArchivos() {
    const cont = $('#archivosLista');
    const carpetas = (cacheData.carpetas || []).slice().sort((a, b) =>
        (a.ruta_carpeta || '').localeCompare(b.ruta_carpeta || ''));
    const archivos = cacheData.archivos || [];

    // Agrupar archivos por ruta_carpeta
    const archivosPorCarp = {};
    archivos.forEach(a => {
        const k = a.ruta_carpeta || '';
        if (!archivosPorCarp[k]) archivosPorCarp[k] = [];
        archivosPorCarp[k].push(a);
    });

    // Filtrar por búsqueda
    const filtrarLista = (lista) => lista.filter(a => archivoCoincideQuery(a, busquedaQuery));

    const archivosRaiz = filtrarLista(archivosPorCarp[''] || []);

    if (!carpetas.length && !archivos.length) {
        cont.innerHTML = `
            <div class="placeholder-card-inline">
                <p>Aún no hay archivos para este paciente.</p>
                <p class="texto-atenuado">Crea una carpeta o sube un archivo. También puedes arrastrar archivos a esta zona.</p>
            </div>`;
        return;
    }

    let html = '';

    carpetas.forEach(car => {
        const ruta = car.ruta_carpeta;
        const archivosCarp = filtrarLista(archivosPorCarp[ruta] || []);
        // Si hay búsqueda activa y la carpeta no tiene matches, ocultar.
        if (busquedaQuery && !archivosCarp.length) return;
        const colapsada = carpetasColapsadas.has(ruta);
        html += `
            <div class="carpeta-bloque ${colapsada ? 'carpeta-colapsada' : ''}">
                <div class="carpeta-titulo">
                    <button type="button" class="btn-link carpeta-toggle" data-toggle-carpeta="${escapar(ruta)}" title="Expandir/colapsar">
                        <span class="carpeta-flecha">${colapsada ? '▶' : '▼'}</span>
                    </button>
                    <strong>📁 ${escapar(ruta)}</strong>
                    <span class="texto-atenuado texto-pequeno">(${archivosCarp.length})</span>
                    <span class="filtros-inline" style="margin-left:auto">
                        <button type="button" class="btn-link" data-carpeta-accion="renombrar" data-carpeta-id="${car.carpeta_id}" data-carpeta-nombre="${escapar(car.nombre)}">Renombrar</button>
                        <button type="button" class="btn-link btn-link-peligro" data-carpeta-accion="eliminar" data-carpeta-id="${car.carpeta_id}" data-carpeta-ruta="${escapar(ruta)}">Eliminar</button>
                    </span>
                </div>
                ${colapsada ? '' : (archivosCarp.length
                    ? '<ul class="lista-archivos">' + archivosCarp.map(renderItemArchivo).join('') + '</ul>'
                    : '<div class="texto-atenuado texto-pequeno">Carpeta vacía.</div>')}
            </div>`;
    });

    if (archivosRaiz.length) {
        html += `
            <div class="carpeta-bloque">
                <div class="carpeta-titulo"><strong>Sin carpeta (raíz)</strong>
                    <span class="texto-atenuado texto-pequeno">(${archivosRaiz.length})</span>
                </div>
                <ul class="lista-archivos">${archivosRaiz.map(renderItemArchivo).join('')}</ul>
            </div>`;
    }

    if (!html) {
        html = '<div class="texto-atenuado">Sin resultados para "' + escapar(busquedaQuery) + '".</div>';
    }
    cont.innerHTML = html;
}

function renderItemArchivo(arc) {
    const previewable = esImagen(arc) || esPDF(arc);
    return `
        <li>
            <span class="archivo-icono" aria-hidden="true">${iconoTipo(arc.tipo)}</span>
            <strong>${escapar(arc.nombre_archivo)}</strong>
            <span class="badge badge-${escapar(arc.tipo)}">${escapar(arc.tipo)}</span>
            <span class="texto-atenuado"> · ${fmtFecha(arc.fecha)}</span>
            <span class="filtros-inline" style="margin-left:auto">
                ${previewable
                    ? `<button type="button" class="btn-link" data-arch-accion="preview" data-arch-id="${arc.archivo_id}" data-arch-url="${escapar(arc.archivo_url)}" data-arch-nombre="${escapar(arc.nombre_archivo)}" data-arch-pdf="${esPDF(arc) ? 1 : 0}">Vista previa</button>`
                    : `<a class="btn-link" href="${escapar(arc.archivo_url)}" target="_blank" rel="noopener">Abrir</a>`}
                <button type="button" class="btn-link" data-arch-accion="renombrar" data-arch-id="${arc.archivo_id}" data-arch-nombre="${escapar(arc.nombre_archivo)}" data-arch-desc="${escapar(arc.descripcion || '')}" data-arch-tipo="${escapar(arc.tipo)}" data-arch-fecha="${escapar(arc.fecha || '')}">Editar</button>
                <button type="button" class="btn-link" data-arch-accion="mover" data-arch-id="${arc.archivo_id}">Mover</button>
                <button type="button" class="btn-link btn-link-peligro" data-arch-accion="papelera" data-arch-id="${arc.archivo_id}">Borrar</button>
            </span>
        </li>`;
}


/* ---------- Búsqueda en vivo ---------- */
let timerBusqueda;
const inputBusqueda = $('#archivosBusqueda');
if (inputBusqueda) {
    inputBusqueda.addEventListener('input', (e) => {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => {
            busquedaQuery = e.target.value.trim();
            renderArchivos();
        }, 250);
    });
}


/* ---------- Eventos delegados sobre la lista ---------- */
$('#archivosLista').addEventListener('click', async (e) => {
    const btnToggle = e.target.closest('[data-toggle-carpeta]');
    if (btnToggle) {
        const ruta = btnToggle.dataset.toggleCarpeta;
        if (carpetasColapsadas.has(ruta)) carpetasColapsadas.delete(ruta);
        else carpetasColapsadas.add(ruta);
        renderArchivos();
        return;
    }

    const btnCar = e.target.closest('[data-carpeta-accion]');
    if (btnCar) {
        const accion = btnCar.dataset.carpetaAccion;
        const id = btnCar.dataset.carpetaId;
        if (accion === 'renombrar') {
            const actual = btnCar.dataset.carpetaNombre || '';
            const nuevo = prompt('Nuevo nombre para la carpeta:', actual);
            if (!nuevo || nuevo === actual) return;
            const datos = new FormData();
            datos.append('carpeta_id', id);
            datos.append('nombre_nuevo', nuevo);
            try {
                const resp = await fetch('carpetas-renombrar.php', { method: 'POST', body: datos });
                const json = await resp.json();
                if (!manejarRedirect(json)) return;
                if (!json.ok) { toast(json.mensaje, 'error'); return; }
                toast(json.mensaje);
                cargarArchivos();
            } catch (e) { toast('No se pudo conectar.', 'error'); }
        } else if (accion === 'eliminar') {
            const ruta = btnCar.dataset.carpetaRuta;
            if (!confirm(`¿Eliminar la carpeta "${ruta}"?\nLos archivos se enviarán a la papelera.`)) return;
            const datos = new FormData();
            datos.append('carpeta_id', id);
            datos.append('modo', 'papelera');
            try {
                const resp = await fetch('carpetas-eliminar.php', { method: 'POST', body: datos });
                const json = await resp.json();
                if (!manejarRedirect(json)) return;
                if (!json.ok) { toast(json.mensaje, 'error'); return; }
                toast(json.mensaje, 'advertencia');
                cargarArchivos();
            } catch (e) { toast('No se pudo conectar.', 'error'); }
        }
        return;
    }

    const btnArc = e.target.closest('[data-arch-accion]');
    if (btnArc) {
        const accion = btnArc.dataset.archAccion;
        const id     = btnArc.dataset.archId;
        if (accion === 'renombrar') {
            $('#ed_arch_id').value     = id;
            $('#ed_arch_nombre').value = btnArc.dataset.archNombre || '';
            $('#ed_arch_desc').value   = btnArc.dataset.archDesc   || '';
            $('#ed_arch_tipo').value   = btnArc.dataset.archTipo   || 'documento';
            $('#ed_arch_fecha').value  = btnArc.dataset.archFecha  || '';
            $('#modalEditarArch').classList.add('visible');
        } else if (accion === 'mover') {
            $('#mv_arch_id').value = id;
            $('#mv_destino').value = '';
            $('#modalMover').classList.add('visible');
        } else if (accion === 'preview') {
            abrirPreview(btnArc.dataset.archUrl, btnArc.dataset.archNombre, btnArc.dataset.archPdf === '1');
        } else if (accion === 'papelera') {
            if (!confirm('¿Enviar este archivo a la papelera?')) return;
            const datos = new FormData();
            datos.append('archivo_id', id);
            try {
                const resp = await fetch('archivos-papelera.php', { method: 'POST', body: datos });
                const json = await resp.json();
                if (!manejarRedirect(json)) return;
                if (!json.ok) { toast(json.mensaje, 'error'); return; }
                toast(json.mensaje, 'advertencia');
                cargarArchivos();
            } catch (e) { toast('No se pudo conectar.', 'error'); }
        }
    }
});


/* ---------- Modales (subir, carpeta, editar archivo, mover, papelera, preview) ---------- */
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


/* ---------- Subir archivo ---------- */
$('#btnSubirArchivo').addEventListener('click', () => $('#modalSubir').classList.add('visible'));

$('#formSubir').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formSubir'));
    try {
        const resp = await fetch('archivos-subir.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        $('#modalSubir').classList.remove('visible');
        $('#formSubir').reset();
        cargarArchivos();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Crear carpeta ---------- */
$('#btnNuevaCarpeta').addEventListener('click', () => {
    $('#formCarpeta').reset();
    $('#modalCarpeta').classList.add('visible');
});

$('#formCarpeta').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formCarpeta'));
    try {
        const resp = await fetch('carpetas-crear.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        $('#modalCarpeta').classList.remove('visible');
        cargarArchivos();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Editar archivo / Mover ---------- */
$('#formEditarArch').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formEditarArch'));
    try {
        const resp = await fetch('archivos-renombrar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        $('#modalEditarArch').classList.remove('visible');
        cargarArchivos();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});

$('#formMover').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData($('#formMover'));
    try {
        const resp = await fetch('archivos-mover.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        $('#modalMover').classList.remove('visible');
        cargarArchivos();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Preview inline ---------- */
function abrirPreview(url, nombre, esPdf) {
    $('#previewTitulo').textContent = nombre || 'Vista previa';
    const cont = $('#previewContenido');
    if (esPdf) {
        cont.innerHTML = `<embed src="${escapar(url)}" type="application/pdf" style="width:100%; height:70vh; border:1px solid var(--vidrio-borde); border-radius: var(--radio-sm);">`;
    } else {
        cont.innerHTML = `<img src="${escapar(url)}" alt="${escapar(nombre)}" style="max-width:100%; max-height:70vh; border-radius: var(--radio-sm);">`;
    }
    $('#previewDescargar').href = url;
    $('#modalPreviewArchivo').classList.add('visible');
}


/* ---------- Drag-and-drop ---------- */
const overlay = $('#archivosDragOverlay');
const card    = $('#archivosCard');
let dragCounter = 0;

if (card && overlay) {
    ['dragenter','dragover'].forEach(ev => {
        card.addEventListener(ev, (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
            e.preventDefault();
            if (ev === 'dragenter') dragCounter++;
            overlay.classList.add('visible');
        });
    });
    card.addEventListener('dragleave', () => {
        dragCounter = Math.max(0, dragCounter - 1);
        if (dragCounter === 0) overlay.classList.remove('visible');
    });
    card.addEventListener('drop', async (e) => {
        e.preventDefault();
        dragCounter = 0;
        overlay.classList.remove('visible');
        const archivos = Array.from(e.dataTransfer.files || []);
        if (!archivos.length) return;
        for (const f of archivos) {
            if (f.size > 50 * 1024 * 1024) {
                toast(`"${f.name}" excede 50 MB y se omitió.`, 'advertencia');
                continue;
            }
            const datos = new FormData();
            datos.append('paciente_id', PACIENTE_ID);
            datos.append('archivo', f);
            datos.append('nombre_archivo', f.name);
            datos.append('tipo', 'documento');
            datos.append('fecha', new Date().toISOString().slice(0, 10));
            datos.append('ruta_carpeta', '');
            try {
                const resp = await fetch('archivos-subir.php', { method: 'POST', body: datos });
                const json = await resp.json();
                if (!manejarRedirect(json)) return;
                if (!json.ok) { toast(`"${f.name}": ${json.mensaje}`, 'error'); continue; }
                toast(`"${f.name}" subido.`);
            } catch (err) { toast(`"${f.name}": error de red.`, 'error'); }
        }
        cargarArchivos();
    });
}


/* ---------- Papelera ---------- */
$('#btnVerPapelera').addEventListener('click', async () => {
    $('#modalPapelera').classList.add('visible');
    pintarPapelera();
});

function pintarPapelera() {
    const cont = $('#papeleraLista');
    const pap = cacheData.papelera || [];
    if (!pap.length) {
        cont.innerHTML = '<div class="texto-atenuado">La papelera está vacía.</div>';
        return;
    }
    cont.innerHTML = `
    <div class="texto-pequeno texto-atenuado" style="margin-bottom: var(--espacio-sm)">
        ⏱ Los archivos se eliminan permanentemente a los 30 días de estar en la papelera.
    </div>
    <table class="tabla-datos">
        <thead><tr>
            <th>Nombre</th><th>Carpeta original</th>
            <th>Eliminado</th>
            <th>Días restantes</th>
            <th style="width:200px">Acciones</th>
        </tr></thead>
        <tbody>
        ${pap.map(a => {
            const dias = parseInt(a.dias_restantes || 0, 10);
            const claseDias = dias <= 7 ? 'dias-restantes-urgente' : 'dias-restantes-ok';
            return `
            <tr>
                <td>
                    <span class="archivo-icono">${iconoTipo(a.tipo)}</span>
                    <strong>${escapar(a.nombre_archivo)}</strong>
                    <span class="badge badge-${escapar(a.tipo)}">${escapar(a.tipo)}</span>
                </td>
                <td>${escapar(a.ruta_carpeta || '(raíz)')}</td>
                <td class="texto-atenuado texto-pequeno">${fmtFecha(a.eliminado_en)}<br>por ${escapar(a.eliminado_por || '—')}</td>
                <td class="mono ${claseDias}">${dias} día${dias === 1 ? '' : 's'}</td>
                <td>
                    <button type="button" class="btn-link" data-pap-accion="restaurar" data-pap-id="${a.archivo_id}">Restaurar</button>
                    <button type="button" class="btn-link btn-link-peligro" data-pap-accion="permanente" data-pap-id="${a.archivo_id}">Eliminar permanente</button>
                </td>
            </tr>`;
        }).join('')}
        </tbody>
    </table>`;
}

$('#papeleraLista').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-pap-accion]');
    if (!btn) return;
    const id     = btn.dataset.papId;
    const accion = btn.dataset.papAccion;

    if (accion === 'restaurar') {
        const datos = new FormData();
        datos.append('archivo_id', id);
        try {
            const resp = await fetch('archivos-restaurar.php', { method: 'POST', body: datos });
            const json = await resp.json();
            if (!manejarRedirect(json)) return;
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            toast(json.mensaje);
            await cargarArchivos();
            pintarPapelera();
        } catch (e) { toast('No se pudo conectar.', 'error'); }
    }
    if (accion === 'permanente') {
        if (!confirm('¿Eliminar PERMANENTEMENTE este archivo? Esta acción no se puede deshacer.')) return;
        const datos = new FormData();
        datos.append('archivo_id', id);
        try {
            const resp = await fetch('archivos-eliminar-permanente.php', { method: 'POST', body: datos });
            const json = await resp.json();
            if (!manejarRedirect(json)) return;
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            toast(json.mensaje, 'advertencia');
            await cargarArchivos();
            pintarPapelera();
        } catch (e) { toast('No se pudo conectar.', 'error'); }
    }
});

$('#btnVaciarPapelera').addEventListener('click', async () => {
    if (!confirm('¿Vaciar TODA la papelera? Los archivos se borrarán PERMANENTEMENTE.')) return;
    const datos = new FormData();
    datos.append('paciente_id', PACIENTE_ID);
    try {
        const resp = await fetch('archivos-vaciar-papelera.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje, 'advertencia');
        await cargarArchivos();
        pintarPapelera();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


/* ---------- Arranque ---------- */
cargarArchivos();

})();

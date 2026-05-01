/* =====================================================================
 * ARCHIVOS · subir, crear carpeta, editar/mover, drag-drop, papelera,
 * preview · y arranque inicial.
 * ---------------------------------------------------------------------
 * Depende de: archivos-core.js, archivos-render.js
 * ====================================================================*/

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
            if (f.size > 10 * 1024 * 1024) {
                toast(`"${f.name}" excede 10 MB y se omitió.`, 'advertencia');
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

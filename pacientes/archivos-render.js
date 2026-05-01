/* =====================================================================
 * ARCHIVOS · render del árbol de carpetas y lista de archivos + búsqueda
 * + acciones inline sobre la lista (toggle carpeta, carpeta/archivo-accion).
 * ---------------------------------------------------------------------
 * Depende de: archivos-core.js. Las acciones (renombrar, mover, papelera,
 * preview) delegan a funciones declaradas en archivos-acciones.js.
 * ====================================================================*/

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

    const archivosPorCarp = {};
    archivos.forEach(a => {
        const k = a.ruta_carpeta || '';
        if (!archivosPorCarp[k]) archivosPorCarp[k] = [];
        archivosPorCarp[k].push(a);
    });

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

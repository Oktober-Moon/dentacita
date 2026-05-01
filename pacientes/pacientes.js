/* =====================================================================
 *  PACIENTES · lógica frontend (vainilla JS)
 *  ----------------------------------------------------------------------
 *  - Carga la tabla paginada vía /mostrar.php
 *  - Búsqueda en vivo con debounce
 *  - Modal de creación/edición → /guardar.php · /actualizar.php
 *  - Eliminar con confirmación → /eliminar.php
 * ===================================================================== */

(() => {

const $ = (sel) => document.querySelector(sel);

// ----- estado -----
const estado = {
    pagina: 1,
    busqueda: '',
    eliminandoId: null
};

// ----- referencias -----
const tablaContenido   = $('#pacientesTablaContenido');
const conteoLabel      = $('#pacientesConteo');
const paginacionEl     = $('#pacientesPaginacion');
const inputBuscar      = $('#buscarPaciente');

const modalPaciente    = $('#modalPaciente');
const formPaciente     = $('#formPaciente');
const modalTitulo      = $('#modalPacienteTitulo');
const btnNuevo         = $('#btnNuevoPaciente');
const btnCerrarModal   = $('#cerrarModalPaciente');
const btnCancelarModal = $('#cancelarPaciente');

const modalEliminar    = $('#modalEliminar');
const eliminarNombre   = $('#eliminarNombre');
const btnCancelarElim  = $('#cancelarEliminar');
const btnConfirmarElim = $('#confirmarEliminar');

const toastEl          = $('#toast');


/* ---------------- Toast ---------------- */
function toast(msj, tipo = 'ok') {
    const tiposValidos = { ok: 'toast-ok', error: 'toast-error', info: 'toast-info', advertencia: 'toast-advertencia' };
    const cls = tiposValidos[tipo] || 'toast-ok';
    toastEl.textContent = msj;
    toastEl.className = 'toast ' + cls + ' visible';
    setTimeout(() => toastEl.classList.remove('visible'), 2400);
}


/* ---------------- Helpers ---------------- */
function escapar(t) {
    if (t === null || t === undefined) return '';
    return String(t)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function fmtFecha(iso) {
    if (!iso) return '';
    const partes = iso.split('-');
    if (partes.length !== 3) return iso;
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function calcularEdad(fechaNac) {
    if (!fechaNac) return '';
    const nac = new Date(fechaNac);
    if (isNaN(nac)) return '';
    const hoy = new Date();
    let edad = hoy.getFullYear() - nac.getFullYear();
    const m = hoy.getMonth() - nac.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
    return edad >= 0 && edad < 150 ? edad + ' años' : '';
}


/* ---------------- Sesión expirada ---------------- */
function manejarRespuesta(json) {
    if (json && json.redirect) {
        window.location.href = '../index.php';
        return false;
    }
    return true;
}


/* ---------------- Cargar tabla ---------------- */
async function cargarPacientes() {
    tablaContenido.innerHTML = '<div class="tabla-cargando">Cargando…</div>';

    const params = new URLSearchParams({
        q: estado.busqueda,
        pag: estado.pagina
    });

    try {
        const resp = await fetch('mostrar.php?' + params.toString());
        const json = await resp.json();
        if (!manejarRespuesta(json)) return;

        if (!json.ok) {
            tablaContenido.innerHTML = `<div class="tabla-vacio">${escapar(json.mensaje || 'Error al cargar.')}</div>`;
            return;
        }

        renderTabla(json.pacientes);
        renderConteo(json.total, json.pagina, json.por_pagina);
        renderPaginacion(json.pagina, json.total_paginas);
    } catch (e) {
        tablaContenido.innerHTML = '<div class="tabla-vacio">No se pudo conectar con el servidor.</div>';
    }
}

function inicial(nombre) {
    if (!nombre) return '?';
    return nombre.trim().charAt(0).toUpperCase();
}

function renderTabla(pacientes) {
    if (!pacientes || pacientes.length === 0) {
        tablaContenido.innerHTML =
            '<div class="tabla-vacio">No hay pacientes que coincidan con tu búsqueda.</div>';
        return;
    }

    const cards = pacientes.map(p => {
        const edad = calcularEdad(p.fecha_nacimiento);
        const totalCitas = parseInt(p.total_citas || 0, 10);
        const avatarHtml = p.foto_url
            ? `<img class="paciente-card-avatar" src="${escapar(p.foto_url)}" alt="" onerror="this.outerHTML='<span class=\\'paciente-card-avatar\\'>${escapar(inicial(p.nombre_completo))}</span>'">`
            : `<span class="paciente-card-avatar">${escapar(inicial(p.nombre_completo))}</span>`;
        return `
            <div class="paciente-card-wrap">
                <a class="paciente-card" href="detalle.php?id=${p.paciente_id}">
                    ${avatarHtml}
                    <div class="paciente-card-cuerpo">
                        <div class="paciente-card-nombre">${escapar(p.nombre_completo)}</div>
                        <div class="paciente-card-meta">
                            ${edad ? escapar(edad) : ''}${edad && p.telefono ? ' · ' : ''}${p.telefono ? escapar(p.telefono) : ''}
                        </div>
                        <div class="paciente-card-info">
                            <span><strong>${totalCitas}</strong> cita${totalCitas === 1 ? '' : 's'}</span>
                            ${p.ultima_cita ? `<span class="texto-atenuado">última: ${escapar(fmtFecha(p.ultima_cita.substring(0, 10)))}</span>` : ''}
                        </div>
                    </div>
                </a>
                <div class="paciente-card-acciones">
                    <button type="button" class="btn-link" data-accion="editar" data-id="${p.paciente_id}">Editar</button>
                    <button type="button" class="btn-link btn-link-peligro" data-accion="eliminar" data-id="${p.paciente_id}" data-nombre="${escapar(p.nombre_completo)}">Eliminar</button>
                </div>
            </div>`;
    }).join('');

    tablaContenido.innerHTML = `<div class="pacientes-cards">${cards}</div>`;
}

function renderConteo(total, pagina, porPag) {
    if (total === 0) {
        conteoLabel.textContent = '';
        return;
    }
    const desde = (pagina - 1) * porPag + 1;
    const hasta = Math.min(pagina * porPag, total);
    conteoLabel.textContent = `${desde}–${hasta} de ${total}`;
}

function renderPaginacion(pagina, totalPaginas) {
    if (totalPaginas <= 1) {
        paginacionEl.innerHTML = '';
        return;
    }
    paginacionEl.innerHTML = `
        <button class="btn-pag" data-pag="${pagina - 1}" ${pagina <= 1 ? 'disabled' : ''}>‹ Anterior</button>
        <span class="texto-atenuado">Página ${pagina} de ${totalPaginas}</span>
        <button class="btn-pag" data-pag="${pagina + 1}" ${pagina >= totalPaginas ? 'disabled' : ''}>Siguiente ›</button>`;
}


/* ---------------- Eventos delegados de tabla y paginación ---------------- */
tablaContenido.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;
    const accion = btn.dataset.accion;

    if (accion === 'editar')   abrirModalEditar(id);
    if (accion === 'eliminar') abrirModalEliminar(id, btn.dataset.nombre);
});

paginacionEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-pag]');
    if (!btn || btn.disabled) return;
    estado.pagina = parseInt(btn.dataset.pag, 10);
    cargarPacientes();
});


/* ---------------- Búsqueda con debounce ---------------- */
let debounceTimer;
inputBuscar.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        estado.busqueda = inputBuscar.value.trim();
        estado.pagina = 1;
        cargarPacientes();
    }, 220);
});


/* ---------------- Modal de paciente (crear/editar) ---------------- */
function abrirModalCrear() {
    formPaciente.reset();
    $('#paciente_id').value = '';
    modalTitulo.textContent = 'Nuevo paciente';
    modalPaciente.classList.add('visible');
    setTimeout(() => $('#nombre_completo').focus(), 50);
}

async function abrirModalEditar(id) {
    formPaciente.reset();
    modalTitulo.textContent = 'Cargando…';
    modalPaciente.classList.add('visible');

    try {
        const resp = await fetch('actualizar.php?id=' + encodeURIComponent(id));
        const json = await resp.json();
        if (!manejarRespuesta(json)) return;

        if (!json.ok) {
            toast(json.mensaje || 'Error al cargar.', 'error');
            cerrarModal();
            return;
        }

        const p = json.paciente;
        $('#paciente_id').value      = p.paciente_id;
        $('#nombre_completo').value  = p.nombre_completo || '';
        $('#telefono').value         = p.telefono || '';
        $('#email').value            = p.email || '';
        $('#fecha_nacimiento').value = p.fecha_nacimiento || '';
        $('#genero').value           = p.genero || 'no_especificado';
        $('#direccion').value        = p.direccion || '';
        $('#ocupacion').value        = p.ocupacion || '';
        $('#alergias').value         = p.alergias || '';
        $('#padecimientos').value    = p.padecimientos || '';
        $('#medicamentos').value     = p.medicamentos || '';
        $('#notas').value            = p.notas || '';

        modalTitulo.textContent = 'Editar · ' + (p.nombre_completo || 'Paciente');
    } catch (e) {
        toast('No se pudo conectar con el servidor.', 'error');
        cerrarModal();
    }
}

function cerrarModal() {
    modalPaciente.classList.remove('visible');
}

btnNuevo.addEventListener('click', abrirModalCrear);
btnCerrarModal.addEventListener('click', cerrarModal);
btnCancelarModal.addEventListener('click', cerrarModal);


/* ---------------- Submit del formulario ---------------- */
formPaciente.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData(formPaciente);
    const id = $('#paciente_id').value;
    const url = id ? 'actualizar.php' : 'guardar.php';

    try {
        const resp = await fetch(url, { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRespuesta(json)) return;

        if (!json.ok) {
            toast(json.mensaje || 'No se pudo guardar.', 'error');
            return;
        }

        toast(json.mensaje || 'Guardado.');
        cerrarModal();
        cargarPacientes();
    } catch (err) {
        toast('No se pudo conectar con el servidor.', 'error');
    }
});


/* ---------------- Modal eliminar ---------------- */
function abrirModalEliminar(id, nombre) {
    estado.eliminandoId = id;
    eliminarNombre.textContent = nombre;
    modalEliminar.classList.add('visible');
}

function cerrarModalEliminar() {
    estado.eliminandoId = null;
    modalEliminar.classList.remove('visible');
}

btnCancelarElim.addEventListener('click', cerrarModalEliminar);

btnConfirmarElim.addEventListener('click', async () => {
    if (!estado.eliminandoId) return;

    const datos = new FormData();
    datos.append('paciente_id', estado.eliminandoId);

    try {
        const resp = await fetch('eliminar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRespuesta(json)) return;

        if (!json.ok) {
            toast(json.mensaje || 'No se pudo eliminar.', 'error');
            return;
        }

        toast(json.mensaje || 'Paciente eliminado.');
        cerrarModalEliminar();
        cargarPacientes();
    } catch (err) {
        toast('No se pudo conectar con el servidor.', 'error');
    }
});


/* ---------------- Cerrar modales con tecla Esc o click afuera ---------------- */
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (modalPaciente.classList.contains('visible')) cerrarModal();
    if (modalEliminar.classList.contains('visible')) cerrarModalEliminar();
});

[modalPaciente, modalEliminar].forEach(m => {
    m.addEventListener('click', (e) => {
        if (e.target === m) {
            if (m === modalPaciente) cerrarModal();
            else cerrarModalEliminar();
        }
    });
});


/* ---------------- Arranque ---------------- */
cargarPacientes();

})();

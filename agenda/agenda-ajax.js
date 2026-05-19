/* =====================================================================
 * AGENDA · core (estado global, helpers, navegación de modal, fetchers)
 * ---------------------------------------------------------------------
 * Primero en cargar. Declara los símbolos globales que usan los demás
 * archivos (agenda-calendario.js, agenda-modal.js, agenda-memorias.js).
 * ====================================================================*/

/* ----- Estado global ----- */
var estado = {
    fechaSeleccionada: new Date(),
    mesVisible:        new Date(),
    citasDelMes:       [],
    modoEditar:        false,
    pacienteActual:    null
};

var NOMBRES_MES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

var NOMBRES_ESTADO = {
    programada: 'Programada',
    confirmada: 'Confirmada',
    completada: 'Completada',
    cancelada:  'Cancelada',
    no_asistio: 'No asistió'
};


/* ----- Helpers genéricos ----- */
function $(id) { return document.getElementById(id); }

function toast(mensaje, exito) {
    var t = $('toast');
    t.textContent = mensaje;
    t.className = 'toast ' + (exito ? 'toast-exito' : 'toast-error') + ' toast-visible';
    setTimeout(function(){ t.className = 'toast'; }, 2800);
}

function fmtFecha(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2,'0');
    var dd = String(d.getDate()).padStart(2,'0');
    return y + '-' + m + '-' + dd;
}

function formatearFechaCorta(iso) {
    if (!iso) return '';
    var partes = String(iso).split('-');
    if (partes.length !== 3) return iso;
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function mismoDia(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

function esHoy(d) { return mismoDia(d, new Date()); }

function escaparHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}


/* ----- Formato de hora 12h/24h -----
 * Toggle persistido en localStorage 'agenda_formato_hora'. Default '24'.
 * fmtHora("13:30")  → "13:30"  (24h)  ó  "1:30 PM"  (12h)
 * fmtHora("13:30", true) → "13:00" o "1 PM" (modo "solo hora", para el eje
 *   vertical de la vista semanal donde los minutos siempre son :00).
 */
var AGENDA_FORMATO_HORA = '24';
try {
    var fmtGuardado = localStorage.getItem('agenda_formato_hora');
    if (fmtGuardado === '12' || fmtGuardado === '24') AGENDA_FORMATO_HORA = fmtGuardado;
} catch (e) { /* ignorar */ }

function fmtHora(hhmm, soloHora) {
    if (!hhmm) return '';
    var partes = String(hhmm).split(':');
    var h = parseInt(partes[0], 10);
    var m = parseInt(partes[1] || '0', 10);
    if (isNaN(h)) return hhmm;
    if (AGENDA_FORMATO_HORA === '12') {
        var sufijo = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12; if (h12 === 0) h12 = 12;
        if (soloHora) return h12 + ' ' + sufijo;
        var mm = (m < 10 ? '0' : '') + m;
        return h12 + ':' + mm + ' ' + sufijo;
    }
    var hh = (h < 10 ? '0' : '') + h;
    if (soloHora) return hh + ':00';
    var mm24 = (m < 10 ? '0' : '') + m;
    return hh + ':' + mm24;
}

function cambiarFormatoHora(fmt) {
    if (fmt !== '12' && fmt !== '24') return;
    AGENDA_FORMATO_HORA = fmt;
    try { localStorage.setItem('agenda_formato_hora', fmt); } catch (e) { /* ignorar */ }
    var b12 = $('btnFormato12h'), b24 = $('btnFormato24h');
    if (b12 && b24) {
        b12.classList.toggle('activo', fmt === '12');
        b24.classList.toggle('activo', fmt === '24');
    }
    /* Redibujar las tres vistas que muestran horas. */
    if (typeof pintarCalendario === 'function') pintarCalendario();
    if (typeof pintarSemana      === 'function') pintarSemana();
    cargarCitasDelDia();
    if (typeof actualizarLineaAhora === 'function') actualizarLineaAhora();
}

/* Listeners del toggle 12h/24h · marca el botón activo según el valor
   cargado de localStorage y delega los clicks a cambiarFormatoHora(). */
(function() {
    var b12 = document.getElementById('btnFormato12h');
    var b24 = document.getElementById('btnFormato24h');
    if (b12 && b24) {
        b12.classList.toggle('activo', AGENDA_FORMATO_HORA === '12');
        b24.classList.toggle('activo', AGENDA_FORMATO_HORA === '24');
    }
    document.querySelectorAll('[data-formato-hora]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            cambiarFormatoHora(btn.dataset.formatoHora);
        });
    });
})();


/* ----- Navegación del modal de cita ----- */
function mostrarPaso(nombre) {
    ['pasoTipoPaciente','pasoSeleccionarPaciente','pasoNuevoPaciente','pasoDatosCita']
        .forEach(function(id){ $(id).classList.add('oculto'); });
    var map = {
        tipo:        'pasoTipoPaciente',
        seleccionar: 'pasoSeleccionarPaciente',
        nuevo:       'pasoNuevoPaciente',
        datos:       'pasoDatosCita'
    };
    $(map[nombre]).classList.remove('oculto');
}

function abrirModal()  { $('modalCita').classList.add('visible'); }
function cerrarModal() { $('modalCita').classList.remove('visible'); }


/* ============================================================
   FETCHERS · cargan datos y delegan el render a los archivos
   de calendario / memorias.
   ============================================================ */
function cargarCitasDelMes() {
    var anio = estado.mesVisible.getFullYear();
    var mes  = String(estado.mesVisible.getMonth() + 1).padStart(2,'0');
    fetch('mostrar.php?mes=' + anio + '-' + mes)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                toast(data.mensaje, false);
                return;
            }
            estado.citasDelMes = data.citas;
            pintarCalendario();
        })
        .catch(function(){ toast('Error al cargar el calendario.', false); });
}

/* refrescarAgenda · llamar después de cualquier mutación de cita (crear,
 * editar, eliminar, cambio de estado, reembolso). Repinta la vista mensual
 * y la lista del día siempre, y la vista semanal solo si está activa
 * (para no disparar fetches innecesarios cuando el usuario está en mes). */
function refrescarAgenda() {
    cargarCitasDelMes();
    cargarCitasDelDia();
    var vs = document.getElementById('vistaSemanal');
    if (vs && vs.style.display !== 'none' && typeof cargarSemana === 'function') {
        cargarSemana();
    }
}

function cargarCitasDelDia() {
    var f = fmtFecha(estado.fechaSeleccionada);
    var titulo = $('citasDiaTitulo');
    var hoy = esHoy(estado.fechaSeleccionada);
    titulo.textContent = hoy ? 'Hoy' :
        estado.fechaSeleccionada.toLocaleDateString('es-MX',
            { weekday: 'long', day: 'numeric', month: 'long' });

    var cont = $('citasDiaContenido');
    cont.innerHTML = '<div class="tabla-cargando">Cargando…</div>';

    fetch('mostrar.php?fecha=' + f)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                cont.innerHTML = '<div class="estado-vacio">' + escaparHtml(data.mensaje) + '</div>';
                return;
            }
            $('citasDiaConteo').textContent =
                data.citas.length + (data.citas.length === 1 ? ' cita' : ' citas');
            var metaConteo = $('agendaMetaConteo');
            if (metaConteo) {
                var sufijo = esHoy(estado.fechaSeleccionada) ? ' hoy' : '';
                metaConteo.textContent = data.citas.length +
                    (data.citas.length === 1 ? ' cita' + sufijo : ' citas' + sufijo);
            }
            pintarCitasDelDia(data.citas);
        })
        .catch(function() {
            cont.innerHTML = '<div class="estado-vacio">Error al cargar las citas.</div>';
        });
}

function cargarPacientesRecientes() {
    $('resultadosPacientes').innerHTML =
        '<div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">Cargando…</div>';
    fetch('pacientes.php?accion=listar-recientes')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                $('resultadosPacientes').innerHTML =
                    '<div class="texto-atenuado" style="padding: var(--espacio-md);">' + escaparHtml(data.mensaje) + '</div>';
                return;
            }
            if (!data.pacientes.length) {
                $('resultadosPacientes').innerHTML =
                    '<div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">' +
                    'Aún no tienes pacientes registrados. Usa "Paciente nuevo" para crear el primero.</div>';
                return;
            }
            pintarResultadosPacientes(data.pacientes);
        })
        .catch(function() {
            $('resultadosPacientes').innerHTML =
                '<div class="texto-atenuado" style="padding: var(--espacio-md);">No se pudo conectar.</div>';
        });
}

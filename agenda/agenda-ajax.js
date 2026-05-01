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
                '<div class="texto-atenuado" style="padding: var(--espacio-md);">Error de conexión.</div>';
        });
}

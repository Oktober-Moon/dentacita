/* =====================================================================
 * AGENDA · modal multi-paso de cita (paso 1: tipo, paso 2: buscar
 * paciente existente, paso 3: registrar paciente nuevo, paso 4: datos
 * de la cita) + cropper de foto del paciente nuevo + edición de cita.
 * ---------------------------------------------------------------------
 * Depende de: agenda-ajax.js
 * ====================================================================*/

/* ---------- Cierre del modal ---------- */
$('cerrarModalCita').addEventListener('click', cerrarModal);
$('modalCita').addEventListener('click', function(e){ if (e.target === this) cerrarModal(); });
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && $('modalCita').classList.contains('visible')) cerrarModal();
});

document.querySelectorAll('[data-volver]').forEach(function(b) {
    b.addEventListener('click', function(){ mostrarPaso(this.dataset.volver); });
});

$('btnVolverPaso3').addEventListener('click', function() { mostrarPaso('tipo'); });


/* ============================================================
   NUEVA CITA · arranca el flujo
   ============================================================ */
$('btnNuevaCita').addEventListener('click', function() {
    estado.modoEditar = false;
    estado.pacienteActual = null;
    $('modalCitaTitulo').textContent = 'Nueva cita';
    $('btnGuardarCita').textContent = 'Guardar cita';
    limpiarFormularioCita();
    // Estado solo visible al editar (igual que en pro-connect-hub)
    $('campo-estado-cita').classList.add('oculto');
    mostrarPaso('tipo');
    abrirModal();
});

function limpiarFormularioCita() {
    $('cita_id').value = '';
    $('paciente_id').value = '';
    $('paciente_nombre').value = '';
    $('paciente_telefono').value = '';
    $('titulo').value = '';
    $('fecha').value = fmtFecha(estado.fechaSeleccionada);
    $('hora_inicio').value = '10:00';
    $('duracion').value = '30';
    $('estado').value = 'programada';
    $('precio').value = '';
    $('metodo_pago').value = 'efectivo';
    $('descripcion').value = '';
    $('notas').value = '';
    $('np_nombre').value = '';
    $('np_telefono').value = '';
    $('np_email').value = '';
    $('np_fecha_nac').value = '';
    $('np_genero').value = 'no_especificado';
    $('np_ocupacion').value = '';
    $('np_direccion').value = '';
    $('np_alergias').value = '';
    $('np_padecimientos').value = '';
    $('np_medicamentos').value = '';
    $('np_notas').value = '';
    $('np_foto').value = '';
    $('npCropContenedor').style.display = 'none';
    npCropState.img = null;
    npCropState.rot = 0;
    if ($('npCropZoom'))  $('npCropZoom').value  = 100;
    if ($('npCropRotar')) $('npCropRotar').value = 0;
    $('buscarPaciente').value = '';
    $('resultadosPacientes').innerHTML =
        '<div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">Empieza a escribir para buscar.</div>';
}


/* (cropper de foto del paciente nuevo: ver agenda-foto-crop.js) */


/* ============================================================
   PASO 1 · TIPO DE PACIENTE
   ============================================================ */
$('btnPacienteExistente').addEventListener('click', function() {
    mostrarPaso('seleccionar');
    cargarPacientesRecientes();
    setTimeout(function(){ $('buscarPaciente').focus(); }, 60);
});

$('btnPacienteNuevo').addEventListener('click', function() {
    mostrarPaso('nuevo');
    setTimeout(function(){ $('np_nombre').focus(); }, 60);
});


/* ============================================================
   PASO 2 · BUSCAR PACIENTE EXISTENTE
   ============================================================ */
var timerBusqueda = null;
$('buscarPaciente').addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(timerBusqueda);
    if (q.length === 0) {
        cargarPacientesRecientes();
        return;
    }
    if (q.length < 2) {
        $('resultadosPacientes').innerHTML =
            '<div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">Escribe al menos 2 letras.</div>';
        return;
    }
    timerBusqueda = setTimeout(function() {
        fetch('pacientes.php?accion=buscar&q=' + encodeURIComponent(q))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.ok) {
                    if (data.redirect) { location.href = '../index.php'; return; }
                    $('resultadosPacientes').innerHTML =
                        '<div class="texto-atenuado" style="padding: var(--espacio-md);">' + escaparHtml(data.mensaje) + '</div>';
                    return;
                }
                pintarResultadosPacientes(data.pacientes);
            })
            .catch(function() {
                $('resultadosPacientes').innerHTML =
                    '<div class="texto-atenuado" style="padding: var(--espacio-md);">Error de conexión.</div>';
            });
    }, 220);
});

function pintarResultadosPacientes(pacientes) {
    var cont = $('resultadosPacientes');
    if (pacientes.length === 0) {
        cont.innerHTML = '<div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">' +
                         'Sin resultados. ¿Quieres registrarlo como paciente nuevo?</div>';
        return;
    }
    var html = '';
    pacientes.forEach(function(p) {
        html +=
            '<button type="button" class="resultado-paciente" data-id="' + p.paciente_id + '">' +
                '<div class="resultado-paciente-nombre">' + escaparHtml(p.nombre_completo) + '</div>' +
                '<div class="resultado-paciente-info mono">' +
                    (p.telefono ? escaparHtml(p.telefono) : '<span class="texto-atenuado">sin teléfono</span>') +
                '</div>' +
            '</button>';
    });
    cont.innerHTML = html;
    cont.querySelectorAll('.resultado-paciente').forEach(function(b) {
        b.addEventListener('click', function() {
            var id = this.dataset.id;
            var p = pacientes.filter(function(x){ return String(x.paciente_id) === String(id); })[0];
            seleccionarPaciente(p);
        });
    });
}

function seleccionarPaciente(p) {
    estado.pacienteActual = p;
    $('paciente_id').value = p.paciente_id;
    $('paciente_nombre').value = p.nombre_completo;
    $('paciente_telefono').value = p.telefono || '';
    $('pacienteResumenNombre').textContent = p.nombre_completo;
    $('pacienteResumenTelefono').textContent = p.telefono ? '· ' + p.telefono : '';
    mostrarPaso('datos');
    setTimeout(function(){ $('titulo').focus(); }, 60);
}


/* ============================================================
   PASO 3 · REGISTRAR PACIENTE NUEVO
   ============================================================ */
$('btnContinuarConPaciente').addEventListener('click', function() {
    var nombre = $('np_nombre').value.trim();
    if (nombre.length < 2) {
        toast('El nombre del paciente es obligatorio (mínimo 2 letras).', false);
        $('np_nombre').focus();
        return;
    }
    var fd = new FormData();
    fd.append('accion', 'crear');
    fd.append('nombre_completo',  nombre);
    fd.append('telefono',         $('np_telefono').value.trim());
    fd.append('email',            $('np_email').value.trim());
    fd.append('fecha_nacimiento', $('np_fecha_nac').value);
    fd.append('genero',           $('np_genero').value);
    fd.append('ocupacion',        $('np_ocupacion').value.trim());
    fd.append('direccion',        $('np_direccion').value.trim());
    fd.append('alergias',         $('np_alergias').value.trim());
    fd.append('padecimientos',    $('np_padecimientos').value.trim());
    fd.append('medicamentos',     $('np_medicamentos').value.trim());
    fd.append('notas',            $('np_notas').value.trim());

    var btn = this;
    btn.disabled = true; btn.textContent = 'Guardando…';

    fetch('pacientes.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                btn.disabled = false; btn.textContent = 'Continuar →';
                toast(data.mensaje, false);
                return;
            }
            if (npCropState.img) {
                var canvas = $('npCropCanvas');
                var dataURL = canvas.toDataURL('image/jpeg', 0.9);
                var fdFoto = new FormData();
                fdFoto.append('paciente_id', data.paciente.paciente_id);
                fdFoto.append('imagen_b64', dataURL);
                fetch('../pacientes/foto-subir.php', { method: 'POST', body: fdFoto })
                    .then(function(rr){ return rr.json(); })
                    .then(function(){})
                    .catch(function(){})
                    .finally(function() {
                        btn.disabled = false; btn.textContent = 'Continuar →';
                        seleccionarPaciente(data.paciente);
                    });
            } else {
                btn.disabled = false; btn.textContent = 'Continuar →';
                seleccionarPaciente(data.paciente);
            }
        })
        .catch(function() {
            btn.disabled = false; btn.textContent = 'Continuar →';
            toast('Error de conexión.', false);
        });
});


/* ============================================================
   PASO 4 · GUARDAR CITA (crear o editar)
   ============================================================ */
$('cancelarCita').addEventListener('click', cerrarModal);

$('formCita').addEventListener('submit', function(e) {
    e.preventDefault();

    var titulo = $('titulo').value.trim();
    var fecha  = $('fecha').value;
    var hora   = $('hora_inicio').value;
    var dur    = parseInt($('duracion').value, 10);

    if (titulo.length < 2) { toast('El motivo de la cita es obligatorio.', false); $('titulo').focus(); return; }
    if (!fecha || !hora)  { toast('Fecha y hora son obligatorias.', false); return; }
    if (!dur || dur <= 0) { toast('Duración no válida.', false); return; }

    var fd = new FormData();
    if (estado.modoEditar) fd.append('cita_id', $('cita_id').value);
    fd.append('paciente_id',       $('paciente_id').value);
    fd.append('paciente_nombre',   $('paciente_nombre').value);
    fd.append('paciente_telefono', $('paciente_telefono').value);
    fd.append('titulo',      titulo);
    fd.append('descripcion', $('descripcion').value.trim());
    fd.append('fecha',       fecha);
    fd.append('hora_inicio', hora);
    fd.append('duracion',    dur);
    fd.append('estado',      $('estado').value);
    fd.append('notas',       $('notas').value.trim());
    fd.append('precio',      $('precio').value);
    fd.append('metodo_pago', $('metodo_pago').value);

    var btn = $('btnGuardarCita');
    btn.disabled = true; btn.textContent = 'Guardando…';

    var url = estado.modoEditar ? 'actualizar.php' : 'guardar.php';

    fetch(url, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            btn.disabled = false; btn.textContent = 'Guardar cita';
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                toast(data.mensaje, false);
                return;
            }
            toast(data.mensaje, true);
            cerrarModal();
            if (data.fecha) {
                estado.fechaSeleccionada = new Date(data.fecha + 'T00:00:00');
                estado.mesVisible = new Date(estado.fechaSeleccionada);
            }
            cargarCitasDelMes();
            cargarCitasDelDia(); cargarMemorias();
        })
        .catch(function() {
            btn.disabled = false; btn.textContent = 'Guardar cita';
            toast('Error de conexión.', false);
        });
});


/* ============================================================
   EDITAR CITA · precarga el formulario
   ============================================================ */
function abrirEditar(id) {
    fetch('actualizar.php?cita_id=' + encodeURIComponent(id))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                toast(data.mensaje, false);
                return;
            }
            estado.modoEditar = true;
            $('modalCitaTitulo').textContent = 'Editar cita';
            $('btnGuardarCita').textContent = 'Actualizar cita';

            var c = data.cita;
            $('cita_id').value           = c.cita_id;
            $('paciente_id').value       = c.paciente_id || '';
            $('paciente_nombre').value   = c.paciente_nombre;
            $('paciente_telefono').value = c.paciente_telefono || '';
            $('pacienteResumenNombre').textContent    = c.paciente_nombre;
            $('pacienteResumenTelefono').textContent  = c.paciente_telefono ? '· ' + c.paciente_telefono : '';

            $('titulo').value      = c.titulo;
            $('descripcion').value = c.descripcion || '';

            $('fecha').value       = c.fecha_hora_inicio.substring(0,10);
            $('hora_inicio').value = c.fecha_hora_inicio.substring(11,16);

            var ini = new Date(c.fecha_hora_inicio.replace(' ', 'T'));
            var fin = new Date(c.fecha_hora_fin.replace(' ', 'T'));
            var dur = Math.round((fin - ini) / 60000);
            $('duracion').value = String(dur > 0 ? dur : 30);

            $('estado').value      = c.estado;
            $('precio').value      = c.precio !== null ? c.precio : '';
            $('notas').value       = c.notas || '';

            // Estado visible solo al editar (igual que en pro-connect-hub)
            $('campo-estado-cita').classList.remove('oculto');

            mostrarPaso('datos');
            abrirModal();
        })
        .catch(function(){ toast('Error de conexión.', false); });
}

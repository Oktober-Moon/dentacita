/* ============================================================
   DENTACITA · AGENDA · LÓGICA DEL FRONTEND
   ------------------------------------------------------------
   Maneja:
   - Calendario mensual con marcadores de citas
   - Lista de citas del día seleccionado
   - Modal multi-paso para crear/editar citas
   - Búsqueda de pacientes y registro de pacientes nuevos
   - Acciones rápidas: cambiar estado, eliminar
   ============================================================ */


/* ----- Estado global ----- */
var estado = {
    fechaSeleccionada: new Date(),
    mesVisible:        new Date(),
    citasDelMes:       [],
    modoEditar:        false,
    pacienteActual:    null  // {id, nombre, telefono, email?, ...}
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
    setTimeout(function(){ t.className = 'toast'; }, 4000);
}

function fmtFecha(d) {
    // YYYY-MM-DD usando hora local (no UTC, para evitar el bug de un día)
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


/* ============================================================
   CALENDARIO
   ============================================================ */
function pintarCalendario() {
    var mes = estado.mesVisible.getMonth();
    var anio = estado.mesVisible.getFullYear();

    $('calendarioMes').textContent = NOMBRES_MES[mes] + ' ' + anio;

    // Día 0 = domingo. Convertimos a "lunes primero" (0=lun, 6=dom)
    var primero = new Date(anio, mes, 1);
    var diaSemanaInicio = (primero.getDay() + 6) % 7;
    var diasEnMes = new Date(anio, mes + 1, 0).getDate();

    // Citas por día (en el mes visible) · agrupadas con su estado
    var citasPorDia = {};
    estado.citasDelMes.forEach(function(c) {
        var f = c.fecha_hora_inicio.substring(0, 10); // YYYY-MM-DD
        if (!citasPorDia[f]) citasPorDia[f] = [];
        citasPorDia[f].push(c);
    });

    var grid = $('calendarioGrid');
    grid.innerHTML = '';

    // Huecos antes del primer día
    for (var i = 0; i < diaSemanaInicio; i++) {
        var hueco = document.createElement('div');
        hueco.className = 'calendario-celda calendario-celda-vacia';
        grid.appendChild(hueco);
    }

    // Días del mes
    for (var d = 1; d <= diasEnMes; d++) {
        var fecha = new Date(anio, mes, d);
        var clave = fmtFecha(fecha);
        var celda = document.createElement('button');
        celda.type = 'button';
        celda.className = 'calendario-celda';
        if (esHoy(fecha)) celda.classList.add('calendario-celda-hoy');
        if (mismoDia(fecha, estado.fechaSeleccionada)) celda.classList.add('calendario-celda-seleccionada');

        var citasDia = citasPorDia[clave] || [];
        var html = '<span class="calendario-celda-num">' + d + '</span>';
        if (citasDia.length > 0) {
            html += '<span class="calendario-celda-puntos">';
            // Hasta 3 puntos · cada uno con la clase del estado de su cita.
            var n = Math.min(citasDia.length, 3);
            for (var p = 0; p < n; p++) {
                var est = citasDia[p].estado || 'programada';
                html += '<span class="punto punto-' + est + '"></span>';
            }
            html += '</span>';
        }
        celda.innerHTML = html;
        celda.dataset.fecha = clave;
        celda.addEventListener('click', function() {
            var f = this.dataset.fecha;
            estado.fechaSeleccionada = new Date(f + 'T00:00:00');
            pintarCalendario();
            cargarCitasDelDia(); cargarMemorias();
        });
        grid.appendChild(celda);
    }
}

function cambiarMes(delta) {
    estado.mesVisible.setMonth(estado.mesVisible.getMonth() + delta);
    cargarCitasDelMes();
}

$('mesAnterior').addEventListener('click', function(){ cambiarMes(-1); });
$('mesSiguiente').addEventListener('click', function(){ cambiarMes(1); });


/* ============================================================
   CITAS DEL MES (para los marcadores del calendario)
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


/* ============================================================
   CITAS DEL DÍA SELECCIONADO
   ============================================================ */
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

/* Determina si una cita tuvo reembolso parcial/total y devuelve un badge HTML.
   Requiere que mostrar.php devuelva cobrado_total y reembolsado_total. */
function refundBadgeHtml(c) {
    var cobrado    = parseFloat(c.cobrado_total || 0);
    var reembolsado = parseFloat(c.reembolsado_total || 0);
    if (reembolsado <= 0) return '';
    if (cobrado > 0 && reembolsado >= cobrado) {
        return ' <span class="badge badge-reembolso">Reembolso total</span>';
    }
    return ' <span class="badge badge-reembolso">Reembolso parcial</span>';
}

/* Devuelve el monto disponible para reembolsar de una cita: cobrado − reembolsado_previo. */
function montoReembolsable(c) {
    var cobrado     = parseFloat(c.cobrado_total || 0);
    var reembolsado = parseFloat(c.reembolsado_total || 0);
    return Math.max(0, cobrado - reembolsado);
}

function pintarCitasDelDia(citas) {
    var cont = $('citasDiaContenido');
    if (citas.length === 0) {
        cont.innerHTML =
            '<div class="estado-vacio">' +
            '<div class="estado-vacio-titulo">Sin citas este día</div>' +
            '<div class="estado-vacio-desc">Da clic en + Nueva cita para agendar.</div>' +
            '</div>';
        return;
    }
    var ahoraTs = Date.now();
    var html = '<ul class="lista-citas">';
    citas.forEach(function(c) {
        var hi = c.fecha_hora_inicio.substring(11, 16);
        var hf = c.fecha_hora_fin.substring(11, 16);
        // Una cita está "vencida" si pasó su hora de inicio y sigue en programada/confirmada.
        var inicioTs = new Date(c.fecha_hora_inicio.replace(' ', 'T')).getTime();
        var vencida = (c.estado === 'programada' || c.estado === 'confirmada')
                   && inicioTs < ahoraTs;
        html +=
            '<li class="cita-item' + (vencida ? ' cita-overdue' : '') + '" data-id="' + c.cita_id + '">' +
                '<div class="cita-hora mono">' + hi + ' – ' + hf + '</div>' +
                '<div class="cita-cuerpo">' +
                    '<div class="cita-titulo">' + escaparHtml(c.titulo) + '</div>' +
                    '<div class="cita-paciente">' + escaparHtml(c.paciente_nombre) +
                    (c.paciente_telefono ? ' · <span class="texto-atenuado mono">' + escaparHtml(c.paciente_telefono) + '</span>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="cita-estado">' +
                    '<span class="badge badge-' + c.estado + '">' + (NOMBRES_ESTADO[c.estado] || c.estado) + '</span>' +
                    (vencida ? ' <span class="badge badge-overdue">Vencida</span>' : '') +
                    refundBadgeHtml(c) +
                    ' <select class="cita-estado-select" data-id="' + c.cita_id + '" title="Cambiar estado rápido">' +
                        Object.keys(NOMBRES_ESTADO).map(function(k) {
                            var sel = (k === c.estado) ? ' selected' : '';
                            return '<option value="' + k + '"' + sel + '>' + NOMBRES_ESTADO[k] + '</option>';
                        }).join('') +
                    '</select>' +
                '</div>' +
                '<div class="cita-acciones">' +
                    (montoReembolsable(c) > 0
                      ? '<button type="button" class="btn btn-secundario btn-sm btn-reembolsar" data-id="' + c.cita_id + '" data-monto-disp="' + montoReembolsable(c) + '" data-titulo="' + escaparHtml(c.titulo) + '" data-paciente="' + escaparHtml(c.paciente_nombre) + '">Reembolsar</button>'
                      : '') +
                    '<button type="button" class="btn btn-secundario btn-sm btn-editar" data-id="' + c.cita_id + '">Editar</button>' +
                    '<button type="button" class="btn btn-peligro-fantasma btn-sm btn-eliminar" data-id="' + c.cita_id + '">Eliminar</button>' +
                '</div>' +
            '</li>';
    });
    html += '</ul>';
    cont.innerHTML = html;
}


/* ============================================================
   ACCIONES SOBRE CITAS (delegación)
   ============================================================ */
$('citasDiaContenido').addEventListener('click', function(e) {
    var btnEditar = e.target.closest('.btn-editar');
    if (btnEditar) { abrirEditar(btnEditar.dataset.id); return; }

    var btnEliminar = e.target.closest('.btn-eliminar');
    if (btnEliminar) {
        if (!confirm('¿Eliminar esta cita? La acción no se puede deshacer.')) return;
        var id = btnEliminar.dataset.id;
        var fd = new FormData(); fd.append('cita_id', id);
        fetch('eliminar.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                toast(data.mensaje, data.ok);
                if (data.ok) { cargarCitasDelMes(); cargarCitasDelDia(); }
                if (data.redirect) location.href = '../index.php';
            })
            .catch(function(){ toast('Error de conexión.', false); });
    }
});

/* Reembolso desde la cita · abre modal y delega a finanzas/reembolso.php */
$('citasDiaContenido').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-reembolsar');
    if (!btn) return;
    var disp = parseFloat(btn.dataset.montoDisp || 0);
    $('reembolsoCita_id').value = btn.dataset.id;
    $('reembolsoCita_monto').value = disp.toFixed(2);
    $('reembolsoCita_monto').max = disp;
    $('reembolsoCitaDetalle').textContent =
        btn.dataset.titulo + ' · ' + btn.dataset.paciente;
    $('reembolsoCita_disponible').textContent =
        'Disponible: $' + disp.toFixed(2) + ' (cobrado − reembolsado previo)';
    $('modalReembolsoCita').classList.add('visible');
});

(function() {
    var modal  = $('modalReembolsoCita');
    var formRC = $('formReembolsoCita');
    function cerrar() { modal.classList.remove('visible'); }
    $('cerrarModalReembolsoCita').addEventListener('click', cerrar);
    $('cancelarReembolsoCita').addEventListener('click', cerrar);
    modal.addEventListener('click', function(e) { if (e.target === modal) cerrar(); });

    formRC.addEventListener('submit', function(e) {
        e.preventDefault();
        var datos = new FormData(formRC);
        fetch('../finanzas/reembolso.php', { method: 'POST', body: datos })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.redirect) { location.href = '../index.php'; return; }
                if (!data.ok) { toast(data.mensaje, false); return; }
                toast(data.mensaje, true);
                cerrar();
                cargarCitasDelMes();
                cargarCitasDelDia();
            })
            .catch(function() { toast('Error de conexión.', false); });
    });
})();

/* Acciones rápidas inline · cambio de estado vía dropdown.
   Si el destino es 'cancelada', pedimos motivo opcional via prompt. */
$('citasDiaContenido').addEventListener('change', function(e) {
    var sel = e.target.closest('.cita-estado-select');
    if (!sel) return;
    var id = sel.dataset.id;
    var nuevo = sel.value;
    var fd = new FormData();
    fd.append('cita_id', id);
    fd.append('estado',  nuevo);
    if (nuevo === 'cancelada') {
        var motivo = prompt('Motivo de la cancelación (opcional):', '');
        if (motivo === null) {
            // El usuario canceló el prompt → revertir el select al estado anterior
            cargarCitasDelDia();
            return;
        }
        if (motivo) fd.append('motivo_cancelacion', motivo);
    }
    fetch('cambiar-estado.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.redirect) { location.href = '../index.php'; return; }
            toast(data.mensaje, data.ok);
            if (data.ok) { cargarCitasDelMes(); cargarCitasDelDia(); }
        })
        .catch(function(){ toast('Error de conexión.', false); });
});


/* ============================================================
   MODAL · NAVEGACIÓN ENTRE PASOS
   ============================================================ */
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

function abrirModal() { $('modalCita').classList.add('visible'); }
function cerrarModal() { $('modalCita').classList.remove('visible'); }

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
    mostrarPaso('tipo');
    abrirModal();
});

function limpiarFormularioCita() {
    $('cita_id').value = '';
    $('paciente_id').value = '';
    $('paciente_nombre').value = '';
    $('paciente_telefono').value = '';
    $('titulo').value = '';
    // Pre-rellena la fecha con la seleccionada en el calendario
    $('fecha').value = fmtFecha(estado.fechaSeleccionada);
    $('hora_inicio').value = '10:00';
    $('duracion').value = '30';
    $('estado').value = 'programada';
    $('precio').value = '';
    $('metodo_pago').value = 'efectivo';
    $('descripcion').value = '';
    $('notas').value = '';
    // Pasos paciente
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

/* ============================================================
   NUEVO PACIENTE · cropper de foto reutilizado
   ============================================================ */
var npCropState = { img: null, scale: 1, dx: 0, dy: 0, rot: 0,
                    dragging: false, startX: 0, startY: 0 };

function npDibujarCrop() {
    var canvas = $('npCropCanvas');
    var ctx = canvas.getContext('2d');
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = 'rgba(255,255,255,0.05)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if (!npCropState.img) { ctx.restore(); return; }
    var cx = canvas.width / 2, cy = canvas.height / 2;
    ctx.translate(cx, cy);
    ctx.rotate((npCropState.rot * Math.PI) / 180);
    ctx.translate(-cx, -cy);
    ctx.drawImage(npCropState.img, npCropState.dx, npCropState.dy,
                  npCropState.img.width * npCropState.scale,
                  npCropState.img.height * npCropState.scale);
    ctx.restore();
}

$('np_foto').addEventListener('change', function(e) {
    var f = e.target.files[0];
    if (!f) return;
    if (!f.type.match(/^image\/(jpeg|png|webp)$/)) {
        toast('Solo JPG, PNG o WEBP.', false);
        e.target.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(ev) {
        var img = new Image();
        img.onload = function() {
            npCropState.img = img;
            var canvas = $('npCropCanvas');
            var minDim = Math.min(img.width, img.height);
            npCropState.scale = canvas.width / minDim;
            npCropState.dx = (canvas.width - img.width * npCropState.scale) / 2;
            npCropState.dy = (canvas.height - img.height * npCropState.scale) / 2;
            npCropState.rot = 0;
            $('npCropZoom').value = 100;
            $('npCropRotar').value = 0;
            $('npCropContenedor').style.display = '';
            npDibujarCrop();
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(f);
});

(function() {
    var canvasEl = $('npCropCanvas');
    canvasEl.addEventListener('mousedown', function(e) {
        if (!npCropState.img) return;
        npCropState.dragging = true;
        npCropState.startX = e.offsetX - npCropState.dx;
        npCropState.startY = e.offsetY - npCropState.dy;
    });
    canvasEl.addEventListener('mousemove', function(e) {
        if (!npCropState.dragging) return;
        npCropState.dx = e.offsetX - npCropState.startX;
        npCropState.dy = e.offsetY - npCropState.startY;
        npDibujarCrop();
    });
    canvasEl.addEventListener('mouseup',   function(){ npCropState.dragging = false; });
    canvasEl.addEventListener('mouseleave',function(){ npCropState.dragging = false; });

    $('npCropZoom').addEventListener('input', function(e) {
        if (!npCropState.img) return;
        var nuevoZoom = (+e.target.value) / 100;
        var canvas = $('npCropCanvas');
        var minDim = Math.min(npCropState.img.width, npCropState.img.height);
        var scaleBase = canvas.width / minDim;
        npCropState.scale = scaleBase * nuevoZoom;
        npDibujarCrop();
    });
    $('npCropRotar').addEventListener('input', function(e) {
        if (!npCropState.img) return;
        npCropState.rot = parseInt(e.target.value, 10) || 0;
        npDibujarCrop();
    });
})();


/* ============================================================
   PASO 1 · TIPO DE PACIENTE
   ============================================================ */
$('btnPacienteExistente').addEventListener('click', function() {
    mostrarPaso('seleccionar');
    cargarPacientesRecientes();
    setTimeout(function(){ $('buscarPaciente').focus(); }, 60);
});

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
            // Si el dentista subió foto, subirla en background y luego seguir.
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
            // Si la cita se movió a otro día, navegar a ese día
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

            // Fecha y hora desde fecha_hora_inicio (formato MySQL: 'YYYY-MM-DD HH:MM:SS')
            $('fecha').value       = c.fecha_hora_inicio.substring(0,10);
            $('hora_inicio').value = c.fecha_hora_inicio.substring(11,16);

            // Duración a partir de la diferencia entre inicio y fin
            var ini = new Date(c.fecha_hora_inicio.replace(' ', 'T'));
            var fin = new Date(c.fecha_hora_fin.replace(' ', 'T'));
            var dur = Math.round((fin - ini) / 60000);
            // Si la duración no está en las opciones, agrega una opción custom
            var sel = $('duracion');
            var coincide = Array.from(sel.options).some(function(o){ return parseInt(o.value,10) === dur; });
            if (!coincide) {
                var op = document.createElement('option');
                op.value = String(dur); op.textContent = dur + ' min'; op.selected = true;
                sel.appendChild(op);
            }
            sel.value = String(dur);

            $('estado').value      = c.estado;
            $('precio').value      = c.precio !== null ? c.precio : '';
            $('notas').value       = c.notas || '';

            mostrarPaso('datos');
            abrirModal();
        })
        .catch(function(){ toast('Error de conexión.', false); });
}


/* ============================================================
   MEMORIAS PERSONALES (panel inferior)
   ============================================================ */
var memoriasCache = [];

function cargarMemorias() {
    var f = fmtFecha(estado.fechaSeleccionada);
    var cont = $('memoriasContenido');
    cont.innerHTML = '<div class="tabla-cargando">Cargando…</div>';

    fetch('memorias-listar.php?fecha_inicio=' + f + '&fecha_fin=' + f)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.redirect) { window.location.href = '../index.php'; return; }
            if (!data.ok) { cont.innerHTML = '<div class="texto-atenuado">Error al cargar.</div>'; return; }
            memoriasCache = data.memorias || [];
            pintarMemorias();
        })
        .catch(function(){
            cont.innerHTML = '<div class="texto-atenuado">Error de conexión.</div>';
        });
}

function pintarMemorias() {
    var cont = $('memoriasContenido');
    if (!memoriasCache.length) {
        cont.innerHTML = '<div class="texto-atenuado">Sin memorias para este día.</div>';
        return;
    }
    var html = '';
    memoriasCache.forEach(function(m) {
        var color = m.color || 'amarillo';
        var fechaTxt = m.fecha ? formatearFechaCorta(m.fecha) : '';
        html += '<div class="memoria-card memoria-' + escaparHtml(color) + '">'
              + '<div class="memoria-fecha">' + escaparHtml(fechaTxt) + '</div>'
              + '<div>' + escaparHtml(m.contenido).replace(/\n/g, '<br>') + '</div>'
              + '<div class="memoria-acciones">'
              + '<button type="button" class="btn-link" data-mem-accion="editar" data-mem-id="' + m.memoria_id + '">Editar</button>'
              + '<button type="button" class="btn-link btn-link-peligro" data-mem-accion="eliminar" data-mem-id="' + m.memoria_id + '">Eliminar</button>'
              + '</div>'
              + '</div>';
    });
    cont.innerHTML = html;
}

/* Acciones de memorias */
$('memoriasContenido').addEventListener('click', function(e) {
    var btn = e.target.closest('button[data-mem-accion]');
    if (!btn) return;
    var id     = btn.dataset.memId;
    var accion = btn.dataset.memAccion;
    var m = memoriasCache.find(function(x){ return String(x.memoria_id) === String(id); });
    if (!m) return;

    if (accion === 'editar') {
        abrirModalMemoria(m);
    }
    if (accion === 'eliminar') {
        if (!confirm('¿Eliminar esta memoria?')) return;
        var datos = new FormData();
        datos.append('memoria_id', id);
        fetch('memoria-eliminar.php', { method: 'POST', body: datos })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.redirect) { window.location.href = '../index.php'; return; }
                if (!data.ok) { toast(data.mensaje, false); return; }
                toast(data.mensaje, true);
                cargarMemorias();
            })
            .catch(function(){ toast('Error de conexión.', false); });
    }
});

/* Modal memoria */
var modalMem    = $('modalMemoria');
var formMem     = $('formMemoria');
var tituloMem   = $('tituloModalMemoria');
var memColorInp = $('memoria_color');

function abrirModalMemoria(memoria) {
    formMem.reset();
    if (memoria) {
        tituloMem.textContent = 'Editar memoria';
        $('memoria_id').value      = memoria.memoria_id;
        $('memoria_fecha').value   = memoria.fecha;
        $('memoria_contenido').value = memoria.contenido;
        memColorInp.value = memoria.color || 'amarillo';
    } else {
        tituloMem.textContent = 'Nueva memoria';
        $('memoria_id').value = '';
        $('memoria_fecha').value = fmtFecha(estado.fechaSeleccionada);
        memColorInp.value = 'amarillo';
    }
    actualizarColorActivo();
    modalMem.classList.add('visible');
}

function cerrarModalMemoria() {
    modalMem.classList.remove('visible');
}

document.querySelectorAll('[data-cerrar-memoria]').forEach(function(b) {
    b.addEventListener('click', cerrarModalMemoria);
});
modalMem.addEventListener('click', function(e) {
    if (e.target === modalMem) cerrarModalMemoria();
});

$('btnNuevaMemoria').addEventListener('click', function(){ abrirModalMemoria(null); });

/* Selector de color */
function actualizarColorActivo() {
    var actual = memColorInp.value;
    document.querySelectorAll('#memoriaColores .color-pick').forEach(function(b) {
        b.classList.toggle('color-activo', b.dataset.color === actual);
    });
}
document.querySelectorAll('#memoriaColores .color-pick').forEach(function(b) {
    b.addEventListener('click', function() {
        memColorInp.value = b.dataset.color;
        actualizarColorActivo();
    });
});

formMem.addEventListener('submit', function(e) {
    e.preventDefault();
    var datos = new FormData(formMem);
    var id    = $('memoria_id').value;
    var url   = id ? 'memoria-guardar.php' : 'memoria-guardar.php';
    fetch(url, { method: 'POST', body: datos })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.redirect) { window.location.href = '../index.php'; return; }
            if (!data.ok) { toast(data.mensaje, false); return; }
            toast(data.mensaje, true);
            cerrarModalMemoria();
            cargarMemorias();
        })
        .catch(function(){ toast('Error de conexión.', false); });
});


/* ============================================================
   VISTA SEMANAL · 7 columnas × franjas horarias
   ============================================================ */
var estadoSemana = {
    inicioSemana: lunesDeLaSemana(new Date()),
    citas: []
};

function lunesDeLaSemana(d) {
    var copia = new Date(d);
    var diaSemana = copia.getDay(); // 0=domingo..6=sábado
    var diff = (diaSemana === 0 ? -6 : 1 - diaSemana);
    copia.setDate(copia.getDate() + diff);
    copia.setHours(0,0,0,0);
    return copia;
}

function cambiarVista(vista) {
    var btnM = $('btnVistaMensual'), btnS = $('btnVistaSemanal');
    if (vista === 'semanal') {
        $('vistaMensual').style.display = 'none';
        $('vistaSemanal').style.display = '';
        if (btnM) btnM.classList.remove('btn-primario'); btnM && btnM.classList.add('btn-secundario');
        if (btnS) btnS.classList.add('btn-primario');
        cargarSemana();
    } else {
        $('vistaSemanal').style.display = 'none';
        $('vistaMensual').style.display = '';
        if (btnS) btnS.classList.remove('btn-primario'); btnS && btnS.classList.add('btn-secundario');
        if (btnM) btnM.classList.add('btn-primario');
    }
}

document.querySelectorAll('[data-vista]').forEach(function(btn) {
    btn.addEventListener('click', function(){ cambiarVista(btn.dataset.vista); });
});
$('semAnterior').addEventListener('click', function() {
    estadoSemana.inicioSemana.setDate(estadoSemana.inicioSemana.getDate() - 7);
    cargarSemana();
});
$('semSiguiente').addEventListener('click', function() {
    estadoSemana.inicioSemana.setDate(estadoSemana.inicioSemana.getDate() + 7);
    cargarSemana();
});

function cargarSemana() {
    var inicio = new Date(estadoSemana.inicioSemana);
    var fin    = new Date(inicio); fin.setDate(fin.getDate() + 6);
    $('semanaTitulo').textContent =
        inicio.toLocaleDateString('es-MX', { day:'numeric', month:'short' }) + ' – ' +
        fin.toLocaleDateString('es-MX', { day:'numeric', month:'short', year:'numeric' });
    var cont = $('semanaContenedor');
    cont.innerHTML = '<div class="tabla-cargando">Cargando…</div>';
    fetch('mostrar.php?desde=' + fmtFecha(inicio) + '&hasta=' + fmtFecha(fin))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                if (data.redirect) { location.href = '../index.php'; return; }
                cont.innerHTML = '<div class="estado-vacio">' + escaparHtml(data.mensaje) + '</div>';
                return;
            }
            estadoSemana.citas = data.citas || [];
            pintarSemana();
        })
        .catch(function(){ cont.innerHTML = '<div class="estado-vacio">Error al cargar.</div>'; });
}

function pintarSemana() {
    var cont = $('semanaContenedor');
    var inicio = new Date(estadoSemana.inicioSemana);
    var diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    var horaIni = 8, horaFin = 20; // 8 AM a 8 PM
    var ahoraTs = Date.now();

    var html = '<div class="semana-cabecera">';
    html += '<div class="semana-col-hora"></div>';
    for (var d = 0; d < 7; d++) {
        var fechaCol = new Date(inicio); fechaCol.setDate(inicio.getDate() + d);
        var esHoyCol = mismoDia(fechaCol, new Date());
        html += '<div class="semana-col-titulo' + (esHoyCol ? ' semana-col-hoy' : '') + '">' +
                  '<div class="texto-pequeno">' + diasNombres[d] + '</div>' +
                  '<div class="mono">' + fechaCol.getDate() + '</div>' +
                '</div>';
    }
    html += '</div>';

    html += '<div class="semana-cuerpo">';
    // Columna de horas
    html += '<div class="semana-col-hora">';
    for (var h = horaIni; h <= horaFin; h++) {
        html += '<div class="semana-fila-hora mono">' + (h < 10 ? '0' : '') + h + ':00</div>';
    }
    html += '</div>';
    // 7 columnas de día
    for (var d = 0; d < 7; d++) {
        var fechaCol = new Date(inicio); fechaCol.setDate(inicio.getDate() + d);
        var claveDia = fmtFecha(fechaCol);
        html += '<div class="semana-col" data-fecha="' + claveDia + '">';
        // Líneas guía (una por hora)
        for (var h = horaIni; h <= horaFin; h++) {
            html += '<div class="semana-celda-hora"></div>';
        }
        // Pintar citas del día como bloques absolutos
        var citasDia = estadoSemana.citas.filter(function(c) {
            return c.fecha_hora_inicio.substring(0, 10) === claveDia;
        });
        citasDia.forEach(function(c) {
            var ini = new Date(c.fecha_hora_inicio.replace(' ', 'T'));
            var fin = new Date(c.fecha_hora_fin.replace(' ', 'T'));
            var iniHorasFlot = ini.getHours() + ini.getMinutes() / 60;
            var finHorasFlot = fin.getHours() + fin.getMinutes() / 60;
            // Cada fila de hora tiene 40px alto
            var top = (iniHorasFlot - horaIni) * 40;
            var height = Math.max(20, (finHorasFlot - iniHorasFlot) * 40 - 2);
            var vencida = (c.estado === 'programada' || c.estado === 'confirmada')
                       && ini.getTime() < ahoraTs;
            var hi = c.fecha_hora_inicio.substring(11, 16);
            html += '<div class="semana-cita semana-cita-' + c.estado +
                    (vencida ? ' semana-cita-overdue' : '') +
                    '" style="top:' + top + 'px; height:' + height + 'px;" ' +
                    'data-id="' + c.cita_id + '" title="' + escaparHtml(c.titulo + ' · ' + c.paciente_nombre) + '">' +
                      '<div class="semana-cita-hora mono">' + hi + '</div>' +
                      '<div class="semana-cita-titulo">' + escaparHtml(c.titulo) + '</div>' +
                      '<div class="semana-cita-paciente">' + escaparHtml(c.paciente_nombre) + '</div>' +
                    '</div>';
        });
        html += '</div>';
    }
    html += '</div>';

    cont.innerHTML = html;

    // Click en cita semanal → abrir editor
    cont.querySelectorAll('.semana-cita').forEach(function(el) {
        el.addEventListener('click', function() { abrirEditar(el.dataset.id); });
    });
}


/* ============================================================
   ARRANQUE
   ============================================================ */
cargarCitasDelMes();
cargarCitasDelDia();
cargarMemorias();

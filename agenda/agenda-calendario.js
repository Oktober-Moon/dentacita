/* =====================================================================
 * AGENDA · vistas (calendario mensual, lista del día, vista semanal)
 * y acciones rápidas sobre citas (cambio de estado, eliminar, reembolso).
 * ---------------------------------------------------------------------
 * Depende de: agenda-ajax.js
 * ====================================================================*/

/* ============================================================
   CALENDARIO MENSUAL
   ============================================================ */
function pintarCalendario() {
    var mes = estado.mesVisible.getMonth();
    var anio = estado.mesVisible.getFullYear();

    $('calendarioMes').textContent = NOMBRES_MES[mes] + ' ' + anio;

    var primero = new Date(anio, mes, 1);
    var diaSemanaInicio = (primero.getDay() + 6) % 7;
    var diasEnMes = new Date(anio, mes + 1, 0).getDate();

    var citasPorDia = {};
    estado.citasDelMes.forEach(function(c) {
        var f = c.fecha_hora_inicio.substring(0, 10);
        if (!citasPorDia[f]) citasPorDia[f] = [];
        citasPorDia[f].push(c);
    });

    var grid = $('calendarioGrid');
    grid.innerHTML = '';

    for (var i = 0; i < diaSemanaInicio; i++) {
        var hueco = document.createElement('div');
        hueco.className = 'calendario-celda calendario-celda-vacia';
        grid.appendChild(hueco);
    }

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
   LISTA DE CITAS DEL DÍA (con badges de reembolso)
   ============================================================ */
function refundBadgeHtml(c) {
    var cobrado    = parseFloat(c.cobrado_total || 0);
    var reembolsado = parseFloat(c.reembolsado_total || 0);
    if (reembolsado <= 0) return '';
    if (cobrado > 0 && reembolsado >= cobrado) {
        return ' <span class="badge badge-reembolso">Reembolso total</span>';
    }
    return ' <span class="badge badge-reembolso">Reembolso parcial</span>';
}

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

/* Cambio rápido de estado vía dropdown inline */
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
   VISTA SEMANAL · 7 columnas × franjas horarias
   ============================================================ */
var estadoSemana = {
    inicioSemana: lunesDeLaSemana(new Date()),
    citas: []
};

function lunesDeLaSemana(d) {
    var copia = new Date(d);
    var diaSemana = copia.getDay();
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
    var horaIni = 8, horaFin = 20;
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
    html += '<div class="semana-col-hora">';
    for (var h = horaIni; h <= horaFin; h++) {
        html += '<div class="semana-fila-hora mono">' + (h < 10 ? '0' : '') + h + ':00</div>';
    }
    html += '</div>';
    for (var d = 0; d < 7; d++) {
        var fechaCol = new Date(inicio); fechaCol.setDate(inicio.getDate() + d);
        var claveDia = fmtFecha(fechaCol);
        html += '<div class="semana-col" data-fecha="' + claveDia + '">';
        for (var h = horaIni; h <= horaFin; h++) {
            html += '<div class="semana-celda-hora"></div>';
        }
        var citasDia = estadoSemana.citas.filter(function(c) {
            return c.fecha_hora_inicio.substring(0, 10) === claveDia;
        });
        citasDia.forEach(function(c) {
            var ini = new Date(c.fecha_hora_inicio.replace(' ', 'T'));
            var fin = new Date(c.fecha_hora_fin.replace(' ', 'T'));
            var iniHorasFlot = ini.getHours() + ini.getMinutes() / 60;
            var finHorasFlot = fin.getHours() + fin.getMinutes() / 60;
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

    cont.querySelectorAll('.semana-cita').forEach(function(el) {
        el.addEventListener('click', function() { abrirEditar(el.dataset.id); });
    });
}

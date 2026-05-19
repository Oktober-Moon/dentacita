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
            html += '<div class="calendario-celda-citas">';
            var n = Math.min(citasDia.length, 3);
            for (var p = 0; p < n; p++) {
                var c = citasDia[p];
                var est = c.estado || 'programada';
                var hi = (c.fecha_hora_inicio || '').substring(11, 16);
                var titulo = c.titulo || c.paciente_nombre || '—';
                html += '<span class="cal-mes-cita cal-mes-cita-' + est + '">' +
                          '<span class="cal-mes-cita-hora">' + fmtHora(hi) + '</span>' +
                          '<span class="cal-mes-cita-titulo">' + escaparHtml(titulo) + '</span>' +
                        '</span>';
            }
            if (citasDia.length > 3) {
                html += '<span class="cal-mes-cita-mas">+' + (citasDia.length - 3) + ' más</span>';
            }
            html += '</div>';
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
$('mesHoy').addEventListener('click', function() {
    var hoy = new Date();
    estado.mesVisible = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    estado.fechaSeleccionada = hoy;
    cargarCitasDelMes();
    cargarCitasDelDia();
    cargarMemorias();
});


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
            '<li class="cita-item cita-item-' + c.estado + (vencida ? ' cita-overdue' : '') + '" data-id="' + c.cita_id + '">' +
                '<div class="cita-hora mono">' + fmtHora(hi) + ' – ' + fmtHora(hf) + '</div>' +
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
                if (data.ok) refrescarAgenda();
                if (data.redirect) location.href = '../index.php';
            })
            .catch(function(){ toast('No se pudo conectar.', false); });
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
                refrescarAgenda();
            })
            .catch(function() { toast('No se pudo conectar.', false); });
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
            if (data.ok) refrescarAgenda();
        })
        .catch(function(){ toast('No se pudo conectar.', false); });
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
        if (btnM) btnM.classList.remove('activo');
        if (btnS) btnS.classList.add('activo');
        cargarSemana();
    } else {
        $('vistaSemanal').style.display = 'none';
        $('vistaMensual').style.display = '';
        if (btnS) btnS.classList.remove('activo');
        if (btnM) btnM.classList.add('activo');
    }
    /* Persistir preferencia entre recargas. localStorage puede fallar
       (modo privado, cuota llena, etc.) — silencioso si pasa. */
    try { localStorage.setItem('agenda_vista_preferida', vista); } catch (e) { /* ignorar */ }
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
$('semHoy').addEventListener('click', function() {
    estadoSemana.inicioSemana = lunesDeLaSemana(new Date());
    /* Resetear scroll para que pintarSemana() salte a la hora útil al
       repintar (si el usuario ya había scrolleado manualmente, queremos
       reseteo en "Hoy"). */
    var cont = $('semanaContenedor');
    if (cont) cont.scrollTop = 0;
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

/* Constantes de rango horario · la agenda cubre el día completo (0:00-23:00)
   y el contenedor scrollea verticalmente. El scroll inicial salta a las 8:00
   para mostrar el bloque útil del día sin que el usuario tenga que arrastrar. */
var SEMANA_HORA_INI    = 0;
var SEMANA_HORA_FIN    = 23;             // inclusive, así que en total 24 filas
var SEMANA_FILA_PX     = 56;
var SEMANA_SCROLL_INI  = 8;              // hora a la que hacemos scrollTop inicial

function pintarSemana() {
    var cont = $('semanaContenedor');
    var inicio = new Date(estadoSemana.inicioSemana);
    var diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    var horaIni = SEMANA_HORA_INI, horaFin = SEMANA_HORA_FIN;
    var ahoraTs = Date.now();

    /* Guardamos el scrollTop actual para conservarlo en repintados (cambio
       de formato de hora, navegar a otra semana, etc.). Si es la primera
       carga, scrollTop=0 y abajo saltamos a SEMANA_SCROLL_INI. */
    var scrollPrevio = cont.scrollTop;

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
        var hhmm = (h < 10 ? '0' : '') + h + ':00';
        html += '<div class="semana-fila-hora mono">' + fmtHora(hhmm, true) + '</div>';
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
            /* Con el rango 0-23 la única forma de quedar fuera es que la
               cita termine en 24:00 (cruzando medianoche) — el server
               valida y rechaza ese caso, así que asumimos rango válido. */
            var top    = (iniHorasFlot - horaIni) * SEMANA_FILA_PX;
            var height = Math.max(28, (finHorasFlot - iniHorasFlot) * SEMANA_FILA_PX - 2);
            var vencida = (c.estado === 'programada' || c.estado === 'confirmada')
                       && ini.getTime() < ahoraTs;
            var hi = c.fecha_hora_inicio.substring(11, 16);
            html += '<div class="semana-cita semana-cita-' + c.estado +
                    (vencida ? ' semana-cita-overdue' : '') +
                    '" style="top:' + top + 'px; height:' + height + 'px;" ' +
                    'data-id="' + c.cita_id + '" title="' + escaparHtml(c.titulo + ' · ' + c.paciente_nombre) + '">' +
                      '<div class="semana-cita-hora mono">' + fmtHora(hi) + '</div>' +
                      '<div class="semana-cita-titulo">' + escaparHtml(c.titulo) + '</div>' +
                      '<div class="semana-cita-paciente">' + escaparHtml(c.paciente_nombre) + '</div>' +
                    '</div>';
        });
        /* Línea horizontal "ahora" · solo en la columna del día actual.
           Con rango 0-23 siempre queda dentro de la franja. */
        if (mismoDia(fechaCol, new Date())) {
            var ahora = new Date();
            var hAhora = ahora.getHours() + ahora.getMinutes() / 60;
            var topAhora = (hAhora - horaIni) * SEMANA_FILA_PX;
            var pad2 = function(n) { return n < 10 ? '0' + n : '' + n; };
            var horaTxt = fmtHora(pad2(ahora.getHours()) + ':' + pad2(ahora.getMinutes()));
            html += '<div class="semana-linea-ahora" style="top:' + topAhora + 'px">' +
                      '<span class="semana-linea-ahora-label">' + horaTxt + '</span>' +
                    '</div>';
        }
        html += '</div>';
    }
    html += '</div>';

    if (estadoSemana.citas.length === 0) {
        html += '<div class="estado-vacio" style="text-align:center; padding:var(--espacio-md)">' +
                'No hay citas en esta semana.</div>';
    }

    cont.innerHTML = html;

    /* Scroll vertical · conservar posición previa si el usuario ya scrolleó;
       en primera carga (scrollPrevio=0) ir a SEMANA_SCROLL_INI para que se
       vea el bloque útil del día. */
    if (scrollPrevio > 0) {
        cont.scrollTop = scrollPrevio;
    } else {
        cont.scrollTop = SEMANA_SCROLL_INI * SEMANA_FILA_PX;
    }

    cont.querySelectorAll('.semana-cita').forEach(function(el) {
        el.addEventListener('click', function() { abrirEditar(el.dataset.id); });
    });
}

/* ============================================================
   AUTO-ACTUALIZACIÓN DE LA LÍNEA "AHORA" (sin recargar la página)
   ------------------------------------------------------------
   pintarSemana() dibuja la línea naranja con la hora actual una
   sola vez. Para que se mueva en tiempo real (cada minuto que
   pasa), corremos un setInterval que SOLO actualiza la posición
   y la etiqueta del elemento .semana-linea-ahora; no re-renderiza
   toda la rejilla (lo cual provocaría parpadeos y pérdida de
   estado en los bloques de citas).
   ============================================================ */
function actualizarLineaAhora() {
    /* Solo tiene sentido si la vista semanal está visible. */
    var vistaSem = $('vistaSemanal');
    if (!vistaSem || vistaSem.style.display === 'none') return;

    var cont = $('semanaContenedor');
    if (!cont) return;

    var ahora = new Date();
    var horaIni = SEMANA_HORA_INI;
    var hAhora = ahora.getHours() + ahora.getMinutes() / 60;

    var pad2 = function(n) { return n < 10 ? '0' + n : '' + n; };
    var horaTxt = fmtHora(pad2(ahora.getHours()) + ':' + pad2(ahora.getMinutes()));

    /* Buscar la columna del día actual dentro de la semana visible.
       Si el usuario está viendo otra semana, no habrá coincidencia
       y simplemente removemos la línea (si quedó visible). */
    var hoyStr = fmtFecha(ahora);
    var colHoy = cont.querySelector('.semana-col[data-fecha="' + hoyStr + '"]');
    var linea  = cont.querySelector('.semana-linea-ahora');

    /* Si hoy no está en la semana visible, quitamos la línea si seguía
       colgada. Con rango 0-23 la hora actual siempre cae dentro. */
    if (!colHoy) {
        if (linea) linea.parentNode.removeChild(linea);
        return;
    }

    var topAhora = (hAhora - horaIni) * SEMANA_FILA_PX;

    if (linea && linea.parentNode === colHoy) {
        /* Mismo contenedor: solo movemos y actualizamos texto. */
        linea.style.top = topAhora + 'px';
        var label = linea.querySelector('.semana-linea-ahora-label');
        if (label) label.textContent = horaTxt;
    } else {
        /* No existe (o estaba en otra columna por cambio de día):
           la creamos en la columna correcta. */
        if (linea) linea.parentNode.removeChild(linea);
        linea = document.createElement('div');
        linea.className = 'semana-linea-ahora';
        linea.style.top = topAhora + 'px';
        linea.innerHTML = '<span class="semana-linea-ahora-label">' + horaTxt + '</span>';
        colHoy.appendChild(linea);
    }
}

/* Tick ANCLADO al cambio de minuto del reloj real.
   ------------------------------------------------------------
   Un setInterval(fn, 30000) puede ir desfasado hasta ~30s, lo
   que hace que el indicador se vea "atrasado". En lugar de eso:
     1) Calculamos cuántos ms faltan para el próximo :00 de minuto.
     2) Disparamos un setTimeout puntual a ese instante.
     3) A partir de ahí, setInterval cada 60s — siempre cae sobre
        el cambio de minuto, así que el indicador nunca se ve
        retrasado más de unos pocos ms.
   También llamamos actualizarLineaAhora() inmediatamente para
   que si la página llevaba mucho tiempo abierta, el indicador
   se ponga al día al instante. */
(function arrancarRelojAgenda() {
    actualizarLineaAhora();
    var ahora = new Date();
    var msHastaSigMinuto = (60 - ahora.getSeconds()) * 1000 - ahora.getMilliseconds();
    setTimeout(function() {
        actualizarLineaAhora();
        setInterval(actualizarLineaAhora, 60000);
    }, msHastaSigMinuto);
})();

/* Cuando la pestaña vuelve a primer plano (visibilitychange) o la
   ventana recibe foco, sincronizamos inmediatamente — sin esperar
   al siguiente tick. Esto cubre el caso de tener la agenda abierta
   en una pestaña en segundo plano durante horas. */
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) actualizarLineaAhora();
});
window.addEventListener('focus', actualizarLineaAhora);


/* Aplicar preferencia de vista al cargar (si existe en localStorage).
   Se ejecuta una sola vez al final de este script — los listeners de
   los botones ya están enganchados arriba. */
try {
    var vistaPref = localStorage.getItem('agenda_vista_preferida');
    if (vistaPref === 'semanal') {
        cambiarVista('semanal');
    }
} catch (e) { /* ignorar */ }

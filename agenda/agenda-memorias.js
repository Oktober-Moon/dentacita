/* =====================================================================
 * AGENDA · panel de memorias personales (notas del día) + modal memoria
 * ---------------------------------------------------------------------
 * Último archivo en cargar. Al final dispara el arranque inicial de
 * la página (calendario + lista del día + memorias del día).
 * Depende de: agenda-ajax.js
 * ====================================================================*/

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
   ARRANQUE
   ============================================================ */
cargarCitasDelMes();
cargarCitasDelDia();
cargarMemorias();

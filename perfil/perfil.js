/* =====================================================================
 * PERFIL · lógica frontend (single-tenant simplificado)
 *   Secciones: nombre + foto · selector de tema (8 colores legacy)
 *   La barra de tabs fue removida; ambas secciones se muestran apiladas.
 * ====================================================================*/
(() => {

const $  = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);
const toastEl = $('#toast');

let perfilCache = null;


/* ---------- Helpers ---------- */
function toast(m, t='ok') {
    const tiposValidos = { ok: 'toast-ok', error: 'toast-error', info: 'toast-info', advertencia: 'toast-advertencia' };
    const cls = tiposValidos[t] || 'toast-ok';
    toastEl.textContent = m;
    toastEl.className = 'toast ' + cls + ' visible';
    setTimeout(() => toastEl.classList.remove('visible'), 2800);
}
function manejarRedirect(json) {
    if (json && json.redirect) { window.location.href = '../index.php'; return false; }
    return true;
}


/* ---------- Modales ---------- */
function abrirModal(id)  { document.getElementById(id).classList.add('visible'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('visible'); }

$$('[data-cerrar-modal]').forEach(b => b.addEventListener('click', () =>
    cerrarModal(b.dataset.cerrarModal)
));
$$('.modal-fondo').forEach(m => m.addEventListener('click', (e) => {
    if (e.target === m) m.classList.remove('visible');
}));
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    $$('.modal-fondo.visible').forEach(m => m.classList.remove('visible'));
});


/* ---------- Cargar datos ---------- */
async function cargar() {
    try {
        const resp = await fetch('mostrar.php');
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }

        perfilCache = json.perfil;
        renderDatos();
        renderTema();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
}


/* ---------- Tema visual · 8 colores planos ---------- */
function renderTema() {
    const actual = perfilCache.variante_tema || 'cyan-default';
    document.querySelectorAll('[data-tema]').forEach(c =>
        c.classList.toggle('tema-card-activo', c.dataset.tema === actual));
}

async function aplicarTema(nuevoTema) {
    /* Optimistic: aplica al DOM primero, persiste, revierte si falla. */
    const previo = document.documentElement.getAttribute('data-tema');
    document.documentElement.setAttribute('data-tema', nuevoTema);
    document.querySelectorAll('[data-tema]').forEach(c =>
        c.classList.toggle('tema-card-activo', c.dataset.tema === nuevoTema));
    const datos = new FormData();
    datos.append('variante', nuevoTema);
    try {
        const resp = await fetch('tema-actualizar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) {
            document.documentElement.setAttribute('data-tema', previo || 'cyan-default');
            renderTema();
            toast(json.mensaje, 'error');
            return;
        }
        perfilCache.variante_tema = nuevoTema;
        toast(json.mensaje);
    } catch (e) {
        document.documentElement.setAttribute('data-tema', previo || 'cyan-default');
        renderTema();
        toast('No se pudo guardar el tema.', 'error');
    }
}

document.querySelectorAll('[data-tema]').forEach(c => c.addEventListener('click', () => {
    aplicarTema(c.dataset.tema);
}));


/* SVG inline (data URI) usado como placeholder cuando no hay foto.
   Va en el `src` del <img> directamente para evitar el ícono "broken
   image" que algunos navegadores renderizan al ver un <img> sin src. */
const AVATAR_PLACEHOLDER = "data:image/svg+xml;utf8," + encodeURIComponent(
    "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'>" +
        "<rect width='64' height='64' fill='rgba(255,255,255,0.04)'/>" +
        "<circle cx='32' cy='24' r='12' fill='rgba(255,255,255,0.35)'/>" +
        "<path d='M12 60c0-11 9-20 20-20s20 9 20 20' fill='rgba(255,255,255,0.35)'/>" +
    "</svg>"
);

/* ---------- Render datos ---------- */
function renderDatos() {
    const p = perfilCache;
    $('#d_nombre').value     = p.nombre_completo || '';
    autosaveUltimoValor      = p.nombre_completo || '';
    const img = $('#fotoPerfilImg');
    if (p.foto_url) {
        img.src = p.foto_url;
        img.classList.remove('perfil-foto-vacia');
        img.alt = 'Foto de perfil';
        img.onerror = () => {
            img.src = AVATAR_PLACEHOLDER;
            img.classList.add('perfil-foto-vacia');
            img.alt = '';
        };
    } else {
        img.src = AVATAR_PLACEHOLDER;
        img.classList.add('perfil-foto-vacia');
        img.alt = '';
    }
}

/* Auto-save con debounce 1500ms · indicador inline */
let autosaveTimer;
let autosaveUltimoValor = '';
const autosaveIndic = $('#autosaveIndicador');

function pintarAutosaveEstado(texto, color) {
    if (!autosaveIndic) return;
    autosaveIndic.textContent = texto ? '· ' + texto : '';
    autosaveIndic.style.color = color || '';
}

async function guardarPerfilDebounce() {
    const valorActual = $('#d_nombre').value.trim();
    if (valorActual === autosaveUltimoValor) return; // sin cambios
    if (!valorActual) {
        pintarAutosaveEstado('el nombre es obligatorio', 'var(--color-peligro-texto)');
        return;
    }
    pintarAutosaveEstado('guardando…');
    const datos = new FormData($('#formDatos'));
    try {
        const resp = await fetch('guardar.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) {
            pintarAutosaveEstado(json.mensaje || 'error', 'var(--color-peligro-texto)');
            return;
        }
        autosaveUltimoValor = valorActual;
        pintarAutosaveEstado('guardado', 'var(--color-exito-texto)');
        setTimeout(() => pintarAutosaveEstado(''), 2000);
    } catch (e) {
        pintarAutosaveEstado('sin conexión, reintentando…', 'var(--color-advertencia-texto)');
    }
}

$('#d_nombre').addEventListener('input', () => {
    pintarAutosaveEstado('editando…');
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(guardarPerfilDebounce, 1500);
});

// El form ya no submitea: prevenir Enter por compatibilidad.
$('#formDatos').addEventListener('submit', (e) => {
    e.preventDefault();
    clearTimeout(autosaveTimer);
    guardarPerfilDebounce();
});


/* ====================================================================
 * FOTO con CROP (canvas)
 * ====================================================================*/
const cropState = {
    img: null, scale: 1, dx: 0, dy: 0,
    rot: 0, // grados, -180 a 180
    dragging: false, startX: 0, startY: 0
};

$('#btnCambiarFoto').addEventListener('click', () => {
    $('#inputFoto').value = '';
    $('#cropContenedor').style.display = 'none';
    $('#btnGuardarFoto').disabled = true;
    cropState.rot = 0;
    if ($('#cropRotar')) $('#cropRotar').value = 0;
    abrirModal('modalFoto');
});

$('#inputFoto').addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (!f.type.match(/^image\/(jpeg|png|webp)$/)) {
        toast('Solo se permiten JPG, PNG o WEBP.', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = (ev) => {
        const img = new Image();
        img.onload = () => {
            cropState.img = img;
            const canvas = $('#cropCanvas');
            const minDim = Math.min(img.width, img.height);
            cropState.scale = canvas.width / minDim;
            cropState.dx = (canvas.width - img.width * cropState.scale) / 2;
            cropState.dy = (canvas.height - img.height * cropState.scale) / 2;
            $('#cropZoom').value = 100;
            $('#cropContenedor').style.display = '';
            $('#btnGuardarFoto').disabled = false;
            dibujarCrop();
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(f);
});

function dibujarCrop() {
    const canvas = $('#cropCanvas');
    const ctx = canvas.getContext('2d');
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = 'rgba(255,255,255,0.05)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if (!cropState.img) { ctx.restore(); return; }

    // Rota alrededor del centro del canvas, después aplica el offset arrastrado.
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    ctx.translate(cx, cy);
    ctx.rotate((cropState.rot * Math.PI) / 180);
    ctx.translate(-cx, -cy);

    ctx.drawImage(cropState.img, cropState.dx, cropState.dy,
                  cropState.img.width * cropState.scale,
                  cropState.img.height * cropState.scale);
    ctx.restore();
}

const canvasEl = $('#cropCanvas');
canvasEl.addEventListener('mousedown', (e) => {
    if (!cropState.img) return;
    cropState.dragging = true;
    cropState.startX = e.offsetX - cropState.dx;
    cropState.startY = e.offsetY - cropState.dy;
});
canvasEl.addEventListener('mousemove', (e) => {
    if (!cropState.dragging) return;
    cropState.dx = e.offsetX - cropState.startX;
    cropState.dy = e.offsetY - cropState.startY;
    dibujarCrop();
});
canvasEl.addEventListener('mouseup',   () => cropState.dragging = false);
canvasEl.addEventListener('mouseleave',() => cropState.dragging = false);

$('#cropZoom').addEventListener('input', (e) => {
    if (!cropState.img) return;
    const nuevoZoom = (+e.target.value) / 100;
    const canvas = $('#cropCanvas');
    const minDim = Math.min(cropState.img.width, cropState.img.height);
    const scaleBase = canvas.width / minDim;
    cropState.scale = scaleBase * nuevoZoom;
    dibujarCrop();
});

$('#cropRotar').addEventListener('input', (e) => {
    if (!cropState.img) return;
    cropState.rot = parseInt(e.target.value, 10) || 0;
    dibujarCrop();
});

$('#btnGuardarFoto').addEventListener('click', async () => {
    if (!cropState.img) return;
    const canvas = $('#cropCanvas');
    const dataURL = canvas.toDataURL('image/jpeg', 0.9);
    const datos = new FormData();
    datos.append('imagen_b64', dataURL);
    try {
        const resp = await fetch('foto-subir.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) { toast(json.mensaje, 'error'); return; }
        toast(json.mensaje);
        cerrarModal('modalFoto');
        cargar();
    } catch (e) { toast('No se pudo conectar.', 'error'); }
});


cargar();

})();

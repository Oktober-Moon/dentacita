/* =====================================================================
 * PACIENTES · cambiar foto desde el header de la ficha (detalle.php)
 * ---------------------------------------------------------------------
 * Replica el patrón de perfil/perfil.js pero para pacientes:
 * abre modal con cropper (zoom + rotación + drag), envía la imagen
 * recortada como dataURL a foto-subir.php y actualiza el avatar in-place.
 * Lee window.PACIENTE_ID seteado por detalle.php.
 * ====================================================================*/

(function() {
    const PACIENTE_ID = window.PACIENTE_ID || 0;
    const $ = (s) => document.querySelector(s);
    const toastEl = $('#toast');

    function toast(m, t='ok') {
        if (!toastEl) { alert(m); return; }
        toastEl.textContent = m;
        toastEl.className = 'toast ' + (t==='error' ? 'toast-error' : 'toast-ok') + ' visible';
        setTimeout(() => toastEl.classList.remove('visible'), 2400);
    }
    function manejarRedirect(json) {
        if (json && json.redirect) { window.location.href = '../index.php'; return false; }
        return true;
    }

    const modal = $('#modalFotoPaciente');
    if (!modal) return;

    document.querySelectorAll('[data-cerrar-modal-pf]').forEach(b =>
        b.addEventListener('click', () => modal.classList.remove('visible'))
    );
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('visible'); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('visible')) modal.classList.remove('visible');
    });

    /* ---------- Cropper ---------- */
    const cropState = {
        img: null, scale: 1, dx: 0, dy: 0, rot: 0,
        dragging: false, startX: 0, startY: 0
    };

    $('#pf_btnCambiar').addEventListener('click', () => {
        $('#pf_inputFoto').value = '';
        $('#pf_cropContenedor').style.display = 'none';
        $('#pf_btnGuardar').disabled = true;
        cropState.rot = 0;
        $('#pf_cropRotar').value = 0;
        $('#pf_cropZoom').value = 100;
        modal.classList.add('visible');
    });

    $('#pf_inputFoto').addEventListener('change', (e) => {
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
                const canvas = $('#pf_cropCanvas');
                const minDim = Math.min(img.width, img.height);
                cropState.scale = canvas.width / minDim;
                cropState.dx = (canvas.width - img.width * cropState.scale) / 2;
                cropState.dy = (canvas.height - img.height * cropState.scale) / 2;
                $('#pf_cropZoom').value = 100;
                $('#pf_cropContenedor').style.display = '';
                $('#pf_btnGuardar').disabled = false;
                dibujarCrop();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(f);
    });

    function dibujarCrop() {
        const canvas = $('#pf_cropCanvas');
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.fillStyle = 'rgba(255,255,255,0.05)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (!cropState.img) { ctx.restore(); return; }

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

    const canvasEl = $('#pf_cropCanvas');
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

    $('#pf_cropZoom').addEventListener('input', (e) => {
        if (!cropState.img) return;
        const nuevoZoom = (+e.target.value) / 100;
        const canvas = $('#pf_cropCanvas');
        const minDim = Math.min(cropState.img.width, cropState.img.height);
        const scaleBase = canvas.width / minDim;
        cropState.scale = scaleBase * nuevoZoom;
        dibujarCrop();
    });

    $('#pf_cropRotar').addEventListener('input', (e) => {
        if (!cropState.img) return;
        cropState.rot = parseInt(e.target.value, 10) || 0;
        dibujarCrop();
    });

    /* ---------- Submit ---------- */
    $('#pf_btnGuardar').addEventListener('click', async () => {
        if (!cropState.img) return;
        const canvas = $('#pf_cropCanvas');
        const dataURL = canvas.toDataURL('image/jpeg', 0.9);
        const datos = new FormData();
        datos.append('paciente_id', PACIENTE_ID);
        datos.append('imagen_b64', dataURL);

        $('#pf_btnGuardar').disabled = true;
        $('#pf_btnGuardar').textContent = 'Guardando…';
        try {
            const resp = await fetch('foto-subir.php', { method: 'POST', body: datos });
            const json = await resp.json();
            if (!manejarRedirect(json)) return;
            if (!json.ok) {
                toast(json.mensaje, 'error');
                $('#pf_btnGuardar').disabled = false;
                $('#pf_btnGuardar').textContent = 'Guardar foto';
                return;
            }
            // Actualiza el avatar in-place sin recargar
            const img = $('#pf_avatar');
            const ini = $('#pf_avatar_inicial');
            if (img && json.foto_url) {
                img.src = json.foto_url + '?t=' + Date.now();
                img.style.display = '';
                if (ini) ini.style.display = 'none';
            }
            toast(json.mensaje);
            modal.classList.remove('visible');
        } catch (e) {
            toast('No se pudo conectar.', 'error');
        } finally {
            $('#pf_btnGuardar').disabled = false;
            $('#pf_btnGuardar').textContent = 'Guardar foto';
        }
    });
})();

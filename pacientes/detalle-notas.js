/* =====================================================================
 * PACIENTES · notas clínicas en detalle.php (tab 5)
 * ---------------------------------------------------------------------
 * Modal nueva/editar nota + autosave inline (debounced).
 * Lee window.PACIENTE_ID seteado por detalle.php antes de cargar este
 * archivo.
 * ====================================================================*/

(function() {
    const PACIENTE_ID = window.PACIENTE_ID || 0;
    const modal = document.getElementById('modalNota');
    const form = document.getElementById('formNota');
    const titulo = document.getElementById('tituloModalNota');
    const toastEl = document.getElementById('toast');
    function toast(m, t='ok') {
        if (!toastEl) { alert(m); return; }
        toastEl.textContent = m;
        toastEl.className = 'toast ' + (t==='error' ? 'toast-error' : 'toast-ok') + ' visible';
        setTimeout(() => toastEl.classList.remove('visible'), 2800);
    }
    function abrir(nota) {
        form.reset();
        if (nota) {
            titulo.textContent = 'Editar nota';
            document.getElementById('nota_id').value = nota.id;
            document.getElementById('nota_fecha').value = nota.fecha;
            document.getElementById('nota_contenido').value = nota.contenido;
        } else {
            titulo.textContent = 'Nueva nota';
            document.getElementById('nota_id').value = '';
            document.getElementById('nota_fecha').value = new Date().toISOString().slice(0, 10);
        }
        modal.classList.add('visible');
    }
    document.getElementById('btnNuevaNota').addEventListener('click', () => abrir(null));
    modal.querySelectorAll('[data-cerrar-modal="modalNota"]').forEach(b =>
        b.addEventListener('click', () => modal.classList.remove('visible'))
    );
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('visible'); });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const datos = new FormData(form);
        const id = document.getElementById('nota_id').value;
        const url = id ? 'notas-actualizar.php' : 'notas-guardar.php';
        try {
            const resp = await fetch(url, { method: 'POST', body: datos });
            const json = await resp.json();
            if (json.redirect) { window.location.href = '../index.php'; return; }
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            toast(json.mensaje);
            setTimeout(() => window.location.reload(), 600);
        } catch (e) { toast('No se pudo conectar.', 'error'); }
    });

    /* Auto-save inline en cada nota existente.
       Debounce de 1500ms desde el último keystroke; muestra estado en
       .nota-autosave-indicador. */
    const timersAutosave = {};
    document.querySelectorAll('.nota-contenido-editable').forEach(ta => {
        const id = ta.dataset.editId;
        const fechaOriginal = ta.dataset.fecha;
        const indic = document.querySelector(`[data-indicador-id="${id}"]`);

        ta.addEventListener('input', () => {
            if (indic) indic.textContent = '· editando…';
            clearTimeout(timersAutosave[id]);
            timersAutosave[id] = setTimeout(async () => {
                if (indic) indic.textContent = '· guardando…';
                const datos = new FormData();
                datos.append('nota_id', id);
                datos.append('paciente_id', PACIENTE_ID);
                datos.append('fecha', fechaOriginal);
                datos.append('contenido', ta.value);
                try {
                    const resp = await fetch('notas-actualizar.php', { method: 'POST', body: datos });
                    const json = await resp.json();
                    if (json.redirect) { window.location.href = '../index.php'; return; }
                    if (!json.ok) {
                        if (indic) indic.textContent = '· ' + (json.mensaje || 'error');
                        if (indic) indic.style.color = 'var(--color-peligro-texto)';
                        return;
                    }
                    if (indic) {
                        indic.textContent = '· guardado';
                        indic.style.color = 'var(--color-exito-texto)';
                        setTimeout(() => { if (indic) indic.textContent = ''; }, 2000);
                    }
                } catch (e) {
                    if (indic) {
                        indic.textContent = '· sin conexión, reintentando…';
                        indic.style.color = 'var(--color-advertencia-texto)';
                    }
                }
            }, 1500);
        });
    });

    document.querySelectorAll('[data-nota-accion]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const accion = btn.dataset.notaAccion;
            const id     = btn.dataset.notaId;
            if (accion === 'editar') {
                abrir({ id, fecha: btn.dataset.notaFecha, contenido: btn.dataset.notaContenido });
            }
            if (accion === 'eliminar') {
                if (!confirm('¿Eliminar esta nota?')) return;
                const datos = new FormData();
                datos.append('nota_id', id);
                try {
                    const resp = await fetch('notas-eliminar.php', { method: 'POST', body: datos });
                    const json = await resp.json();
                    if (!json.ok) { toast(json.mensaje, 'error'); return; }
                    toast(json.mensaje);
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) { toast('No se pudo conectar.', 'error'); }
            }
        });
    });
})();

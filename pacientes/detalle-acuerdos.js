/* =====================================================================
 * PACIENTES · acciones del modal de acuerdos en detalle.php (tab 3)
 * ---------------------------------------------------------------------
 * Recibe el ID del paciente desde window.PACIENTE_ID (lo setea
 * detalle.php antes de cargar este archivo).
 * ====================================================================*/

(function() {
    const modal = document.getElementById('modalAcuerdoPaciente');
    const btnNuevo = document.getElementById('btnNuevoAcuerdo');
    const form = document.getElementById('formAcuerdoPaciente');
    const toastEl = document.getElementById('toast');
    function toast(m, t='ok') {
        if (!toastEl) { alert(m); return; }
        toastEl.textContent = m;
        toastEl.className = 'toast ' + (t==='error' ? 'toast-error' : 'toast-ok') + ' visible';
        setTimeout(() => toastEl.classList.remove('visible'), 2800);
    }
    const tituloModal = document.getElementById('acuerdoModalTitulo');
    const inputAcuerdoId = document.getElementById('acuerdoModalId');

    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            form.reset();
            inputAcuerdoId.value = '';
            tituloModal.textContent = 'Nuevo acuerdo';
            const d = new Date();
            d.setDate(d.getDate() + 7); d.setHours(10, 0, 0, 0);
            document.getElementById('acp_fecha').value = d.toISOString().slice(0, 16);
            modal.classList.add('visible');
        });
    }

    async function abrirEditarAcuerdo(acuerdoId) {
        form.reset();
        tituloModal.textContent = 'Editar acuerdo';
        inputAcuerdoId.value = acuerdoId;
        modal.classList.add('visible');
        try {
            const r = await fetch('acuerdo-actualizar.php?acuerdo_id=' + encodeURIComponent(acuerdoId));
            const json = await r.json();
            if (json.redirect) { window.location.href = '../index.php'; return; }
            if (!json.ok) { toast(json.mensaje, 'error'); modal.classList.remove('visible'); return; }
            const a = json.acuerdo;
            document.getElementById('acp_servicio').value    = a.servicio || '';
            document.getElementById('acp_descripcion').value = a.descripcion || '';
            let fp = (a.fecha_programada || '').replace(' ', 'T').slice(0, 16);
            document.getElementById('acp_fecha').value       = fp;
            document.getElementById('acp_duracion').value    = a.duracion_minutos || 60;
            document.getElementById('acp_precio').value      = a.precio || '';
        } catch (e) {
            toast('Error de conexión.', 'error');
            modal.classList.remove('visible');
        }
    }
    document.querySelectorAll('[data-cerrar-acuerdo]').forEach(b =>
        b.addEventListener('click', () => modal.classList.remove('visible'))
    );
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('visible');
    });

    if (form) form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const datos = new FormData(form);
        const fecha = datos.get('fecha_programada');
        if (fecha) datos.set('fecha_programada', fecha.replace('T', ' ') + ':00');
        const acuerdoId = inputAcuerdoId.value;
        const url = acuerdoId ? 'acuerdo-actualizar.php' : 'acuerdo-crear.php';
        try {
            const resp = await fetch(url, { method: 'POST', body: datos });
            const json = await resp.json();
            if (json.redirect) { window.location.href = '../index.php'; return; }
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            toast(json.mensaje);
            setTimeout(() => window.location.reload(), 800);
        } catch (e) { toast('No se pudo conectar.', 'error'); }
    });

    document.querySelectorAll('[data-ac-accion]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.acId;
            const accion = btn.dataset.acAccion;
            if (accion === 'editar') {
                await abrirEditarAcuerdo(id);
                return;
            }
            let url, datos = new FormData();
            datos.append('acuerdo_id', id);
            if (accion === 'aceptar') {
                if (!confirm('¿Aceptar este acuerdo? Se creará la cita correspondiente.')) return;
                url = 'acuerdo-aceptar.php';
            } else {
                const motivo = (accion !== 'completar') ? prompt('Motivo (opcional):', '') : '';
                if (motivo === null) return;
                const map = { rechazar:'rechazado', cancelar:'cancelado', completar:'completado' };
                datos.append('nuevo_estado', map[accion]);
                if (motivo) datos.append('motivo', motivo);
                url = 'acuerdo-cambiar-estado.php';
            }
            try {
                const resp = await fetch(url, { method: 'POST', body: datos });
                const json = await resp.json();
                if (json.redirect) { window.location.href = '../index.php'; return; }
                if (!json.ok) { toast(json.mensaje, 'error'); return; }
                toast(json.mensaje);
                setTimeout(() => window.location.reload(), 800);
            } catch (e) { toast('No se pudo conectar.', 'error'); }
        });
    });
})();

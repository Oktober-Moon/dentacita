/* =====================================================================
 * FINANZAS · tabla de transacciones, citas cobrables y filtros
 * ---------------------------------------------------------------------
 * Depende de: finanzas-ajax.js. Las acciones de tabla (editar, anular,
 * reembolsar, eliminar) se delegan a funciones declaradas en
 * finanzas-modal.js.
 * ====================================================================*/

/* ---------- Listeners de filtros (recargan al cambiar) ---------- */
['#filtroTipo','#filtroEstado','#filtroDesde','#filtroHasta','#filtroPaciente','#filtroMetodo','#filtroCategoria']
    .forEach(sel => $(sel).addEventListener('change', cargar));


/* ---------- Citas pendientes de cobro ---------- */
function renderCobrables() {
    const card = $('#cobrablesCard');
    const cont = $('#cobrablesLista');
    if (!cacheCobrables.length) {
        card.style.display = 'none';
        return;
    }
    card.style.display = '';
    cont.innerHTML = `
    <table class="tabla-datos">
        <thead><tr>
            <th>Fecha</th><th>Paciente</th><th>Concepto</th>
            <th style="text-align:right">Monto</th><th></th>
        </tr></thead>
        <tbody>
            ${cacheCobrables.map(c => `
            <tr>
                <td class="mono">${fmtFecha(c.fecha_hora_inicio)}</td>
                <td>${escapar(c.paciente_nombre)}</td>
                <td>${escapar(c.titulo)}</td>
                <td style="text-align:right" class="mono">${fmtDinero(c.precio)}</td>
                <td><button type="button" class="btn btn-primario btn-sm" data-cobrar="${c.cita_id}">Cobrar</button></td>
            </tr>`).join('')}
        </tbody>
    </table>`;
}

$('#cobrablesLista').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-cobrar]');
    if (!btn) return;
    const id   = btn.dataset.cobrar;
    const cita = cacheCobrables.find(c => String(c.cita_id) === String(id));
    if (!cita) return;
    $('#cobro_cita_id').value     = id;
    $('#cobro_monto').value       = cita.precio;
    $('#cobroDetalle').textContent = `${cita.titulo} · ${cita.paciente_nombre} · ${fmtFecha(cita.fecha_hora_inicio)}`;
    abrirModal(modalCobro);
});


/* ---------- Tabla principal de transacciones ---------- */
function renderTabla() {
    const cont = $('#tablaContenedor');
    if (!cacheTrans.length) {
        cont.innerHTML = '<div class="estado-vacio"><div class="estado-vacio-titulo">Sin movimientos</div><div class="estado-vacio-desc">Registra una transacción con el botón "+ Nueva transacción".</div></div>';
        return;
    }

    cont.innerHTML = `
    <table class="tabla-datos">
        <thead>
            <tr>
                <th>Fecha</th><th>Tipo</th><th>Categoría</th><th>Descripción</th>
                <th>Vínculo</th><th style="text-align:right">Monto</th>
                <th style="width:160px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            ${cacheTrans.map(t => {
                const auditada = t.inventario_movimiento_id || t.cita_id;
                const reembolso = t.categoria === 'Reembolso';
                const anulada   = t.estado === 'anulada';
                let vinculo = '—';
                if (t.cita_titulo)         vinculo = `<span class="texto-atenuado">Cita:</span> ${escapar(t.cita_titulo)}`;
                else if (t.item_nombre)    vinculo = `<span class="texto-atenuado">Inventario:</span> ${escapar(t.item_nombre)}`;
                else if (t.paciente_nombre)vinculo = escapar(t.paciente_nombre);
                return `
                <tr class="${anulada ? 'fila-anulada' : ''}">
                    <td class="mono">${fmtFecha(t.fecha)}</td>
                    <td><span class="badge badge-${t.tipo}">${escapar(t.tipo)}</span>${reembolso ? ' <span class="badge badge-reembolso">reembolso</span>' : ''}</td>
                    <td>${escapar(t.categoria)}</td>
                    <td>
                        ${escapar(t.descripcion || '')}
                        ${anulada ? `<div class="texto-atenuado texto-pequeno">Anulada: ${escapar(t.motivo_anulacion || '')}</div>` : ''}
                    </td>
                    <td>${vinculo}</td>
                    <td style="text-align:right" class="mono ${t.tipo === 'ingreso' ? 'texto-verde' : 'texto-rojo'}">
                        ${fmtDinero(t.monto)}
                    </td>
                    <td>
                        ${t.cita_id && !reembolso && !anulada
                            ? `<button type="button" class="btn-link" data-accion="reembolsar" data-id="${t.transaccion_id}">Reembolsar</button>`
                            : ''}
                        ${reembolso && !anulada
                            ? `<button type="button" class="btn-link btn-link-peligro" data-accion="anular-reembolso" data-id="${t.transaccion_id}">Anular</button>`
                            : ''}
                        ${!reembolso && !anulada
                            ? `<button type="button" class="btn-link btn-link-peligro" data-accion="anular-trans" data-id="${t.transaccion_id}">Anular</button>`
                            : ''}
                        ${!auditada && !reembolso && !anulada
                            ? `<button type="button" class="btn-link" data-accion="editar" data-id="${t.transaccion_id}">Editar</button>
                               <button type="button" class="btn-link btn-link-peligro" data-accion="eliminar" data-id="${t.transaccion_id}">Eliminar</button>`
                            : ''}
                    </td>
                </tr>`;
            }).join('')}
        </tbody>
    </table>`;
}

$('#tablaContenedor').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    const id = btn.dataset.id;
    const accion = btn.dataset.accion;
    const trans = cacheTrans.find(t => String(t.transaccion_id) === String(id));
    if (!trans) return;

    if (accion === 'editar')           abrirModalTrans(trans);
    if (accion === 'eliminar')         await eliminarTrans(id);
    if (accion === 'reembolsar')       abrirModalReembolso(trans);
    if (accion === 'anular-reembolso') await anularReembolso(id);
    if (accion === 'anular-trans')     await anularTransaccion(id);
});

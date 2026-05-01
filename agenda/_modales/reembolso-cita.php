<!-- Modal · reembolso desde la cita -->
<div id="modalReembolsoCita" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" id="cerrarModalReembolsoCita" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Registrar reembolso</h2>
        <p id="reembolsoCitaDetalle" class="texto-atenuado" style="margin-top:0">—</p>

        <form id="formReembolsoCita" autocomplete="off">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="cita_id" id="reembolsoCita_id">

            <div class="campo">
                <label for="reembolsoCita_monto">Monto a reembolsar *</label>
                <input type="number" name="monto" id="reembolsoCita_monto" min="0.01" step="0.01" required>
                <div class="campo-ayuda" id="reembolsoCita_disponible">—</div>
            </div>

            <div class="campo">
                <label for="reembolsoCita_motivo">Motivo (opcional)</label>
                <textarea name="motivo" id="reembolsoCita_motivo" rows="2" maxlength="200"
                          placeholder="Ej. Paciente solicitó reembolso por insatisfacción."></textarea>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarReembolsoCita">Cancelar</button>
                <button type="submit" class="btn btn-primario">Registrar reembolso</button>
            </div>
        </form>
    </div>
</div>

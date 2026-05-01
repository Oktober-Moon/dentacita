<!-- Modal · memoria personal -->
<div id="modalMemoria" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-memoria aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo" id="tituloModalMemoria">Nueva memoria</h2>
        <form id="formMemoria" autocomplete="off">
            <input type="hidden" name="memoria_id" id="memoria_id">

            <div class="campo">
                <label for="memoria_fecha">Fecha *</label>
                <input type="date" name="fecha" id="memoria_fecha" required>
            </div>

            <div class="campo">
                <label for="memoria_contenido">Contenido *</label>
                <textarea name="contenido" id="memoria_contenido" rows="4" maxlength="1000" required
                          placeholder="Recordatorio, pendiente, observación privada…"></textarea>
            </div>

            <div class="campo">
                <label>Color</label>
                <div class="memoria-colores" id="memoriaColores">
                    <button type="button" class="color-pick color-amarillo" data-color="amarillo" title="Amarillo"></button>
                    <button type="button" class="color-pick color-azul"     data-color="azul"     title="Azul"></button>
                    <button type="button" class="color-pick color-verde"    data-color="verde"    title="Verde"></button>
                    <button type="button" class="color-pick color-rosa"     data-color="rosa"     title="Rosa"></button>
                    <button type="button" class="color-pick color-violeta"  data-color="violeta"  title="Violeta"></button>
                    <button type="button" class="color-pick color-gris"     data-color="gris"     title="Gris"></button>
                </div>
                <input type="hidden" name="color" id="memoria_color" value="amarillo">
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-memoria>Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>

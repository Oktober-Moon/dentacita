<?php
/* Pestaña NOTAS · bitácora clínica del dentista
 * Espera: $notas, $idInt
 */
?>
<div class="ficha-card">
    <div class="ficha-card-titulo">
        Notas clínicas
        <button type="button" class="btn btn-primario btn-sm" id="btnNuevaNota" style="margin-left:auto">+ Nueva nota</button>
    </div>
    <?php if (empty($notas)): ?>
        <div class="placeholder-card-inline">
            <p>Aún no hay notas para este paciente.</p>
            <p class="texto-atenuado">Las notas son tu bitácora libre: observaciones, diagnósticos, recordatorios para la próxima sesión.</p>
        </div>
    <?php else: ?>
        <ul class="lista-notas">
            <?php foreach ($notas as $n): ?>
            <li class="nota-card" data-nota-id="<?php echo (int)$n['nota_id']; ?>">
                <div class="nota-fecha">
                    <?php echo fmtFecha($n['fecha']); ?>
                    <span class="nota-autosave-indicador texto-pequeno texto-atenuado" data-indicador-id="<?php echo (int)$n['nota_id']; ?>"></span>
                </div>
                <textarea class="nota-contenido-editable"
                          data-edit-id="<?php echo (int)$n['nota_id']; ?>"
                          data-fecha="<?php echo htmlspecialchars($n['fecha']); ?>"
                          rows="4" maxlength="5000"><?php echo htmlspecialchars($n['contenido']); ?></textarea>
                <?php if ($n['actualizado_en'] !== $n['creado_en']): ?>
                    <div class="texto-atenuado texto-pequeno">Editada el <?php echo fmtFecha($n['actualizado_en']); ?></div>
                <?php endif; ?>
                <div class="nota-acciones">
                    <button type="button" class="btn-link btn-link-peligro"
                            data-nota-accion="eliminar"
                            data-nota-id="<?php echo (int)$n['nota_id']; ?>">Eliminar</button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>


<!-- Modal nueva/editar nota -->
<div id="modalNota" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalNota" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo" id="tituloModalNota">Nueva nota</h2>
        <form id="formNota" autocomplete="off">
            <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
            <input type="hidden" name="nota_id" id="nota_id">

            <div class="campo">
                <label for="nota_fecha">Fecha *</label>
                <input type="date" name="fecha" id="nota_fecha" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="campo">
                <label for="nota_contenido">Contenido *</label>
                <textarea name="contenido" id="nota_contenido" rows="6" maxlength="5000" required></textarea>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-modal="modalNota">Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>


<script>window.PACIENTE_ID = <?php echo $idInt; ?>;</script>
<script src="detalle-notas.js?v=<?php echo @filemtime(__DIR__ . '/../detalle-notas.js'); ?>"></script>

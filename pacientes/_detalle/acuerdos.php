<?php
/* Pestaña ACUERDOS · acuerdos de servicio + modal
 * Espera: $acuerdos, $idInt
 */
?>
<div class="ficha-card">
    <div class="ficha-card-titulo">
        Acuerdos de servicio
        <button type="button" class="btn btn-primario btn-sm" id="btnNuevoAcuerdo" style="margin-left:auto">+ Nuevo acuerdo</button>
    </div>
    <?php if (empty($acuerdos)): ?>
        <div class="placeholder-card-inline">
            <p>Aún no hay acuerdos de servicio con este paciente.</p>
            <p class="texto-atenuado">Los acuerdos representan servicios propuestos al paciente. Cuando se aceptan, generan automáticamente una cita en la agenda.</p>
        </div>
    <?php else: ?>
        <ul class="lista-acuerdos">
            <?php foreach ($acuerdos as $a): ?>
            <li class="acuerdo-card">
                <div class="acuerdo-header">
                    <h3><?php echo htmlspecialchars($a['servicio']); ?></h3>
                    <span class="badge badge-acuerdo-<?php echo htmlspecialchars($a['estado']); ?>">
                        <?php echo htmlspecialchars($a['estado']); ?>
                    </span>
                </div>
                <?php if ($a['descripcion']): ?>
                    <p><?php echo nl2br(htmlspecialchars($a['descripcion'])); ?></p>
                <?php endif; ?>
                <div class="acuerdo-detalles">
                    <div><span class="texto-atenuado">Fecha programada:</span> <?php echo fmtFechaHora($a['fecha_programada']); ?></div>
                    <div><span class="texto-atenuado">Duración:</span> <?php echo (int)$a['duracion_minutos']; ?> min</div>
                    <div><span class="texto-atenuado">Precio:</span> <?php echo fmtDinero($a['precio']); ?></div>
                    <?php if ($a['cita_id']): ?>
                        <div><span class="texto-atenuado">Cita generada:</span>
                            <a href="../agenda/?cita=<?php echo (int)$a['cita_id']; ?>">#<?php echo (int)$a['cita_id']; ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="acuerdo-acciones">
                    <?php if ($a['estado'] === 'pendiente'): ?>
                        <button type="button" class="btn btn-primario btn-sm" data-ac-accion="aceptar"  data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Aceptar</button>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="editar"   data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Editar</button>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="rechazar" data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Rechazar</button>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="cancelar" data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Cancelar</button>
                    <?php elseif ($a['estado'] === 'aceptado'): ?>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="editar"     data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Editar</button>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="completar"  data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Marcar completado</button>
                        <button type="button" class="btn btn-secundario btn-sm" data-ac-accion="cancelar"   data-ac-id="<?php echo (int)$a['acuerdo_id']; ?>">Cancelar</button>
                    <?php endif; ?>
                    <?php if ($a['estado'] === 'aceptado' && $a['cita_id']): ?>
                        <a href="../agenda/?cita=<?php echo (int)$a['cita_id']; ?>" class="btn-link">Ver cita</a>
                    <?php endif; ?>
                </div>
                <div class="texto-atenuado texto-pequeno">Creado: <?php echo fmtFechaHora($a['creado_en']); ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>


<!-- Modal nuevo / editar acuerdo -->
<div id="modalAcuerdoPaciente" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-acuerdo aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo" id="acuerdoModalTitulo">Nuevo acuerdo</h2>
        <form id="formAcuerdoPaciente" autocomplete="off">
            <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
            <input type="hidden" name="acuerdo_id" id="acuerdoModalId" value="">

            <div class="campo">
                <label for="acp_servicio">Servicio *</label>
                <input type="text" name="servicio" id="acp_servicio" maxlength="150" required
                       placeholder="Ej. Limpieza dental">
            </div>

            <div class="campo">
                <label for="acp_descripcion">Descripción</label>
                <textarea name="descripcion" id="acp_descripcion" rows="2" maxlength="500"></textarea>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="acp_fecha">Fecha y hora *</label>
                    <input type="datetime-local" name="fecha_programada" id="acp_fecha" required>
                </div>
                <div class="campo">
                    <label for="acp_duracion">Duración (min) *</label>
                    <input type="number" name="duracion_minutos" id="acp_duracion"
                           min="5" max="600" step="5" value="60" required>
                </div>
            </div>

            <div class="campo">
                <label for="acp_precio">Precio *</label>
                <input type="number" name="precio" id="acp_precio" min="0" step="0.01" required>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-acuerdo>Cancelar</button>
                <button type="submit" class="btn btn-primario">Crear acuerdo</button>
            </div>
        </form>
    </div>
</div>

<script src="detalle-acuerdos.js?v=<?php echo @filemtime(__DIR__ . '/../detalle-acuerdos.js'); ?>"></script>

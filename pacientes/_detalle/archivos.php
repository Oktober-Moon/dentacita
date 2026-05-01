<?php
/* Pestaña ARCHIVOS · gestión de archivos del paciente con carpetas, papelera y previews
 * Espera: $idInt, $carpetas
 */
?>
<div class="ficha-card" id="archivosCard">
    <div class="ficha-card-titulo">
        Archivos del paciente
        <span id="archivosStorageStats" class="texto-pequeno texto-atenuado" style="margin-left:auto; margin-right: var(--espacio-md)"></span>
        <div class="filtros-inline">
            <input type="search" id="archivosBusqueda" placeholder="Buscar…" class="campo-busqueda" style="max-width:180px">
            <button type="button" class="btn btn-secundario btn-sm" id="btnNuevaCarpeta">+ Carpeta</button>
            <button type="button" class="btn btn-primario btn-sm"   id="btnSubirArchivo">+ Subir archivo</button>
            <button type="button" class="btn btn-secundario btn-sm" id="btnVerPapelera">Papelera</button>
        </div>
    </div>

    <div id="archivosLista">
        <!-- placeholder · JS lo llena -->
    </div>

    <div id="archivosDragOverlay" class="archivos-drag-overlay">
        <div class="archivos-drag-mensaje">
            <div style="font-size: 48px">📁</div>
            <div>Suelta los archivos aquí para subirlos</div>
        </div>
    </div>

    <div id="modalPreviewArchivo" class="modal-fondo">
        <div class="modal-caja modal-caja-grande">
            <button type="button" class="modal-cerrar" data-cerrar-modal="modalPreviewArchivo" aria-label="Cerrar">&times;</button>
            <h2 class="modal-titulo" id="previewTitulo">Vista previa</h2>
            <div id="previewContenido" style="margin-top: var(--espacio-md); text-align:center"></div>
            <div class="modal-acciones">
                <a href="#" target="_blank" rel="noopener" class="btn btn-secundario" id="previewDescargar">Abrir en nueva pestaña</a>
            </div>
        </div>
    </div>
</div>


<!-- Modal subir archivo -->
<div id="modalSubir" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalSubir" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Subir archivo</h2>
        <form id="formSubir" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
            <div class="campo">
                <label for="sub_archivo">Archivo *</label>
                <input type="file" name="archivo" id="sub_archivo" required>
            </div>
            <div class="campo">
                <label for="sub_nombre">Nombre (opcional)</label>
                <input type="text" name="nombre_archivo" id="sub_nombre" maxlength="150" placeholder="Si lo dejas vacío, se usa el original">
            </div>
            <div class="campo-grid-2">
                <div class="campo">
                    <label for="sub_tipo">Tipo</label>
                    <select name="tipo" id="sub_tipo">
                        <option value="documento">Documento</option>
                        <option value="imagen">Imagen</option>
                        <option value="pdf">PDF</option>
                        <option value="radiografia">Radiografía</option>
                        <option value="laboratorio">Laboratorio</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="sub_fecha">Fecha</label>
                    <input type="date" name="fecha" id="sub_fecha" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="campo">
                <label for="sub_carpeta">Carpeta destino</label>
                <select name="ruta_carpeta" id="sub_carpeta">
                    <option value="">— Raíz —</option>
                    <?php foreach ($carpetas as $rk => $car): ?>
                        <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="sub_descripcion">Descripción</label>
                <textarea name="descripcion" id="sub_descripcion" rows="2" maxlength="200"></textarea>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-modal="modalSubir">Cancelar</button>
                <button type="submit" class="btn btn-primario">Subir</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal nueva carpeta -->
<div id="modalCarpeta" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalCarpeta" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Nueva carpeta</h2>
        <form id="formCarpeta" autocomplete="off">
            <input type="hidden" name="paciente_id" value="<?php echo $idInt; ?>">
            <div class="campo">
                <label for="car_padre">Carpeta padre</label>
                <select name="ruta_padre" id="car_padre">
                    <option value="">— Raíz —</option>
                    <?php foreach ($carpetas as $rk => $car): ?>
                        <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="car_nombre">Nombre *</label>
                <input type="text" name="nombre" id="car_nombre" maxlength="120" required placeholder="Ej. Ortodoncia, Radiografías…">
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-modal="modalCarpeta">Cancelar</button>
                <button type="submit" class="btn btn-primario">Crear</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal editar archivo -->
<div id="modalEditarArch" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalEditarArch" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Editar archivo</h2>
        <form id="formEditarArch" autocomplete="off">
            <input type="hidden" name="archivo_id" id="ed_arch_id">
            <div class="campo">
                <label for="ed_arch_nombre">Nombre *</label>
                <input type="text" name="nombre_archivo" id="ed_arch_nombre" maxlength="150" required>
            </div>
            <div class="campo-grid-2">
                <div class="campo">
                    <label for="ed_arch_tipo">Tipo</label>
                    <select name="tipo" id="ed_arch_tipo">
                        <option value="documento">Documento</option>
                        <option value="imagen">Imagen</option>
                        <option value="pdf">PDF</option>
                        <option value="radiografia">Radiografía</option>
                        <option value="laboratorio">Laboratorio</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="ed_arch_fecha">Fecha</label>
                    <input type="date" name="fecha" id="ed_arch_fecha">
                </div>
            </div>
            <div class="campo">
                <label for="ed_arch_desc">Descripción</label>
                <textarea name="descripcion" id="ed_arch_desc" rows="2" maxlength="200"></textarea>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-modal="modalEditarArch">Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal mover archivo -->
<div id="modalMover" class="modal-fondo">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalMover" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Mover archivo</h2>
        <form id="formMover" autocomplete="off">
            <input type="hidden" name="archivo_id" id="mv_arch_id">
            <div class="campo">
                <label for="mv_destino">Carpeta destino</label>
                <select name="ruta_carpeta_destino" id="mv_destino">
                    <option value="">— Raíz —</option>
                    <?php foreach ($carpetas as $rk => $car): ?>
                        <option value="<?php echo htmlspecialchars($rk); ?>"><?php echo htmlspecialchars($rk); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-modal="modalMover">Cancelar</button>
                <button type="submit" class="btn btn-primario">Mover</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal papelera -->
<div id="modalPapelera" class="modal-fondo">
    <div class="modal-caja modal-caja-grande">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalPapelera" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Papelera</h2>
        <p class="texto-atenuado" style="margin-top:0">Archivos eliminados que aún se pueden restaurar.</p>
        <div id="papeleraLista" class="historial-mov">
            <div class="tabla-cargando">Cargando…</div>
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-secundario" data-cerrar-modal="modalPapelera">Cerrar</button>
            <button type="button" class="btn btn-link btn-link-peligro" id="btnVaciarPapelera">Vaciar papelera</button>
        </div>
    </div>
</div>


<script>window.PACIENTE_ID = <?php echo $idInt; ?>;</script>
<script src="archivos-core.js?v=<?php echo @filemtime(__DIR__ . '/../archivos-core.js'); ?>"></script>
<script src="archivos-render.js?v=<?php echo @filemtime(__DIR__ . '/../archivos-render.js'); ?>"></script>
<script src="archivos-acciones.js?v=<?php echo @filemtime(__DIR__ . '/../archivos-acciones.js'); ?>"></script>

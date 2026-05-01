<?php
/*
 * PACIENTES · Vista lista
 * -----------------------
 * Listado paginado con búsqueda en vivo y modal para crear/editar.
 * Click en una fila → ficha en detalle.php.
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');
$conexion->close();

$nav_actual   = 'pacientes';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Pacientes</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>


<!-- ================ MODAL · PACIENTE (crear / editar) ================ -->
<div id="modalPaciente" class="modal-fondo">
    <div class="modal-caja modal-caja-grande">
        <button type="button" class="modal-cerrar" id="cerrarModalPaciente" aria-label="Cerrar">&times;</button>

        <h2 class="modal-titulo" id="modalPacienteTitulo">Nuevo paciente</h2>

        <form id="formPaciente" autocomplete="off">
            <input type="hidden" name="paciente_id" id="paciente_id" value="">

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Datos personales</div>

                <div class="campo">
                    <label for="nombre_completo">Nombre completo *</label>
                    <input type="text" name="nombre_completo" id="nombre_completo" maxlength="100" required>
                </div>

                <div class="campo-grid-2">
                    <div class="campo">
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
                    </div>
                    <div class="campo">
                        <label for="genero">Género</label>
                        <select name="genero" id="genero">
                            <option value="no_especificado">No especificado</option>
                            <option value="femenino">Femenino</option>
                            <option value="masculino">Masculino</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="campo">
                    <label for="ocupacion">Ocupación</label>
                    <input type="text" name="ocupacion" id="ocupacion" maxlength="100">
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Contacto</div>

                <div class="campo-grid-2">
                    <div class="campo">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" maxlength="20">
                    </div>
                    <div class="campo">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" maxlength="100">
                    </div>
                </div>

                <div class="campo">
                    <label for="direccion">Dirección</label>
                    <input type="text" name="direccion" id="direccion" maxlength="200">
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Datos clínicos</div>

                <div class="campo">
                    <label for="alergias">Alergias</label>
                    <textarea name="alergias" id="alergias" rows="2" maxlength="500"
                              placeholder="Penicilina, latex, etc."></textarea>
                </div>

                <div class="campo">
                    <label for="padecimientos">Padecimientos</label>
                    <textarea name="padecimientos" id="padecimientos" rows="2" maxlength="500"
                              placeholder="Hipertensión, diabetes, bruxismo, etc."></textarea>
                </div>

                <div class="campo">
                    <label for="medicamentos">Medicamentos actuales</label>
                    <textarea name="medicamentos" id="medicamentos" rows="2" maxlength="500"
                              placeholder="Anticoagulantes, antihipertensivos, etc."></textarea>
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Notas adicionales</div>
                <div class="campo">
                    <textarea name="notas" id="notas" rows="3" maxlength="1000"
                              placeholder="Cualquier otra observación relevante."></textarea>
                </div>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" id="cancelarPaciente">Cancelar</button>
                <button type="submit" class="btn btn-primario" id="guardarPaciente">Guardar paciente</button>
            </div>
        </form>
    </div>
</div>


<!-- ================ MODAL · CONFIRMAR ELIMINAR ================ -->
<div id="modalEliminar" class="modal-fondo">
    <div class="modal-caja modal-caja-pequena">
        <h2 class="modal-titulo">Eliminar paciente</h2>
        <p>Vas a eliminar a <strong id="eliminarNombre"></strong>.</p>
        <p class="texto-atenuado" style="font-size:.92em">
            Sus <strong>notas clínicas</strong> y <strong>archivos</strong>
            (incluyendo carpetas y papelera) también se borrarán.
            Sus <strong>citas</strong> y <strong>transacciones financieras</strong> se conservarán
            pero ya no estarán vinculadas a este paciente. Esta acción es irreversible.
        </p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-secundario" id="cancelarEliminar">Cancelar</button>
            <button type="button" class="btn btn-peligro" id="confirmarEliminar">Eliminar</button>
        </div>
    </div>
</div>


<!-- ================ LAYOUT PRINCIPAL ================ -->
<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <h1 class="page-titulo">Pacientes</h1>
                <div class="page-subtitulo">Lista completa con datos clínicos.</div>
            </div>
            <div class="page-acciones">
                <button type="button" class="btn btn-primario" id="btnNuevoPaciente">+ Nuevo paciente</button>
            </div>
        </div>

        <div class="tabla-card">
            <div class="tabla-toolbar">
                <input type="search" class="input-buscar" id="buscarPaciente"
                       placeholder="Buscar por nombre, teléfono o email…" maxlength="100">
                <span class="tabla-conteo" id="pacientesConteo"></span>
            </div>

            <div id="pacientesTablaContenido">
                <div class="tabla-cargando">Cargando…</div>
            </div>

            <div class="tabla-paginacion" id="pacientesPaginacion"></div>
        </div>

    </main>

</div>

<script src="pacientes.js?v=<?php echo @filemtime(__DIR__ . '/pacientes.js'); ?>"></script>

</body>
</html>

<?php
/*
 * AGENDA · Vista principal
 * --------------------------
 * Layout: calendario mensual a la izquierda, lista de citas del
 * día seleccionado a la derecha, y un modal multi-paso para crear
 * o editar citas. La gestión de pacientes está embebida aquí: al
 * agendar puedes elegir paciente existente o registrar uno nuevo.
 *
 * Toda la lógica AJAX consume los endpoints PHP de esta misma
 * carpeta: mostrar.php, guardar.php, actualizar.php, eliminar.php,
 * cambiar-estado.php, pacientes.php.
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Agenda</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>

<!-- ================ MODAL · CITA (crear / editar) ================ -->
<div id="modalCita" class="modal-fondo">
    <div class="modal-caja modal-caja-grande">
        <button type="button" class="modal-cerrar" id="cerrarModalCita" aria-label="Cerrar">&times;</button>

        <h2 class="modal-titulo" id="modalCitaTitulo">Nueva cita</h2>

        <!-- PASO 1: tipo de paciente (solo al crear) -->
        <div id="pasoTipoPaciente" class="modal-paso">
            <p class="modal-subtitulo">¿Es un paciente que ya tienes registrado?</p>
            <div class="opciones-grid">
                <button type="button" class="opcion-card" id="btnPacienteExistente">
                    <div class="opcion-card-titulo">Paciente existente</div>
                    <div class="opcion-card-desc">Buscar entre los pacientes que ya tienes.</div>
                </button>
                <button type="button" class="opcion-card" id="btnPacienteNuevo">
                    <div class="opcion-card-titulo">Paciente nuevo</div>
                    <div class="opcion-card-desc">Registrar a alguien que viene por primera vez.</div>
                </button>
            </div>
        </div>

        <!-- PASO 2: seleccionar paciente existente -->
        <div id="pasoSeleccionarPaciente" class="modal-paso oculto">
            <button type="button" class="btn-volver" data-volver="tipo">← Volver</button>
            <p class="modal-subtitulo">Buscar paciente</p>
            <div class="campo">
                <input type="text" id="buscarPaciente" placeholder="Escribe el nombre…" autocomplete="off">
            </div>
            <div id="resultadosPacientes" class="lista-resultados">
                <div class="texto-atenuado" style="padding: var(--espacio-md); text-align: center;">
                    Empieza a escribir para buscar.
                </div>
            </div>
        </div>

        <!-- PASO 3: registrar paciente nuevo -->
        <div id="pasoNuevoPaciente" class="modal-paso oculto">
            <button type="button" class="btn-volver" data-volver="tipo">← Volver</button>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Datos personales</div>
                <div class="campo">
                    <label for="np_nombre">Nombre completo *</label>
                    <input type="text" id="np_nombre" maxlength="100" required>
                </div>
                <div class="grid-2">
                    <div class="campo">
                        <label for="np_fecha_nac">Fecha de nacimiento</label>
                        <input type="date" id="np_fecha_nac">
                    </div>
                    <div class="campo">
                        <label for="np_genero">Género</label>
                        <select id="np_genero">
                            <option value="no_especificado">No especificado</option>
                            <option value="femenino">Femenino</option>
                            <option value="masculino">Masculino</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="campo">
                    <label for="np_ocupacion">Ocupación</label>
                    <input type="text" id="np_ocupacion" maxlength="100">
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Contacto</div>
                <div class="grid-2">
                    <div class="campo">
                        <label for="np_telefono">Teléfono</label>
                        <input type="tel" id="np_telefono" maxlength="20" inputmode="tel">
                    </div>
                    <div class="campo">
                        <label for="np_email">Email</label>
                        <input type="email" id="np_email" maxlength="100">
                    </div>
                </div>
                <div class="campo">
                    <label for="np_direccion">Dirección</label>
                    <input type="text" id="np_direccion" maxlength="200">
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Datos clínicos</div>
                <div class="campo">
                    <label for="np_alergias">Alergias</label>
                    <textarea id="np_alergias" rows="2" maxlength="500"
                              placeholder="Penicilina, latex, etc."></textarea>
                </div>
                <div class="campo">
                    <label for="np_padecimientos">Padecimientos</label>
                    <textarea id="np_padecimientos" rows="2" maxlength="500"
                              placeholder="Hipertensión, diabetes, bruxismo, etc."></textarea>
                </div>
                <div class="campo">
                    <label for="np_medicamentos">Medicamentos actuales</label>
                    <textarea id="np_medicamentos" rows="2" maxlength="500"
                              placeholder="Anticoagulantes, antihipertensivos, etc."></textarea>
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Notas adicionales</div>
                <div class="campo">
                    <textarea id="np_notas" rows="3" maxlength="1000" placeholder="Cualquier otra observación relevante."></textarea>
                </div>
            </div>

            <div class="modal-seccion">
                <div class="modal-seccion-titulo">Foto (opcional)</div>
                <div class="campo">
                    <input type="file" id="np_foto" accept="image/jpeg,image/png,image/webp">
                    <div id="npCropContenedor" style="display:none; margin-top: var(--espacio-xm)">
                        <div class="crop-area">
                            <canvas id="npCropCanvas" width="240" height="240"></canvas>
                        </div>
                        <div class="crop-controles">
                            <label>Zoom <input type="range" id="npCropZoom" min="100" max="400" value="100"></label>
                            <label>Rotación <input type="range" id="npCropRotar" min="-180" max="180" value="0"></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-acciones">
                <button type="button" class="btn btn-secundario" data-volver="tipo">Cancelar</button>
                <button type="button" class="btn btn-primario" id="btnContinuarConPaciente">Continuar →</button>
            </div>
        </div>

        <!-- PASO 4: datos de la cita -->
        <div id="pasoDatosCita" class="modal-paso oculto">
            <button type="button" class="btn-volver" id="btnVolverPaso3">← Cambiar paciente</button>

            <div class="paciente-resumen">
                <div class="paciente-resumen-nombre" id="pacienteResumenNombre">—</div>
                <div class="paciente-resumen-telefono" id="pacienteResumenTelefono"></div>
            </div>

            <form id="formCita" novalidate>
                <input type="hidden" id="cita_id">
                <input type="hidden" id="paciente_id">
                <input type="hidden" id="paciente_nombre">
                <input type="hidden" id="paciente_telefono">

                <div class="campo">
                    <label for="titulo">Motivo / Tratamiento *</label>
                    <input type="text" id="titulo" maxlength="120" required
                           placeholder="Ej. Limpieza, Empaste, Consulta…">
                </div>

                <div class="grid-2">
                    <div class="campo">
                        <label for="fecha">Fecha *</label>
                        <input type="date" id="fecha" required>
                    </div>
                    <div class="campo">
                        <label for="hora_inicio">Hora *</label>
                        <input type="time" id="hora_inicio" required>
                    </div>
                    <div class="campo oculto" id="campo-estado-cita">
                        <label for="estado">Estado</label>
                        <select id="estado">
                            <option value="programada">Programada</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                            <option value="no_asistio">No asistió</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="duracion" value="30">

                <div class="grid-2">
                    <div class="campo">
                        <label for="precio">Precio</label>
                        <input type="number" id="precio" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="campo">
                        <label for="metodo_pago">Método de pago (al completar)</label>
                        <select id="metodo_pago">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="cheque">Cheque</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="campo">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" rows="2" maxlength="500" placeholder="Detalles del tratamiento…"></textarea>
                </div>

                <div class="campo">
                    <label for="notas">Notas internas</label>
                    <textarea id="notas" rows="2" maxlength="500" placeholder="Notas privadas para tu referencia…"></textarea>
                </div>

                <div class="modal-acciones">
                    <button type="button" class="btn btn-secundario" id="cancelarCita">Cancelar</button>
                    <button type="submit" class="btn btn-primario" id="btnGuardarCita">Guardar cita</button>
                </div>
            </form>
        </div>

    </div>
</div>


<!-- ================ LAYOUT PRINCIPAL ================ -->
<?php $nav_actual = 'agenda'; $nav_base_url = '../'; ?>
<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <div class="page-pre-titulo">Operación · Agenda</div>
                <h1 class="page-titulo">Agenda</h1>
                <div class="page-subtitulo">
                    Calendario y citas de tus pacientes
                    <span class="sep"></span>
                    <span class="mono" id="agendaMetaConteo">—</span>
                </div>
            </div>
            <div class="page-acciones">
                <div class="seg-vista" role="tablist" aria-label="Vista de agenda">
                    <button type="button" data-vista="mensual" id="btnVistaMensual" class="activo">Mes</button>
                    <button type="button" data-vista="semanal" id="btnVistaSemanal">Semana</button>
                </div>
                <button type="button" class="btn btn-primario" id="btnNuevaCita">
                    <svg class="btn-icono-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nueva cita
                </button>
            </div>
        </div>

        <div class="agenda-grid">

            <!-- Columna principal · alterna entre vista mensual y vista semanal -->
            <div class="agenda-grid-principal">

                <!-- Vista semanal · 7 columnas × franjas horarias (8-20h). Visible solo cuando vista=semanal. -->
                <div id="vistaSemanal" class="ficha-card" style="display:none">
                    <div class="semana-header">
                        <div class="semana-header-nav">
                            <button type="button" class="btn-icono" id="semAnterior" aria-label="Semana anterior">‹</button>
                            <span id="semanaTitulo" class="semana-titulo">—</span>
                            <button type="button" class="btn-icono" id="semSiguiente" aria-label="Semana siguiente">›</button>
                        </div>
                        <button type="button" class="btn btn-secundario btn-sm" id="semHoy">Hoy</button>
                    </div>
                    <div id="semanaContenedor" class="semana-grid">
                        <div class="tabla-cargando">Cargando…</div>
                    </div>
                </div>

                <!-- Vista mensual · solo el calendario; el aside (citas del día y memorias)
                     vive fuera de este contenedor para que persista al cambiar de vista. -->
                <div id="vistaMensual" class="calendario-card">
                    <div class="calendario-header">
                        <div class="calendario-header-nav">
                            <button type="button" class="btn-icono" id="mesAnterior" aria-label="Mes anterior">‹</button>
                            <div class="calendario-mes" id="calendarioMes"></div>
                            <button type="button" class="btn-icono" id="mesSiguiente" aria-label="Mes siguiente">›</button>
                        </div>
                        <button type="button" class="btn btn-secundario btn-sm" id="mesHoy">Hoy</button>
                    </div>
                    <div class="calendario-dias-semana">
                        <div>L</div><div>M</div><div>M</div><div>J</div><div>V</div><div>S</div><div>D</div>
                    </div>
                    <div class="calendario-grid" id="calendarioGrid"></div>
                </div>

            </div>

            <!-- Aside permanente · siempre visible en mes y semana -->
            <aside class="agenda-aside">
                <!-- Citas del día seleccionado -->
                <div class="citas-dia-card">
                    <div class="citas-dia-header">
                        <h3 id="citasDiaTitulo">Citas</h3>
                        <span class="texto-atenuado" id="citasDiaConteo"></span>
                    </div>
                    <div id="citasDiaContenido">
                        <div class="tabla-cargando">Cargando…</div>
                    </div>
                </div>

                <!-- Memorias personales del día -->
                <div class="citas-dia-card">
                    <div class="citas-dia-header">
                        <h3>Memorias personales</h3>
                        <button type="button" class="btn btn-secundario btn-sm" id="btnNuevaMemoria">+ Nueva</button>
                    </div>
                    <div id="memoriasContenido">
                        <div class="tabla-cargando">Cargando…</div>
                    </div>
                </div>
            </aside>

        </div>

    </main>

</div>


<?php require __DIR__ . '/_modales/reembolso-cita.php'; ?>
<?php require __DIR__ . '/_modales/memoria.php'; ?>


<script src="agenda-ajax.js?v=<?php echo @filemtime(__DIR__ . '/agenda-ajax.js'); ?>"></script>
<script src="agenda-foto-crop.js?v=<?php echo @filemtime(__DIR__ . '/agenda-foto-crop.js'); ?>"></script>
<script src="agenda-calendario.js?v=<?php echo @filemtime(__DIR__ . '/agenda-calendario.js'); ?>"></script>
<script src="agenda-modal.js?v=<?php echo @filemtime(__DIR__ . '/agenda-modal.js'); ?>"></script>
<script src="agenda-memorias.js?v=<?php echo @filemtime(__DIR__ . '/agenda-memorias.js'); ?>"></script>

</body>
</html>

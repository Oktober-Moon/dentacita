<?php
/* Pestaña DATOS · ficha personal y clínica
 * Espera: $paciente, $edad, $cnt
 */
?>
<div class="ficha-grid">
    <div class="ficha-col">
        <div class="ficha-card">
            <div class="ficha-card-titulo">Datos personales</div>
            <dl class="ficha-dl">
                <dt>Nombre</dt> <dd><?php echo htmlspecialchars($paciente['nombre_completo']); ?></dd>
                <dt>Nacimiento</dt> <dd><?php echo fmtFecha($paciente['fecha_nacimiento']); ?>
                    <?php if ($edad !== null): ?> · <?php echo $edad; ?> años<?php endif; ?>
                </dd>
                <dt>Género</dt> <dd><?php echo htmlspecialchars(generoBonito($paciente['genero'])); ?></dd>
                <dt>Ocupación</dt> <dd><?php echo $paciente['ocupacion'] ? htmlspecialchars($paciente['ocupacion']) : '—'; ?></dd>
            </dl>
        </div>

        <div class="ficha-card">
            <div class="ficha-card-titulo">Contacto</div>
            <dl class="ficha-dl">
                <dt>Teléfono</dt><dd><?php echo $paciente['telefono'] ? htmlspecialchars($paciente['telefono']) : '—'; ?></dd>
                <dt>Email</dt>   <dd><?php echo $paciente['email']    ? htmlspecialchars($paciente['email'])    : '—'; ?></dd>
                <dt>Dirección</dt><dd><?php echo $paciente['direccion'] ? htmlspecialchars($paciente['direccion']) : '—'; ?></dd>
            </dl>
        </div>
    </div>

    <div class="ficha-col">
        <div class="ficha-card">
            <div class="ficha-card-titulo">Datos clínicos</div>
            <div class="ficha-bloque-clinico">
                <div class="ficha-bloque-titulo">Alergias</div>
                <div class="ficha-bloque-contenido">
                    <?php echo $paciente['alergias']
                        ? nl2br(htmlspecialchars($paciente['alergias']))
                        : '<span class="texto-atenuado">Sin alergias registradas.</span>'; ?>
                </div>
            </div>
            <div class="ficha-bloque-clinico">
                <div class="ficha-bloque-titulo">Padecimientos</div>
                <div class="ficha-bloque-contenido">
                    <?php echo $paciente['padecimientos']
                        ? nl2br(htmlspecialchars($paciente['padecimientos']))
                        : '<span class="texto-atenuado">Sin padecimientos registrados.</span>'; ?>
                </div>
            </div>
            <div class="ficha-bloque-clinico">
                <div class="ficha-bloque-titulo">Medicamentos</div>
                <div class="ficha-bloque-contenido">
                    <?php echo $paciente['medicamentos']
                        ? nl2br(htmlspecialchars($paciente['medicamentos']))
                        : '<span class="texto-atenuado">Sin medicamentos registrados.</span>'; ?>
                </div>
            </div>
        </div>

        <div class="ficha-card">
            <div class="ficha-card-titulo">Resumen</div>
            <ul class="ficha-lista">
                <li><strong><?php echo $cnt['citas']; ?></strong> cita(s) registradas</li>
                <li><strong><?php echo $cnt['acuerdos']; ?></strong> acuerdo(s) de servicio</li>
                <li><strong><?php echo $cnt['archivos']; ?></strong> archivo(s)</li>
                <li><strong><?php echo $cnt['notas']; ?></strong> nota(s) clínicas</li>
            </ul>
        </div>
    </div>
</div>

<div class="ficha-meta">
    Registrado el <?php echo fmtFecha($paciente['creado_en']); ?> ·
    Última actualización: <?php echo fmtFecha($paciente['actualizado_en']); ?>
</div>

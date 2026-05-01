<?php
/* Pestaña CITAS · historial de citas con este paciente
 * Espera: $citas
 */
?>
<div class="ficha-card">
    <div class="ficha-card-titulo">Historial de citas</div>
    <?php if (empty($citas)): ?>
        <div class="texto-atenuado">No hay citas registradas con este paciente.</div>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Motivo</th><th>Estado</th><th style="text-align:right">Precio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($citas as $c): ?>
                <tr>
                    <td><?php echo fmtFechaHora($c['fecha_hora_inicio']); ?></td>
                    <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($c['estado']); ?>"><?php echo htmlspecialchars($c['estado']); ?></span></td>
                    <td style="text-align:right" class="mono"><?php echo fmtDinero($c['precio']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="ficha-card-pie">
        <a href="../agenda/" class="btn btn-secundario btn-sm">Ir a la agenda completa</a>
    </div>
</div>

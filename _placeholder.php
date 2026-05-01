<?php
/*
 * PLACEHOLDER · módulo en construcción
 * -------------------------------------
 * Vista compartida que muestran los módulos cuyo código todavía no
 * existe. La página index.php de cada módulo en obra solo define
 * dos variables y luego incluye este archivo.
 *
 *   $modulo_titulo = 'Tratamientos';
 *   $modulo_id     = 'tratamientos';
 *   $modulo_plan   = '06-tratamientos';   // sin .md
 *   require __DIR__ . '/../_placeholder.php';
 */

require __DIR__ . '/conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');
$conexion->close();

$titulo = $modulo_titulo ?? 'Módulo';
$id     = $modulo_id     ?? '';
$plan   = $modulo_plan   ?? '';

$nav_actual   = $id;
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · <?php echo htmlspecialchars($titulo); ?></title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>

<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <h1 class="page-titulo"><?php echo htmlspecialchars($titulo); ?></h1>
                <div class="page-subtitulo">Módulo en construcción.</div>
            </div>
        </div>

        <div class="placeholder-card">
            <div class="placeholder-titulo">Módulo en obra</div>
            <div class="placeholder-desc">
                Este módulo aún no está implementado. Su plan de funcionalidad
                ya está escrito y listo para construirse con Claude Code.
            </div>
            <a href="../dashboard.php" class="btn btn-secundario">Volver al inicio</a>

            <?php if ($plan): ?>
            <div class="placeholder-meta">
                <strong>Plan:</strong> <code>dentacita-docs/01-plan/planes-individuales/<?php echo htmlspecialchars($plan); ?>.md</code>
            </div>
            <?php endif; ?>
        </div>

    </main>

</div>

</body>
</html>

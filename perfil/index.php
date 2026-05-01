<?php
/*
 * PERFIL · vista principal v8 (mínimo: nombre + foto + tema)
 *
 * Tres secciones apiladas: foto (con cropper), nombre (con autosave)
 * y selector de tema visual (8 variantes de color planos).
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');
$conexion->close();

$nav_actual   = 'perfil';
$nav_base_url = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Mi perfil</title>
    <link rel="stylesheet" href="../styles.css?v=<?php echo @filemtime(__DIR__ . '/../styles.css'); ?>">
</head>
<body>

<div id="toast" class="toast"></div>


<!-- Modal · crop de foto -->
<div id="modalFoto" class="modal-fondo">
    <div class="modal-caja modal-caja-grande">
        <button type="button" class="modal-cerrar" data-cerrar-modal="modalFoto" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Foto de perfil</h2>
        <div class="campo">
            <input type="file" id="inputFoto" accept="image/jpeg,image/png,image/webp">
            <div class="campo-ayuda">Recorta una zona cuadrada arrastrando la imagen.</div>
        </div>
        <div id="cropContenedor" style="display:none">
            <div class="crop-area">
                <canvas id="cropCanvas" width="320" height="320"></canvas>
            </div>
            <div class="crop-controles">
                <label>Zoom <input type="range" id="cropZoom" min="100" max="400" value="100"></label>
                <label>Rotación <input type="range" id="cropRotar" min="-180" max="180" value="0" step="1"></label>
            </div>
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-secundario" data-cerrar-modal="modalFoto">Cancelar</button>
            <button type="button" class="btn btn-primario" id="btnGuardarFoto" disabled>Guardar foto</button>
        </div>
    </div>
</div>


<div class="app-layout app-layout-sidebar">

    <?php require __DIR__ . '/../_nav.php'; ?>

    <main class="app-main">

        <div class="page-header">
            <div>
                <h1 class="page-titulo">Mi perfil</h1>
                <div class="page-subtitulo">Tu nombre, tu foto y tu tema visual.</div>
            </div>
        </div>


        <div class="ficha-card">
            <div class="ficha-card-titulo">Foto y nombre</div>

            <div class="perfil-foto-row">
                <div class="perfil-foto-bloque">
                    <img id="fotoPerfilImg" src="" alt="Foto de perfil" class="perfil-foto-img">
                    <button type="button" class="btn btn-secundario btn-sm" id="btnCambiarFoto">Cambiar foto</button>
                </div>
                <form id="formDatos" autocomplete="off" class="perfil-form">
                    <div class="campo">
                        <label for="d_nombre">
                            Nombre completo *
                            <span id="autosaveIndicador" class="texto-pequeno texto-atenuado" style="font-style:italic; margin-left: var(--espacio-sm)"></span>
                        </label>
                        <input type="text" name="nombre_completo" id="d_nombre" maxlength="150" required>
                        <div class="campo-ayuda">Los cambios se guardan automáticamente.</div>
                    </div>
                </form>
            </div>
        </div>


        <div class="ficha-card">
            <div class="ficha-card-titulo">Tema visual</div>
            <p class="texto-atenuado">Elige el color que quieras para personalizar el panel.</p>

            <div class="tema-grid" id="temaGrid">
                <button type="button" class="tema-card" data-tema="cyan-default">
                    <div class="tema-circulo" style="background:#14b8a6"></div>
                    <div class="tema-nombre">Cyan</div>
                </button>
                <button type="button" class="tema-card" data-tema="azul-marino">
                    <div class="tema-circulo" style="background:#3b82f6"></div>
                    <div class="tema-nombre">Azul marino</div>
                </button>
                <button type="button" class="tema-card" data-tema="verde-bosque">
                    <div class="tema-circulo" style="background:#22c55e"></div>
                    <div class="tema-nombre">Verde bosque</div>
                </button>
                <button type="button" class="tema-card" data-tema="naranja-citrico">
                    <div class="tema-circulo" style="background:#f97316"></div>
                    <div class="tema-nombre">Naranja</div>
                </button>
                <button type="button" class="tema-card" data-tema="violeta-real">
                    <div class="tema-circulo" style="background:#8b5cf6"></div>
                    <div class="tema-nombre">Violeta</div>
                </button>
                <button type="button" class="tema-card" data-tema="rosa-coral">
                    <div class="tema-circulo" style="background:#ec4899"></div>
                    <div class="tema-nombre">Rosa coral</div>
                </button>
                <button type="button" class="tema-card" data-tema="ambar-sol">
                    <div class="tema-circulo" style="background:#f59e0b"></div>
                    <div class="tema-nombre">Ámbar</div>
                </button>
                <button type="button" class="tema-card" data-tema="grafito">
                    <div class="tema-circulo" style="background:#64748b"></div>
                    <div class="tema-nombre">Grafito</div>
                </button>
            </div>
        </div>

    </main>

</div>

<script src="perfil.js?v=<?php echo @filemtime(__DIR__ . '/perfil.js'); ?>"></script>

</body>
</html>

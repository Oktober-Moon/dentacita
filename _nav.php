<?php
/*
 * NAVEGACIÓN LATERAL · sidebar del dentista (v8)
 * ----------------------------------------------
 * 6 enlaces principales. Sin mensajes ni portafolio (decisión de
 * alcance: la app la usa solo el dentista, no hay vista pública).
 *
 * Uso desde una vista:
 *   $nav_actual   = 'agenda';   // id del módulo
 *   $nav_base_url = '../';      // '' si está en raíz, '../' si está en subcarpeta
 */

if (!isset($nav_actual))   $nav_actual   = '';
if (!isset($nav_base_url)) $nav_base_url = '';

$usuarioMenu      = $_SESSION['usuario_nombre'] ?? 'usuario';
$usuarioMenuEmail = $_SESSION['usuario_email']  ?? '';

// Tema visual del usuario (persiste en sesión para evitar query por request)
$varianteTema = $_SESSION['variante_tema'] ?? null;
if ($varianteTema === null && function_exists('obtenerConexion')) {
    $cnx = obtenerConexion();
    if ($cnx) {
        $stmtTema = @$cnx->prepare("SELECT variante_tema FROM perfil_dentista WHERE usuario_id = ?");
        if ($stmtTema) {
            $uid = getUsuarioId();
            if ($uid !== null) {
                $stmtTema->bind_param("i", $uid);
                @$stmtTema->execute();
                $r = $stmtTema->get_result()->fetch_assoc();
                if ($r && $r['variante_tema']) {
                    $varianteTema = $r['variante_tema'];
                    $_SESSION['variante_tema'] = $varianteTema;
                }
            }
            $stmtTema->close();
        }
        $cnx->close();
    }
}
if (!$varianteTema) $varianteTema = 'cyan-default';
?>
<script>document.documentElement.setAttribute('data-tema', <?php echo json_encode($varianteTema); ?>);</script>
<script>
/* Anti-CSRF · interceptor global de fetch
   Añade X-CSRF-Token a TODAS las peticiones POST/PUT/DELETE sin tocar
   los archivos JS individuales. El token vive en window.CSRF_TOKEN. */
window.CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
(function() {
    var origFetch = window.fetch.bind(window);
    window.fetch = function(input, init) {
        init = init || {};
        var metodo = (init.method || 'GET').toUpperCase();
        if (['POST','PUT','DELETE'].indexOf(metodo) !== -1) {
            var headers = new Headers(init.headers || {});
            headers.set('X-CSRF-Token', window.CSRF_TOKEN);
            init.headers = headers;
        }
        return origFetch(input, init);
    };
})();

/* Anti doble-click · al hacer submit en cualquier form, deshabilita el
   botón submit por 1.5s. Evita inserciones duplicadas si el dentista
   pulsa dos veces rápido o tiene latencia alta. Captura para correr
   antes que los handlers locales. */
document.addEventListener('submit', function(e) {
    if (!(e.target instanceof HTMLFormElement)) return;
    var btn = e.target.querySelector('button[type="submit"]:not([disabled])');
    if (!btn) return;
    btn.disabled = true;
    setTimeout(function() { btn.disabled = false; }, 1500);
}, true);
</script>
<?php

$seccionesMenu = [
    ['titulo' => 'Operación', 'items' => [
        ['id'=>'dashboard', 'label'=>'Inicio',    'href'=>'dashboard.php'],
        ['id'=>'agenda',    'label'=>'Agenda',    'href'=>'agenda/'],
        ['id'=>'pacientes', 'label'=>'Pacientes', 'href'=>'pacientes/'],
    ]],
    ['titulo' => 'Negocio', 'items' => [
        ['id'=>'finanzas',   'label'=>'Finanzas',   'href'=>'finanzas/'],
        ['id'=>'inventario', 'label'=>'Inventario', 'href'=>'inventario/'],
    ]],
    ['titulo' => 'Cuenta', 'items' => [
        ['id'=>'perfil', 'label'=>'Mi perfil', 'href'=>'perfil/'],
    ]],
];

// Iconos SVG inline · solo el path; el wrapper común vive en el render.
$iconosSidebar = [
    'dashboard'  => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
    'agenda'     => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'pacientes'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'finanzas'   => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'inventario' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
    'perfil'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
];
$svgAttrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
?>
<aside class="app-sidebar">
    <div class="app-sidebar-marca">DENTACITA</div>

    <nav class="app-sidebar-nav">
        <?php foreach ($seccionesMenu as $sec): ?>
            <div class="app-sidebar-seccion"><?php echo htmlspecialchars($sec['titulo']); ?></div>
            <?php foreach ($sec['items'] as $m):
                $clase = 'sidebar-item';
                if ($m['id'] === $nav_actual) $clase .= ' activo';
                $svgPath = $iconosSidebar[$m['id']] ?? '';
            ?>
            <a class="<?php echo $clase; ?>" href="<?php echo htmlspecialchars($nav_base_url . $m['href']); ?>">
                <svg class="sidebar-item-icono" <?php echo $svgAttrs; ?>><?php echo $svgPath; ?></svg>
                <span class="sidebar-item-label"><?php echo htmlspecialchars($m['label']); ?></span>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        <button type="button" class="btn btn-secundario btn-sm sidebar-cerrar sidebar-btn-notif" id="btnNotificaciones" aria-label="Notificaciones">
            <svg class="sidebar-btn-icono" <?php echo $svgAttrs; ?>><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="sidebar-btn-texto">Notificaciones</span>
            <span id="notifBadge" class="notif-badge" style="display:none">0</span>
        </button>
        <div class="app-sidebar-usuario">
            <div class="app-sidebar-usuario-nombre"><?php echo htmlspecialchars($usuarioMenu); ?></div>
            <div class="app-sidebar-usuario-rol"><?php echo htmlspecialchars($usuarioMenuEmail ?: 'Dentista'); ?></div>
        </div>
        <a href="<?php echo htmlspecialchars($nav_base_url); ?>logout.php"
           class="btn btn-secundario btn-sm sidebar-cerrar sidebar-btn-logout"
           aria-label="Cerrar sesión">
            <svg class="sidebar-btn-icono" <?php echo $svgAttrs; ?>><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="sidebar-btn-texto">Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- Panel de notificaciones (overlay) -->
<div id="notifPanel" class="modal-fondo">
    <div class="modal-caja modal-caja-pequena modal-caja-notif">
        <button type="button" class="modal-cerrar" id="cerrarNotifPanel" aria-label="Cerrar">&times;</button>
        <h2 class="modal-titulo">Notificaciones</h2>
        <p class="modal-subtitulo" id="notifSubtitulo">—</p>
        <div id="notifLista" class="notif-lista-scroll">
            <div class="texto-atenuado">Cargando…</div>
        </div>
        <div class="modal-acciones modal-acciones-spread">
            <button type="button" class="btn btn-secundario btn-sm" id="btnMarcarTodas">Marcar todas como leídas</button>
        </div>
    </div>
</div>

<script>
(function() {
    var btn = document.getElementById('btnNotificaciones');
    var badge = document.getElementById('notifBadge');
    var panel = document.getElementById('notifPanel');
    var lista = document.getElementById('notifLista');
    var subtit = document.getElementById('notifSubtitulo');
    var btnMarcar = document.getElementById('btnMarcarTodas');
    if (!btn || !panel) return;

    var navBase = <?php echo json_encode($nav_base_url); ?>;

    function escapar(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function tiempoRel(iso) {
        if (!iso) return '';
        var d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d)) return iso;
        var diffMs = Date.now() - d.getTime();
        var min = Math.round(diffMs / 60000);
        if (min < 1)   return 'ahora';
        if (min < 60)  return 'hace ' + min + ' min';
        var hr = Math.round(min / 60);
        if (hr < 24)   return 'hace ' + hr + ' h';
        var dias = Math.round(hr / 24);
        if (dias < 30) return 'hace ' + dias + ' día' + (dias === 1 ? '' : 's');
        return d.toLocaleDateString('es-MX');
    }
    /* Mapa de emojis por tipo de notificación. Mantener sincronizado con
       inventario/index.php (banner ⚠), agenda (📅 citas) y centro de
       notificaciones (esta tabla es la fuente de verdad). */
    function iconoTipo(tipo) {
        var map = { cita:'📅', stock_bajo:'⚠', stock_agotado:'❌', exito:'✅', error:'⛔', info:'ℹ' };
        return map[tipo] || '🔔';
    }

    async function refrescarBadge() {
        try {
            var r = await fetch(navBase + 'notificaciones-listar.php?solo_no_leidas=1');
            var json = await r.json();
            if (json && json.ok) actualizarBadge(json.no_leidas);
        } catch(e) { /* silencioso */ }
    }
    function actualizarBadge(n) {
        if (!badge) return;
        if (n > 0) {
            badge.textContent = n > 9 ? '9+' : String(n);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    async function cargarPanel() {
        lista.innerHTML = '<div class="texto-atenuado">Cargando…</div>';
        try {
            var r = await fetch(navBase + 'notificaciones-listar.php');
            var json = await r.json();
            if (!json.ok) { lista.innerHTML = '<div class="texto-atenuado">Error al cargar.</div>'; return; }
            actualizarBadge(json.no_leidas);
            subtit.textContent = json.no_leidas > 0
                ? json.no_leidas + ' sin leer'
                : 'Todo al día.';
            if (!json.notificaciones.length) {
                lista.innerHTML = '<div class="placeholder-card-inline"><p>Sin notificaciones todavía.</p></div>';
                return;
            }
            lista.innerHTML = json.notificaciones.map(function(n) {
                var leidaCls = n.leida == 1 ? 'notif-item-leida' : 'notif-item-no-leida';
                var enlace   = n.enlace ? navBase.replace(/\/$/,'') + n.enlace : '#';
                return '<a class="notif-item ' + leidaCls + '" href="' + escapar(enlace) + '" data-id="' + n.notificacion_id + '">' +
                         '<span class="notif-icono">' + iconoTipo(n.tipo) + '</span>' +
                         '<div class="notif-cuerpo">' +
                            '<div class="notif-titulo">' + escapar(n.titulo) + '</div>' +
                            '<div class="notif-mensaje">' + escapar(n.mensaje || '') + '</div>' +
                            '<div class="notif-meta">' + tiempoRel(n.fecha) + '</div>' +
                         '</div>' +
                       '</a>';
            }).join('');

            lista.querySelectorAll('.notif-item').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    var id = el.dataset.id;
                    var fd = new FormData();
                    fd.append('notificacion_id', id);
                    // Marca como leída en background; el navegador sigue al href.
                    fetch(navBase + 'notificaciones-marcar-leida.php', { method: 'POST', body: fd });
                });
            });
        } catch(e) {
            lista.innerHTML = '<div class="texto-atenuado">Sin conexión.</div>';
        }
    }

    function abrirPanel() { panel.classList.add('visible'); cargarPanel(); }
    function cerrarPanel() { panel.classList.remove('visible'); refrescarBadge(); }

    btn.addEventListener('click', abrirPanel);
    document.getElementById('cerrarNotifPanel').addEventListener('click', cerrarPanel);
    panel.addEventListener('click', function(e) { if (e.target === panel) cerrarPanel(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel.classList.contains('visible')) cerrarPanel();
    });

    btnMarcar.addEventListener('click', async function() {
        var fd = new FormData();
        fd.append('todas', '1');
        try {
            await fetch(navBase + 'notificaciones-marcar-leida.php', { method: 'POST', body: fd });
            cargarPanel();
        } catch(e) { /* silencioso */ }
    });

    // Carga inicial del badge.
    refrescarBadge();
})();
</script>

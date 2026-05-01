/* =====================================================================
 * FINANZAS · dashboard (gráficos, reportes, presets, filtros)
 * ---------------------------------------------------------------------
 * Depende de: finanzas-ajax.js. Renderiza el reporte mensual/diario
 * y el desglose de ingresos por categoría sobre <canvas>.
 * ====================================================================*/

let cacheIngresosCat = [];
let modoGraficoCat = 'bar'; // bar | pie
let modoReporte = 'mensual'; // mensual | diario


/* ---------- Presets de rango de fecha ---------- */
function aplicarPreset(preset) {
    const hoy = new Date();
    const fmt = (d) => d.toISOString().slice(0, 10);
    let desde = '', hasta = fmt(hoy);
    if (preset === 'hoy')   desde = fmt(hoy);
    else if (preset === '7d')  { const d = new Date(hoy); d.setDate(d.getDate() - 6);  desde = fmt(d); }
    else if (preset === '30d') { const d = new Date(hoy); d.setDate(d.getDate() - 29); desde = fmt(d); }
    else if (preset === '3m')  { const d = new Date(hoy); d.setMonth(d.getMonth() - 3); desde = fmt(d); }
    else if (preset === 'ano') { desde = `${hoy.getFullYear()}-01-01`; }
    $('#filtroDesde').value = desde;
    $('#filtroHasta').value = hasta;
    cargar();
}
document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => aplicarPreset(btn.dataset.preset));
});

$('#btnLimpiarFiltros').addEventListener('click', () => {
    $('#filtroTipo').value = '';
    $('#filtroEstado').value = 'activa';
    $('#filtroDesde').value = '';
    $('#filtroHasta').value = '';
    $('#filtroCategoria').value = '';
    $('#filtroPaciente').value = '';
    $('#filtroMetodo').value = '';
    cargar();
});


/* ---------- Reporte mensual / diario ---------- */
async function cargarReporte() {
    try {
        const params = modoReporte === 'diario'
            ? 'modo=diario&dias=30'
            : 'modo=mensual&meses=6';
        const resp = await fetch('reporte.php?' + params);
        const json = await resp.json();
        if (!manejarRedirect(json)) return;
        if (!json.ok) {
            $('#reporteEstado').textContent = 'Error al cargar reporte.';
            return;
        }
        renderGrafico(json.serie || []);
        renderTopCategorias(json.top_categorias_egreso || []);
        cacheIngresosCat = json.ingresos_por_categoria || [];
        renderIngresosCategoria();
        const titulo = $('#reporteTitulo');
        if (titulo) titulo.textContent = (modoReporte === 'diario')
            ? 'Últimos 30 días'
            : 'Últimos 6 meses';
        document.querySelectorAll('[data-reporte-modo]').forEach(b => {
            b.classList.toggle('btn-primario', b.dataset.reporteModo === modoReporte);
            b.classList.toggle('btn-secundario', b.dataset.reporteModo !== modoReporte);
        });
    } catch (e) {
        $('#reporteEstado').textContent = 'Sin conexión.';
    }
}

document.querySelectorAll('[data-reporte-modo]').forEach(btn => {
    btn.addEventListener('click', () => {
        modoReporte = btn.dataset.reporteModo;
        cargarReporte();
    });
});

function renderGrafico(serie) {
    const cv = document.getElementById('reporteCanvas');
    if (!cv || !serie.length) return;

    const dpr = window.devicePixelRatio || 1;
    const cssW = cv.clientWidth || 600;
    const cssH = cv.clientHeight || 220;
    cv.width  = cssW * dpr;
    cv.height = cssH * dpr;
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    const padding = { top: 16, right: 12, bottom: 30, left: 50 };
    const w = cssW - padding.left - padding.right;
    const h = cssH - padding.top  - padding.bottom;

    const root = getComputedStyle(document.documentElement);
    const colExito  = root.getPropertyValue('--color-exito').trim()      || '#2ECC8A';
    const colPelig  = root.getPropertyValue('--color-peligro').trim()    || '#E84545';
    const colTexto  = root.getPropertyValue('--texto-atenuado').trim()   || 'rgba(255,255,255,.42)';
    const colBorde  = root.getPropertyValue('--vidrio-borde').trim()     || 'rgba(255,255,255,.09)';

    const max = Math.max(1, ...serie.flatMap(s => [s.ingresos, s.egresos]));
    const yTicks = 4;

    ctx.strokeStyle = colBorde; ctx.lineWidth = 1;
    ctx.fillStyle = colTexto; ctx.font = '10px "JetBrains Mono", monospace';
    ctx.textBaseline = 'middle';
    for (let i = 0; i <= yTicks; i++) {
        const y = padding.top + (h * i / yTicks);
        ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(padding.left + w, y); ctx.stroke();
        const v = max * (1 - i / yTicks);
        ctx.textAlign = 'right';
        ctx.fillText('$' + Math.round(v / 1000) + 'k', padding.left - 6, y);
    }

    const grupos = serie.length;
    const grupoW = w / grupos;
    const barW   = Math.min(22, (grupoW - 14) / 2);
    serie.forEach((s, i) => {
        const x0 = padding.left + grupoW * i + grupoW / 2;
        const hI = (s.ingresos / max) * h;
        const hE = (s.egresos  / max) * h;

        ctx.fillStyle = colExito;
        ctx.fillRect(x0 - barW - 2, padding.top + h - hI, barW, hI);
        ctx.fillStyle = colPelig;
        ctx.fillRect(x0 + 2, padding.top + h - hE, barW, hE);

        ctx.fillStyle = colTexto;
        ctx.textAlign = 'center';
        ctx.fillText(s.etiqueta, x0, padding.top + h + 14);
    });
}

function renderTopCategorias(lista) {
    const cont = $('#reporteTopLista');
    if (!cont) return;
    if (!lista.length) {
        cont.innerHTML = '<div class="texto-atenuado texto-pequeno">Sin egresos registrados aún.</div>';
        return;
    }
    const max = lista[0].total;
    cont.innerHTML = lista.map(c => {
        const pct = max ? Math.round((c.total / max) * 100) : 0;
        return `
            <div class="reporte-top-item">
                <div class="reporte-top-fila">
                    <span class="reporte-top-cat">${escapar(c.categoria)}</span>
                    <span class="reporte-top-monto mono">${fmtDinero(c.total)}</span>
                </div>
                <div class="progreso"><div class="progreso-llenado" style="width:${pct}%"></div></div>
            </div>`;
    }).join('');
}


/* ---------- Gráfico ingresos por categoría · toggle bar/pie ---------- */
function renderIngresosCategoria() {
    const cv = document.getElementById('ingresosCategoriaCanvas');
    const leyendaEl = document.getElementById('ingresosCategoriaLeyenda');
    if (!cv) return;
    if (!cacheIngresosCat.length) {
        const ctx = cv.getContext('2d');
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, cv.width, cv.height);
        if (leyendaEl) leyendaEl.innerHTML = '<span class="texto-atenuado texto-pequeno">Sin ingresos en el período.</span>';
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const cssW = cv.clientWidth || 600;
    const cssH = cv.clientHeight || 220;
    cv.width  = cssW * dpr;
    cv.height = cssH * dpr;
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    const root = getComputedStyle(document.documentElement);
    const colExito  = root.getPropertyValue('--color-exito').trim()      || '#2ECC8A';
    const colInfo   = root.getPropertyValue('--color-info').trim()       || '#4A8EBC';
    const colPurp   = root.getPropertyValue('--color-purpura').trim()    || '#8E4DD9';
    const colAdver  = root.getPropertyValue('--color-advertencia').trim()|| '#E8972A';
    const colPelig  = root.getPropertyValue('--color-peligro').trim()    || '#E84545';
    const colAcento = root.getPropertyValue('--color-acento').trim()     || '#14b8a6';
    const paleta = [colExito, colInfo, colPurp, colAdver, colPelig, colAcento];
    const colTexto = root.getPropertyValue('--texto-atenuado').trim()    || 'rgba(255,255,255,.42)';
    const colBorde = root.getPropertyValue('--vidrio-borde').trim()      || 'rgba(255,255,255,.09)';

    if (leyendaEl) {
        leyendaEl.innerHTML = cacheIngresosCat.map((c, i) => {
            const color = paleta[i % paleta.length];
            return `<span class="reporte-pill" style="background:${color}33;border-color:${color}66;color:${color}">
                ${escapar(c.categoria)}: ${fmtDinero(c.total)}
            </span>`;
        }).join('');
    }

    if (modoGraficoCat === 'bar') {
        const padding = { top: 12, right: 12, bottom: 12, left: 130 };
        const w = cssW - padding.left - padding.right;
        const h = cssH - padding.top - padding.bottom;
        const max = Math.max(1, ...cacheIngresosCat.map(c => c.total));
        const altoBarra = Math.min(28, h / cacheIngresosCat.length - 6);
        ctx.font = '11px "Inter", sans-serif';
        ctx.textBaseline = 'middle';
        cacheIngresosCat.forEach((c, i) => {
            const y = padding.top + (i * (altoBarra + 6));
            const ancho = (c.total / max) * w;
            ctx.fillStyle = paleta[i % paleta.length];
            ctx.fillRect(padding.left, y, ancho, altoBarra);
            ctx.fillStyle = colTexto;
            ctx.textAlign = 'right';
            ctx.fillText(c.categoria.length > 14 ? c.categoria.substring(0, 13) + '…' : c.categoria, padding.left - 8, y + altoBarra / 2);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#fff';
            ctx.fillText('$' + Math.round(c.total).toLocaleString('es-MX'), padding.left + ancho + 6, y + altoBarra / 2);
        });
    } else {
        const cx = cssW / 2;
        const cy = cssH / 2;
        const r  = Math.min(cssW, cssH) / 2 - 14;
        const total = cacheIngresosCat.reduce((sum, c) => sum + c.total, 0);
        let inicio = -Math.PI / 2;
        cacheIngresosCat.forEach((c, i) => {
            const fraccion = c.total / total;
            const fin = inicio + fraccion * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, inicio, fin);
            ctx.closePath();
            ctx.fillStyle = paleta[i % paleta.length];
            ctx.fill();
            ctx.strokeStyle = colBorde;
            ctx.lineWidth = 2;
            ctx.stroke();
            inicio = fin;
        });
    }
}

document.querySelectorAll('[data-grafico-modo]').forEach(btn => {
    btn.addEventListener('click', () => {
        modoGraficoCat = btn.dataset.graficoModo;
        document.querySelectorAll('[data-grafico-modo]').forEach(b =>
            b.classList.toggle('btn-primario', b.dataset.graficoModo === modoGraficoCat)
        );
        renderIngresosCategoria();
    });
});

window.addEventListener('resize', () => {
    clearTimeout(window.__resizeRep);
    window.__resizeRep = setTimeout(() => { cargarReporte(); }, 200);
});

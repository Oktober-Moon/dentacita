/* =====================================================================
 * DASHBOARD · gráfico de tendencia financiera (últimos 3 meses)
 * ---------------------------------------------------------------------
 * Carga la serie desde finanzas/reporte.php y renderiza un bar chart
 * doble (ingresos vs egresos) sobre #dashReporteCanvas.
 * ====================================================================*/

(function() {
    var cv = document.getElementById('dashReporteCanvas');
    if (!cv) return;

    function renderGrafico(serie) {
        if (!serie.length) return;
        var dpr = window.devicePixelRatio || 1;
        var cssW = cv.clientWidth || 600;
        var cssH = cv.clientHeight || 180;
        cv.width  = cssW * dpr;
        cv.height = cssH * dpr;
        var ctx = cv.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, cssW, cssH);

        var padding = { top: 12, right: 10, bottom: 26, left: 50 };
        var w = cssW - padding.left - padding.right;
        var h = cssH - padding.top - padding.bottom;

        var root = getComputedStyle(document.documentElement);
        var colExito  = root.getPropertyValue('--color-exito').trim()    || '#2ECC8A';
        var colPelig  = root.getPropertyValue('--color-peligro').trim()  || '#E84545';
        var colTexto  = root.getPropertyValue('--texto-atenuado').trim() || 'rgba(255,255,255,.42)';
        var colBorde  = root.getPropertyValue('--vidrio-borde').trim()   || 'rgba(255,255,255,.09)';

        var max = Math.max(1);
        serie.forEach(function(s) {
            if (s.ingresos > max) max = s.ingresos;
            if (s.egresos  > max) max = s.egresos;
        });
        var yTicks = 4;
        ctx.strokeStyle = colBorde; ctx.lineWidth = 1;
        ctx.fillStyle = colTexto; ctx.font = '10px "JetBrains Mono", monospace';
        ctx.textBaseline = 'middle';
        for (var i = 0; i <= yTicks; i++) {
            var y = padding.top + (h * i / yTicks);
            ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(padding.left + w, y); ctx.stroke();
            var v = max * (1 - i / yTicks);
            ctx.textAlign = 'right';
            ctx.fillText('$' + Math.round(v / 1000) + 'k', padding.left - 6, y);
        }
        var grupos = serie.length;
        var grupoW = w / grupos;
        var barW = Math.min(20, (grupoW - 14) / 2);
        serie.forEach(function(s, i) {
            var x0 = padding.left + grupoW * i + grupoW / 2;
            var hI = (s.ingresos / max) * h;
            var hE = (s.egresos / max) * h;
            ctx.fillStyle = colExito;
            ctx.fillRect(x0 - barW - 2, padding.top + h - hI, barW, hI);
            ctx.fillStyle = colPelig;
            ctx.fillRect(x0 + 2, padding.top + h - hE, barW, hE);
            ctx.fillStyle = colTexto;
            ctx.textAlign = 'center';
            ctx.fillText(s.etiqueta, x0, padding.top + h + 14);
        });
    }

    fetch('finanzas/reporte.php?meses=3')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.ok) renderGrafico(data.serie || []);
        })
        .catch(function() { /* silencioso · no crítico para el dashboard */ });
})();

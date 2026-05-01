/* =====================================================================
 * AGENDA · cropper de foto del paciente nuevo
 * ---------------------------------------------------------------------
 * Encapsula el estado del cropper, el dibujo sobre canvas y todos los
 * listeners (input file, drag, zoom, rotación). El submit del paciente
 * en agenda-modal.js consume el dataURL desde el canvas final.
 * Depende de: agenda-ajax.js
 * ====================================================================*/

var npCropState = { img: null, scale: 1, dx: 0, dy: 0, rot: 0,
                    dragging: false, startX: 0, startY: 0 };

function npDibujarCrop() {
    var canvas = $('npCropCanvas');
    var ctx = canvas.getContext('2d');
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = 'rgba(255,255,255,0.05)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if (!npCropState.img) { ctx.restore(); return; }
    var cx = canvas.width / 2, cy = canvas.height / 2;
    ctx.translate(cx, cy);
    ctx.rotate((npCropState.rot * Math.PI) / 180);
    ctx.translate(-cx, -cy);
    ctx.drawImage(npCropState.img, npCropState.dx, npCropState.dy,
                  npCropState.img.width * npCropState.scale,
                  npCropState.img.height * npCropState.scale);
    ctx.restore();
}

$('np_foto').addEventListener('change', function(e) {
    var f = e.target.files[0];
    if (!f) return;
    if (!f.type.match(/^image\/(jpeg|png|webp)$/)) {
        toast('Solo JPG, PNG o WEBP.', false);
        e.target.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(ev) {
        var img = new Image();
        img.onload = function() {
            npCropState.img = img;
            var canvas = $('npCropCanvas');
            var minDim = Math.min(img.width, img.height);
            npCropState.scale = canvas.width / minDim;
            npCropState.dx = (canvas.width - img.width * npCropState.scale) / 2;
            npCropState.dy = (canvas.height - img.height * npCropState.scale) / 2;
            npCropState.rot = 0;
            $('npCropZoom').value = 100;
            $('npCropRotar').value = 0;
            $('npCropContenedor').style.display = '';
            npDibujarCrop();
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(f);
});

(function() {
    var canvasEl = $('npCropCanvas');
    canvasEl.addEventListener('mousedown', function(e) {
        if (!npCropState.img) return;
        npCropState.dragging = true;
        npCropState.startX = e.offsetX - npCropState.dx;
        npCropState.startY = e.offsetY - npCropState.dy;
    });
    canvasEl.addEventListener('mousemove', function(e) {
        if (!npCropState.dragging) return;
        npCropState.dx = e.offsetX - npCropState.startX;
        npCropState.dy = e.offsetY - npCropState.startY;
        npDibujarCrop();
    });
    canvasEl.addEventListener('mouseup',   function(){ npCropState.dragging = false; });
    canvasEl.addEventListener('mouseleave',function(){ npCropState.dragging = false; });

    $('npCropZoom').addEventListener('input', function(e) {
        if (!npCropState.img) return;
        var nuevoZoom = (+e.target.value) / 100;
        var canvas = $('npCropCanvas');
        var minDim = Math.min(npCropState.img.width, npCropState.img.height);
        var scaleBase = canvas.width / minDim;
        npCropState.scale = scaleBase * nuevoZoom;
        npDibujarCrop();
    });
    $('npCropRotar').addEventListener('input', function(e) {
        if (!npCropState.img) return;
        npCropState.rot = parseInt(e.target.value, 10) || 0;
        npDibujarCrop();
    });
})();

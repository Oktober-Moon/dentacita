/* =====================================================================
 * DASHBOARD · tour de onboarding (5 pasos, primera visita)
 * ---------------------------------------------------------------------
 * Recibe el nombre del dentista desde window.DC_NOMBRE (lo setea
 * dashboard.php antes de cargar este archivo) para el saludo del paso 1.
 * Al completar/saltar, marca onboarding como completado en la BD.
 * ====================================================================*/

(function() {
    var nombre = (window.DC_NOMBRE || 'dentista');

    var pasos = [
        {
            titulo: '¡Bienvenido a DentaCita!',
            cuerpo: '<p>Hola ' + nombre + ' 👋</p>'
                  + '<p>Esta app es tu centro de operaciones diario. En 5 pasos rápidos te muestro qué puedes hacer.</p>'
        },
        {
            titulo: 'Agenda y citas',
            cuerpo: '<p>En la <strong>Agenda</strong> ves un calendario con tus citas. Puedes:</p>'
                  + '<ul>'
                  + '<li>Crear citas con paciente, hora, precio y estado.</li>'
                  + '<li>Marcar como completadas (genera ingreso automático en Finanzas).</li>'
                  + '<li>Anotar memorias personales privadas para cada día.</li>'
                  + '</ul>'
        },
        {
            titulo: 'Pacientes y ficha clínica',
            cuerpo: '<p>Cada paciente tiene su ficha con 5 pestañas:</p>'
                  + '<ul>'
                  + '<li><strong>Datos:</strong> contacto, alergias, padecimientos.</li>'
                  + '<li><strong>Citas:</strong> historial completo.</li>'
                  + '<li><strong>Acuerdos:</strong> propuestas de servicio (al aceptar, crea cita).</li>'
                  + '<li><strong>Archivos:</strong> radiografías, PDFs, organizados en carpetas con papelera.</li>'
                  + '<li><strong>Notas:</strong> bitácora clínica del paciente.</li>'
                  + '</ul>'
        },
        {
            titulo: 'Finanzas e Inventario',
            cuerpo: '<p>Todo conectado entre sí:</p>'
                  + '<ul>'
                  + '<li><strong>Cita completada</strong> → ingreso automático.</li>'
                  + '<li><strong>Compra de inventario</strong> → egreso automático.</li>'
                  + '<li><strong>Venta de producto</strong> → ingreso automático.</li>'
                  + '<li><strong>Stock bajo</strong> → notificación.</li>'
                  + '</ul>'
                  + '<p>Reembolsos, anulaciones y reportes mensuales también disponibles.</p>'
        },
        {
            titulo: 'Tu perfil',
            cuerpo: '<p>En <strong>Mi perfil</strong> configuras lo esencial:</p>'
                  + '<ul>'
                  + '<li><strong>Nombre:</strong> tu nombre completo (se guarda automáticamente al editar).</li>'
                  + '<li><strong>Foto:</strong> sube y recorta tu foto de perfil para que se muestre en cabecera.</li>'
                  + '<li><strong>Tema visual:</strong> 8 colores para personalizar el panel.</li>'
                  + '</ul>'
                  + '<p>Cuando quieras, vuelve aquí para ajustar lo que sea.</p>'
        }
    ];

    var paso = 0;
    var overlay = document.getElementById('onboardingOverlay');
    if (!overlay) return;
    overlay.classList.add('visible');

    function pintar() {
        var p = pasos[paso];
        document.getElementById('obTitulo').textContent  = p.titulo;
        document.getElementById('obPasoInfo').textContent = 'Paso ' + (paso + 1) + ' de ' + pasos.length;
        document.getElementById('obCuerpo').innerHTML    = p.cuerpo;
        var pts = document.querySelectorAll('#obProgreso span');
        pts.forEach(function(s, i){ s.classList.toggle('activo', i <= paso); });
        document.getElementById('obSiguiente').textContent =
            (paso === pasos.length - 1) ? 'Listo, empezar' : 'Siguiente →';
    }
    function cerrar() {
        overlay.classList.remove('visible');
        fetch('perfil/onboarding-completar.php', { method: 'POST', body: new FormData() }).catch(function(){});
    }
    document.getElementById('obSaltar').addEventListener('click', cerrar);
    document.getElementById('obSiguiente').addEventListener('click', function() {
        if (paso < pasos.length - 1) { paso++; pintar(); }
        else { cerrar(); }
    });
})();

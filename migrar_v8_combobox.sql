-- ============================================================
-- DENTACITA · MIGRACIÓN v8 · combobox cerrado de inventario y finanzas
-- ------------------------------------------------------------
-- Aplicar SOLO sobre BDs que ya estaban en v7. Si la BD se acaba
-- de instalar con `instalar.sql` (v8+), este script es innecesario.
--
-- Cómo correr:
--   1. Hacer backup:   mysqldump dentacita > dentacita_backup.sql
--   2. Abrir phpMyAdmin → BD `dentacita` → pestaña SQL
--   3. Pegar y ejecutar TODO el contenido de este archivo.
--
-- Qué hace:
--   - Normaliza `inventario_items.categoria` a las 6 claves del catálogo
--     cerrado (consumibles, instrumentos, materiales, equipamiento,
--     medicamentos, general).
--   - Normaliza `inventario_items.unidad` a las 6 claves del catálogo
--     cerrado (unidad, caja, paquete, ml, gr, par).
--   - Cambia los DEFAULT de las columnas (de 'pieza' → 'unidad').
--   - Amplía el ENUM `inventario_movimientos.motivo` para incluir 'danado'.
--   - Normaliza `transacciones.categoria` a los catálogos del proyecto
--     grande (Consulta/Tratamiento/Procedimiento/Producto/Otro para
--     ingresos; Materiales/Equipo/Alquiler/Servicios/Marketing/Software/
--     Transporte/Otro para egresos). 'Reembolso' se preserva intacto.
-- ============================================================

USE dentacita;


-- ------------------------------------------------------------
-- 1. INVENTARIO · normalizar `categoria`
-- ------------------------------------------------------------
UPDATE inventario_items SET categoria = 'medicamentos'
    WHERE LOWER(categoria) IN ('anestesicos','anestésicos','anestesia','medicamento','medicina','farmacia','medicamentos');

UPDATE inventario_items SET categoria = 'consumibles'
    WHERE LOWER(categoria) IN ('insumo','insumos','consumible','consumibles','desechable','desechables','protección','proteccion','limpieza','algodon','algodón');

UPDATE inventario_items SET categoria = 'materiales'
    WHERE LOWER(categoria) IN ('restaurativo','restaurativos','material','materiales','resina','cemento','impresion','impresión');

UPDATE inventario_items SET categoria = 'instrumentos'
    WHERE LOWER(categoria) IN ('instrumento','instrumentos','herramienta','herramientas','endodoncia','lima','limas');

UPDATE inventario_items SET categoria = 'equipamiento'
    WHERE LOWER(categoria) IN ('equipo','equipos','equipamiento','sillon','sillón','autoclave','rayos','rayosx');

UPDATE inventario_items SET categoria = 'general'
    WHERE categoria IS NULL OR categoria = ''
       OR categoria NOT IN ('consumibles','instrumentos','materiales','equipamiento','medicamentos','general');


-- ------------------------------------------------------------
-- 2. INVENTARIO · normalizar `unidad`
-- ------------------------------------------------------------
UPDATE inventario_items SET unidad = 'ml'
    WHERE LOWER(unidad) IN ('ml','mililitros','mililitro');

UPDATE inventario_items SET unidad = 'gr'
    WHERE LOWER(unidad) IN ('gr','g','gramo','gramos');

UPDATE inventario_items SET unidad = 'caja'
    WHERE LOWER(unidad) IN ('caja','cajas','cj');

UPDATE inventario_items SET unidad = 'paquete'
    WHERE LOWER(unidad) IN ('paquete','paquetes','pack','paq');

UPDATE inventario_items SET unidad = 'par'
    WHERE LOWER(unidad) IN ('par','pares');

UPDATE inventario_items SET unidad = 'unidad'
    WHERE unidad IS NULL OR unidad = ''
       OR unidad NOT IN ('unidad','caja','paquete','ml','gr','par');


-- ------------------------------------------------------------
-- 3. INVENTARIO · cambiar DEFAULT y NOT NULL
-- ------------------------------------------------------------
ALTER TABLE inventario_items
    MODIFY COLUMN categoria VARCHAR(50) NOT NULL DEFAULT 'general',
    MODIFY COLUMN unidad    VARCHAR(20) NOT NULL DEFAULT 'unidad';


-- ------------------------------------------------------------
-- 4. INVENTARIO · ampliar ENUM `motivo` para incluir 'danado'
-- ------------------------------------------------------------
ALTER TABLE inventario_movimientos
    MODIFY COLUMN motivo ENUM(
        'compra','donacion','ajuste_inicial','devolucion_proveedor',
        'venta','uso_consulta','vencimiento','perdida','danado','ajuste_inventario',
        'otro'
    ) NOT NULL;


-- ------------------------------------------------------------
-- 5. FINANZAS · normalizar `categoria` de ingresos
-- ------------------------------------------------------------
UPDATE transacciones SET categoria = 'Consulta'
    WHERE tipo = 'ingreso'
      AND categoria IN ('Consultas','Consulta general','Cita','Citas','Revision','Revisión');

UPDATE transacciones SET categoria = 'Tratamiento'
    WHERE tipo = 'ingreso'
      AND categoria IN ('Tratamientos','Tratamiento dental','Tratamientos dentales','Limpieza','Empaste','Endodoncia','Ortodoncia');

UPDATE transacciones SET categoria = 'Procedimiento'
    WHERE tipo = 'ingreso'
      AND categoria IN ('Procedimientos','Cirugia','Cirugía','Cirugias','Cirugías','Extracción','Extracciones');

UPDATE transacciones SET categoria = 'Producto'
    WHERE tipo = 'ingreso'
      AND categoria IN ('Productos','Venta de producto','Venta','Ventas','Material vendido');

UPDATE transacciones SET categoria = 'Otro'
    WHERE tipo = 'ingreso'
      AND categoria NOT IN ('Consulta','Tratamiento','Procedimiento','Producto','Otro','Reembolso');


-- ------------------------------------------------------------
-- 6. FINANZAS · normalizar `categoria` de egresos
-- ------------------------------------------------------------
UPDATE transacciones SET categoria = 'Materiales'
    WHERE tipo = 'egreso'
      AND categoria IN ('Material','Insumos','Insumo','Materiales clínicos','Materiales clinicos','Compra de material');

UPDATE transacciones SET categoria = 'Servicios'
    WHERE tipo = 'egreso'
      AND categoria IN ('Servicio','Luz','Agua','Internet','Teléfono','Telefono','Gas','Servicios públicos','Servicios publicos');

UPDATE transacciones SET categoria = 'Alquiler'
    WHERE tipo = 'egreso'
      AND categoria IN ('Renta','Renta del consultorio','Arriendo','Local','Renta local');

UPDATE transacciones SET categoria = 'Equipo'
    WHERE tipo = 'egreso'
      AND categoria IN ('Equipos','Equipamiento','Herramientas','Compra de equipo','Mantenimiento de equipo');

UPDATE transacciones SET categoria = 'Otro'
    WHERE tipo = 'egreso'
      AND categoria NOT IN ('Materiales','Equipo','Alquiler','Servicios','Marketing','Software','Transporte','Otro','Reembolso');


-- ------------------------------------------------------------
-- 7. VERIFICACIÓN (correr manualmente después)
-- ------------------------------------------------------------
-- SELECT DISTINCT categoria FROM inventario_items;
-- SELECT DISTINCT unidad    FROM inventario_items;
-- SELECT DISTINCT categoria FROM transacciones WHERE tipo='ingreso';
-- SELECT DISTINCT categoria FROM transacciones WHERE tipo='egreso';

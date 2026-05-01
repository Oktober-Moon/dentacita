-- ============================================================
-- MIGRACIÓN · Fase 2 / M6 · motivo_cancelacion en citas
-- ------------------------------------------------------------
-- Para BDs ya pobladas que NO se quieren reinstalar desde cero.
-- Si vas a re-ejecutar instalar.sql (con DROP DATABASE), no necesitas
-- correr este script — la columna ya está en la versión nueva del SQL.
--
-- Ejecutar:
--   mysql -u dentista -pdentista dentacita < migraciones/fase2-motivo-cancelacion.sql
--   o pegar el contenido en phpMyAdmin con la BD `dentacita` seleccionada.
-- ============================================================

USE dentacita;

ALTER TABLE citas
    ADD COLUMN motivo_cancelacion VARCHAR(200) NULL
    AFTER notas;

-- Verificación rápida (opcional):
--   DESCRIBE citas;
--   SHOULD show motivo_cancelacion VARCHAR(200) YES NULL.

-- ============================================================
-- DENTACITA · ARCHIVO MAESTRO DE BASE DE DATOS · v8 multi-tenant
-- ------------------------------------------------------------
-- Único archivo SQL del proyecto. Crea la BD desde cero, las 12
-- tablas, la cuenta MySQL de servicio y los datos seed demo.
-- Cada cuenta `usuarios` tiene su propio espacio aislado: sus
-- pacientes, citas, finanzas, inventario, perfil, etc.
--
-- Cómo correr:
--   1. Abrir phpMyAdmin (http://localhost/phpmyadmin/).
--   2. Pegar TODO este archivo y ejecutarlo (DROP DATABASE incluido).
--   3. Crea: BD `dentacita`, 12 tablas, la cuenta MySQL de servicio
--      `dentista`@`localhost` (password `dentista`) con permisos
--      SELECT/INSERT/UPDATE/DELETE, y un usuario demo de la app
--      (email: dentista@demo.com · password: dentista).
--
-- Si ya tienes una BD anterior, este script la sobrescribe (DROP).
-- No hay scripts de migración separados: este archivo es la fuente
-- de verdad y siempre refleja la última versión del schema.
-- ============================================================


DROP DATABASE IF EXISTS dentacita;
CREATE DATABASE dentacita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dentacita;


-- ============================================================
-- BLOQUE 0 · USUARIOS (cuentas web)
-- ============================================================

CREATE TABLE usuarios (
    usuario_id      INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(100) NOT NULL UNIQUE,
    nombre          VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login    TIMESTAMP NULL,
    INDEX idx_usuario_email (email)
);


-- ============================================================
-- BLOQUE A · PERFIL Y PROFESIONES DEL DENTISTA
-- ============================================================

CREATE TABLE perfil_dentista (
    perfil_id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id             INT NOT NULL UNIQUE,
    nombre_completo        VARCHAR(150),
    foto_url               VARCHAR(255),
    variante_tema          VARCHAR(40) DEFAULT 'cyan-default',
    onboarding_completado  TINYINT(1) DEFAULT 0,
    creado_en              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id) ON DELETE CASCADE
);


-- ============================================================
-- BLOQUE B · PACIENTES Y CITAS
-- ============================================================

CREATE TABLE pacientes (
    paciente_id      INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    nombre_completo  VARCHAR(100) NOT NULL,
    telefono         VARCHAR(20),
    email            VARCHAR(100),
    fecha_nacimiento DATE,
    genero           ENUM('femenino','masculino','otro','no_especificado') DEFAULT 'no_especificado',
    direccion        VARCHAR(200),
    ocupacion        VARCHAR(100),
    alergias         TEXT,
    padecimientos    TEXT,
    medicamentos     TEXT,
    foto_url         VARCHAR(255),
    notas_dentista   TEXT,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id) ON DELETE CASCADE,
    INDEX idx_pac_usuario (usuario_id),
    INDEX idx_nombre   (nombre_completo),
    INDEX idx_telefono (telefono)
);

CREATE TABLE citas (
    cita_id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id         INT NOT NULL,
    paciente_id        INT NULL,
    paciente_nombre    VARCHAR(100) NOT NULL,
    paciente_telefono  VARCHAR(20),
    titulo             VARCHAR(120) NOT NULL,
    descripcion        TEXT,
    fecha_hora_inicio  DATETIME NOT NULL,
    fecha_hora_fin     DATETIME NOT NULL,
    estado             ENUM('programada','confirmada','completada','cancelada','no_asistio')
                       NOT NULL DEFAULT 'programada',
    notas              TEXT,
    motivo_cancelacion VARCHAR(200),
    precio             DECIMAL(10,2),
    creado_en          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(usuario_id)  ON DELETE CASCADE,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id) ON DELETE SET NULL,
    INDEX idx_cita_usuario (usuario_id),
    INDEX idx_fecha_cita   (fecha_hora_inicio),
    INDEX idx_estado_cita  (estado)
);


-- ============================================================
-- BLOQUE C · FICHA DEL PACIENTE
-- ============================================================

CREATE TABLE carpetas_paciente (
    carpeta_id        INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id       INT NOT NULL,
    ruta_carpeta      VARCHAR(255) NOT NULL,
    nombre            VARCHAR(120) NOT NULL,
    creado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
    UNIQUE KEY uk_carpeta_path (paciente_id, ruta_carpeta),
    INDEX idx_carpeta_paciente (paciente_id)
);

CREATE TABLE archivos_paciente (
    archivo_id        INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id       INT NOT NULL,
    nombre_archivo    VARCHAR(150) NOT NULL,
    descripcion       VARCHAR(200),
    tipo              ENUM('imagen','pdf','radiografia','documento','laboratorio','otro')
                      DEFAULT 'documento',
    archivo_url       VARCHAR(255) NOT NULL,
    ruta_carpeta      VARCHAR(255) DEFAULT '',
    fecha             DATE,
    eliminado_en      DATETIME NULL,
    eliminado_por     VARCHAR(80) NULL,
    creado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
    INDEX idx_archivo_paciente (paciente_id),
    INDEX idx_archivo_carpeta  (paciente_id, ruta_carpeta),
    INDEX idx_archivo_papelera (eliminado_en)
);

CREATE TABLE notas_paciente (
    nota_id           INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id       INT NOT NULL,
    contenido         TEXT NOT NULL,
    fecha             DATE NOT NULL,
    creado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
    INDEX idx_nota_paciente (paciente_id, fecha)
);


-- ============================================================
-- BLOQUE D · OPERACIÓN
-- ============================================================

CREATE TABLE inventario_items (
    item_id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    nombre           VARCHAR(120) NOT NULL,
    categoria        VARCHAR(50) NOT NULL DEFAULT 'general',
    cantidad_actual  INT NOT NULL DEFAULT 0,
    cantidad_minima  INT NOT NULL DEFAULT 0,
    unidad           VARCHAR(20) NOT NULL DEFAULT 'unidad',
    costo_unitario   DECIMAL(10,2),
    precio_venta     DECIMAL(10,2),
    proveedor        VARCHAR(100),
    notas            TEXT,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id) ON DELETE CASCADE,
    INDEX idx_inv_usuario   (usuario_id),
    INDEX idx_inv_categoria (categoria),
    INDEX idx_inv_stock_bajo (cantidad_actual, cantidad_minima)
);

CREATE TABLE inventario_movimientos (
    movimiento_id    INT AUTO_INCREMENT PRIMARY KEY,
    item_id          INT NOT NULL,
    tipo             ENUM('entrada','salida','ajuste') NOT NULL,
    motivo           ENUM(
                       'compra','donacion','ajuste_inicial','devolucion_proveedor',
                       'venta','uso_consulta','vencimiento','perdida','danado','ajuste_inventario',
                       'otro'
                     ) NOT NULL,
    cantidad         INT NOT NULL,
    cita_id          INT NULL,
    costo_unitario   DECIMAL(10,2),
    precio_venta_unitario DECIMAL(10,2),
    tiene_costo      TINYINT(1) DEFAULT 0,
    notas            TEXT,
    fecha            DATE NOT NULL,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventario_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (cita_id) REFERENCES citas(cita_id) ON DELETE SET NULL,
    INDEX idx_mov_item (item_id, fecha),
    INDEX idx_mov_tipo (tipo, motivo)
);

CREATE TABLE transacciones (
    transaccion_id   INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    tipo             ENUM('ingreso','egreso') NOT NULL,
    categoria        VARCHAR(50) NOT NULL,
    monto            DECIMAL(10,2) NOT NULL,
    fecha            DATE NOT NULL,
    descripcion      VARCHAR(200),
    paciente_id      INT NULL,
    cita_id          INT NULL,
    inventario_item_id      INT NULL,
    inventario_movimiento_id INT NULL,
    metodo_pago      ENUM('efectivo','tarjeta','transferencia','cheque','otro') DEFAULT 'efectivo',
    estado           ENUM('activa','anulada') DEFAULT 'activa',
    anulada_en       DATETIME NULL,
    motivo_anulacion VARCHAR(200) NULL,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)               REFERENCES usuarios(usuario_id)                ON DELETE CASCADE,
    FOREIGN KEY (paciente_id)              REFERENCES pacientes(paciente_id)              ON DELETE SET NULL,
    FOREIGN KEY (cita_id)                  REFERENCES citas(cita_id)                      ON DELETE SET NULL,
    FOREIGN KEY (inventario_item_id)       REFERENCES inventario_items(item_id)           ON DELETE SET NULL,
    FOREIGN KEY (inventario_movimiento_id) REFERENCES inventario_movimientos(movimiento_id) ON DELETE SET NULL,
    INDEX idx_trans_usuario  (usuario_id),
    INDEX idx_trans_fecha    (fecha),
    INDEX idx_trans_tipo     (tipo),
    INDEX idx_trans_estado   (estado),
    INDEX idx_trans_categoria (categoria)
);


-- ============================================================
-- BLOQUE E · MEMORIAS PERSONALES + NOTIFICACIONES
-- ============================================================

CREATE TABLE personal_memories (
    memoria_id       INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    contenido        TEXT NOT NULL,
    fecha            DATE NOT NULL,
    color            VARCHAR(20) DEFAULT 'amarillo',
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id) ON DELETE CASCADE,
    INDEX idx_memoria_usuario (usuario_id, fecha)
);

CREATE TABLE notificaciones (
    notificacion_id  INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    tipo             ENUM('info','cita','stock_bajo','stock_agotado','exito','error') NOT NULL,
    titulo           VARCHAR(150) NOT NULL,
    mensaje          TEXT,
    enlace           VARCHAR(255),
    leida            TINYINT(1) DEFAULT 0,
    fecha            DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id) ON DELETE CASCADE,
    INDEX idx_notif_usuario (usuario_id, leida, fecha),
    INDEX idx_notif_tipo  (tipo)
);


-- ============================================================
-- CUENTA MYSQL · cuenta de servicio para que la app conecte
-- ============================================================

DROP USER IF EXISTS 'dentista'@'localhost';
CREATE USER 'dentista'@'localhost' IDENTIFIED BY 'dentista';
GRANT SELECT, INSERT, UPDATE, DELETE ON dentacita.* TO 'dentista'@'localhost';
FLUSH PRIVILEGES;


-- ============================================================
-- USUARIO DEMO + DATOS DE PRUEBA
--   email:    dentista@demo.com
--   password: dentista
--   El hash es bcrypt de 'dentista' (cost 10).
-- ============================================================

INSERT INTO usuarios (email, nombre, password_hash) VALUES
    ('dentista@demo.com', 'Dra. Camila Reyes',
     '$2y$10$4zd.ixnFJvoecw6rqMqjkOOOhwpxu60N46U7qugXgmz1Uh6FxR1yC');

SET @uid := LAST_INSERT_ID();

INSERT INTO perfil_dentista
    (usuario_id, nombre_completo, onboarding_completado)
VALUES
    (@uid, 'Dra. Camila Reyes', 1);

INSERT INTO pacientes (usuario_id, nombre_completo, telefono, email, fecha_nacimiento, genero, direccion, ocupacion, alergias, padecimientos, notas_dentista) VALUES
    (@uid, 'Ana Torres',         '5512345678', 'ana.torres@correo.com',  '1992-03-14', 'femenino',  'Calle Roble 12',  'Diseñadora',     'Penicilina',     NULL, 'Paciente regular. Higiene cada 6 meses.'),
    (@uid, 'Carlos Méndez',      '5598765432', 'carlos.m@correo.com',    '1985-07-22', 'masculino', 'Av. Pino 305',    'Ingeniero',      NULL,             'Bruxismo nocturno.', NULL),
    (@uid, 'Lucía Hernández',    '5511223344', 'lucia.h@correo.com',     '2001-11-05', 'femenino',  NULL,              'Estudiante',     NULL,             NULL, NULL),
    (@uid, 'Roberto Vega',       '5599887766', NULL,                     '1978-01-30', 'masculino', 'Cda. Cedro 7',    'Comerciante',    'Latex',          'Hipertensión leve.', 'Confirmar siempre antes de la cita.'),
    (@uid, 'Sofía Ramírez',      '5544556677', 'sofiar@correo.com',      '1995-09-18', 'femenino',  NULL,              'Médica',         NULL,             NULL, NULL);

INSERT INTO citas (usuario_id, paciente_id, paciente_nombre, paciente_telefono, titulo, descripcion,
                   fecha_hora_inicio, fecha_hora_fin, estado, precio) VALUES
    (@uid, 1, 'Ana Torres',      '5512345678', 'Limpieza dental',      'Limpieza profunda y revisión.',
        DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR,
        DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 11 HOUR, 'confirmada', 800.00),
    (@uid, 2, 'Carlos Méndez',   '5598765432', 'Consulta · bruxismo',  'Revisión de guarda nocturna.',
        DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 16 HOUR,
        DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 17 HOUR, 'programada', 600.00),
    (@uid, 3, 'Lucía Hernández', '5511223344', 'Empaste',              'Caries molar inferior derecha.',
        DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 12 HOUR + INTERVAL 30 MINUTE,
        DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 13 HOUR + INTERVAL 30 MINUTE, 'programada', 1200.00),
    (@uid, 5, 'Sofía Ramírez',   '5544556677', 'Ajuste de ortodoncia', 'Cambio de ligas y revisión.',
        CURDATE() + INTERVAL 9 HOUR,
        CURDATE() + INTERVAL 9 HOUR + INTERVAL 30 MINUTE, 'completada', 500.00);

INSERT INTO citas (usuario_id, paciente_id, paciente_nombre, paciente_telefono, titulo, descripcion,
                   fecha_hora_inicio, fecha_hora_fin, estado, precio) VALUES
    (@uid, 1, 'Ana Torres', '5512345678', 'Blanqueamiento profesional', 'Sesión completa con luz LED.',
     DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 17 HOUR,
     DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 18 HOUR, 'confirmada', 2500.00);

INSERT INTO carpetas_paciente (paciente_id, ruta_carpeta, nombre) VALUES
    (5, 'Ortodoncia',          'Ortodoncia'),
    (5, 'Ortodoncia/Antes',    'Antes'),
    (1, 'Radiografías',        'Radiografías');

INSERT INTO archivos_paciente (paciente_id, nombre_archivo, descripcion, tipo, archivo_url, ruta_carpeta, fecha) VALUES
    (5, 'Foto inicial · Sofía',           'Antes del tratamiento.',    'imagen',      'https://example.com/sofia-inicial.jpg', 'Ortodoncia/Antes', DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),
    (5, 'Plan de tratamiento PDF',        'Documento firmado.',        'pdf',         'https://example.com/plan-sofia.pdf',    'Ortodoncia',       DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),
    (1, 'Radiografía panorámica · Ana',   'Estudio inicial.',          'radiografia', 'https://example.com/ana-pano.png',      'Radiografías',     DATE_SUB(CURDATE(), INTERVAL 1 MONTH));

INSERT INTO notas_paciente (paciente_id, contenido, fecha) VALUES
    (1, 'La paciente reporta sensibilidad al frío. Recomendado pasta para dientes sensibles por 4 semanas.', DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
    (2, 'Diagnóstico: bruxismo nocturno moderado. Confeccionar guarda oclusal.', DATE_SUB(CURDATE(), INTERVAL 14 DAY)),
    (5, 'Avance del tratamiento ortodóncico bueno. Próximo ajuste programado.', CURDATE());

INSERT INTO inventario_items (usuario_id, nombre, categoria, cantidad_actual, cantidad_minima, unidad, costo_unitario, precio_venta, proveedor) VALUES
    (@uid, 'Anestesia lidocaína 2%',    'medicamentos',  45,  20, 'unidad', 35.00,  NULL,    'Dental Supply MX'),
    (@uid, 'Guantes de nitrilo · M',    'consumibles',  180, 100, 'caja',    2.50,  NULL,    'Dental Supply MX'),
    (@uid, 'Resina compuesta A2',       'materiales',    12,   5, 'unidad', 280.00,  NULL,   'Distribuidora Dental'),
    (@uid, 'Pasta profiláctica',        'consumibles',    3,   8, 'unidad',  90.00, 150.00,  'Dental Supply MX'),
    (@uid, 'Algodón en rollo',          'consumibles',   25,  10, 'unidad',  18.00,  NULL,   'Distribuidora Dental'),
    (@uid, 'Lima endodóncica K · 25mm', 'instrumentos',   8,   6, 'unidad',  85.00,  NULL,   'Endodental'),
    (@uid, 'Cepillo dental premium',    'general',       50,  10, 'unidad',  25.00,  60.00,  'Distribuidora Dental');

INSERT INTO inventario_movimientos (item_id, tipo, motivo, cantidad, costo_unitario, fecha) VALUES
    (1, 'entrada', 'compra',         50, 35.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
    (1, 'salida',  'uso_consulta',    5, NULL,  DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
    (2, 'entrada', 'compra',        200,  2.50, DATE_SUB(CURDATE(), INTERVAL 25 DAY)),
    (2, 'salida',  'uso_consulta',   20, NULL,  DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
    (4, 'salida',  'uso_consulta',    5, NULL,  DATE_SUB(CURDATE(), INTERVAL 3 DAY));
INSERT INTO inventario_movimientos (item_id, tipo, motivo, cantidad, precio_venta_unitario, fecha) VALUES
    (7, 'salida',  'venta',           2, 60.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY));

INSERT INTO transacciones (usuario_id, tipo, categoria, monto, fecha, descripcion, paciente_id, cita_id, metodo_pago, estado) VALUES
    (@uid, 'ingreso', 'Tratamiento',      500.00, CURDATE(),                                  'Ajuste ortodoncia · Sofía Ramírez',     5, 4, 'efectivo',     'activa'),
    (@uid, 'ingreso', 'Consulta',         400.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY),        'Consulta general · Ana Torres',         1, NULL, 'tarjeta',     'activa'),
    (@uid, 'ingreso', 'Tratamiento',      800.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY),        'Limpieza · Ana Torres',                 1, NULL, 'tarjeta',     'activa'),
    (@uid, 'ingreso', 'Tratamiento',     1200.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY),       'Empaste · Lucía Hernández',             3, NULL, 'efectivo',    'activa');

INSERT INTO transacciones (usuario_id, tipo, categoria, monto, fecha, descripcion, inventario_item_id, inventario_movimiento_id, metodo_pago, estado) VALUES
    (@uid, 'egreso',  'Materiales', 1750.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Inventario: Anestesia lidocaína 2% x 50 (Compra)',  1, 1, 'tarjeta', 'activa'),
    (@uid, 'egreso',  'Materiales',  500.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Inventario: Guantes de nitrilo · M x 200 (Compra)', 2, 3, 'tarjeta', 'activa'),
    (@uid, 'ingreso', 'Producto',    120.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Venta inventario: Cepillo dental premium x 2 @ $60.00/u', 7, 6, 'efectivo', 'activa');

INSERT INTO transacciones (usuario_id, tipo, categoria, monto, fecha, descripcion, metodo_pago, estado) VALUES
    (@uid, 'egreso',  'Alquiler',  12000.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY),  'Renta del consultorio',             'transferencia', 'activa'),
    (@uid, 'egreso',  'Servicios',   650.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY),   'Luz, agua, internet',               'transferencia', 'activa');

INSERT INTO personal_memories (usuario_id, contenido, fecha, color) VALUES
    (@uid, 'Llamar al laboratorio dental por las prótesis pendientes.', CURDATE(), 'amarillo'),
    (@uid, 'Preparar material para la cita de ortodoncia de mañana.',   DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'azul');

INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, leida, fecha) VALUES
    (@uid, 'cita',        'Cita confirmada',       'Ana Torres confirmó su cita.',                        '/agenda/',      1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (@uid, 'stock_bajo',  'Stock bajo',            'Pasta profiláctica tiene stock bajo: 3/8 pieza.',     '/inventario/',  0, DATE_SUB(NOW(), INTERVAL 3 DAY));

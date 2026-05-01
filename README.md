# DentaCita · proyecto XAMPP (PHP + MariaDB)

Aplicación de gestión para consultorio dental, hecha en **PHP plano + MariaDB** sobre XAMPP. Cada dentista crea su propia cuenta web y trabaja en un espacio aislado: sus pacientes, citas, finanzas e inventario nunca se mezclan con los de otra cuenta.

---

## Cómo correr

1. Copia toda la carpeta `dentacita/` dentro de `htdocs/` de XAMPP.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Abre phpMyAdmin (`http://localhost/phpmyadmin/`).
4. Pega el contenido de `instalar.sql` y ejecútalo. Esto crea:
   - La base de datos `dentacita` con 13 tablas
   - El usuario MySQL **de servicio** `dentista`@`localhost` (password `dentista`) con permisos `SELECT, INSERT, UPDATE, DELETE` sobre la base
   - Un **usuario demo de la app**: email `dentista@demo.com`, contraseña `dentista`
   - Datos seed de prueba (pacientes, citas, inventario, transacciones)
5. Abre `http://localhost/dentacita/`.
   - Inicia sesión con el usuario demo, **o**
   - Ve a "Crear una cuenta nueva" desde el login (`registro.php`) y empieza desde cero.

> **Carpeta de uploads:** la app guarda archivos del paciente y fotos de perfil en `dentacita/uploads/`. Asegúrate que el servidor web tenga permiso de escritura en esa carpeta.

---

## Estructura

```
dentacita/
├── README.md                       este archivo
├── instalar.sql                    schema multi-tenant + cuenta de servicio + datos seed (13 tablas)
├── conexion.php                    conexión MySQL + helpers de sesión + bootstrap de perfil
├── styles.css                      estilos compartidos
├── _nav.php                        sidebar reusable (6 enlaces)
│
├── index.php                       login (email + contraseña, autenticado contra `usuarios`)
├── registro.php                    alta de cuenta nueva (bcrypt + bootstrap de perfil)
├── logout.php
├── dashboard.php                   home: KPIs operativos + financieros + banners
│
├── agenda/                         calendario + citas + memorias personales
│   ├── _validaciones.php · _validaciones-memorias.php
│   ├── mostrar.php · pacientes.php
│   ├── guardar.php · actualizar.php · eliminar.php
│   ├── memorias-listar.php · memoria-guardar.php · memoria-eliminar.php
│   ├── index.php · agenda.js
│
├── pacientes/                      ficha completa del paciente con 4 pestañas
│   ├── _validaciones.php
│   ├── mostrar.php · guardar.php · actualizar.php · eliminar.php
│   ├── detalle.php (vista de ficha con pestañas: datos, citas, archivos, notas)
│   ├── archivos-listar.php · archivos-subir.php · archivos-renombrar.php
│   ├── archivos-mover.php · archivos-papelera.php · archivos-restaurar.php
│   ├── archivos-eliminar-permanente.php · archivos-vaciar-papelera.php
│   ├── carpetas-crear.php · carpetas-renombrar.php · carpetas-eliminar.php
│   ├── notas-guardar.php · notas-actualizar.php · notas-eliminar.php
│   ├── index.php · pacientes.js · archivos.js
│
├── inventario/                     productos + movimientos + bitácora
│   ├── _validaciones.php
│   ├── mostrar.php · movimientos.php
│   ├── guardar.php · actualizar.php · eliminar.php
│   ├── movimiento-guardar.php (genera transacciones + notificaciones)
│   ├── bitacora.php · bitacora-listar.php · bitacora.js
│   ├── index.php · inventario.js
│
├── finanzas/                       transacciones, reembolsos, cobros, reporte mensual
│   ├── _validaciones.php
│   ├── mostrar.php · cobrar-cita.php · reembolso.php · reporte.php
│   ├── guardar.php · actualizar.php · eliminar.php
│   ├── index.php · finanzas.js
│
├── perfil/                         2 pestañas: datos (nombre + foto) y tema visual
│   ├── _validaciones.php
│   ├── mostrar.php · guardar.php
│   ├── foto-subir.php (recibe imagen recortada en canvas)
│   ├── tema-actualizar.php
│   ├── onboarding-completar.php
│   ├── index.php · perfil.js
│
└── uploads/                        archivos subidos (creado en runtime)
    ├── .htaccess                   bloquea ejecución de scripts
    ├── pacientes/{id}/...
    └── perfil/{usuario}/...
```

---

## Las 13 tablas

| Tabla | Para qué sirve |
|---|---|
| `usuarios` | Cuentas web (email único, `password_hash` bcrypt). Es la raíz del aislamiento multi-tenant |
| `perfil_dentista` | Datos del dentista, foto, tema visual, flag de onboarding (1 fila por `usuario_id`) |
| `pacientes` | Datos del paciente: contacto, alergias, padecimientos, foto |
| `citas` | Citas con paciente, fecha, estado (programada/confirmada/completada/cancelada/no_asistio), precio |
| `carpetas_paciente` | Carpetas (path-based) para organizar archivos del paciente |
| `archivos_paciente` | Archivos del paciente con soft-delete (papelera) |
| `notas_paciente` | Bitácora clínica del paciente |
| `inventario_items` | Productos del consultorio (insumos, anestesia, productos a la venta) |
| `inventario_movimientos` | Entradas/salidas/ajustes que cambian el stock |
| `transacciones` | Ingresos y egresos. Soporta reembolsos (monto negativo) y anulación |
| `personal_memories` | Notas privadas del dentista en su agenda |
| `notificaciones` | Alertas internas (citas, stock bajo) |

Todas las tablas raíz (`pacientes`, `citas`, `inventario_items`, `transacciones`, `personal_memories`, `notificaciones`, `perfil_dentista`) cargan `usuario_id` con `FOREIGN KEY ... ON DELETE CASCADE` hacia `usuarios`. Las tablas hijas (`archivos_paciente`, `notas_paciente`, etc.) heredan el aislamiento a través de su FK al paciente o al item.

---

## Flujos cruzados (acciones que disparan otras automáticamente)

Estos flujos viven en endpoints PHP con transacciones atómicas (`begin_transaction` / `commit` / `rollback`).

| Acción del dentista | Efectos automáticos |
|---|---|
| **Crear cuenta nueva** (`registro.php`) | INSERT en `usuarios` + `bootstrapPerfilNuevoUsuario()` crea fila inicial en `perfil_dentista`, todo en una transacción |
| Marcar cita como **completada** con precio | INSERT en `transacciones` (ingreso) vinculado a la cita |
| **Eliminar cita** que tenía cobro | La transacción se preserva (FK ON DELETE SET NULL) |
| Crear **reembolso** sobre cita | INSERT en `transacciones` con `monto` negativo y categoría `Reembolso` |
| **Anular reembolso** | UPDATE `estado='anulada'`, registra fecha y motivo |
| Movimiento inventario tipo **compra** | INSERT egreso en finanzas con `inventario_item_id` y `inventario_movimiento_id` |
| Movimiento inventario tipo **venta** | INSERT ingreso en finanzas vinculado igual |
| Cualquier salida que deje stock ≤ mínimo | INSERT notificación `stock_bajo` |
| Cualquier salida que deje stock = 0 | INSERT notificación `stock_agotado` |
| **Eliminar producto** del inventario | INSERT notificación de auditoría; transacciones vinculadas se preservan |
| **Eliminar paciente** | Cascada: borra archivos, carpetas, notas. Citas y transacciones se preservan (SET NULL) |
| **Renombrar carpeta** del paciente | Cascada: actualiza la ruta en sub-carpetas y archivos |

---

## Autenticación y aislamiento

- **Login (`index.php`):** email + contraseña validados contra la tabla `usuarios` con `password_verify()`. Al éxito guarda `$_SESSION['usuario_id']` (más nombre y email).
- **Registro (`registro.php`):** valida email único, nombre 2-150 chars, password ≥8 chars; hashea con `password_hash(... PASSWORD_BCRYPT)`; INSERT en `usuarios` + `bootstrapPerfilNuevoUsuario()` dentro de una transacción.
- **Cuenta MySQL `dentista`@`localhost`:** es la **cuenta de servicio** que la app usa para conectarse. El usuario final nunca la teclea; vive en `conexion.php` (`obtenerConexion()`). Tiene permisos mínimos (SELECT/INSERT/UPDATE/DELETE).
- **Aislamiento multi-tenant:** `conexion.php` expone `getUsuarioId()`. Todos los endpoints lo usan en sus queries (`WHERE usuario_id = ?`). Las views llaman `exigirSesionVista()` y los AJAX llaman `exigirSesionAjax()` (devuelve `{"ok":false,"redirect":true}` si la sesión expiró).

---

## Notas para el desarrollador

- **Prepared statements:** todos los endpoints usan `mysqli` orientado a objetos con `bind_param`. Nunca interpolamos `$_POST` ni `$_SESSION` en SQL.
- **AJAX:** los endpoints de mutación devuelven JSON. Si la sesión expira, devuelven `{"ok":false,"redirect":true}` y el frontend redirige al login.
- **Validaciones:** cada módulo tiene su `_validaciones.php` con funciones puras que devuelven `null` si OK o un mensaje de error en español.
- **Seguridad de uploads:** `uploads/.htaccess` bloquea ejecución de cualquier script PHP/CGI dentro de la carpeta.
- **Tema visual:** se persiste en `perfil_dentista.variante_tema` y `_nav.php` lo cachea en `$_SESSION['variante_tema']` para evitar una query por request.
- **Sin frameworks:** el frontend es JS vanilla. Sin React, sin jQuery, sin Vue.

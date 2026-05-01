<?php
/*
 * REGISTRO DE CUENTA · v8
 * ------------------------
 * Crea una cuenta nueva en `usuarios`. Tras el INSERT exitoso,
 * inicia sesión automáticamente y redirige al dashboard.
 *
 * Reglas:
 *   - email único, formato válido, ≤100 chars
 *   - nombre obligatorio, 2-150 chars
 *   - contraseña ≥8 chars, ≤128 chars
 *   - bootstrapPerfilNuevoUsuario() crea la fila en perfil_dentista
 *   - Todo dentro de una transacción para que un fallo no deje cuenta huérfana
 */

require __DIR__ . '/conexion.php';

if (getUsuarioId() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$emailValor  = '';
$nombreValor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim((string)($_POST['email']     ?? ''));
    $nombre    = preg_replace('/\s+/', ' ', trim((string)($_POST['nombre']    ?? '')));
    $password  = (string)($_POST['password']  ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    $emailValor  = $email;
    $nombreValor = $nombre;

    if (!csrfValidar()) {
        $error = "Sesión expirada o token inválido. Recarga la página e inténtalo de nuevo.";
    } elseif ($email === '' || $nombre === '' || $password === '') {
        $error = "Todos los campos son obligatorios.";
    } elseif (mb_strlen($email) > 100) {
        $error = "El email es demasiado largo.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email no tiene un formato válido.";
    } elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 150) {
        $error = "El nombre debe tener entre 2 y 150 caracteres.";
    } elseif (!preg_match("/^[A-Za-zÁÉÍÓÚÑáéíóúñÜü .'\-]+$/u", $nombre)) {
        $error = "El nombre solo puede contener letras, espacios, apóstrofes y guiones.";
    } elseif (mb_strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (mb_strlen($password) > 128) {
        $error = "La contraseña es demasiado larga.";
    } elseif ($password !== $password2) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $conexion = obtenerConexion();
        if ($conexion === null) {
            $error = "No se pudo conectar al servidor. Inténtalo en un momento.";
        } else {
            // Email duplicado (chequeo previo amigable; el UNIQUE igual lo cubre).
            $check = @$conexion->prepare("SELECT 1 FROM usuarios WHERE email = ?");
            if ($check) {
                $check->bind_param("s", $email);
                @$check->execute();
                $existe = (bool)$check->get_result()->fetch_row();
                $check->close();
                if ($existe) {
                    $error = "Ya existe una cuenta con ese email.";
                }
            }

            if ($error === null) {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                @$conexion->begin_transaction();
                try {
                    $stmt = @$conexion->prepare(
                        "INSERT INTO usuarios (email, nombre, password_hash) VALUES (?, ?, ?)"
                    );
                    if (!$stmt) throw new Exception("prepare usuarios");
                    $stmt->bind_param("sss", $email, $nombre, $hash);
                    if (!@$stmt->execute()) {
                        $errno = $stmt->errno; $stmt->close();
                        if ($errno === 1062) throw new Exception("Ya existe una cuenta con ese email.");
                        throw new Exception("No se pudo crear la cuenta.");
                    }
                    $usuarioId = $stmt->insert_id;
                    $stmt->close();

                    $perfilId = bootstrapPerfilNuevoUsuario($conexion, $usuarioId, $nombre);
                    if ($perfilId === null) throw new Exception("No se pudo crear tu perfil.");

                    @$conexion->commit();

                    $_SESSION['usuario_id']     = (int)$usuarioId;
                    $_SESSION['usuario_nombre'] = $nombre;
                    $_SESSION['usuario_email']  = $email;

                    $conexion->close();
                    header('Location: dashboard.php');
                    exit;
                } catch (Exception $e) {
                    @$conexion->rollback();
                    $error = $e->getMessage();
                    if ($error === "prepare usuarios" || $error === "No se pudo crear la cuenta." || $error === "No se pudo crear tu perfil.") {
                        $error = "No se pudo crear la cuenta. Inténtalo de nuevo.";
                    }
                }
            }
            $conexion->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentaCita · Crear cuenta</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo @filemtime(__DIR__ . '/styles.css'); ?>">
</head>
<body>

<div class="login-layout">

    <div class="login-card">

        <div class="login-marca">
            <span class="login-marca-pastilla">DENTACITA</span>
        </div>

        <h1 class="login-titulo">Crear cuenta</h1>
        <p class="login-subtitulo">Tu propio espacio para tu consultorio.</p>

        <?php if ($error): ?>
            <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="registro.php" autocomplete="off">
            <?php echo csrfTokenInput(); ?>

            <div class="campo">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre"
                       placeholder="Dra. Camila Reyes"
                       maxlength="150"
                       value="<?php echo htmlspecialchars($nombreValor); ?>"
                       required autofocus>
            </div>

            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       placeholder="tu@correo.com"
                       maxlength="100"
                       value="<?php echo htmlspecialchars($emailValor); ?>"
                       required>
            </div>

            <div class="campo-grid-2">
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password"
                           placeholder="Mínimo 8 caracteres" minlength="8" maxlength="128" required>
                </div>
                <div class="campo">
                    <label for="password2">Confirmar contraseña</label>
                    <input type="password" id="password2" name="password2"
                           placeholder="Repítela" minlength="8" maxlength="128" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primario btn-bloque">
                Crear mi cuenta
            </button>

        </form>

        <div class="login-chips">
            <div class="login-chips-titulo">¿Ya tienes cuenta?</div>
            <a href="index.php" class="btn btn-secundario btn-bloque">
                Iniciar sesión
            </a>
        </div>

    </div>

</div>

</body>
</html>

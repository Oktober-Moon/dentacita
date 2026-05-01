<?php
/*
 * PANTALLA DE LOGIN · v6 (web)
 * ----------------------------
 * El usuario teclea su email y contraseña. La autenticación la
 * hace la tabla `usuarios` con bcrypt (`password_verify`).
 */

require __DIR__ . '/conexion.php';

if (getUsuarioId() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$emailValor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email']    ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $emailValor = $email;

    if ($email === '' || $password === '') {
        $error = "Email y contraseña son obligatorios.";
    } elseif (mb_strlen($email) > 100) {
        $error = "Email demasiado largo.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email no válido.";
    } else {
        $conexion = obtenerConexion();
        if ($conexion === null) {
            $error = "No se pudo conectar al servidor. Inténtalo en un momento.";
        } else {
            $stmt = @$conexion->prepare(
                "SELECT usuario_id, nombre, password_hash FROM usuarios WHERE email = ?"
            );
            if ($stmt) {
                $stmt->bind_param("s", $email);
                @$stmt->execute();
                $usuario = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($usuario && password_verify($password, $usuario['password_hash'])) {
                    $_SESSION['usuario_id']     = (int)$usuario['usuario_id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['usuario_email']  = $email;

                    // Marca último login (no bloqueante si falla)
                    $up = @$conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE usuario_id = ?");
                    if ($up) {
                        $up->bind_param("i", $_SESSION['usuario_id']);
                        @$up->execute();
                        $up->close();
                    }

                    $conexion->close();
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = "Email o contraseña incorrectos.";
                }
            } else {
                $error = "No se pudo procesar el inicio de sesión.";
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
    <title>DentaCita · Iniciar sesión</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo @filemtime(__DIR__ . '/styles.css'); ?>">
</head>
<body>

<div class="login-layout">

    <div class="login-card">

        <div class="login-marca">
            <span class="login-marca-pastilla">DENTACITA</span>
        </div>

        <h1 class="login-titulo">Bienvenido</h1>
        <p class="login-subtitulo">Tu agenda dental, en un solo lugar.</p>

        <?php if ($error): ?>
            <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" autocomplete="on">

            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       placeholder="tu@correo.com"
                       maxlength="100"
                       value="<?php echo htmlspecialchars($emailValor); ?>"
                       required autofocus>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primario btn-bloque">
                Iniciar sesión
            </button>

        </form>

        <div class="login-chips">
            <div class="login-chips-titulo">¿No tienes cuenta?</div>
            <a href="registro.php" class="btn btn-secundario btn-bloque">
                Crear una cuenta nueva
            </a>
            <p class="login-pista">
                Cuenta demo: <code class="mono">dentista@demo.com</code> / <code class="mono">dentista</code>
            </p>
        </div>

    </div>

</div>

</body>
</html>

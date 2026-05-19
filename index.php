<?php
/*
 * PANTALLA DE LOGIN · v8 (web)
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

    if (!csrfValidar()) {
        $error = "Sesión expirada o token inválido. Recarga la página e inténtalo de nuevo.";
    } elseif ($email === '' || $password === '') {
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
                    // Soft delete: si la cuenta fue marcada como eliminada por el
                    // dentista desde "Mi perfil", se rechaza el login. La fila de
                    // perfil_dentista se conserva por integridad histórica; solo
                    // un UPDATE manual en la BD reactiva la cuenta.
                    $cuentaEliminada = 0;
                    $stmtSoft = @$conexion->prepare(
                        "SELECT cuenta_eliminada FROM perfil_dentista WHERE usuario_id = ?"
                    );
                    if ($stmtSoft) {
                        $stmtSoft->bind_param("i", $usuario['usuario_id']);
                        @$stmtSoft->execute();
                        $rSoft = $stmtSoft->get_result()->fetch_assoc();
                        $stmtSoft->close();
                        $cuentaEliminada = $rSoft ? (int)$rSoft['cuenta_eliminada'] : 0;
                    }

                    if ($cuentaEliminada === 1) {
                        $error = "Esta cuenta fue marcada como eliminada y no puede iniciar sesión.";
                        // El close global de línea 89 cierra la conexión.
                    } else {
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
                    }
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
            <?php echo csrfTokenInput(); ?>

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
                       placeholder="••••••••" maxlength="128" required>
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
        </div>

    </div>

</div>

</body>
</html>

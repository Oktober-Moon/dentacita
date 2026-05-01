<?php
/*
 * CIERRE DE SESIÓN
 * ------------------
 * Borra todos los datos de sesión (usuario y contraseña MySQL)
 * y manda al login.
 */
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;

<?php
// logout.php - Sistema de cierre de sesión de usuarios

// Iniciar sesión para poder acceder a las variables de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destruir completamente la sesión del usuario
session_destroy();

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Redirigir al usuario a la página principal después del logout
header("Location: {$base_url}index.php");
exit;
?>
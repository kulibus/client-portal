<?php
// includes/auth.php - Funciones de autenticación y autorización

// Protección contra el reinicio de la sesión - iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verificar si el usuario está autenticado
 * @return bool true si el usuario está logueado, false en caso contrario
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Verificar si el usuario tiene permisos de administrador
 * @return bool true si es admin, false en caso contrario
 */
function isAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * Redirigir al login si el usuario no está autenticado
 * Función de seguridad para proteger páginas que requieren autenticación
 */
function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Redirigir al inicio si el usuario no es administrador
 * Función de seguridad para proteger páginas de administración
 */
function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header("Location: ../index.php");
        exit();
    }
}
?>
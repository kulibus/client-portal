<?php
// includes/csrf.php - Protección contra ataques CSRF (Cross-Site Request Forgery)

// Iniciar sesión si no está activa - necesario para almacenar tokens CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generar token CSRF único y seguro
 * Crea un token criptográficamente seguro de 64 caracteres hexadecimales
 * @return string Token CSRF generado o existente
 */
function generar_token_csrf() {
    // Generar nuevo token solo si no existe uno en la sesión
    if (empty($_SESSION['csrf_token'])) {
        // Generar 32 bytes aleatorios y convertir a hexadecimal (64 caracteres)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF contra el almacenado en sesión
 * Usa hash_equals() para comparación segura contra timing attacks
 * @param string $token Token a validar
 * @return bool true si el token es válido, false en caso contrario
 */
function validar_token_csrf($token) {
    // Verificar que existe token en sesión y comparar de forma segura
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
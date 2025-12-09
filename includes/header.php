<?php
// includes/header.php - Cabecera común del sitio web con navegación dinámica

// Asegurar que la sesión esté iniciada - necesario para verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// URL base del proyecto - ruta relativa para enlaces
// $base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Obtener nombre de la página actual para resaltar enlace activo
$current_page = basename($_SERVER['PHP_SELF']);

/**
 * Función para determinar si una página está activa en la navegación
 * Maneja páginas administrativas con rutas especiales
 * @param string $page_name Nombre de la página a verificar
 * @param string $current_page Página actual
 * @return bool true si la página está activa, false en caso contrario
 */
function isActive($page_name, $current_page) {
    // Para páginas administrativas, comparar solo el nombre del archivo
    if (strpos($page_name, 'admin/') === 0) {
        return $current_page === basename($page_name);
    }
    // Para páginas normales, comparación directa
    return $page_name === $current_page;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos básicos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/styles.css">
</head>
<body>
<!-- Barra de navegación principal - Bootstrap navbar responsivo -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <!-- Logo/marca del sitio -->
        <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">El Garage</a>
        
        <!-- Botón hamburguesa para dispositivos móviles -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Menú de navegación colapsable -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <!-- Enlaces de navegación públicos -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('index.php', $current_page) ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('noticias.php', $current_page) ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>noticias.php">Noticias</a>
                </li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Menú para usuarios autenticados -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('perfil.php', $current_page) ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>perfil.php">Perfil</a>
                    </li>
                    
                    <?php if ($_SESSION['rol'] === 'user'): ?>
                        <!-- Menú específico para usuarios normales -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('citaciones.php', $current_page) ? 'active' : ''; ?>" 
                               href="<?php echo $base_url; ?>citaciones.php">Citaciones</a>
                        </li>
                    <?php elseif ($_SESSION['rol'] === 'admin'): ?>
                        <!-- Menú específico para administradores - dropdown con opciones de administración -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (isActive('admin/usuarios-administracion.php', $current_page) || 
                                isActive('admin/citas-administracion.php', $current_page) || 
                                isActive('admin/noticias-administracion.php', $current_page)) ? 'active' : ''; ?>" 
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Administración
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo isActive('admin/usuarios-administracion.php', $current_page) ? 'active' : ''; ?>" 
                                       href="<?php echo $base_url; ?>admin/usuarios-administracion.php">Usuarios</a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActive('admin/citas-administracion.php', $current_page) ? 'active' : ''; ?>" 
                                       href="<?php echo $base_url; ?>admin/citas-administracion.php">Citas</a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActive('admin/noticias-administracion.php', $current_page) ? 'active' : ''; ?>" 
                                       href="<?php echo $base_url; ?>admin/noticias-administracion.php">Noticias</a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Enlace de cierre de sesión -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>logout.php">Cerrar Sesión</a>
                    </li>
                <?php else: ?>
                    <!-- Menú para usuarios no autenticados (invitados) -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('registro.php', $current_page) ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>registro.php">Registro</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('login.php', $current_page) ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>login.php">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Información del usuario autenticado - mostrada en la parte derecha -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="navbar-text ms-auto">
                    Hola, <?= htmlspecialchars($_SESSION['nombre']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>)
                </span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Contenedor principal del contenido - iniciado aquí, cerrado en footer.php -->
<div class="container mt-4">
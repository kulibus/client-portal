<?php
// index.php - Página principal del sistema de gestión de citas y noticias

// Iniciar sesión y cargar dependencias necesarias
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/database.php';

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Obtener las últimas 3 noticias con información del autor
$stmt = $pdo->query("
    SELECT n.*, ud.nombre, ud.apellidos 
    FROM noticias n 
    JOIN users_data ud ON n.idUser = ud.idUser 
    ORDER BY n.fecha DESC 
    LIMIT 3
");
$ultimas_noticias = $stmt->fetchAll();

// Obtener estadísticas del sistema para mostrar en el dashboard
$stats = [
    'total_usuarios' => $pdo->query("SELECT COUNT(*) FROM users_data")->fetchColumn(),
    'total_citas' => $pdo->query("SELECT COUNT(*) FROM citas")->fetchColumn(),
    'total_noticias' => $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn(),
    'citas_hoy' => $pdo->query("SELECT COUNT(*) FROM citas WHERE fecha_cita = CURDATE()")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos básicos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal de la página de inicio -->
    <div class="container mt-5">
        <!-- Sección main con información principal y estadísticas -->
        <div class="row align-items-center main-section mb-5">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">Bienvenido a Nuestro Taller</h1>
                <p class="lead">Sistema integral de gestión de citas</p>
                
                <!-- Estadísticas principales del sistema -->
                <div class="row mt-4">
                    <div class="col-6">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $stats['total_usuarios'] ?></h3>
                                <p class="text-muted">Usuarios</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $stats['total_citas'] ?></h3>
                                <p class="text-muted">Citas para el taller</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas adicionales del sistema -->
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h3 class="text-success"><?= $stats['total_noticias'] ?></h3>
                                <p class="text-muted">Noticias Publicadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $stats['citas_hoy'] ?></h3>
                                <p class="text-muted">Citas Hoy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Imagen principal del sistema -->
            <div class="col-md-6 text-center">
                <img src="<?= $base_url ?>css/foto_main.jpg" alt="Sistema de Gestión" class="img-fluid rounded">
            </div>
        </div>

        <!-- Sección de últimas noticias -->
        <div class="row mt-5">
            <div class="col-12">
                <h2 class="text-center mb-4">Últimas Noticias</h2>
                
                <?php if (count($ultimas_noticias) > 0): ?>
                    <div class="row">
                        <?php foreach ($ultimas_noticias as $noticia): ?>
                        <div class="col-md-4 mb-4">
                            <!-- Tarjeta de noticia con datos para el modal -->
                            <div class="card h-100 news-card" data-bs-toggle="modal" data-bs-target="#newsModal" 
                                data-title="<?= htmlspecialchars($noticia['titulo']) ?>"
                                data-image="<?= htmlspecialchars($noticia['imagen']) ?>"
                                data-text="<?= htmlspecialchars($noticia['texto']) ?>"
                                data-date="<?= date('d/m/Y', strtotime($noticia['fecha'])) ?>"
                                data-author="<?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos']) ?>">
                                
                                <!-- Imagen de la noticia o placeholder si no existe -->
                                <?php if (!empty($noticia['imagen'])): ?>
                                    <img src="<?= $base_url . htmlspecialchars($noticia['imagen']) ?>" class="card-img-top news-image" alt="<?= htmlspecialchars($noticia['titulo']) ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/800x450?text=Sin+Imagen" class="card-img-top news-image" alt="Sin imagen">
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($noticia['titulo']) ?></h5>
                                    <p class="card-text">
                                        <!-- Mostrar resumen del texto (primeros 100 caracteres) -->
                                        <?= mb_substr(strip_tags($noticia['texto']), 0, 100) ?>...
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Por: <?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos']) ?>
                                        </small>
                                        <span class="btn btn-sm btn-outline-primary">Leer más</span>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <small class="text-muted">
                                        Publicado: <?= date('d/m/Y', strtotime($noticia['fecha'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Enlace para ver todas las noticias -->
                    <div class="text-center mt-4">
                        <a href="<?= $base_url ?>noticias.php" class="btn btn-primary">Ver todas las noticias</a>
                    </div>
                <?php else: ?>
                    <!-- Mensaje cuando no hay noticias disponibles -->
                    <div class="alert alert-info text-center">
                        No hay noticias disponibles en este momento.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección de acciones rápidas para usuarios autenticados -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h2 class="text-center mb-4">Acciones Rápidas</h2>
                <div class="row">
                    <!-- Acción específica para usuarios normales -->
                    <?php if ($_SESSION['rol'] === 'user'): ?>
                    <div class="col-md-4 text-center mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5>Gestionar Citas</h5>
                                <p>Agenda o modifica tus citas</p>
                                <a href="<?= $base_url ?>citaciones.php" class="btn btn-primary">Ir a Citas</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Acción común para todos los usuarios autenticados -->
                    <div class="col-md-4 text-center mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5>Mi Perfil</h5>
                                <p>Actualiza tu información personal</p>
                                <a href="<?= $base_url ?>perfil.php" class="btn btn-primary">Ver Perfil</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acción específica para administradores -->
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <div class="col-md-4 text-center mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5>Panel Admin</h5>
                                <p>Gestión del sistema</p>
                                <a href="<?= $base_url ?>admin/dashboard.php" class="btn btn-danger">Panel Admin</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para visualización detallada de noticias -->
    <div class="modal fade news-modal" id="newsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newsModalTitle">Título de la noticia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- Imagen de la noticia en el modal -->
                    <div class="text-center p-3">
                        <img src="" class="news-modal-img" id="newsModalImage" alt="Imagen de la noticia">
                    </div>
                    <div class="news-modal-body">
                        <!-- Metadatos de la noticia (fecha y autor) -->
                        <div class="news-modal-meta">
                            Publicado el <span id="newsModalDate"></span> por <span id="newsModalAuthor"></span>
                        </div>
                        <!-- Contenido completo de la noticia -->
                        <div class="news-modal-text" id="newsModalText"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Incluir pie de página común del sitio -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts de Bootstrap para funcionalidad interactiva -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JavaScript para funcionalidad del modal de noticias
    document.addEventListener('DOMContentLoaded', function() {
        const newsModal = document.getElementById('newsModal');
        
        // Evento que se ejecuta cuando se abre el modal de noticias
        newsModal.addEventListener('show.bs.modal', function(event) {
            // Obtener datos de la tarjeta que activó el modal
            const card = event.relatedTarget;
            const title = card.getAttribute('data-title');
            const image = card.getAttribute('data-image');
            const text = card.getAttribute('data-text');
            const date = card.getAttribute('data-date');
            const author = card.getAttribute('data-author');
            
            // Poblar el modal con los datos de la noticia
            document.getElementById('newsModalTitle').textContent = title;
            document.getElementById('newsModalImage').src = image;
            document.getElementById('newsModalText').textContent = text;
            document.getElementById('newsModalDate').textContent = date;
            document.getElementById('newsModalAuthor').textContent = author;
        });
    });
    </script>
</body>
</html>
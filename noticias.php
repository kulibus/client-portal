<?php
// noticias.php - Sistema de visualización de noticias con paginación

// Iniciar sesión y cargar dependencias necesarias
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/database.php';

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Configuración de paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$noticias_por_pagina = 6;
$offset = ($pagina - 1) * $noticias_por_pagina;

// Obtener el total de noticias para calcular el número de páginas
$total_noticias = $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn();
$total_paginas = ceil($total_noticias / $noticias_por_pagina);

// Obtener noticias para la página actual con información del autor
$stmt = $pdo->prepare("
    SELECT n.*, ud.nombre, ud.apellidos 
    FROM noticias n 
    JOIN users_data ud ON n.idUser = ud.idUser 
    ORDER BY n.fecha DESC, n.idNoticia DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $noticias_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$noticias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos básicos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal de la página de noticias -->
    <div class="container mt-5">
        <h2 class="mb-4">Últimas Noticias</h2>
        
        <?php if (count($noticias) > 0): ?>
            <!-- Grid de noticias -->
            <div class="row">
                <?php foreach ($noticias as $noticia): ?>
                <div class="col-md-6 mb-4">
                    <!-- Tarjeta de noticia con datos para el modal -->
                    <div class="card news-card h-100" data-bs-toggle="modal" data-bs-target="#newsModal" 
                        data-title="<?= htmlspecialchars($noticia['titulo']) ?>"
                        data-image="<?= htmlspecialchars($noticia['imagen']) ?>"
                        data-text="<?= htmlspecialchars($noticia['texto']) ?>"
                        data-date="<?= date('d/m/Y', strtotime($noticia['fecha'])) ?>"
                        data-author="<?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos']) ?>">
                        
                        <!-- Contenedor de imagen con overlay -->
                        <div class="news-image-container">
                            <!-- Imagen de la noticia o placeholder si no existe -->
                            <img src="<?= !empty($noticia['imagen']) ? $base_url . htmlspecialchars($noticia['imagen']) : 'https://via.placeholder.com/800x450?text=Sin+Imagen' ?>" class="card-img-top news-image" alt="<?= htmlspecialchars($noticia['titulo']) ?>">
                            
                            <!-- Overlay con información de la noticia -->
                            <div class="news-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white"><?= htmlspecialchars($noticia['titulo']) ?></h5>
                                    <p class="text-light mb-0"><?= mb_substr(strip_tags($noticia['texto']), 0, 100) ?>...</p>
                                </div>
                            </div>
                            
                            <!-- Indicador de "Leer más" -->
                            <span class="news-read-more">Leer más</span>
                        </div>
                        
                        <!-- Contenido de la tarjeta -->
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($noticia['titulo']) ?></h5>
                            <p class="card-text"><?= mb_substr(strip_tags($noticia['texto']), 0, 150) ?>...</p>
                        </div>
                        <!-- Pie de la tarjeta con metadatos -->
                        <div class="card-footer">
                            <small class="text-muted">
                                Publicado el <?= date('d/m/Y', strtotime($noticia['fecha'])) ?> por 
                                <?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos']) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Sistema de paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <!-- Botón "Anterior" si no estamos en la primera página -->
                    <?php if ($pagina > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>">Anterior</a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Números de página -->
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <!-- Botón "Siguiente" si no estamos en la última página -->
                    <?php if ($pagina < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>">Siguiente</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Mensaje cuando no hay noticias disponibles -->
            <div class="alert alert-info">
                No hay noticias disponibles en este momento.
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
        
        // Agregar funcionalidad de scroll suave para mejorar UX
        const newsCards = document.querySelectorAll('.news-card');
        newsCards.forEach(card => {
            card.addEventListener('click', function() {
                // Scroll suave hacia el modal cuando se abre
                setTimeout(() => {
                    const modalContent = document.querySelector('.modal.show .modal-content');
                    if (modalContent) {
                        modalContent.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 300);
            });
        });
    });
    </script>
</body>
</html>
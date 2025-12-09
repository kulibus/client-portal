<?php
require '../includes/auth.php';
require '../includes/database.php';
require '../includes/csrf.php';

// Comprobar si la biblioteca GD está instalada - necesaria para el procesamiento de imágenes
// if (!extension_loaded('gd') || !function_exists('gd_info')) {
//     die("Error: La extensión GD no está instalada. Contacta con el administrador del sistema.");
// }

// Verificar permisos de administrador - solo admins pueden gestionar noticias
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$base_url = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';

$errors = [];
$success = false;

// Procesamiento de creación/edición/eliminación de noticias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación de token CSRF
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    
    // Crear una nueva noticia
    if (isset($_POST['crear_noticia'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $texto = trim($_POST['texto'] ?? '');
        
        // Validación
        if (empty($titulo)) $errors[] = "El título es obligatorio";
        if (empty($texto)) $errors[] = "El texto de la noticia es obligatorio";
        
        // Procesamiento de carga de imagen - validar, redimensionar y optimizar
        $imagen = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            // Definir extensiones permitidas para seguridad
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $nombre_archivo = $_FILES['imagen']['name'];
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            
            // Validar extensión del archivo
            if (in_array($extension, $extensiones_permitidas)) {
                // Guardar el archivo
                if (!is_dir('../uploads')) {
                    mkdir('../uploads', 0777, true);
                }
                $nombre_unico = uniqid() . '.' . $extension;
                $ruta_destino = '../uploads/' . $nombre_unico;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                    $imagen = 'uploads/' . $nombre_unico;
                } else {
                    $errors[] = "Error al guardar la imagen.";
                }
            } else {
                $errors[] = "Solo se permiten archivos JPG, JPEG, PNG, GIF y WEBP";
            }
        } else {
            $errors[] = "Debes seleccionar una imagen válida";
        }
        
        // Insertar nueva noticia en la base de datos
        if (empty($errors)) {
            try {
                // Insertar noticia con fecha actual y ID del usuario autenticado
                $stmt = $pdo->prepare("INSERT INTO noticias (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, CURDATE(), ?)");
                $stmt->execute([$titulo, $imagen, $texto, $_SESSION['user_id']]);
                $success = "Noticia creada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al crear la noticia: " . $e->getMessage();
                
                // Verificar si el error es por título duplicado (violación de unicidad)
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $errors[] = "Ya existe una noticia con ese título";
                }
            }
        }
    }
    
    // Edición de noticia existente - actualizar título, texto e imagen opcional
    if (isset($_POST['editar_noticia'])) {
        $idNoticia = $_POST['idNoticia'] ?? '';
        $titulo = trim($_POST['titulo'] ?? '');
        $texto = trim($_POST['texto'] ?? '');
        
        // Validación de campos obligatorios
        if (empty($titulo)) $errors[] = "El título es obligatorio";
        if (empty($texto)) $errors[] = "El texto de la noticia es obligatorio";
        
        // Obtener la imagen actual para mantenerla si no se sube una nueva
        $imagen = $_POST['imagen_actual'] ?? null;
        
        // Procesamiento de nueva imagen (opcional) - reemplazar imagen actual si se proporciona
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            // Validar extensión del archivo
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $nombre_archivo = $_FILES['imagen']['name'];
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            
            if (in_array($extension, $extensiones_permitidas)) {
                // Guardar el nuevo archivo
                if (!is_dir('../uploads')) {
                    mkdir('../uploads', 0777, true);
                }
                // Eliminar imagen anterior del servidor para liberar espacio
                if (!empty($imagen) && file_exists('../' . $imagen)) {
                    unlink('../' . $imagen);
                }
                $nombre_unico = uniqid() . '.' . $extension;
                $ruta_destino = '../uploads/' . $nombre_unico;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                    $imagen = 'uploads/' . $nombre_unico;
                } else {
                    $errors[] = "Error al guardar la imagen.";
                }
            } else {
                $errors[] = "Solo se permiten archivos JPG, JPEG, PNG, GIF y WEBP";
            }
        }
        
        // Actualizar noticia en la base de datos
        if (empty($errors)) {
            try {
                // Actualizar campos de la noticia existente
                $stmt = $pdo->prepare("UPDATE noticias SET titulo = ?, imagen = ?, texto = ? WHERE idNoticia = ?");
                $stmt->execute([$titulo, $imagen, $texto, $idNoticia]);
                $success = "Noticia actualizada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar la noticia: " . $e->getMessage();
            }
        }
    }
    
    // Eliminación de noticia - eliminar registro y archivo de imagen asociado
    if (isset($_POST['eliminar_noticia'])) {
        $idNoticia = $_POST['idNoticia'] ?? '';
        
        try {
            // Obtener información de la imagen para eliminarla del servidor
            $stmt = $pdo->prepare("SELECT imagen FROM noticias WHERE idNoticia = ?");
            $stmt->execute([$idNoticia]);
            $noticia = $stmt->fetch();
            
            // Eliminar archivo de imagen del servidor si existe
            if ($noticia && !empty($noticia['imagen']) && file_exists('../' . $noticia['imagen'])) {
                unlink('../' . $noticia['imagen']);
            }
            
            // Eliminar registro de la noticia de la base de datos
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE idNoticia = ?");
            $stmt->execute([$idNoticia]);
            $success = "Noticia eliminada correctamente";
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar la noticia: " . $e->getMessage();
        }
    }
}

// Obtener todas las noticias con información del autor - JOIN para mostrar nombre del creador
$stmt = $pdo->query("
    SELECT n.*, ud.nombre, ud.apellidos 
    FROM noticias n 
    JOIN users_data ud ON n.idUser = ud.idUser 
    ORDER BY n.fecha DESC
");
$noticias = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Noticias - El Garage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css"></head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <h2>Gestión de Noticias</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Forma de crear una nueva noticia -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Crear Nueva Noticia</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="imagen" class="form-label">Imagen</label>
                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*" required>
                        <div class="form-text">Formatos permitidos: JPG, JPEG, PNG, GIF</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="texto" class="form-label">Texto</label>
                        <textarea class="form-control" id="texto" name="texto" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" name="crear_noticia" class="btn btn-primary">Crear Noticia</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de noticias -->
        <h4>Lista de Noticias</h4>
        
        <?php if (count($noticias) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Imagen</th>
                            <th>Autor</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($noticias as $noticia): ?>
                        <tr>
                            <td><?= htmlspecialchars($noticia['titulo']) ?></td>
                            <td>
                                <?php if (!empty($noticia['imagen'])): ?>
                                    <img src="../<?= htmlspecialchars($noticia['imagen']) ?>" alt="Imagen de noticia" style="max-width: 100px; max-height: 100px;">
                                <?php else: ?>
                                    <span class="text-muted">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellidos']) ?></td>
                            <td><?= date('d/m/Y', strtotime($noticia['fecha'])) ?></td>
                            <td>
                                <!-- Botón de edición -->
                                <button type="button" class="btn btn-sm btn-primary me-1" 
                                        data-bs-toggle="modal" data-bs-target="#editarNoticiaModal"
                                        data-id="<?= $noticia['idNoticia'] ?>"
                                        data-titulo="<?= htmlspecialchars($noticia['titulo']) ?>"
                                        data-texto="<?= htmlspecialchars($noticia['texto']) ?>"
                                        data-imagen="<?= htmlspecialchars($noticia['imagen']) ?>">
                                    Editar
                                </button>
                                
                                <!-- Formulario de eliminación -->
                                <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar esta noticia?')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                    <input type="hidden" name="idNoticia" value="<?= $noticia['idNoticia'] ?>">
                                    <button type="submit" name="eliminar_noticia" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay noticias disponibles.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal de edición de la noticia -->
    <div class="modal fade" id="editarNoticiaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Noticia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="formEditarNoticia">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <input type="hidden" name="idNoticia" id="editarIdNoticia">
                        <input type="hidden" name="imagen_actual" id="imagenActual">
                        
                        <div class="mb-3">
                            <label for="editarTitulo" class="form-label">Título</label>
                            <input type="text" class="form-control" id="editarTitulo" name="titulo" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarImagen" class="form-label">Nueva Imagen (opcional)</label>
                            <input type="file" class="form-control" id="editarImagen" name="imagen" accept="image/*">
                            <div class="form-text">Dejar en blanco para mantener la imagen actual</div>
                            <div id="imagenPrevia" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarTexto" class="form-label">Texto</label>
                            <textarea class="form-control" id="editarTexto" name="texto" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_noticia" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JavaScript para funcionalidad del modal de edición de noticias
    document.addEventListener('DOMContentLoaded', function() {
        const editarNoticiaModal = document.getElementById('editarNoticiaModal');
        
        // Evento para llenar el modal de edición con datos de la noticia seleccionada
        editarNoticiaModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget; // Botón que activó el modal
            
            // Obtener datos de los atributos data-* del botón
            const idNoticia = button.getAttribute('data-id');
            const titulo = button.getAttribute('data-titulo');
            const texto = button.getAttribute('data-texto');
            const imagen = button.getAttribute('data-imagen');
            
            // Llenar campos del formulario con los datos obtenidos
            document.getElementById('editarIdNoticia').value = idNoticia;
            document.getElementById('editarTitulo').value = titulo;
            document.getElementById('editarTexto').value = texto;
            document.getElementById('imagenActual').value = imagen;
            
            // Mostrar vista previa de la imagen actual
            const imagenPrevia = document.getElementById('imagenPrevia');
            if (imagen) {
                imagenPrevia.innerHTML = `<img src="../${imagen}" alt="Imagen actual" style="max-width: 200px; max-height: 150px;" class="img-thumbnail">`;
            } else {
                imagenPrevia.innerHTML = '<p class="text-muted">No hay imagen actual</p>';
            }
        });
    });
    </script>
</body>
</html>
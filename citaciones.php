<?php
// citaciones.php - Sistema de gestión de citas para usuarios autenticados

// Iniciar sesión y cargar dependencias necesarias
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/database.php';
require 'includes/csrf.php';

// Verificar autenticación y autorización - solo usuarios normales pueden acceder
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'user') {
    header("Location: login.php");
    exit;
}

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Inicializar variables para manejo de mensajes
$errors = [];
$success = false;

// Procesar formularios solo si es método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF para prevenir ataques de falsificación de solicitudes
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }

    // Procesar creación de nueva cita
    if (isset($_POST['crear_cita'])) {
        // Obtener datos del formulario con valores por defecto seguros
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $motivo_cita = trim($_POST['motivo_cita'] ?? '');
        
        // Validación de campos obligatorios
        if (empty($fecha_cita)) $errors[] = "La fecha de la cita es obligatoria";
        if (empty($motivo_cita)) $errors[] = "El motivo de la cita es obligatorio";
        
        // Validación de fecha - no permitir citas en fechas pasadas
        if ($fecha_cita < date('Y-m-d')) {
            $errors[] = "No puedes agendar citas en fechas pasadas";
        }
        
        // Si no hay errores, insertar nueva cita
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO citas (idUser, fecha_cita, motivo_cita) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $fecha_cita, $motivo_cita]);
                $success = "Cita agendada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al agendar la cita: " . $e->getMessage();
            }
        }
    }

    // Procesar edición de cita existente
    if (isset($_POST['editar_cita'])) {
        // Obtener datos del formulario
        $idCita = $_POST['idCita'] ?? '';
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $motivo_cita = trim($_POST['motivo_cita'] ?? '');
        
        // Validación de campos obligatorios
        if (empty($fecha_cita)) $errors[] = "La fecha de la cita es obligatoria";
        if (empty($motivo_cita)) $errors[] = "El motivo de la cita es obligatorio";
        
        // Validación de fecha - no permitir citas en fechas pasadas
        if ($fecha_cita < date('Y-m-d')) {
            $errors[] = "No puedes agendar citas en fechas pasadas";
        }
        
        // Si no hay errores, proceder con la edición
        if (empty($errors)) {
            try {
                // Verificar que la cita pertenece al usuario y aún no ha pasado
                $stmt = $pdo->prepare("SELECT idCita FROM citas WHERE idCita = ? AND idUser = ? AND fecha_cita >= CURDATE()");
                $stmt->execute([$idCita, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    // Actualizar cita existente
                    $stmt = $pdo->prepare("UPDATE citas SET fecha_cita = ?, motivo_cita = ? WHERE idCita = ?");
                    $stmt->execute([$fecha_cita, $motivo_cita, $idCita]);
                    $success = "Cita actualizada correctamente";
                } else {
                    $errors[] = "No puedes editar esta cita. Puede que no exista, no te pertenezca o ya haya pasado.";
                }
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar la cita: " . $e->getMessage();
            }
        }
    }

    // Procesar cancelación de cita
    if (isset($_POST['cancelar_cita'])) {
        $idCita = $_POST['idCita'] ?? '';
        
        try {
            // Verificar que la cita pertenece al usuario antes de eliminar
            $stmt = $pdo->prepare("SELECT idCita FROM citas WHERE idCita = ? AND idUser = ?");
            $stmt->execute([$idCita, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Eliminar cita de la base de datos
                $stmt = $pdo->prepare("DELETE FROM citas WHERE idCita = ?");
                $stmt->execute([$idCita]);
                $success = "Cita cancelada correctamente";
            } else {
                $errors[] = "No tienes permiso para cancelar esta cita";
            }
        } catch (PDOException $e) {
            $errors[] = "Error al cancelar la cita: " . $e->getMessage();
        }
    }
}

// Obtener todas las citas del usuario ordenadas por fecha descendente
$stmt = $pdo->prepare("SELECT * FROM citas WHERE idUser = ? ORDER BY fecha_cita DESC");
$stmt->execute([$_SESSION['user_id']]);
$citas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos básicos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal del sistema de citas -->
    <div class="container mt-5">
        <h2>Mis Citas</h2>
        
        <!-- Mostrar mensaje de éxito si la operación fue exitosa -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Mostrar errores de validación si los hay -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Formulario para crear nueva cita -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Agendar Nueva Cita</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <!-- Token CSRF para protección contra ataques de falsificación -->
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Campo para seleccionar fecha de la cita -->
                            <div class="mb-3">
                                <label for="fecha_cita" class="form-label">Fecha de la Cita</label>
                                <input type="date" class="form-control" id="fecha_cita" name="fecha_cita" 
                                       min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Campo para describir el motivo de la cita -->
                            <div class="mb-3">
                                <label for="motivo_cita" class="form-label">Motivo de la Cita</label>
                                <textarea class="form-control" id="motivo_cita" name="motivo_cita" rows="3" required></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="crear_cita" class="btn btn-primary">Agendar Cita</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de citas programadas del usuario -->
        <div class="card">
            <div class="card-header">
                <h5>Mis Citas Programadas</h5>
            </div>
            <div class="card-body">
                <?php if (count($citas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($citas as $cita): 
                                    // Determinar si la cita es futura o ya pasó
                                    $es_futura = $cita['fecha_cita'] >= date('Y-m-d');
                                ?>
                                <tr>
                                    <td><?= $cita['fecha_cita'] ?></td>
                                    <td><?= htmlspecialchars($cita['motivo_cita']) ?></td>
                                    <td>
                                        <!-- Mostrar estado de la cita basado en la fecha -->
                                        <?php if (!$es_futura): ?>
                                            <span class="badge bg-secondary">Completada</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($es_futura): ?>
                                            <!-- Botón para editar cita futura -->
                                            <button type="button" class="btn btn-sm btn-primary me-1" 
                                                    data-bs-toggle="modal" data-bs-target="#editarCitaModal"
                                                    data-id="<?= $cita['idCita'] ?>"
                                                    data-fecha="<?= $cita['fecha_cita'] ?>"
                                                    data-motivo="<?= htmlspecialchars($cita['motivo_cita']) ?>">
                                                Editar
                                            </button>
                                            
                                            <!-- Formulario para cancelar cita con confirmación -->
                                            <form method="POST" onsubmit="return confirm('¿Estás seguro de cancelar esta cita?')" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                                <input type="hidden" name="idCita" value="<?= $cita['idCita'] ?>">
                                                <button type="submit" name="cancelar_cita" class="btn btn-sm btn-danger">Cancelar</button>
                                            </form>
                                        <?php else: ?>
                                            <!-- No se permiten acciones en citas pasadas -->
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- Mensaje cuando no hay citas programadas -->
                    <p class="text-muted">No tienes citas programadas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar citas existentes -->
    <div class="modal fade" id="editarCitaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formEditarCita">
                    <div class="modal-body">
                        <!-- Token CSRF y ID de cita ocultos -->
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <input type="hidden" name="idCita" id="editarIdCita">
                        
                        <!-- Campo para editar fecha de la cita -->
                        <div class="mb-3">
                            <label for="editarFechaCita" class="form-label">Fecha de la cita</label>
                            <input type="date" class="form-control" id="editarFechaCita" name="fecha_cita" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <!-- Campo para editar motivo de la cita -->
                        <div class="mb-3">
                            <label for="editarMotivoCita" class="form-label">Motivo de la cita</label>
                            <textarea class="form-control" id="editarMotivoCita" name="motivo_cita" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_cita" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Incluir pie de página común del sitio -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts de Bootstrap para funcionalidad interactiva -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JavaScript para funcionalidad del modal de edición de citas
    document.addEventListener('DOMContentLoaded', function() {
        const editarCitaModal = document.getElementById('editarCitaModal');
        
        // Evento que se ejecuta cuando se abre el modal de edición
        editarCitaModal.addEventListener('show.bs.modal', function(event) {
            // Obtener datos del botón que activó el modal
            const button = event.relatedTarget;
            const idCita = button.getAttribute('data-id');
            const fecha = button.getAttribute('data-fecha');
            const motivo = button.getAttribute('data-motivo');
            
            // Poblar los campos del formulario con los datos de la cita
            document.getElementById('editarIdCita').value = idCita;
            document.getElementById('editarFechaCita').value = fecha;
            document.getElementById('editarMotivoCita').value = motivo;
        });
    });
    </script>
</body>
</html>
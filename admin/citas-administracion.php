<?php
require '../includes/auth.php';
require '../includes/database.php';
require '../includes/csrf.php';

// Verificar permisos de administrador - solo admins pueden gestionar citas
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$base_url = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';

// Obtener todos los usuarios para las listas desplegables - ordenados alfabéticamente
$stmt = $pdo->query("SELECT idUser, nombre, apellidos FROM users_data ORDER BY nombre, apellidos");
$usuarios = $stmt->fetchAll();

$errors = [];
$success = false;

// Procesamiento de operaciones CRUD de citas - crear, editar y eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación de token CSRF para prevenir ataques de falsificación
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }

    // Crear nueva cita - validar datos y insertar en base de datos
    if (isset($_POST['crear_cita'])) {
        $idUser = $_POST['idUser'] ?? '';
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $motivo_cita = trim($_POST['motivo_cita'] ?? '');
        
        // Validación de campos obligatorios
        if (empty($idUser)) $errors[] = "Debe seleccionar un usuario";
        if (empty($fecha_cita)) $errors[] = "La fecha de la cita es obligatoria";
        if (empty($motivo_cita)) $errors[] = "El motivo de la cita es obligatorio";
        
        // Validación de fecha - no permitir citas en fechas pasadas
        if ($fecha_cita < date('Y-m-d')) {
            $errors[] = "No puedes agendar citas en fechas pasadas";
        }
        
        // Insertar nueva cita en la base de datos
        if (empty($errors)) {
            try {
                // Insertar cita con datos validados
                $stmt = $pdo->prepare("INSERT INTO citas (idUser, fecha_cita, motivo_cita) VALUES (?, ?, ?)");
                $stmt->execute([$idUser, $fecha_cita, $motivo_cita]);
                $success = "Cita creada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al crear la cita: " . $e->getMessage();
            }
        }
    }

    // Editar cita existente - actualizar datos de la cita
    if (isset($_POST['editar_cita'])) {
        $idCita = $_POST['idCita'] ?? '';
        $idUser = $_POST['idUser'] ?? '';
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $motivo_cita = trim($_POST['motivo_cita'] ?? '');
        
        // Validación de campos obligatorios
        if (empty($idUser)) $errors[] = "Debe seleccionar un usuario";
        if (empty($fecha_cita)) $errors[] = "La fecha de la cita es obligatoria";
        if (empty($motivo_cita)) $errors[] = "El motivo de la cita es obligatorio";
        
        // Actualizar cita en la base de datos
        if (empty($errors)) {
            try {
                // Actualizar campos de la cita existente
                $stmt = $pdo->prepare("UPDATE citas SET idUser = ?, fecha_cita = ?, motivo_cita = ? WHERE idCita = ?");
                $stmt->execute([$idUser, $fecha_cita, $motivo_cita, $idCita]);
                $success = "Cita actualizada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar la cita: " . $e->getMessage();
            }
        }
    }

    // Eliminar cita - eliminar registro de la base de datos
    if (isset($_POST['eliminar_cita'])) {
        $idCita = $_POST['idCita'] ?? '';
        
        try {
            // Eliminar cita de la base de datos
            $stmt = $pdo->prepare("DELETE FROM citas WHERE idCita = ?");
            $stmt->execute([$idCita]);
            $success = "Cita eliminada correctamente";
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar la cita: " . $e->getMessage();
        }
    }
}

// Búsqueda y filtrado - obtener parámetros de filtrado desde GET
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$filtro_fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Consulta base con JOIN para obtener información del usuario - mostrar datos completos
$sql = "
    SELECT c.*, ud.nombre, ud.apellidos, ud.email 
    FROM citas c 
    JOIN users_data ud ON c.idUser = ud.idUser 
";

// Condiciones de búsqueda - construir WHERE dinámicamente según filtros aplicados
$where = [];
$params = [];

// Búsqueda por texto - buscar en nombre, apellidos, email y motivo de la cita
if (!empty($busqueda)) {
    $where[] = "(ud.nombre LIKE ? OR ud.apellidos LIKE ? OR ud.email LIKE ? OR c.motivo_cita LIKE ?)";
    $search_term = "%$busqueda%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

// Filtro por usuario específico
if (!empty($filtro_usuario)) {
    $where[] = "c.idUser = ?";
    $params[] = $filtro_usuario;
}

// Filtro por fecha específica
if (!empty($filtro_fecha)) {
    $where[] = "c.fecha_cita = ?";
    $params[] = $filtro_fecha;
}

// Filtro por estado - pendientes (futuras) o completadas (pasadas)
if (!empty($filtro_estado)) {
    $today = date('Y-m-d');
    if ($filtro_estado === 'pendientes') {
        $where[] = "c.fecha_cita >= ?"; // Citas futuras o de hoy
        $params[] = $today;
    } elseif ($filtro_estado === 'completadas') {
        $where[] = "c.fecha_cita < ?"; // Citas pasadas
        $params[] = $today;
    }
}

// Añadir condiciones a la consulta - construir WHERE dinámicamente
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY c.fecha_cita DESC, c.idCita DESC"; // Ordenar por fecha descendente

// Obtener las citas - ejecutar consulta preparada
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Citas - El Garage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <h2>Gestión de Citas</h2>
        
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
        
        <!-- Formulario de búsqueda y filtrado -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Búsqueda y Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por nombre, email o motivo" value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="usuario">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['idUser'] ?>" <?= $filtro_usuario == $usuario['idUser'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="pendientes" <?= $filtro_estado == 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="completadas" <?= $filtro_estado == 'completadas' ? 'selected' : '' ?>>Completadas</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Buscar</button>
                        <?php if (!empty($busqueda) || !empty($filtro_usuario) || !empty($filtro_fecha) || !empty($filtro_estado)): ?>
                            <a href="citas-administracion.php" class="btn btn-secondary w-100 mt-2">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Forma de crear una nueva reunión -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Crear Nueva Cita</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="idUser" class="form-label">Usuario</label>
                                <select class="form-select" id="idUser" name="idUser" required>
                                    <option value="">Seleccionar usuario</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= $usuario['idUser'] ?>">
                                            <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fecha_cita" class="form-label">Fecha de la cita</label>
                                <input type="date" class="form-control" id="fecha_cita" name="fecha_cita" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="motivo_cita" class="form-label">Motivo de la cita</label>
                                <input type="text" class="form-control" id="motivo_cita" name="motivo_cita" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="crear_cita" class="btn btn-primary">Crear Cita</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de citas -->
        <h4>Lista de Citas</h4>
        
        <?php if (count($citas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellidos']) ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($cita['email']) ?></small>
                            </td>
                            <td><?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?></td>
                            <td><?= htmlspecialchars($cita['motivo_cita']) ?></td>
                            <td>
                                <?php if ($cita['fecha_cita'] < date('Y-m-d')): ?>
                                    <span class="badge bg-secondary">Completada</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Botón de edición -->
                                <button type="button" class="btn btn-sm btn-primary me-1" 
                                        data-bs-toggle="modal" data-bs-target="#editarCitaModal"
                                        data-id="<?= $cita['idCita'] ?>"
                                        data-user="<?= $cita['idUser'] ?>"
                                        data-fecha="<?= $cita['fecha_cita'] ?>"
                                        data-motivo="<?= htmlspecialchars($cita['motivo_cita']) ?>">
                                    Editar
                                </button>
                                
                                <!-- Formulario de eliminación -->
                                <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar esta cita?')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                    <input type="hidden" name="idCita" value="<?= $cita['idCita'] ?>">
                                    <button type="submit" name="eliminar_cita" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                Total de citas encontradas: <strong><?= count($citas) ?></strong>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No se encontraron citas con los filtros seleccionados.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal de edición de la cita -->
    <div class="modal fade" id="editarCitaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formEditarCita">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <input type="hidden" name="idCita" id="editarIdCita">
                        
                        <div class="mb-3">
                            <label for="editarIdUser" class="form-label">Usuario</label>
                            <select class="form-select" id="editarIdUser" name="idUser" required>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['idUser'] ?>">
                                        <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarFechaCita" class="form-label">Fecha de la cita</label>
                            <input type="date" class="form-control" id="editarFechaCita" name="fecha_cita" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarMotivoCita" class="form-label">Motivo de la cita</label>
                            <input type="text" class="form-control" id="editarMotivoCita" name="motivo_cita" required>
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
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JavaScript para funcionalidad del modal de edición de citas
    document.addEventListener('DOMContentLoaded', function() {
        const editarCitaModal = document.getElementById('editarCitaModal');
        
        // Evento para llenar el modal de edición con datos de la cita seleccionada
        editarCitaModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget; // Botón que activó el modal
            
            // Obtener datos de los atributos data-* del botón
            const idCita = button.getAttribute('data-id');
            const idUser = button.getAttribute('data-user');
            const fecha = button.getAttribute('data-fecha');
            const motivo = button.getAttribute('data-motivo');
            
            // Llenar campos del formulario con los datos obtenidos
            document.getElementById('editarIdCita').value = idCita;
            document.getElementById('editarIdUser').value = idUser;
            document.getElementById('editarFechaCita').value = fecha;
            document.getElementById('editarMotivoCita').value = motivo;
        });
    });
    </script>
</body>
</html>
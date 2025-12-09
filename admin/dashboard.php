<?php
require '../includes/auth.php';
require '../includes/database.php';

// Verificar permisos de administrador - solo admins pueden acceder al dashboard
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$base_url = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';

// Estadísticas para el panel de administración - obtener conteos para el dashboard
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users_data")->fetchColumn(), // Total de usuarios registrados
    'total_admins' => $pdo->query("SELECT COUNT(*) FROM users_login WHERE rol = 'admin'")->fetchColumn(), // Administradores del sistema
    'total_citas' => $pdo->query("SELECT COUNT(*) FROM citas")->fetchColumn(), // Total de citas programadas
    'citas_hoy' => $pdo->query("SELECT COUNT(*) FROM citas WHERE fecha_cita = CURDATE()")->fetchColumn(), // Citas programadas para hoy
    'total_noticias' => $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn() // Total de noticias publicadas
];

// Obtener últimas citas programadas - mostrar las 5 más recientes con información del usuario
$ultimas_citas = $pdo->query("
    SELECT c.*, ud.nombre, ud.apellidos 
    FROM citas c 
    JOIN users_data ud ON c.idUser = ud.idUser 
    ORDER BY c.fecha_cita DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - El Garage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <h2 class="mb-4">Panel de Administración</h2>
        
        <!-- Estadísticas del sistema - tarjetas con métricas principales -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary">
                    <div class="card-body text-center">
                        <h3><?= $stats['total_users'] ?></h3>
                        <p>Usuarios Totales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success">
                    <div class="card-body text-center">
                        <h3><?= $stats['total_admins'] ?></h3>
                        <p>Administradores</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info">
                    <div class="card-body text-center">
                        <h3><?= $stats['total_citas'] ?></h3>
                        <p>Citas Totales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning">
                    <div class="card-body text-center">
                        <h3><?= $stats['citas_hoy'] ?></h3>
                        <p>Citas Hoy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas - enlaces directos a las secciones de administración -->
        <div class="row mb-5">
            <div class="col-12">
                <h4>Acciones Rápidas</h4>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="usuarios-administracion.php" class="btn btn-primary">Gestionar Usuarios</a>
                    <a href="citas-administracion.php" class="btn btn-success">Gestionar Citas</a>
                    <a href="noticias-administracion.php" class="btn btn-info">Gestionar Noticias</a>
                    <a href="../perfil.php" class="btn btn-secondary">Mi Perfil</a>
                </div>
            </div>
        </div>

        <!-- Últimas citas programadas - tabla con las 5 citas más recientes -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Últimas Citas Programadas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($ultimas_citas) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Fecha</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_citas as $cita): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellidos']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?></td>
                                            <td><?= htmlspecialchars($cita['motivo_cita']) ?></td>
                                            <td>
                                                <?php 
                                                // Determinar estado de la cita basado en la fecha
                                                // Si la fecha es anterior a hoy = completada, si no = pendiente
                                                if ($cita['fecha_cita'] < date('Y-m-d')): ?>
                                                    <span class="badge bg-secondary">Completada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Pendiente</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hay citas programadas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
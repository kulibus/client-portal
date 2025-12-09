<?php
// cambiar-password.php - Página para cambio de contraseña de usuario autenticado

// Iniciar sesión y cargar dependencias necesarias
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/database.php';
require 'includes/csrf.php';

// Verificar que el usuario esté autenticado - redirigir si no hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Inicializar variables para manejo de mensajes
$errors = [];
$success = false;

// Procesar formulario de cambio de contraseña solo si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF para prevenir ataques de falsificación de solicitudes
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    
    // Obtener datos del formulario con valores por defecto seguros
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nuevo = $_POST['password_nuevo'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validación de campos obligatorios y reglas de negocio
    if (empty($password_actual)) {
        $errors[] = "La contraseña actual es obligatoria";
    }
    if (empty($password_nuevo)) {
        $errors[] = "La nueva contraseña es obligatoria";
    } elseif (strlen($password_nuevo) < 8) {
        $errors[] = "La nueva contraseña debe tener al menos 8 caracteres";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$/', $password_nuevo)) {
        $errors[] = "La nueva contraseña debe contener letras y números";
    }
    if ($password_nuevo !== $password_confirmar) {
        $errors[] = "Las contraseñas nuevas no coinciden";
    }
    
    // Si no hay errores de validación, proceder con el cambio
    if (empty($errors)) {
        try {
            // Verificar que la contraseña actual sea correcta
            $stmt = $pdo->prepare("SELECT password FROM users_login WHERE idUser = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            // Verificar contraseña actual usando hash seguro
            if ($user && password_verify($password_actual, $user['password'])) {
                // Generar hash seguro para la nueva contraseña
                $passwordHash = password_hash($password_nuevo, PASSWORD_DEFAULT);
                
                // Actualizar contraseña en la base de datos
                $stmt = $pdo->prepare("UPDATE users_login SET password = ? WHERE idUser = ?");
                $stmt->execute([$passwordHash, $_SESSION['user_id']]);
                
                $success = "Contraseña cambiada correctamente";
            } else {
                $errors[] = "La contraseña actual es incorrecta";
            }
        } catch (PDOException $e) {
            // Manejar errores de base de datos de forma segura
            $errors[] = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos básicos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal del formulario -->
    <div class="container mt-5">
        <h2>Cambiar Contraseña</h2>
        
        <!-- Mostrar mensaje de éxito si el cambio fue exitoso -->
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
        
        <!-- Formulario de cambio de contraseña -->
        <form method="POST">
            <!-- Token CSRF para protección contra ataques de falsificación -->
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <!-- Campo para contraseña actual -->
                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña Actual</label>
                        <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                    </div>
                    
                    <!-- Campo para nueva contraseña -->
                    <div class="mb-3">
                        <label for="password_nuevo" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password_nuevo" name="password_nuevo" required>
                    </div>
                    
                    <!-- Campo para confirmar nueva contraseña -->
                    <div class="mb-3">
                        <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required>
                    </div>
                    
                    <!-- Botones de acción -->
                    <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                    <a href="perfil.php" class="btn btn-secondary">Volver al Perfil</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Incluir pie de página común del sitio -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts de Bootstrap para funcionalidad interactiva -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
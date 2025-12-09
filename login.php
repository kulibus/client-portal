<?php
// login.php - Sistema de autenticación de usuarios

// Iniciar sesión y cargar dependencias necesarias
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/database.php';
require 'includes/csrf.php';

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Inicializar array para manejo de errores
$errors = [];

// Procesar formulario de login solo si es método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF para prevenir ataques de falsificación de solicitudes
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    
    // Obtener datos del formulario with valores por defecto seguros
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validación de campos obligatorios
    if (empty($usuario)) {
        $errors[] = "El nombre de usuario es obligatorio";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]{4,20}$/', $usuario)) {
        $errors[] = "El nombre de usuario solo puede contener letras, números, guiones y guiones bajos (4-20 caracteres)";
    }
    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres, incluir letras y números";
    }

    // Si no hay errores de validación, proceder con la autenticación
    if (empty($errors)) {
        try {
            // Buscar usuario en la base de datos con información completa
            $stmt = $pdo->prepare("
                SELECT ul.*, ud.nombre, ud.apellidos 
                FROM users_login ul 
                JOIN users_data ud ON ul.idUser = ud.idUser 
                WHERE ul.usuario = ?
            ");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            // Verificar credenciales usando hash seguro
            if ($user && password_verify($password, $user['password'])) {
                // Autenticación exitosa - establecer variables de sesión
                $_SESSION['user_id'] = $user['idUser'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['apellidos'] = $user['apellidos'];
                $_SESSION['rol'] = $user['rol'];
                
                // Redirigir según el rol del usuario
                if ($user['rol'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: perfil.php");
                }
                exit;
            } else {
                $errors[] = "Usuario o contraseña incorrectos";
            }
        } catch (PDOException $e) {
            // Manejar errores de base de datos de forma segura
            $errors[] = "Error en el sistema: " . $e->getMessage();
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
    <title>Iniciar Sesión - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal del formulario de login -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mb-4">Iniciar Sesión</h2>
                
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
                
                <!-- Formulario de autenticación -->
                <form method="POST">
                    <!-- Token CSRF para protección contra ataques de falsificación -->
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    
                    <!-- Campo para nombre de usuario -->
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required
                               value="<?= htmlspecialchars($usuario ?? '') ?>">
                    </div>
                    
                    <!-- Campo para contraseña -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <!-- Botón de envío y enlace de registro -->
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                    <p class="mt-3">¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Incluir pie de página común del sitio -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts de Bootstrap para funcionalidad interactiva -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// perfil.php - Sistema de gestión de perfil de usuario autenticado

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

// Obtener datos completos del usuario desde la base de datos
$stmt = $pdo->prepare("
    SELECT ud.*, ul.usuario, ul.rol 
    FROM users_data ud 
    JOIN users_login ul ON ud.idUser = ul.idUser 
    WHERE ud.idUser = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Inicializar variables para manejo de mensajes
$errors = [];
$success = false;

// Procesar formulario de edición solo si es método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF para prevenir ataques de falsificación de solicitudes
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    
    // Obtener datos del formulario con valores por defecto seguros
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $direccion = trim($_POST['direccion'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    
    // Validación de campos obligatorios
    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio";
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
        $errors[] = "El nombre solo puede contener letras y espacios";
    }

    if (empty($apellidos)) {
        $errors[] = "Los apellidos son obligatorios";
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellidos)) {
        $errors[] = "Los apellidos solo pueden contener letras y espacios";
    }

    if (empty($telefono)) {
        $errors[] = "El teléfono es obligatorio";
    } elseif (!preg_match('/^\d{7,15}$/', $telefono)) {
        $errors[] = "El teléfono solo puede contener números (7-15 dígitos)";
    }

    if (empty($email)) {
        $errors[] = "El email es obligatorio";
    } elseif (!preg_match('/^[\w\.-]+@[\w\.-]+\.\w{2,}$/', $email)) {
        $errors[] = "El email no es válido";
    }
    
    // Verificar unicidad del email (excluyendo al usuario actual)
    $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ? AND idUser != ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->rowCount() > 0) $errors[] = "Este email ya está registrado por otro usuario";
    
    // Si no hay errores de validación, proceder con la actualización
    if (empty($errors)) {
        try {
            // Actualizar datos del usuario en la tabla users_data
            $stmt = $pdo->prepare("
                UPDATE users_data 
                SET nombre = ?, apellidos = ?, email = ?, telefono = ?, 
                    fecha_nacimiento = ?, direccion = ?, sexo = ?
                WHERE idUser = ?
            ");
            $stmt->execute([
                $nombre, $apellidos, $email, $telefono, 
                $fecha_nacimiento, $direccion, $sexo, $_SESSION['user_id']
            ]);
            
            // Actualizar variables de sesión con los nuevos datos
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellidos'] = $apellidos;
            
            $success = "Perfil actualizado correctamente";
        } catch (PDOException $e) {
            // Manejar errores de base de datos de forma segura
            $errors[] = "Error al actualizar el perfil: " . $e->getMessage();
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
    <title>Mi Perfil - El Garage</title>
    
    <!-- Enlaces a frameworks y estilos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <!-- Incluir cabecera común del sitio -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Contenedor principal del formulario de perfil -->
    <div class="container mt-5">
        <h2>Mi Perfil</h2>
        
        <!-- Mostrar mensaje de éxito si la actualización fue exitosa -->
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
        
        <!-- Formulario de edición de perfil -->
        <form method="POST">
            <!-- Token CSRF para protección contra ataques de falsificación -->
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            
            <div class="row">
                <!-- Columna izquierda con información personal básica -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?= htmlspecialchars($user['nombre']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="apellidos" class="form-label">Apellidos</label>
                        <input type="text" class="form-control" id="apellidos" name="apellidos" 
                               value="<?= htmlspecialchars($user['apellidos']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                               value="<?= htmlspecialchars($user['telefono']) ?>" required>
                    </div>
                </div>
                
                <!-- Columna derecha con información adicional -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                               value="<?= htmlspecialchars($user['fecha_nacimiento']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="direccion" name="direccion" rows="3"><?= htmlspecialchars($user['direccion']) ?></textarea>
                    </div>
                    
                    <!-- Campo de selección de sexo con opciones múltiples -->
                    <div class="mb-3">
                        <label class="form-label">Sexo</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_hombre" value="Hombre" 
                                       <?= $user['sexo'] === 'Hombre' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_hombre">Hombre</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_mujer" value="Mujer"
                                       <?= $user['sexo'] === 'Mujer' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_mujer">Mujer</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_otro" value="Otro"
                                       <?= $user['sexo'] === 'Otro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_otro">Otro</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campo de usuario (solo lectura) -->
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['usuario']) ?>" disabled>
                        <small class="form-text text-muted">El nombre de usuario no se puede cambiar</small>
                    </div>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="<?= $base_url ?>cambiar-password.php" class="btn btn-outline-secondary">Cambiar Contraseña</a>
        </form>
    </div>
    
    <!-- Incluir pie de página común del sitio -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts de Bootstrap para funcionalidad interactiva -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
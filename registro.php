<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexión a la base de datos y protección CSRF
require 'includes/database.php';
require 'includes/csrf.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
$nombre = $apellidos = $email = $telefono = $direccion = $usuario = '';
$fecha_nacimiento = date('Y-m-d');
$sexo = 'Hombre';

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Procesamiento del formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación del token CSRF
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    // Obtener datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? date('Y-m-d');
    $direccion = trim($_POST['direccion'] ?? '');
    $sexo = $_POST['sexo'] ?? 'Hombre';
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validaciones de los campos del formulario
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

    if (empty($usuario)) {
        $errors[] = "Nombre de usuario obligatorio";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]{4,20}$/', $usuario)) {
        $errors[] = "El nombre de usuario solo puede contener letras, números, guiones y guiones bajos (4-20 caracteres)";
    }
    if (strlen($password) < 8) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $errors[] = "La contraseña debe contener letras y números";
    }
    if ($password !== $confirm_password) $errors[] = "Las contraseñas no coinciden";
    
    // Validación de la fecha de nacimiento (edad mínima y máxima)
    $min_age = date('Y-m-d', strtotime('-100 years'));
    $max_age = date('Y-m-d', strtotime('-13 years'));
    if ($fecha_nacimiento > $max_age) {
        $errors[] = "Debes tener al menos 13 años para registrarte.";
    } elseif ($fecha_nacimiento < $min_age) {
        $errors[] = "Fecha de nacimiento no válida. Por favor, verifica tu fecha de nacimiento.";
    }

    // Comprobación de unicidad de email y usuario
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) $errors[] = "Este email ya está registrado";

        $stmt = $pdo->prepare("SELECT idLogin FROM users_login WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->rowCount() > 0) $errors[] = "Este nombre de usuario ya existe";
    }

    // Registro del usuario en la base de datos
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insertar datos personales en users_data
            $stmt = $pdo->prepare("INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_nacimiento, direccion, sexo) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellidos, $email, $telefono, $fecha_nacimiento, $direccion, $sexo]);
            $idUser = $pdo->lastInsertId();
            
            // Insertar datos de acceso en users_login
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users_login (idUser, usuario, password, rol) 
                                   VALUES (?, ?, ?, 'user')");
            $stmt->execute([$idUser, $usuario, $passwordHash]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "¡Registro exitoso! Ahora puedes iniciar sesión";
            header("Location: login.php");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error en el registro: " . $e->getMessage();
        } finally {
            if (isset($consultaData)) $consultaData->closeCursor();
            if (isset($consultaLogin)) $consultaLogin->closeCursor();
            $pdo = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - El Garage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mb-4">Registro de Usuario</h2>
                
                <!-- Mostrar errores de validación si existen -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario de registro de usuario -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <!-- Campo: Nombre -->
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($nombre) ?>">
                    </div>
                    
                    <!-- Campo: Apellidos -->
                    <div class="mb-3">
                        <label for="apellidos" class="form-label">Apellidos</label>
                        <input type="text" class="form-control" id="apellidos" name="apellidos" required value="<?= htmlspecialchars($apellidos) ?>">
                    </div>
                    
                    <!-- Campo: Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <!-- Campo: Teléfono -->
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" required value="<?= htmlspecialchars($telefono) ?>">
                    </div>

                    <!-- Campo: Fecha de nacimiento -->
                    <div class="mb-3">
                        <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required value="<?= htmlspecialchars($fecha_nacimiento) ?>">
                    </div>

                    <!-- Campo: Dirección -->
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="direccion" name="direccion"><?= htmlspecialchars($direccion) ?></textarea>
                    </div>

                    <!-- Campo: Sexo -->
                    <div class="mb-3">
                        <label class="form-label">Sexo</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_hombre" value="Hombre" 
                                    <?= $sexo === 'Hombre' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_hombre">Hombre</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_mujer" value="Mujer"
                                    <?= $sexo === 'Mujer' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_mujer">Mujer</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_otro" value="Otro"
                                    <?= $sexo === 'Otro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sexo_otro">Otro</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campo: Nombre de usuario -->
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required value="<?= htmlspecialchars($usuario) ?>">
                    </div>
                    
                    <!-- Campo: Contraseña -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required value="<?= htmlspecialchars($password) ?>">
                    </div>
                    
                    <!-- Campo: Confirmar contraseña -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required value="<?= htmlspecialchars($confirm_password) ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Registrarse</button>
                    <p class="mt-3">¿Ya tienes cuenta? <a href="<?= $base_url ?>login.php">Inicia sesión aquí</a></p>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
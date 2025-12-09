<?php
require '../includes/auth.php';
require '../includes/database.php';
require '../includes/csrf.php';

// Comprobar si el usuario tiene permisos de administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$base_url = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';

// Procesamiento de acciones: creación, eliminación, cambio de rol, edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación de token CSRF
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        die('Error de validación CSRF');
    }
    
    // Crear un nuevo usuario
    if (isset($_POST['crear_usuario'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $direccion = trim($_POST['direccion'] ?? '');
        $sexo = $_POST['sexo'] ?? 'Hombre';
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $rol = $_POST['rol'] ?? 'user';
        
        // Validación
        // Validación de nombre: solo letras y espacios
        if (empty($nombre)) {
            $errors[] = "El nombre es obligatorio";
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
            $errors[] = "El nombre solo puede contener letras y espacios";
        }

        // Validación de apellidos: solo letras y espacios
        if (empty($apellidos)) {
            $errors[] = "Los apellidos son obligatorios";
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellidos)) {
            $errors[] = "Los apellidos solo pueden contener letras y espacios";
        }

        // Validación de teléfono: solo números, 7-15 dígitos
        if (empty($telefono)) {
            $errors[] = "El teléfono es obligatorio";
        } elseif (!preg_match('/^\d{7,15}$/', $telefono)) {
            $errors[] = "El teléfono solo puede contener números (7-15 dígitos)";
    }
        
        // Validación de la fecha de nacimiento - verificar que el usuario tenga entre 13 y 100 años
        $min_age = date('Y-m-d', strtotime('-100 years')); // Fecha límite superior (100 años atrás)
        $max_age = date('Y-m-d', strtotime('-13 years'));  // Fecha límite inferior (13 años atrás)
        if ($fecha_nacimiento > $max_age) {
            $errors[] = "El usuario debe tener al menos 13 años";
        } elseif ($fecha_nacimiento < $min_age) {
            $errors[] = "Fecha de nacimiento no válida";
        }

        if (empty($usuario)) {
            $errors[] = "El nombre de usuario es obligatorio";
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]{4,20}$/', $usuario)) {
            $errors[] = "El nombre de usuario solo puede contener letras, números, guiones y guiones bajos (4-20 caracteres)";
        }
        
        if (empty($password)) {
            $errors[] = "La contraseña es obligatoria";
        } elseif (strlen($password) < 8) {
            $errors[] = "La contraseña debe tener al menos 8 caracteres";
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $errors[] = "La contraseña debe contener letras y números";
        }

        
        if (empty($email)) {
            $errors[] = "El email es obligatorio";
        } elseif (!preg_match('/^[\w\.-]+@[\w\.-]+\.\w{2,}$/', $email)) {
            $errors[] = "El email no es válido";
        }

        // Validación de unicidad - verificar que email y usuario no existan en la base de datos
        if (empty($errors)) {
            // Verificar si el email ya está registrado
            $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) $errors[] = "Este email ya está registrado";
            
            // Verificar si el nombre de usuario ya existe
            $stmt = $pdo->prepare("SELECT idLogin FROM users_login WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->rowCount() > 0) $errors[] = "Este nombre de usuario ya existe";
        }
        
        // Crear un usuario - transacción para insertar en ambas tablas
        if (empty($errors)) {
            try {
                $pdo->beginTransaction(); // Iniciar transacción para garantizar consistencia
                
                // Inserción en users_data - tabla con información personal del usuario
                $stmt = $pdo->prepare("INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_nacimiento, direccion, sexo) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $apellidos, $email, $telefono, $fecha_nacimiento, $direccion, $sexo]);
                $idUser = $pdo->lastInsertId(); // Obtener el ID del usuario recién creado
                
                // Inserción en users_login - tabla con credenciales y rol del usuario
                $passwordHash = password_hash($password, PASSWORD_DEFAULT); // Hash seguro de la contraseña
                $stmt = $pdo->prepare("INSERT INTO users_login (idUser, usuario, password, rol) 
                                       VALUES (?, ?, ?, ?)");
                $stmt->execute([$idUser, $usuario, $passwordHash, $rol]);
                
                $pdo->commit(); // Confirmar transacción si todo salió bien
                $success = "Usuario creado correctamente";
                
            } catch (PDOException $e) {
                $pdo->rollBack(); // Revertir transacción en caso de error
                $errors[] = "Error al crear el usuario: " . $e->getMessage();
            }
        }
    }
    
    // Cambiar rol de usuario - actualizar el rol en la tabla users_login
    if (isset($_POST['cambiar_rol'])) {
        $idUser = $_POST['idUser'];
        $nuevo_rol = $_POST['nuevo_rol'];
        
        if (empty($idUser) || empty($nuevo_rol)) {
            $errors[] = "Datos incompletos para cambiar el rol";
        } elseif (!in_array($nuevo_rol, ['user', 'admin'])) {
            $errors[] = "Rol no válido";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE users_login SET rol = ? WHERE idUser = ?");
                $stmt->execute([$nuevo_rol, $idUser]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Rol actualizado correctamente";
                } else {
                    $errors[] = "No se pudo actualizar el rol. El usuario puede no existir.";
                }

                header("Location: usuarios-administracion.php");
                exit;
                } catch (PDOException $e) {
                $errors[] = "Error al actualizar el rol: " . $e->getMessage();
            }
        }
    }
    
    // Editar información de usuario - actualizar datos personales en users_data
    if (isset($_POST['editar_usuario'])) {
        $idUser = $_POST['idUser'];
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $direccion = trim($_POST['direccion'] ?? '');
        $sexo = $_POST['sexo'] ?? '';
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validación de nombre: solo letras y espacios
        if (empty($nombre)) {
            $errors[] = "El nombre es obligatorio";
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
            $errors[] = "El nombre solo puede contener letras y espacios";
        }

        // Validación de apellidos: solo letras y espacios
        if (empty($apellidos)) {
            $errors[] = "Los apellidos son obligatorios";
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellidos)) {
            $errors[] = "Los apellidos solo pueden contener letras y espacios";
        }

        // Validación de teléfono: solo números, 7-15 dígitos
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

        // Validación de unicidad de email (excepto el usuario actual)
        $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ? AND idUser != ?");
        $stmt->execute([$email, $idUser]);
        if ($stmt->rowCount() > 0) $errors[] = "Este email ya está registrado por otro usuario";
        
        // Validación de usuario: solo letras, números, guiones y guiones bajos (4-20 caracteres)
        if (empty($usuario)) {
            $errors[] = "El nombre de usuario es obligatorio";
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]{4,20}$/', $usuario)) {
            $errors[] = "El nombre de usuario solo puede contener letras, números, guiones y guiones bajos (4-20 caracteres)";
        }
        
        // Validación de contraseña: mínimo 8 caracteres, debe contener letras y números
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "La contraseña debe tener al menos 8 caracteres";
            } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                $errors[] = "La contraseña debe contener letras y números";
            }
        }

        if (empty($errors)) {
            try {
                // Actualizar datos del usuario
                $stmt = $pdo->prepare("
                    UPDATE users_data 
                    SET nombre = ?, apellidos = ?, email = ?, telefono = ?, 
                        fecha_nacimiento = ?, direccion = ?, sexo = ?
                    WHERE idUser = ?
                ");
                $stmt->execute([
                    $nombre, $apellidos, $email, $telefono, 
                    $fecha_nacimiento, $direccion, $sexo, $idUser
                ]);

                // Actualizar usuario
                $stmt = $pdo->prepare("UPDATE users_login SET usuario = ? WHERE idUser = ?");
                $stmt->execute([$usuario, $idUser]);

                // Actualizar contraseña (si se proporciona)
                if (!empty($password)) {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users_login SET password = ? WHERE idUser = ?");
                    $stmt->execute([$passwordHash, $idUser]);
                }

                $success = "Usuario actualizado correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar el usuario: " . $e->getMessage();
            }
        }
    }
    
    // Eliminar usuario - eliminar registro de la base de datos
    if (isset($_POST['eliminar_usuario'])) {
        $idUser = $_POST['idUser'];
        
        try {
            // No se permite eliminar a uno mismo - medida de seguridad para evitar auto-eliminación
            if ($idUser == $_SESSION['user_id']) {
                $errors[] = "No puedes eliminarte a ti mismo";
            } else {
                // Eliminamos al usuario (la eliminación en cascada debe eliminar la entrada en users_login)
                // Se elimina de users_data primero, la FK en cascada eliminará el registro de users_login
                $stmt = $pdo->prepare("DELETE FROM users_data WHERE idUser = ?");
                $stmt->execute([$idUser]);
                $success = "Usuario eliminado correctamente";
            }
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar el usuario: " . $e->getMessage();
        }
    }
}

// Búsqueda y filtrado - obtener parámetros de búsqueda desde GET
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['rol']) ? $_GET['rol'] : '';
$filtro_sexo = isset($_GET['sexo']) ? $_GET['sexo'] : '';

// Consulta base - JOIN entre users_data y users_login para obtener información completa
$sql = "
    SELECT ud.*, ul.usuario, ul.rol 
    FROM users_data ud 
    JOIN users_login ul ON ud.idUser = ul.idUser 
";

// Condiciones de búsqueda - construir WHERE dinámicamente según filtros aplicados
$where = [];
$params = [];

// Búsqueda por texto - buscar en nombre, apellidos, email, usuario y teléfono
if (!empty($busqueda)) {
    $where[] = "(ud.nombre LIKE ? OR ud.apellidos LIKE ? OR ud.email LIKE ? OR ul.usuario LIKE ? OR ud.telefono LIKE ?)";
    $search_term = "%$busqueda%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

// Filtro por rol - admin o user
if (!empty($filtro_rol)) {
    $where[] = "ul.rol = ?";
    $params[] = $filtro_rol;
}

// Filtro por sexo - Hombre, Mujer u Otro
if (!empty($filtro_sexo)) {
    $where[] = "ud.sexo = ?";
    $params[] = $filtro_sexo;
}

// Añadir condiciones a la consulta - construir WHERE dinámicamente
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ud.nombre, ud.apellidos"; // Ordenar alfabéticamente por nombre

// Obtener los usuarios - ejecutar consulta preparada
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Estadísticas de los usuarios - obtener conteos para el dashboard
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users_data")->fetchColumn(), // Total de usuarios registrados
    'admins' => $pdo->query("SELECT COUNT(*) FROM users_login WHERE rol = 'admin'")->fetchColumn(), // Administradores
    'users' => $pdo->query("SELECT COUNT(*) FROM users_login WHERE rol = 'user'")->fetchColumn(), // Usuarios normales
    'hombres' => $pdo->query("SELECT COUNT(*) FROM users_data WHERE sexo = 'Hombre'")->fetchColumn(), // Usuarios masculinos
    'mujeres' => $pdo->query("SELECT COUNT(*) FROM users_data WHERE sexo = 'Mujer'")->fetchColumn(), // Usuarios femeninos
    'otros' => $pdo->query("SELECT COUNT(*) FROM users_data WHERE sexo = 'Otro' OR sexo IS NULL")->fetchColumn() // Otros géneros o no especificado
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - El Garage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>css/styles.css"></head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <h2>Gestión de Usuarios</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
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
        
        <!-- Estadísticas de los usuarios -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['total'] ?></h5>
                        <p class="card-text">Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['admins'] ?></h5>
                        <p class="card-text">Administradores</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['users'] ?></h5>
                        <p class="card-text">Usuarios</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['hombres'] ?></h5>
                        <p class="card-text">Hombres</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['mujeres'] ?></h5>
                        <p class="card-text">Mujeres</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card text-center bg-secondary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['otros'] ?></h5>
                        <p class="card-text">Otros</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Forma de búsqueda y filtrado -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Búsqueda y Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar por nombre, apellidos, email, usuario o teléfono" value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="rol">
                            <option value="">Todos los roles</option>
                            <option value="user" <?= $filtro_rol == 'user' ? 'selected' : '' ?>>Usuario</option>
                            <option value="admin" <?= $filtro_rol == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="sexo">
                            <option value="">Todos los géneros</option>
                            <option value="Hombre" <?= $filtro_sexo == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                            <option value="Mujer" <?= $filtro_sexo == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                            <option value="Otro" <?= $filtro_sexo == 'Otro' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Buscar</button>
                    </div>
                    
                    <div class="col-md-2">
                        <?php if (!empty($busqueda) || !empty($filtro_rol) || !empty($filtro_sexo)): ?>
                            <a href="usuarios-administracion.php" class="btn btn-secondary w-100">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Forma de creación de un nuevo usuario -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Crear Nuevo Usuario</h5>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
                    <i class="fas fa-plus"></i> Nuevo Usuario
                </button>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) > 0): ?>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= $usuario['idUser'] ?></td>
                            <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                            <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                            <td><?= htmlspecialchars($usuario['apellidos']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td><?= htmlspecialchars($usuario['telefono']) ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                    <input type="hidden" name="idUser" value="<?= $usuario['idUser'] ?>">
                                    <input type="hidden" name="cambiar_rol" value="1">
                                    <select name="nuevo_rol" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="user" <?= $usuario['rol'] === 'user' ? 'selected' : '' ?>>Usuario</option>
                                        <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <!-- Botón de edición -->
                                <button type="button" class="btn btn-sm btn-primary me-1" 
                                    data-bs-toggle="modal" data-bs-target="#editarUsuarioModal"
                                    data-id="<?= $usuario['idUser'] ?>"
                                    data-nombre="<?= htmlspecialchars($usuario['nombre']) ?>"
                                    data-apellidos="<?= htmlspecialchars($usuario['apellidos']) ?>"
                                    data-email="<?= htmlspecialchars($usuario['email']) ?>"
                                    data-telefono="<?= htmlspecialchars($usuario['telefono']) ?>"
                                    data-fechanacimiento="<?= $usuario['fecha_nacimiento'] ?>"
                                    data-direccion="<?= htmlspecialchars($usuario['direccion']) ?>"
                                    data-sexo="<?= htmlspecialchars($usuario['sexo']) ?>"
                                    data-usuario="<?= htmlspecialchars($usuario['usuario']) ?>"
                                >
                                    Editar
                                </button>
                                
                                <!-- Formulario de eliminación -->
                                <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar este usuario? Se eliminarán todas sus citas y noticias.')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                    <input type="hidden" name="idUser" value="<?= $usuario['idUser'] ?>">
                                    <button type="submit" name="eliminar_usuario" class="btn btn-sm btn-danger" <?= $usuario['idUser'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron usuarios</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($usuarios) > 0): ?>
        <div class="alert alert-info mt-3">
            Total de usuarios encontrados: <strong><?= count($usuarios) ?></strong>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal de creación de usuario -->
    <div class="modal fade" id="crearUsuarioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formCrearUsuario">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearNombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="crearNombre" name="nombre" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearApellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="crearApellidos" name="apellidos" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearEmail" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="crearEmail" name="email" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearTelefono" class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" id="crearTelefono" name="telefono" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearFechaNacimiento" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="crearFechaNacimiento" name="fecha_nacimiento">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sexo</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="crearSexoHombre" value="Hombre" checked>
                                            <label class="form-check-label" for="crearSexoHombre">Hombre</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="crearSexoMujer" value="Mujer">
                                            <label class="form-check-label" for="crearSexoMujer">Mujer</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="crearSexoOtro" value="Otro">
                                            <label class="form-check-label" for="crearSexoOtro">Otro</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="crearDireccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="crearDireccion" name="direccion" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearUsuario" class="form-label">Nombre de Usuario *</label>
                                    <input type="text" class="form-control" id="crearUsuario" name="usuario" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearRol" class="form-label">Rol *</label>
                                    <select class="form-select" id="crearRol" name="rol" required>
                                        <option value="user">Usuario</option>
                                        <option value="admin">Administrador</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearPassword" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="crearPassword" name="password" required minlength="6">
                                    <div class="form-text">Mínimo 6 caracteres</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crearConfirmPassword" class="form-label">Confirmar Contraseña *</label>
                                    <input type="password" class="form-control" id="crearConfirmPassword" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_usuario" class="btn btn-success">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de edición de usuario -->
    <div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formEditarUsuario">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <input type="hidden" name="idUser" id="editarIdUser">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarNombre" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="editarNombre" name="nombre" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarApellidos" class="form-label">Apellidos</label>
                                    <input type="text" class="form-control" id="editarApellidos" name="apellidos" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="editarEmail" name="email" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarTelefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="editarTelefono" name="telefono" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarFechaNacimiento" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="editarFechaNacimiento" name="fecha_nacimiento">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sexo</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="editarSexoHombre" value="Hombre">
                                            <label class="form-check-label" for="editarSexoHombre">Hombre</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="editarSexoMujer" value="Mujer">
                                            <label class="form-check-label" for="editarSexoMujer">Mujer</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="sexo" id="editarSexoOtro" value="Otro">
                                            <label class="form-check-label" for="editarSexoOtro">Otro</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarDireccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="editarDireccion" name="direccion" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarUsuario" class="form-label">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="editarUsuario" name="usuario" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editarPassword" class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                                    <input type="password" class="form-control" id="editarPassword" name="password">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_usuario" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JavaScript para funcionalidad de modales y validación de formularios
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar modal de edición de usuario
        const editarUsuarioModal = document.getElementById('editarUsuarioModal');
        
        // Evento para llenar el modal de edición con datos del usuario seleccionado
        editarUsuarioModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget; // Botón que activó el modal
            
            // Llenar campos del formulario con datos de los atributos data-*
            document.getElementById('editarIdUser').value = button.getAttribute('data-id');
            document.getElementById('editarNombre').value = button.getAttribute('data-nombre');
            document.getElementById('editarApellidos').value = button.getAttribute('data-apellidos');
            document.getElementById('editarEmail').value = button.getAttribute('data-email');
            document.getElementById('editarTelefono').value = button.getAttribute('data-telefono');
            document.getElementById('editarFechaNacimiento').value = button.getAttribute('data-fechanacimiento');
            document.getElementById('editarDireccion').value = button.getAttribute('data-direccion');
            document.getElementById('editarUsuario').value = button.getAttribute('data-usuario');
            
            // Establecer el sexo seleccionado
            const sexo = button.getAttribute('data-sexo');
            if (sexo) {
                document.querySelector(`input[name="sexo"][value="${sexo}"]`).checked = true;
            }
        });
        
        // Validación del formulario de creación de usuario
        const formCrearUsuario = document.getElementById('formCrearUsuario');
        const crearPassword = document.getElementById('crearPassword');
        const crearConfirmPassword = document.getElementById('crearConfirmPassword');
        
        // Función para validar coincidencia de contraseñas en tiempo real
        function validatePasswordMatch() {
            if (crearPassword.value !== crearConfirmPassword.value) {
                crearConfirmPassword.setCustomValidity('Las contraseñas no coinciden');
            } else {
                crearConfirmPassword.setCustomValidity('');
            }
        }
        
        // Eventos para validación en tiempo real
        crearPassword.addEventListener('input', validatePasswordMatch);
        crearConfirmPassword.addEventListener('input', validatePasswordMatch);
        
        // Limpiar formulario al cerrar el modal de creación
        const crearUsuarioModal = document.getElementById('crearUsuarioModal');
        crearUsuarioModal.addEventListener('hidden.bs.modal', function() {
            formCrearUsuario.reset();
            crearConfirmPassword.setCustomValidity('');
        });
        
        // Validación final al enviar el formulario
        formCrearUsuario.addEventListener('submit', function(event) {
            validatePasswordMatch();
            
            if (!formCrearUsuario.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            formCrearUsuario.classList.add('was-validated');
        });
    });
    </script>
</body>
</html>
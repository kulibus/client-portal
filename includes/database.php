<?php
// includes/database.php - Configuración y conexión a la base de datos MySQL

// Configuración de conexión a la base de datos
$host = '127.0.0.1';        // Host de la base de datos (localhost)
$db   = 'trabajo_final';    // Nombre de la base de datos
$user = 'root';             // Usuario de la base de datos
$pass = '';                 // Contraseña vacía para desarrollo local
$port = 3307;               // Puerto personalizado de MySQL
$charset = 'utf8mb4';       // Charset para soporte completo de Unicode

// Construir DSN (Data Source Name) para la conexión PDO
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// Opciones de configuración para PDO - configuraciones de seguridad y rendimiento
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Lanzar excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Retornar arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES => false,                // Usar prepared statements nativos
];

// Establecer conexión a la base de datos con manejo de errores
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "Conexión exitosa a la base de datos!";
} catch (\PDOException $e) {
    // Terminar ejecución si no se puede conectar a la base de datos
    die("Error de conexión: " . $e->getMessage());
}
?>

<?php


define('ENVIRONMENT', 'production');


define('DB_HOST', 'localhost');
define('DB_USER', 'u618126694_admin');
define('DB_PASS', '909909Remi_');
define('DB_NAME', 'u618126694_reminiscencia');
define('BASE_URL', '/');
define('ADMIN_URL', '/admin/');

// Configuración de errores
if (ENVIRONMENT === 'production') {
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 'On');
    ini_set('log_errors', 'Off');
    error_reporting(E_ALL);
}

// Conexión a la base de datos
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // En producción, no mostrar detalles del error al usuario
    if (ENVIRONMENT === 'production') {
        error_log('Database connection error: ' . $e->getMessage());
        die('Ha ocurrido un error inesperado. Por favor, inténtalo de nuevo más tarde.');
    } else {
        die("Error de conexión: " . $e->getMessage());
    }
}

ini_set('memory_limit', '128M');
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '5M');

// Iniciar sesión
session_start();

?>


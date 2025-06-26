<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');

header('Content-Type: application/json');

// Configuración del archivo de log
$logFile = __DIR__ . '/logs/database_errors.log';

// Función para registrar errores
function log_error($message, $logFile) {
    // Asegurar que el directorio de logs existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0700, true);
    }
    
    // Formatear mensaje con marca de tiempo
    $formattedMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    
    // Intentar escribir en el archivo de log
    if (error_log($formattedMessage, 3, $logFile) === false) {
        // Fallback al log del sistema si falla escritura en archivo
        error_log("Fallback: " . $message);
    }
}

if (isset($_GET['fecha'])) {
    $fecha = $_GET['fecha'];
    
    // Validar formato de fecha (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        echo json_encode([]);
        exit;
    }
    
    // Validar que sea una fecha real
    $date = DateTime::createFromFormat('Y-m-d', $fecha);
    $dateErrors = DateTime::getLastErrors();
    
    if (!$date || $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0) {
        echo json_encode([]);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT hora FROM llamadas WHERE fecha = :fecha");
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        
        $horasOcupadas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo json_encode($horasOcupadas);
    } catch(PDOException $e) {
        // Registrar error en log con detalles
        $errorMsg = "PDOException: [{$e->getCode()}] {$e->getMessage()}";
        log_error($errorMsg, $logFile);
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
<?php
// Archivo: api_photo_handler.php
// API para manejo de fotos desde el frontend

// Configuración de seguridad
session_start();

// Configurar directorio de logs
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
ini_set('error_log', $log_dir . '/error.log');

// Solo permitir dominios específicos
$allowedOrigins = [
    'https://reminiscencia-photography.com',
    'https://www.reminiscencia-photography.com'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    // Bloquear acceso no autorizado
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso prohibido');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/photo_upload_functions.php');

/**
 * Respuesta JSON segura
 */
function jsonResponse($success, $message, $data = null) {
    // Mensajes genéricos para errores
    if (!$success) {
        $message = 'Error en la operación. Por favor intente nuevamente.';
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Validar token CSRF robusto
 */
function validateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        error_log('Intento de acceso sin token CSRF: ' . $_SERVER['REMOTE_ADDR']);
        return false;
    }
    
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';
    
    if (empty($headerToken) && empty($postToken)) {
        error_log('Solicitud sin token CSRF: ' . $_SERVER['REMOTE_ADDR']);
        return false;
    }
    
    $token = !empty($headerToken) ? $headerToken : $postToken;
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        error_log('Token CSRF inválido: ' . $_SERVER['REMOTE_ADDR']);
        return false;
    }
    
    return true;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Intento de acceso con método no permitido: ' . $_SERVER['REQUEST_METHOD']);
    jsonResponse(false, 'Método no permitido');
}

// Validar CSRF
if (!validateCSRF()) {
    jsonResponse(false, 'Token de seguridad inválido');
}

// Obtener acción
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'upload_photo':
        handlePhotoUpload();
        break;
    
    case 'validate_registration':
        handleRegistrationValidation();
        break;
    
    case 'check_email':
        handleEmailCheck();
        break;
    
    default:
        error_log('Acción no válida en API: ' . $action);
        jsonResponse(false, 'Acción no válida');
}

/**
 * Maneja la subida de fotos con validación robusta
 */
function handlePhotoUpload() {
    global $conn;
    
    if (!isset($_FILES['photo'])) {
        jsonResponse(false, 'No se recibió ningún archivo');
    }
    
    $type = $_POST['type'] ?? 'cliente';
    
    // Validar type contra lista blanca
    $allowedTypes = ['cliente', 'colaborador'];
    if (!in_array($type, $allowedTypes)) {
        error_log('Tipo no válido en subida de foto: ' . $type);
        jsonResponse(false, 'Tipo no válido');
    }
    
    // Configurar límites de recursos
    ini_set('memory_limit', '256M');
    set_time_limit(60);
    
    // Procesar subida de foto con validación mejorada
    $prefix = $type . '_';
    $upload_result = processPhotoUpload($_FILES['photo'], $prefix);
    
    if (!$upload_result['success']) {
        error_log('Error en subida de foto: ' . $upload_result['message']);
        jsonResponse(false, 'Error al procesar la imagen');
    }
    
    // Generar miniatura con validación de seguridad
    $source_path = __DIR__ . '/' . $upload_result['file_path'];
    $thumb_path = __DIR__ . '/uploads/fotos_id/thumbs/' . basename($upload_result['file_path']);
    
    // Validar que la ruta esté dentro del directorio permitido
    $allowed_path = realpath(__DIR__ . '/uploads');
    if (strpos(realpath($source_path), $allowed_path) !== 0) {
        error_log('Intento de acceso a ruta no permitida: ' . $source_path);
        jsonResponse(false, 'Ruta no válida');
    }
    
    $thumb_result = generateThumbnail($source_path, $thumb_path);
    
    if (!$thumb_result['success']) {
        error_log('Error al generar miniatura: ' . $thumb_result['message']);
        jsonResponse(false, 'Error al procesar la imagen');
    }
    
    jsonResponse(true, 'Foto procesada exitosamente', [
        'file_path' => $upload_result['file_path'],
        'thumb_path' => 'uploads/fotos_id/thumbs/' . basename($upload_result['file_path'])
    ]);
}

/**
 * Valida datos de registro
 */
function handleRegistrationValidation() {
    $type = $_POST['type'] ?? 'cliente';
    
    // Validar type contra lista blanca
    $allowedTypes = ['cliente', 'colaborador'];
    if (!in_array($type, $allowedTypes)) {
        error_log('Tipo no válido en validación: ' . $type);
        jsonResponse(false, 'Tipo no válido');
    }
    
    $data = $_POST;
    
    $validation = validateRegistrationData($data, $type);
    
    if (!$validation['valid']) {
        error_log('Datos de registro inválidos: ' . json_encode($validation['errors']));
        jsonResponse(false, 'Datos inválidos', [
            'errors' => $validation['errors']
        ]);
    }
    
    jsonResponse(true, 'Datos válidos', [
        'validated_data' => $validation['data']
    ]);
}

/**
 * Verifica si un email ya está registrado
 */
function handleEmailCheck() {
    global $conn;
    
    $email = trim($_POST['email'] ?? '');
    $type = $_POST['type'] ?? 'both';
    
    if (empty($email)) {
        jsonResponse(false, 'Email requerido');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Email no válido');
    }
    
    try {
        $exists = emailExists($conn, $email, $type);
    } catch (Exception $e) {
        error_log('Error en verificación de email: ' . $e->getMessage());
        jsonResponse(false, 'Error en la verificación');
    }
    
    jsonResponse(true, $exists ? 'Email ya registrado' : 'Email disponible', [
        'exists' => $exists,
        'email' => $email
    ]);
}
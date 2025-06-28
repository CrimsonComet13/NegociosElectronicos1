<?php
// Archivo: photo_upload_functions.php
// Funciones auxiliares para el manejo de fotos de identificación

// Configurar directorio de logs
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
ini_set('error_log', $log_dir . '/error.log');

// Directorio seguro para uploads (fuera del root público)
$secure_upload_dir = __DIR__ . '/../private_uploads/fotos_id/';

/**
 * Procesa la subida de una foto de identificación con medidas de seguridad mejoradas
 * @param array $file - Archivo $_FILES['foto_id']
 * @param string $prefix - Prefijo para el nombre del archivo (ej: 'cliente_', 'colaborador_')
 * @return array - ['success' => bool, 'message' => string, 'file_path' => string|null]
 */
function processPhotoUpload($file, $prefix = '') {
    global $secure_upload_dir;
    
    $result = [
        'success' => false,
        'message' => '',
        'file_path' => null
    ];
    
    // Verificar si se subió un archivo
    if (empty($file['name'])) {
        $result['message'] = 'No se seleccionó ningún archivo';
        return $result;
    }
    
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Error al subir el archivo: ' . getUploadErrorMessage($file['error']);
        return $result;
    }
    
    // Crear directorio seguro si no existe
    if (!is_dir($secure_upload_dir)) {
        if (!mkdir($secure_upload_dir, 0700, true)) {
            $result['message'] = 'Error al crear el directorio de subida';
            return $result;
        }
        
        // Añadir archivo .htaccess para prevenir ejecución
        file_put_contents($secure_upload_dir . '.htaccess', "deny from all\nphp_flag engine off");
    }
    
    // Validar tipo MIME real del archivo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!array_key_exists($mime, $allowed_mimes)) {
        $result['message'] = 'Formato de imagen no válido. Use JPG, PNG, GIF o WebP';
        error_log('Intento de subida con tipo MIME inválido: ' . $mime);
        return $result;
    }
    
    // Obtener extensión correcta del tipo MIME
    $file_extension = $allowed_mimes[$mime];
    
    // Validar extensión contra tipo MIME real
    $client_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($client_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $result['message'] = 'Extensión de archivo no permitida';
        error_log('Intento de subida con extensión no permitida: ' . $client_extension);
        return $result;
    }
    
    // Escanear contenido en busca de malware
    if (!scanFileForMalware($file['tmp_name'])) {
        $result['message'] = 'El archivo contiene contenido sospechoso';
        error_log('Archivo sospechoso detectado: ' . $file['name']);
        return $result;
    }
    
    // Validar tamaño del archivo (5MB máximo)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        $result['message'] = 'La foto es demasiado grande (máximo 5MB)';
        return $result;
    }
    
    // Validar que sea una imagen real
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $result['message'] = 'El archivo no es una imagen válida';
        return $result;
    }
    
    // Generar nombre único para el archivo
    $filename = uniqid('foto_id_' . $prefix) . '.' . $file_extension;
    $file_path = $secure_upload_dir . $filename;
    
    // Mover archivo a destino final
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Optimizar imagen si es necesario
        optimizeImage($file_path, $image_info[2]);
        
        $result['success'] = true;
        $result['message'] = 'Foto subida exitosamente';
        $result['file_path'] = 'private_uploads/fotos_id/' . $filename; // Ruta relativa para BD
    } else {
        $result['message'] = 'Error al mover el archivo al directorio de destino';
        error_log('Error moviendo archivo: ' . $file['tmp_name'] . ' a ' . $file_path);
    }
    
    return $result;
}

/**
 * Escanea un archivo en busca de contenido malicioso
 * @param string $file_path - Ruta al archivo temporal
 * @return bool - true si el archivo es seguro
 */
function scanFileForMalware($file_path) {
    // Lista de cadenas peligrosas (simplificada)
    $dangerous_patterns = [
        '<?php',
        'eval(',
        'base64_decode',
        'system(',
        'shell_exec(',
        'passthru(',
        'exec(',
        'popen(',
        'proc_open',
        '`',
    ];
    
    $content = file_get_contents($file_path);
    
    // Si no podemos leer el contenido, rechazar
    if ($content === false) {
        return false;
    }
    
    // Buscar patrones peligrosos
    foreach ($dangerous_patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            return false;
        }
    }
    
    // Verificar que sea una imagen válida (segunda capa)
    if (!@getimagesize($file_path)) {
        return false;
    }
    
    return true;
}

/**
 * Optimiza una imagen redimensionándola con límites de recursos
 * @param string $file_path - Ruta del archivo
 * @param int $image_type - Tipo de imagen (IMAGETYPE_*)
 */
function optimizeImage($file_path, $image_type) {
    // Establecer límites de recursos
    ini_set('memory_limit', '256M');
    set_time_limit(30); // 30 segundos máximo
    
    $max_width = 800;
    $max_height = 800;
    $quality = 85;
    
    // Obtener dimensiones actuales
    list($width, $height) = @getimagesize($file_path);
    if (!$width || !$height) return;
    
    // Si la imagen es pequeña, no hacer nada
    if ($width <= $max_width && $height <= $max_height) {
        return;
    }
    
    // Calcular nuevas dimensiones manteniendo proporción
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = intval($width * $ratio);
    $new_height = intval($height * $ratio);
    
    // Crear imagen desde archivo con verificación
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($file_path);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($file_path);
            break;
        case IMAGETYPE_WEBP:
            $source = @imagecreatefromwebp($file_path);
            break;
        default:
            return; // Tipo no soportado
    }
    
    if (!$source) return;
    
    // Crear nueva imagen redimensionada
    $destination = @imagecreatetruecolor($new_width, $new_height);
    if (!$destination) {
        imagedestroy($source);
        return;
    }
    
    // Preservar transparencia para PNG y GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        @imagealphablending($destination, false);
        @imagesavealpha($destination, true);
        $transparent = @imagecolorallocatealpha($destination, 255, 255, 255, 127);
        @imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Redimensionar
    @imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Guardar imagen optimizada
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            @imagejpeg($destination, $file_path, $quality);
            break;
        case IMAGETYPE_PNG:
            @imagepng($destination, $file_path, 9);
            break;
        case IMAGETYPE_GIF:
            @imagegif($destination, $file_path);
            break;
        case IMAGETYPE_WEBP:
            @imagewebp($destination, $file_path, $quality);
            break;
    }
    
    // Liberar memoria
    @imagedestroy($source);
    @imagedestroy($destination);
}

/**
 * Obtiene el mensaje de error para códigos de error de subida
 * @param int $error_code - Código de error de $_FILES['file']['error']
 * @return string - Mensaje de error
 */
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El archivo es demasiado grande (límite del servidor)';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo es demasiado grande (límite del formulario)';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió parcialmente';
        case UPLOAD_ERR_NO_FILE:
            return 'No se subió ningún archivo';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Falta el directorio temporal';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Error al escribir el archivo en disco';
        case UPLOAD_ERR_EXTENSION:
            return 'Subida detenida por extensión';
        default:
            return 'Error desconocido';
    }
}

/**
 * Valida y sanitiza los datos del formulario de registro
 * @param array $data - Datos del formulario
 * @param string $type - Tipo de registro ('cliente' o 'colaborador')
 * @return array - ['valid' => bool, 'errors' => array, 'data' => array]
 */
function validateRegistrationData($data, $type = 'cliente') {
    $result = [
        'valid' => true,
        'errors' => [],
        'data' => []
    ];
    
    // Validar nombre (100 caracteres máximo)
    $nombre = trim($data['nombre'] ?? '');
    if (empty($nombre)) {
        $result['errors'][] = 'El nombre es obligatorio';
        $result['valid'] = false;
    } elseif (strlen($nombre) > 100) {
        $result['errors'][] = 'El nombre no puede exceder los 100 caracteres';
        $result['valid'] = false;
    } else {
        $result['data']['nombre'] = $nombre;
    }
    
    // Validar email
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $result['errors'][] = 'El email es obligatorio';
        $result['valid'] = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['errors'][] = 'El email no tiene un formato válido';
        $result['valid'] = false;
    } else {
        $result['data']['email'] = strtolower($email);
    }
    
    // Validar teléfono (20 caracteres máximo, formato específico)
    $telefono = trim($data['telefono'] ?? '');
    if (!empty($telefono)) {
        if (strlen($telefono) > 20) {
            $result['errors'][] = 'El teléfono no puede exceder los 20 caracteres';
            $result['valid'] = false;
        } elseif (!preg_match('/^[0-9+\-()\s]{8,20}$/', $telefono)) {
            $result['errors'][] = 'Formato de teléfono inválido. Solo números, guiones, espacios y paréntesis';
            $result['valid'] = false;
        } else {
            $result['data']['telefono'] = $telefono;
        }
    } else {
        $result['data']['telefono'] = null;
    }
    
    // Validar contraseña
    $password = $data['password'] ?? '';
    if (empty($password)) {
        $result['errors'][] = 'La contraseña es obligatoria';
        $result['valid'] = false;
    } elseif (strlen($password) < 8) {
        $result['errors'][] = 'La contraseña debe tener al menos 8 caracteres';
        $result['valid'] = false;
    } else {
        $result['data']['password'] = $password;
    }
    
    // Validar confirmación de contraseña
    $password_confirm = $data['password_confirm'] ?? '';
    if (empty($password_confirm)) {
        $result['errors'][] = 'La confirmación de contraseña es obligatoria';
        $result['valid'] = false;
    } elseif ($password !== $password_confirm) {
        $result['errors'][] = 'Las contraseñas no coinciden';
        $result['valid'] = false;
    }
    
    // Validaciones específicas para colaboradores
    if ($type === 'colaborador') {
        $tipo = $data['tipo_colaborador'] ?? '';
        if (!empty($tipo)) {
            $tipos_validos = ['fotografo', 'videografo', 'auxiliar'];
            if (!in_array($tipo, $tipos_validos)) {
                $result['errors'][] = 'Tipo de colaborador no válido';
                $result['valid'] = false;
            } else {
                $result['data']['tipo_colaborador'] = $tipo;
            }
        }
        
        $rango = $data['rango_colaborador'] ?? '';
        if (!empty($rango)) {
            $rangos_validos = ['I', 'II', 'III'];
            if (!in_array($rango, $rangos_validos)) {
                $result['errors'][] = 'Rango de colaborador no válido';
                $result['valid'] = false;
            } else {
                $result['data']['rango_colaborador'] = $rango;
            }
        }
    }
    
    return $result;
}

/**
 * Verifica si un email ya existe en la base de datos
 * @param PDO $conn - Conexión a la base de datos
 * @param string $email - Email a verificar
 * @param string $type - Tipo de verificación ('cliente', 'colaborador', 'both')
 * @return bool - true si el email ya existe
 */
function emailExists($conn, $email, $type = 'both') {
    try {
        $queries = [];
        
        if ($type === 'cliente' || $type === 'both') {
            $queries[] = "SELECT id FROM usuarios WHERE email = :email AND rol = 'cliente'";
            $queries[] = "SELECT id FROM prospectos_clientes WHERE email = :email";
        }
        
        if ($type === 'colaborador' || $type === 'both') {
            $queries[] = "SELECT id FROM usuarios WHERE email = :email AND rol = 'colaborador'";
            $queries[] = "SELECT id FROM prospectos_colaboradores WHERE email = :email";
        }
        
        if (empty($queries)) {
            return false;
        }
        
        $sql = implode(' UNION ', $queries);
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error verificando email: " . $e->getMessage());
        return true; // En caso de error, asumir que existe para evitar duplicados
    }
}

/**
 * Registra un prospecto cliente en la base de datos
 * @param PDO $conn - Conexión a la base de datos
 * @param array $data - Datos validados del cliente
 * @param string $foto_path - Ruta de la foto de identificación
 * @return array - ['success' => bool, 'message' => string, 'id' => int|null]
 */
function registerClientProspect($conn, $data, $foto_path) {
    $result = [
        'success' => false,
        'message' => '',
        'id' => null
    ];
    
    try {
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO prospectos_clientes (nombre, email, telefono, password, foto_path) 
                VALUES (:nombre, :email, :telefono, :password, :foto_path)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':telefono', $data['telefono']);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindParam(':foto_path', $foto_path);
        
        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = 'Solicitud de registro enviada exitosamente';
            $result['id'] = $conn->lastInsertId();
        } else {
            $result['message'] = 'Error al registrar el prospecto cliente';
        }
    } catch (PDOException $e) {
        error_log("Error registrando prospecto cliente: " . $e->getMessage());
        $result['message'] = 'Error en el sistema. Por favor intente más tarde.';
    }
    
    return $result;
}

/**
 * Registra un prospecto colaborador en la base de datos
 * @param PDO $conn - Conexión a la base de datos
 * @param array $data - Datos validados del colaborador
 * @param string $foto_path - Ruta de la foto de identificación
 * @return array - ['success' => bool, 'message' => string, 'id' => int|null]
 */
function registerCollaboratorProspect($conn, $data, $foto_path) {
    $result = [
        'success' => false,
        'message' => '',
        'id' => null
    ];
    
    try {
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO prospectos_colaboradores (nombre, email, telefono, password, tipo_colaborador, rango_colaborador, foto_path) 
                VALUES (:nombre, :email, :telefono, :password, :tipo_colaborador, :rango_colaborador, :foto_path)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':telefono', $data['telefono']);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindValue(':tipo_colaborador', $data['tipo_colaborador'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':rango_colaborador', $data['rango_colaborador'] ?? null, PDO::PARAM_STR);
        $stmt->bindParam(':foto_path', $foto_path);
        
        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = 'Solicitud de registro enviada exitosamente';
            $result['id'] = $conn->lastInsertId();
        } else {
            $result['message'] = 'Error al registrar el prospecto colaborador';
        }
    } catch (PDOException $e) {
        error_log("Error registrando prospecto colaborador: " . $e->getMessage());
        $result['message'] = 'Error en el sistema. Por favor intente más tarde.';
    }
    
    return $result;
}

/**
 * Elimina un archivo de foto si existe
 * @param string $file_path - Ruta del archivo a eliminar
 * @return bool - true si se eliminó exitosamente o no existía
 */
function deletePhotoFile($file_path) {
    if (empty($file_path)) {
        return true;
    }
    
    $full_path = __DIR__ . '/../' . $file_path;
    
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    
    return true; // Si no existe, consideramos que está "eliminado"
}

/**
 * Genera una miniatura de una imagen con límites de recursos
 * @param string $source_path - Ruta de la imagen original
 * @param string $thumb_path - Ruta donde guardar la miniatura
 * @param int $max_width - Ancho máximo de la miniatura
 * @param int $max_height - Alto máximo de la miniatura
 * @return bool - true si se generó exitosamente
 */
function generateThumbnail($source_path, $thumb_path, $max_width = 150, $max_height = 150) {
    // Establecer límites de recursos
    ini_set('memory_limit', '256M');
    set_time_limit(30); // 30 segundos máximo
    
    if (!file_exists($source_path)) {
        return false;
    }
    
    $image_info = @getimagesize($source_path);
    if ($image_info === false) {
        return false;
    }
    
    list($width, $height, $type) = $image_info;
    
    // Calcular nuevas dimensiones
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = intval($width * $ratio);
    $new_height = intval($height * $ratio);
    
    // Crear imagen desde archivo con verificación
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source = @imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Crear miniatura
    $thumbnail = @imagecreatetruecolor($new_width, $new_height);
    if (!$thumbnail) {
        imagedestroy($source);
        return false;
    }
    
    // Preservar transparencia
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        @imagealphablending($thumbnail, false);
        @imagesavealpha($thumbnail, true);
        $transparent = @imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        @imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Redimensionar
    @imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Crear directorio si no existe
    $thumb_dir = dirname($thumb_path);
    if (!is_dir($thumb_dir)) {
        @mkdir($thumb_dir, 0700, true);
    }
    
    // Guardar miniatura (siempre como JPEG para miniaturas)
    $success = @imagejpeg($thumbnail, $thumb_path, 85);
    
    // Liberar memoria
    @imagedestroy($source);
    @imagedestroy($thumbnail);
    
    return $success;
}
?>
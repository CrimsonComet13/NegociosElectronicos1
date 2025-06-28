<?php
/**
 * Funciones auxiliares para validación de horarios de llamadas
 * Reminiscencia Photography - Sistema de Agendamiento
 */

/**
 * Valida que el horario solicitado respete el intervalo mínimo de 40 minutos
 * entre llamadas del mismo día
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $fecha Fecha de la llamada (YYYY-MM-DD)
 * @param string $hora Hora de la llamada (HH:MM)
 * @return array ['valid' => bool, 'message' => string]
 */
function validarIntervaloLlamadas($conn, $fecha, $hora) {
    try {
        // Convertir la hora solicitada a timestamp para cálculos
        $datetime_solicitada = new DateTime($fecha . ' ' . $hora);
        $timestamp_solicitada = $datetime_solicitada->getTimestamp();
        
        // Buscar todas las llamadas del mismo día
        $stmt = $conn->prepare("SELECT hora FROM llamadas WHERE fecha = :fecha AND estado != 'cancelada'");
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        
        $llamadas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($llamadas_existentes as $hora_existente) {
            $datetime_existente = new DateTime($fecha . ' ' . $hora_existente);
            $timestamp_existente = $datetime_existente->getTimestamp();
            
            // Calcular diferencia en minutos
            $diferencia_minutos = abs($timestamp_solicitada - $timestamp_existente) / 60;
            
            if ($diferencia_minutos < 40) {
                return [
                    'valid' => false,
                    'message' => 'El horario solicitado debe tener al menos 40 minutos de diferencia con otras llamadas. La llamada más cercana es a las ' . $hora_existente . '.'
                ];
            }
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Error al validar el intervalo de llamadas: ' . $e->getMessage()
        ];
    }
}

/**
 * Verifica que no exista una llamada exactamente a la misma hora el mismo día
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $fecha Fecha de la llamada (YYYY-MM-DD)
 * @param string $hora Hora de la llamada (HH:MM)
 * @return array ['valid' => bool, 'message' => string]
 */
function validarConflictoHorario($conn, $fecha, $hora) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM llamadas WHERE fecha = :fecha AND hora = :hora AND estado != 'cancelada'");
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->execute();
        
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return [
                'valid' => false,
                'message' => 'Ya existe una llamada agendada para esa fecha y hora exacta. Por favor selecciona otro horario.'
            ];
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Error al verificar conflictos de horario: ' . $e->getMessage()
        ];
    }
}

/**
 * Valida que la fecha y hora sean válidas y futuras
 * 
 * @param string $fecha Fecha de la llamada (YYYY-MM-DD)
 * @param string $hora Hora de la llamada (HH:MM)
 * @return array ['valid' => bool, 'message' => string]
 */
function validarFechaHora($fecha, $hora) {
    try {
        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return [
                'valid' => false,
                'message' => 'Formato de fecha inválido. Use YYYY-MM-DD.'
            ];
        }
        
        // Validar formato de hora
        if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return [
                'valid' => false,
                'message' => 'Formato de hora inválido. Use HH:MM.'
            ];
        }
        
        // Crear objeto DateTime para validar
        $datetime = new DateTime($fecha . ' ' . $hora);
        $ahora = new DateTime();
        
        // Verificar que sea una fecha futura (al menos 1 hora de anticipación)
        $ahora->add(new DateInterval('PT1H'));
        
        if ($datetime <= $ahora) {
            return [
                'valid' => false,
                'message' => 'La fecha y hora deben ser al menos 1 hora en el futuro.'
            ];
        }
        
        // Verificar horario de atención (9:00 AM a 6:00 PM)
        $hora_int = (int)substr($hora, 0, 2);
        if ($hora_int < 9 || $hora_int >= 18) {
            return [
                'valid' => false,
                'message' => 'Las llamadas solo pueden agendarse entre las 9:00 AM y las 6:00 PM.'
            ];
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Error al validar fecha y hora: ' . $e->getMessage()
        ];
    }
}

/**
 * Genera un token único para verificación de identidad
 * 
 * @return string Token de verificación
 */
function generarTokenVerificacion() {
    return bin2hex(random_bytes(32));
}

/**
 * Envía email de verificación (simulado - requiere configuración SMTP real)
 * 
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param string $token Token de verificación
 * @return bool Éxito del envío
 */
function enviarEmailVerificacion($email, $nombre, $token) {
    // En un entorno real, aquí se configuraría el envío de email
    // Por ahora, solo registramos en log o base de datos
    
    $mensaje = "Hola $nombre,\n\n";
    $mensaje .= "Para confirmar tu identidad y completar el agendamiento de tu llamada, ";
    $mensaje .= "por favor haz clic en el siguiente enlace:\n\n";
    $mensaje .= "http://localhost/remi_photography/verificar_identidad.php?token=$token\n\n";
    $mensaje .= "Si no solicitaste agendar una llamada, puedes ignorar este mensaje.\n\n";
    $mensaje .= "Saludos,\nEquipo Reminiscencia Photography";
    
    // Simular envío exitoso
    error_log("Email de verificación para $email: $mensaje");
    
    return true;
}

/**
 * Obtiene horarios sugeridos disponibles para una fecha
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $fecha Fecha para buscar horarios (YYYY-MM-DD)
 * @return array Lista de horarios disponibles
 */
function obtenerHorariosDisponibles($conn, $fecha) {
    try {
        // Horarios base (cada hora de 9 AM a 6 PM)
        $horarios_base = [];
        for ($h = 9; $h < 18; $h++) {
            $horarios_base[] = sprintf('%02d:00', $h);
            $horarios_base[] = sprintf('%02d:30', $h);
        }
        
        // Obtener horarios ocupados
        $stmt = $conn->prepare("SELECT hora FROM llamadas WHERE fecha = :fecha AND estado != 'cancelada'");
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        
        $horarios_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filtrar horarios disponibles considerando el intervalo de 40 minutos
        $horarios_disponibles = [];
        
        foreach ($horarios_base as $horario) {
            $disponible = true;
            
            foreach ($horarios_ocupados as $ocupado) {
                $datetime_base = new DateTime($fecha . ' ' . $horario);
                $datetime_ocupado = new DateTime($fecha . ' ' . $ocupado);
                
                $diferencia_minutos = abs($datetime_base->getTimestamp() - $datetime_ocupado->getTimestamp()) / 60;
                
                if ($diferencia_minutos < 40) {
                    $disponible = false;
                    break;
                }
            }
            
            if ($disponible) {
                $horarios_disponibles[] = $horario;
            }
        }
        
        return $horarios_disponibles;
        
    } catch (Exception $e) {
        error_log("Error al obtener horarios disponibles: " . $e->getMessage());
        return [];
    }
}
?>


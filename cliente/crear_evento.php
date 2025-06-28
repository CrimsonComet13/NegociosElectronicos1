<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');
// Solo clientes pueden acceder
if (!isClient()) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$errors = [];

// Generar token CSRF para el formulario
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token CSRF inválido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $fecha_evento = trim($_POST['fecha_evento'] ?? '');
        $hora_inicio = trim($_POST['hora_inicio'] ?? '');
        $hora_fin = trim($_POST['hora_fin'] ?? '');
        $lugar = trim($_POST['lugar'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $personas = trim($_POST['personas'] ?? '');
        
        // Lista blanca para tipos de evento
        $tiposPermitidos = ['boda', 'xv', 'graduacion', 'bautizo', 'sesion', 'otro'];
        
        // Validaciones
        // Nombre: máximo 255 caracteres, solo caracteres permitidos
        if (empty($nombre)) {
            $errors[] = 'El nombre del evento es obligatorio.';
        } elseif (strlen($nombre) > 255) {
            $errors[] = 'El nombre no puede exceder los 255 caracteres.';
        } elseif (!preg_match('/^[\p{L}\p{N}\s\-\'",.:;()áéíóúÁÉÍÓÚñÑ!?¿¡]{1,255}$/u', $nombre)) {
            $errors[] = 'El nombre contiene caracteres no permitidos.';
        }
        
        // Tipo: debe estar en la lista blanca
        if (empty($tipo)) {
            $errors[] = 'El tipo de evento es obligatorio.';
        } elseif (!in_array($tipo, $tiposPermitidos)) {
            $errors[] = 'Tipo de evento seleccionado no válido.';
        }
        
        // Fecha: formato válido y fecha futura
        $fecha_actual = new DateTime();
        $fecha_actual->setTime(0, 0, 0); // Solo fecha sin hora
        
        if (empty($fecha_evento)) {
            $errors[] = 'La fecha del evento es obligatoria.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $fecha_evento)) {
            $errors[] = 'Formato de fecha inválido (AAAA-MM-DD).';
        } else {
            $fecha_evento_obj = DateTime::createFromFormat('Y-m-d', $fecha_evento);
            $fecha_evento_obj->setTime(0, 0, 0);
            
            if ($fecha_evento_obj < $fecha_actual) {
                $errors[] = 'La fecha debe ser hoy o en el futuro.';
            }
        }
        
        // Horas: formato válido y hora_fin posterior a hora_inicio
        $formato_hora = '/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';
        
        if (empty($hora_inicio)) {
            $errors[] = 'La hora de inicio es obligatoria.';
        } elseif (!preg_match($formato_hora, $hora_inicio)) {
            $errors[] = 'Formato de hora inicio inválido (HH:MM).';
        }
        
        if (!empty($hora_fin) && !preg_match($formato_hora, $hora_fin)) {
            $errors[] = 'Formato de hora fin inválido (HH:MM).';
        }
        
        // Validar que hora_fin sea posterior a hora_inicio
        if (empty($errors) {
            $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
            $hora_fin_obj = !empty($hora_fin) ? DateTime::createFromFormat('H:i', $hora_fin) : null;
            
            if ($hora_fin_obj && $hora_inicio_obj >= $hora_fin_obj) {
                $errors[] = 'La hora de fin debe ser posterior a la hora de inicio.';
            }
        }
        
        // Lugar: máximo 255 caracteres
        if (empty($lugar)) {
            $errors[] = 'El lugar del evento es obligatorio.';
        } elseif (strlen($lugar) > 255) {
            $errors[] = 'El lugar no puede exceder los 255 caracteres.';
        }
        
        // Descripción: máximo 5000 caracteres
        if (strlen($descripcion) > 5000) {
            $errors[] = 'La descripción no puede exceder los 5000 caracteres.';
        }
        
        // Personas: entero positivo entre 1 y 10000
        if (!empty($personas)) {
            $personas = filter_var($personas, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 10000
                ]
            ]);
            
            if ($personas === false) {
                $errors[] = 'El número de personas debe ser un entero entre 1 y 10000.';
            }
        } else {
            $personas = null; // Para la base de datos
        }
        
        // Si no hay errores, crear el evento
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("INSERT INTO eventos 
                                      (cliente_id, nombre, tipo, fecha_evento, hora_inicio, hora_fin, lugar, descripcion, personas_estimadas, estado, fecha_creacion) 
                                      VALUES 
                                      (:cliente_id, :nombre, :tipo, :fecha_evento, :hora_inicio, :hora_fin, :lugar, :descripcion, :personas, 'pendiente', NOW())");
                
                $stmt->bindParam(':cliente_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
                $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
                $stmt->bindParam(':fecha_evento', $fecha_evento, PDO::PARAM_STR);
                $stmt->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
                $stmt->bindParam(':hora_fin', $hora_fin, PDO::PARAM_STR);
                $stmt->bindParam(':lugar', $lugar, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->bindParam(':personas', $personas, PDO::PARAM_INT);
                $stmt->execute();
                
                $evento_id = $conn->lastInsertId();
                
                // Notificar al administrador (simulado)
                $stmt = $conn->prepare("INSERT INTO notificaciones 
                                      (usuario_id, tipo, mensaje, leido, fecha_creacion) 
                                      VALUES 
                                      (1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, NOW())");
                $stmt->execute();
                
                $success = 'Evento creado exitosamente! Nuestro equipo se pondrá en contacto contigo para confirmar los detalles.';
                
                // Regenerar token para el próximo formulario
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirigir después de 3 segundos
                header("Refresh: 3; url=dashboard.php?success=1");
                
            } catch(PDOException $e) {
                $error = 'Error al crear el evento: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Regenerar token si vamos a mostrar el formulario
if (empty($success)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Evento - Reminiscencia Photography</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      --dark-bg: #0f1419;
      --card-bg: rgba(255, 255, 255, 0.08);
      --glass-border: rgba(255, 255, 255, 0.18);
      --text-primary: #ffffff;
      --text-secondary: rgba(255, 255, 255, 0.7);
      --shadow-glow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--dark-bg);
      background-image: 
        radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
      min-height: 100vh;
      color: var(--text-primary);
      overflow-x: hidden;
    }

    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      width: 280px;
      background: rgba(15, 20, 25, 0.9);
      backdrop-filter: blur(20px);
      border-right: 1px solid var(--glass-border);
      z-index: 1000;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar-header {
      padding: 2rem 1.5rem;
      border-bottom: 1px solid var(--glass-border);
    }

    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-menu {
      padding: 1rem 0;
    }

    .nav-item {
      margin: 0.5rem 1rem;
    }

    .nav-link {
      display: flex;
      align-items: center;
      padding: 1rem 1.5rem;
      color: var(--text-secondary);
      text-decoration: none;
      border-radius: 12px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .nav-link::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: var(--primary-gradient);
      transition: left 0.3s ease;
      z-index: -1;
    }

    .nav-link:hover::before,
    .nav-link.active::before {
      left: 0;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--text-primary);
      transform: translateX(5px);
    }

    .nav-link i {
      margin-right: 12px;
      font-size: 1.2rem;
      width: 20px;
    }

    .main-content {
      margin-left: 280px;
      padding: 2rem;
      min-height: 100vh;
    }

    .header {
      margin-bottom: 2rem;
    }

    .header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .header p {
      color: var(--text-secondary);
      font-size: 1.1rem;
    }

    .form-container {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      padding: 2rem;
      max-width: 800px;
      margin: 0 auto;
      box-shadow: var(--shadow-glow);
    }

    .form-label {
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      color: var(--text-primary);
      padding: 0.75rem 1rem;
      border-radius: 12px;
    }

    .form-control:focus, .form-select:focus {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(102, 126, 234, 0.5);
      color: var(--text-primary);
      box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    }

    .form-text {
      color: var(--text-secondary);
      font-size: 0.85rem;
    }

    .btn {
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1.5rem;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-primary {
      background: var(--primary-gradient);
      color: white;
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .alert {
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
    }

    .alert-danger {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.2);
      color: #ff6b6b;
    }

    .alert-success {
      background: rgba(79, 172, 254, 0.1);
      border: 1px solid rgba(79, 172, 254, 0.2);
      color: #4facfe;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        padding-bottom: 6rem;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .form-container {
        padding: 1.5rem;
      }

      .header h1 {
        font-size: 2rem;
      }
    }

    /* Mobile Navigation */
    .mobile-nav {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(15, 20, 25, 0.95);
      backdrop-filter: blur(20px);
      border-top: 1px solid var(--glass-border);
      padding: 1rem;
      z-index: 1000;
    }

    .mobile-nav-items {
      display: flex;
      justify-content: space-around;
      align-items: center;
    }

    .mobile-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: var(--text-secondary);
      text-decoration: none;
      transition: all 0.3s ease;
      padding: 0.5rem;
      border-radius: 8px;
    }

    .mobile-nav-item.active,
    .mobile-nav-item:hover {
      color: var(--text-primary);
      background: rgba(255, 255, 255, 0.1);
    }

    .mobile-nav-item i {
      font-size: 1.2rem;
      margin-bottom: 0.25rem;
    }

    .mobile-nav-item span {
      font-size: 0.7rem;
    }

    @media (max-width: 1024px) {
      .mobile-nav {
        display: block;
      }
    }

    .menu-toggle {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1001;
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 50%;
      width: 50px;
      height: 50px;
      color: var(--text-primary);
      font-size: 1.2rem;
    }

    @media (max-width: 1024px) {
      .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-container {
      animation: fadeInUp 0.6s ease forwards;
    }
  </style>
</head>
<body>
  <!-- Menu Toggle Button -->
  <button class="menu-toggle btn" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
  </button>

  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">
        <i class="bi bi-camera-reels"></i>
        Reminiscencia
      </div>
    </div>
    
    <div class="nav-menu">
      <div class="nav-item">
        <a href="dashboard.php" class="nav-link">
          <i class="bi bi-grid-fill"></i>
          <span>Dashboard</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="crear_evento.php" class="nav-link active">
          <i class="bi bi-plus-circle-fill"></i>
          <span>Crear Evento</span>
        </a>
      </div>
      <div class="nav-item" style="margin-top: 2rem;">
        <a href="/logout.php" class="nav-link" style="color: #ff6b6b;">
          <i class="bi bi-box-arrow-right"></i>
          <span>Cerrar Sesión</span>
        </a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <div class="header">
      <h1>Crear Nuevo Evento</h1>
      <p>Solicita un nuevo evento fotográfico con nuestro equipo</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $error; ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="row mb-3">
          <div class="col-md-6 mb-3">
            <label for="nombre" class="form-label">Nombre del Evento *</label>
            <input type="text" class="form-control" id="nombre" name="nombre" 
                   value="<?php echo !empty($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" 
                   maxlength="255" required>
            <small class="form-text">Máximo 255 caracteres. Solo letras, números y signos básicos</small>
          </div>
          <div class="col-md-6 mb-3">
            <label for="tipo" class="form-label">Tipo de Evento *</label>
            <select class="form-select" id="tipo" name="tipo" required>
              <option value="" class="text-dark">Seleccionar...</option>
              <option value="boda" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'boda') ? 'selected' : ''; ?> class="text-dark">Boda</option>
              <option value="xv" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'xv') ? 'selected' : ''; ?> class="text-dark">XV Años</option>
              <option value="graduacion" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'graduacion') ? 'selected' : ''; ?> class="text-dark">Graduación</option>
              <option value="bautizo" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'bautizo') ? 'selected' : ''; ?> class="text-dark">Bautizo</option>
              <option value="sesion" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'sesion') ? 'selected' : ''; ?> class="text-dark">Sesión Fotográfica</option>
              <option value="otro" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'otro') ? 'selected' : ''; ?> class="text-dark">Otro</option>
            </select>
          </div>
        </div>
        
        <div class="row mb-3">
          <div class="col-md-4 mb-3">
            <label for="fecha_evento" class="form-label">Fecha del Evento *</label>
            <input type="date" class="form-control" id="fecha_evento" name="fecha_evento" 
                   value="<?php echo !empty($_POST['fecha_evento']) ? htmlspecialchars($_POST['fecha_evento']) : ''; ?>" 
                   min="<?php echo date('Y-m-d'); ?>" required>
            <small class="form-text">Formato: AAAA-MM-DD. Debe ser hoy o en el futuro</small>
          </div>
          <div class="col-md-4 mb-3">
            <label for="hora_inicio" class="form-label">Hora de Inicio *</label>
            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" 
                   value="<?php echo !empty($_POST['hora_inicio']) ? htmlspecialchars($_POST['hora_inicio']) : '10:00'; ?>" 
                   required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="hora_fin" class="form-label">Hora de Finalización</label>
            <input type="time" class="form-control" id="hora_fin" name="hora_fin" 
                   value="<?php echo !empty($_POST['hora_fin']) ? htmlspecialchars($_POST['hora_fin']) : '12:00'; ?>">
            <small class="form-text">Debe ser posterior a la hora de inicio</small>
          </div>
        </div>
        
        <div class="mb-3">
          <label for="lugar" class="form-label">Lugar del Evento *</label>
          <input type="text" class="form-control" id="lugar" name="lugar" 
                 value="<?php echo !empty($_POST['lugar']) ? htmlspecialchars($_POST['lugar']) : ''; ?>" 
                 maxlength="255" required>
          <small class="form-text">Máximo 255 caracteres</small>
        </div>
        
        <div class="mb-3">
          <label for="descripcion" class="form-label">Descripción del Evento</label>
          <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                    maxlength="5000"><?php echo !empty($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
          <div class="form-text">Describe el tipo de fotografía que deseas, momentos especiales que quieres capturar, etc. (Máximo 5000 caracteres)</div>
        </div>
        
        <div class="mb-4">
          <label for="personas" class="form-label">Número estimado de personas</label>
          <input type="number" class="form-control" id="personas" name="personas" 
                 value="<?php echo !empty($_POST['personas']) ? htmlspecialchars($_POST['personas']) : ''; ?>" 
                 min="1" max="10000">
          <small class="form-text">Entre 1 y 10000 personas</small>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
          <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-send-fill me-2"></i>
            Solicitar Evento
          </button>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('open');
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      
      if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });

    // Establecer hora de inicio por defecto (10:00 AM)
    if (!document.getElementById('hora_inicio').value) {
      document.getElementById('hora_inicio').value = '10:00';
    }
    
    // Establecer hora de fin por defecto (2 horas después de inicio)
    document.getElementById('hora_inicio').addEventListener('change', function() {
      if (this.value) {
        const [hours, minutes] = this.value.split(':');
        const endTime = new Date();
        endTime.setHours(parseInt(hours) + 2);
        endTime.setMinutes(minutes);
        
        // Formatear a HH:MM
        const endHours = endTime.getHours().toString().padStart(2, '0');
        const endMinutes = endTime.getMinutes().toString().padStart(2, '0');
        
        if (!document.getElementById('hora_fin').value) {
          document.getElementById('hora_fin').value = `${endHours}:${endMinutes}`;
        }
      }
    });

    // Add click ripple effect
    document.querySelectorAll('.btn').forEach(button => {
      button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
          position: absolute;
          width: ${size}px;
          height: ${size}px;
          left: ${x}px;
          top: ${y}px;
          background: rgba(255, 255, 255, 0.3);
          border-radius: 50%;
          transform: scale(0);
          animation: ripple 0.6s ease-out;
          pointer-events: none;
        `;
        
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
      });
    });

    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(2);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>
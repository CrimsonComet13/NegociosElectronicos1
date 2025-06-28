<?php
// Iniciar sesión para CSRF
session_start();

// Configurar directorio de logs
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
ini_set('error_log', $log_dir . '/error.log');

// Incluir archivos de configuración
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');

$error = '';
$success = '';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de seguridad. Por favor recarga la página e intenta de nuevo.';
        error_log('Intento de agendar llamada sin token CSRF válido. IP: ' . $_SERVER['REMOTE_ADDR']);
    } else {
        // Verificar que los datos POST existan
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
        $hora = isset($_POST['hora']) ? trim($_POST['hora']) : '';
        $comentarios = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';
        
        // Validaciones básicas
        if (empty($nombre) || empty($email) || empty($telefono) || empty($fecha) || empty($hora)) {
            $error = 'Todos los campos marcados con * son obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Por favor ingresa un email válido';
        } elseif (strlen($nombre) > 100) {
            $error = 'El nombre no puede exceder los 100 caracteres';
        } elseif (strlen($telefono) > 20) {
            $error = 'El teléfono no puede exceder los 20 caracteres';
        } elseif (strlen($comentarios) > 500) {
            $error = 'Los comentarios no pueden exceder los 500 caracteres';
        } elseif (!preg_match('/^[0-9+\-\s()]{8,20}$/', $telefono)) {
            $error = 'Formato de teléfono inválido. Solo números, espacios, guiones y paréntesis';
        } else {
            // Validar formato de fecha (YYYY-MM-DD)
            $fecha_dt = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$fecha_dt || $fecha_dt->format('Y-m-d') !== $fecha) {
                $error = 'Formato de fecha inválido';
            } else {
                // Validar fecha futura
                $hoy = new DateTime();
                $hoy->setTime(0, 0, 0);
                if ($fecha_dt < $hoy) {
                    $error = 'La fecha debe ser futura';
                } else {
                    // Validar intervalo de 40 minutos
                    $minutos = date('i', strtotime($hora));
                    if ($minutos % 40 !== 0) {
                        $error = 'Las horas deben tener intervalos de 40 minutos (ej: 09:00, 09:40, 10:20)';
                    } else {
                        // Verificar conexión a la base de datos
                        if (!isset($conn) || $conn === null) {
                            $error = 'Error en el sistema. Por favor intenta más tarde.';
                            error_log('Error de conexión a BD en agendar_llamada.php');
                        } else {
                            try {
                                // Verificar disponibilidad de hora
                                $stmt_check = $conn->prepare("SELECT id FROM llamadas WHERE fecha = :fecha AND hora = :hora");
                                $stmt_check->bindParam(':fecha', $fecha);
                                $stmt_check->bindParam(':hora', $hora);
                                $stmt_check->execute();
                                
                                if ($stmt_check->rowCount() > 0) {
                                    $error = 'Esta hora ya está ocupada para la fecha seleccionada. Por favor elige otra.';
                                } else {
                                    // Insertar en la base de datos
                                    $stmt = $conn->prepare("INSERT INTO llamadas (nombre, email, telefono, fecha, hora, comentarios, estado) 
                                                           VALUES (:nombre, :email, :telefono, :fecha, :hora, :comentarios, 'pendiente')");
                                    
                                    $stmt->bindParam(':nombre', $nombre);
                                    $stmt->bindParam(':email', $email);
                                    $stmt->bindParam(':telefono', $telefono);
                                    $stmt->bindParam(':fecha', $fecha);
                                    $stmt->bindParam(':hora', $hora);
                                    $stmt->bindParam(':comentarios', $comentarios);
                                    
                                    if ($stmt->execute()) {
                                        $success = 'Tu llamada ha sido agendada correctamente. Nos pondremos en contacto contigo.';
                                        // Limpiar variables después del éxito
                                        $nombre = $email = $telefono = $fecha = $hora = $comentarios = '';
                                    } else {
                                        $error = 'Error al guardar la cita. Por favor intenta de nuevo.';
                                        error_log('Error ejecutando consulta en agendar_llamada: ' . implode(' ', $stmt->errorInfo()));
                                    }
                                }
                            } catch(PDOException $e) {
                                $error = 'Error en el sistema. Por favor intenta más tarde.';
                                error_log('Error en agendar_llamada: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agendar Llamada - Reminiscencia Photography</title>
  <!-- Vincular Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Iconos de Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary-dark: #0a0a0a;
      --secondary-dark: #1a1a1a;
      --tertiary-dark: #2a2a2a;
      --light-bg: #fafafa;
      --accent-gold: #d4af37;
      --accent-gold-hover: #b8941f;
      --accent-purple: #8b5a96;
      --text-muted: #6c757d;
      --border-light: rgba(255,255,255,0.1);
      --glass-bg: rgba(255,255,255,0.05);
      --shadow-soft: 0 20px 60px rgba(0,0,0,0.1);
      --shadow-hover: 0 30px 80px rgba(0,0,0,0.15);
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, var(--light-bg) 0%, #f0f2f5 100%);
      color: var(--secondary-dark);
      font-family: 'Inter', sans-serif;
      line-height: 1.7;
      overflow-x: hidden;
    }
    
    h1, h2, h3, h4, h5, h6 {
      font-family: 'Playfair Display', serif;
      font-weight: 600;
      letter-spacing: -0.02em;
    }
    
    /* Navbar moderna con glassmorphism */
    header {
      width: 100%;
      position: fixed;
      top: 0;
      z-index: 1000;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .navbar {
      padding: 20px 0;
      background: rgba(10, 10, 10, 0.8) !important;
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-light);
      transition: all 0.4s ease;
    }
    
    .navbar.scrolled {
      padding: 12px 0;
      background: rgba(10, 10, 10, 0.95) !important;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(30px);
    }
    
    .navbar-brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      font-weight: 700;
      background: linear-gradient(135deg, #fff, var(--accent-gold));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      transition: all 0.3s ease;
    }
    
    .navbar-brand:hover {
      transform: scale(1.05);
    }
    
    .nav-link {
      font-weight: 500;
      padding: 10px 18px !important;
      border-radius: 25px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: left 0.5s;
    }
    
    .nav-link:hover::before {
      left: 100%;
    }
    
    .nav-link:hover {
      color: var(--accent-gold) !important;
      background: var(--glass-bg);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(212, 175, 55, 0.2);
    }
    
    /* Hero Section para el formulario */
    .form-hero {
      background-image: 
        linear-gradient(135deg, rgba(10, 10, 10, 0.7) 0%, rgba(139, 90, 150, 0.3) 100%),
        url('assets/images/Foto-20.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 300px;
      color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
      position: relative;
      overflow: hidden;
      margin-top: 80px;
    }
    
    .form-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.3) 100%);
    }
    
    .form-hero-content {
      max-width: 900px;
      padding: 3rem;
      position: relative;
      z-index: 2;
      animation: heroFadeIn 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .form-hero h1 {
      font-size: 3rem;
      margin-bottom: 1rem;
      text-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      background: linear-gradient(135deg, #fff 0%, var(--accent-gold) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1.2;
    }
    
    /* Formulario moderno */
    .form-container {
      max-width: 700px;
      margin: 5rem auto;
      padding: 3rem;
      border-radius: 25px;
      box-shadow: var(--shadow-soft);
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      position: relative;
      overflow: hidden;
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .form-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(212, 175, 55, 0.05) 0%, rgba(139, 90, 150, 0.05) 100%);
      opacity: 0;
      transition: opacity 0.5s ease;
      z-index: 1;
    }
    
    .form-container:hover::before {
      opacity: 1;
    }
    
    .form-container:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-hover);
      border-color: rgba(212, 175, 55, 0.3);
    }
    
    .form-label {
      font-weight: 500;
      color: var(--primary-dark);
      margin-bottom: 0.5rem;
    }
    
    .form-control {
      padding: 12px 20px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      background: rgba(255, 255, 255, 0.8);
      position: relative;
      z-index: 2;
    }
    
    .form-control:focus {
      border-color: var(--accent-gold);
      box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
      background: white;
    }
    
    textarea.form-control {
      min-height: 120px;
    }
    
    /* Botones modernos */
    .btn {
      padding: 12px 32px;
      border-radius: 50px;
      font-weight: 500;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border: none;
      position: relative;
      overflow: hidden;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.9rem;
      z-index: 2;
    }
    
    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    
    .btn:hover::before {
      width: 300px;
      height: 300px;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-hover));
      color: white;
      box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
    }
    
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(212, 175, 55, 0.4);
      background: linear-gradient(135deg, var(--accent-gold-hover), var(--accent-gold));
    }
    
    /* Alertas personalizadas */
    .alert {
      border-radius: 10px;
      padding: 1rem 1.5rem;
      margin-bottom: 2rem;
      border: none;
      box-shadow: var(--shadow-soft);
      position: relative;
      z-index: 2;
    }
    
    .alert-danger {
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(220, 53, 69, 0.7));
      color: white;
    }
    
    .alert-success {
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.9), rgba(25, 135, 84, 0.7));
      color: white;
    }
    
    /* Footer */
    footer {
      background: var(--primary-dark);
      color: white;
      padding: 2rem 0;
      margin-top: 5rem;
    }
    
    .social-links a {
      margin: 0 10px;
      font-size: 1.2rem;
      transition: color 0.3s ease;
    }
    
    .social-links a:hover {
      color: var(--accent-gold);
    }
    
    /* Animaciones */
    @keyframes heroFadeIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .fade-in.visible {
      opacity: 1;
      transform: translateY(0);
    }
    
    /* Responsive mejorado */
    @media (max-width: 768px) {
      .form-hero h1 {
        font-size: 2.2rem;
      }
      
      .form-container {
        padding: 2rem;
        margin: 3rem auto;
      }
    }
    
    @media (max-width: 576px) {
      .form-hero h1 {
        font-size: 1.8rem;
      }
      
      .form-container {
        padding: 1.5rem;
        margin: 2rem auto;
        border-radius: 15px;
      }
    }
  </style>
</head>
<body>

  <!-- Barra de navegación -->
  <header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-black">
      <div class="container">
        <a class="navbar-brand" href="index.php">Reminiscencia Photography</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <div class="nav-buttons ms-auto">
            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link" href="https://wa.me/524491543138?text=Hola,%20quiero%20pedir%20informes%20sobre%20sus%20servicios." style="color: white;">
                  <i class="bi bi-headset"></i> Atención a Clientes
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" href="agendar_llamada.php" style="color: white;">
                  <i class="bi bi-telephone"></i> Agendar Llamada
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="login_colaborador.php" style="color: white;">
                  <i class="bi bi-people"></i> Colaboradores
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link btn-admin" href="admin_login.php" style="color: white;">
                  <i class="bi bi-shield-lock"></i> Admin
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </nav>
  </header>
  
  <!-- Hero Section para el formulario -->
  <section class="form-hero">
    <div class="form-hero-content">
      <h1 class="fade-in">Agenda una llamada personalizada</h1>
      <p class="lead fade-in">Hablemos sobre cómo podemos capturar tus momentos especiales</p>
    </div>
  </section>
  
  <!-- Formulario -->
  <section>
    <div class="container">
      <div class="form-container fade-in">
        <h2 class="text-center mb-4">Programa tu llamada</h2>
        
        <?php if ($error): ?>
          <div class="alert alert-danger fade-in">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
          <div class="alert alert-success fade-in">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
          <!-- Campo oculto para token CSRF -->
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          
          <div class="mb-4">
            <label for="nombre" class="form-label">Nombre Completo *</label>
            <input type="text" class="form-control" id="nombre" name="nombre" 
                   value="<?php echo isset($nombre) ? htmlspecialchars($nombre) : ''; ?>" 
                   required maxlength="100">
          </div>
          
          <div class="mb-4">
            <label for="email" class="form-label">Email *</label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                   required maxlength="150">
          </div>
          
          <div class="mb-4">
            <label for="telefono" class="form-label">Teléfono *</label>
            <input type="tel" class="form-control" id="telefono" name="telefono" 
                   value="<?php echo isset($telefono) ? htmlspecialchars($telefono) : ''; ?>" 
                   required maxlength="20" pattern="[0-9+\-\s()]*"
                   title="Solo números, espacios, guiones y paréntesis">
            <small class="form-text text-muted">Formato: +52 449 123 4567</small>
          </div>

          <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
              <label for="fecha" class="form-label">Fecha *</label>
              <input type="date" class="form-control" id="fecha" name="fecha" 
                     value="<?php echo isset($fecha) ? htmlspecialchars($fecha) : ''; ?>" 
                     min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-6">
              <label for="hora" class="form-label">Hora * (intervalos de 40 min)</label>
              <select class="form-control" id="hora" name="hora" required>
                <?php
                // Generar opciones de hora con intervalos de 40 minutos
                $start = strtotime('09:00');
                $end = strtotime('18:00');
                
                for ($time = $start; $time <= $end; $time = strtotime('+40 minutes', $time)) {
                    $time_value = date('H:i', $time);
                    $selected = (isset($hora) && $hora == $time_value) ? 'selected' : '';
                    echo "<option value='$time_value' $selected>$time_value</option>";
                }
                ?>
              </select>
            </div>
          </div>
          
          <div class="mb-4">
            <label for="comentarios" class="form-label">Comentarios o Motivo de la Llamada</label>
            <textarea class="form-control" id="comentarios" name="comentarios" rows="4" 
                      maxlength="500"><?php echo isset($comentarios) ? htmlspecialchars($comentarios) : ''; ?></textarea>
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-telephone"></i> Agendar Llamada
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="container">
      <div class="row">
        <div class="col-12 text-center">
          <p>&copy; 2024 Reminiscencia Photography. Todos los derechos reservados.</p>
          <div class="social-links mt-3">
            <a href="https://www.facebook.com/presenda.ph" class="text-white"><i class="bi bi-facebook"></i></a>
            <a href="https://www.instagram.com/reminiscencias.photography/" class="text-white"><i class="bi bi-instagram"></i></a>
            <a href="https://wa.me/524491543138?text=Hola,%20quiero%20pedir%20informes%20sobre%20sus%20servicios." class="text-white"><i class="bi bi-whatsapp"></i></a>
          </div>
          <div class="mt-3">
            <a href="login_cliente.php" class="btn btn-outline-light btn-sm">Área de Clientes</a>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- Vincular Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
  
  <script>
    // Efecto de navbar al hacer scroll
    window.addEventListener('scroll', function() {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // Intersection Observer para animaciones al hacer scroll
    const observerOptions = {
      threshold: 0.2,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
        }
      });
    }, observerOptions);

    // Observar todos los elementos con clase fade-in
    document.querySelectorAll('.fade-in').forEach(el => {
      observer.observe(el);
    });

    // Validación de fecha y hora
    document.addEventListener('DOMContentLoaded', function() {
      const fechaInput = document.getElementById('fecha');
      const hoy = new Date().toISOString().split('T')[0];
      fechaInput.min = hoy;
      
      // Deshabilitar fines de semana (opcional)
      fechaInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const day = selectedDate.getDay();
        
        if (day === 0 || day === 6) { // Domingo (0) o Sábado (6)
          if (confirm('Has seleccionado un fin de semana. ¿Estás seguro de que quieres agendar en esta fecha?')) {
            // El usuario confirma, mantener la fecha
          } else {
            this.value = '';
          }
        }
      });

      // Validación de formulario en tiempo real
      const form = document.querySelector('form');
      const inputs = form.querySelectorAll('input[required], textarea[required]');
      
      inputs.forEach(input => {
        input.addEventListener('blur', function() {
          if (this.value.trim() === '') {
            this.classList.add('is-invalid');
          } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
          }
        });
      });

      // Validación del email
      const emailInput = document.getElementById('email');
      emailInput.addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(this.value)) {
          this.classList.add('is-invalid');
        } else {
          this.classList.remove('is-invalid');
          this.classList.add('is-valid');
        }
      });

      // Validación del teléfono
      const telefonoInput = document.getElementById('telefono');
      telefonoInput.addEventListener('input', function() {
        // Permitir solo números, espacios, paréntesis, guiones y el signo +
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
      });
      
      // Validación de longitud máxima para comentarios
      const comentariosInput = document.getElementById('comentarios');
      comentariosInput.addEventListener('input', function() {
        if (this.value.length > 500) {
          this.value = this.value.substring(0, 500);
        }
      });
    });
  </script>
</body>
</html>
<?php
// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Solo usar en producción con HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 1800); // 30 minutos
ini_set('session.cookie_samesite', 'Strict');
session_name('Secure_Session');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');

// Si ya está logueado, redirigir según su rol
if (isLoggedIn()) {
    redirectByRole();
}

// Inicializar contador de intentos
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

$error = '';
$delaySeconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validación de longitud
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingresa tu email y contraseña';
    } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email proporcionado no es válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        // Verificar bloqueo por intentos
        $currentTime = time();
        $lastAttempt = $_SESSION['last_attempt_time'];
        
        // Calcular retraso progresivo
        $attempts = $_SESSION['login_attempts'];
        if ($attempts >= 5) {
            $error = 'Demasiados intentos fallidos. Por favor intente más tarde.';
        } else {
            // Aplicar retraso basado en intentos fallidos
            $delayMap = [2 => 2, 3 => 4, 4 => 8]; // Intentos => segundos
            if (isset($delayMap[$attempts])) {
                $delaySeconds = $delayMap[$attempts];
                sleep($delaySeconds);
            }

            try {
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND rol = 'cliente'");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (password_verify($password, $user['password'])) {
                        // Éxito: restablecer intentos
                        $_SESSION['login_attempts'] = 0;
                        // Iniciar sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['nombre'];
                        $_SESSION['user_role'] = $user['rol'];
                        
                        // Redirigir al dashboard del cliente
                        header("Location: /cliente/dashboard.php");
                        exit();
                    } else {
                        $error = 'Credenciales incorrectas';
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                    }
                } else {
                    $error = 'Credenciales incorrectas';
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                }
            } catch(PDOException $e) {
                // Registrar error internamente
                error_log('Error en login_cliente.php: ' . $e->getMessage());
                $error = 'Error al iniciar sesión. Por favor, intenta de nuevo.';
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
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
  <title>Iniciar Sesión Cliente - Reminiscencia Photography</title>
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
    background:
      linear-gradient(135deg, rgba(10, 10, 10, 0.6) 0%, rgba(139, 90, 150, 0.3) 100%),
      url('/assets/images/Foto-20.jpg') no-repeat center center fixed;
    background-size: cover;
    color: var(--secondary-dark);
    font-family: 'Inter', sans-serif;
    line-height: 1.7;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
  }
    
    /* Animated background particles */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="0.5" fill="rgba(255,255,255,0.02)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    animation: moveGrain 20s linear infinite;
    z-index: -1;
  }
    
    .particles {
      position: fixed;
      width: 100%;
      height: 100%;
      overflow: hidden;
      top: 0;
      left: 0;
      z-index: -1;
    }
    
    .particle {
      position: absolute;
      background: rgba(212, 175, 55, 0.1);
      border-radius: 50%;
      animation: float 12s infinite ease-in-out;
    }
    
    .particle:nth-child(1) { width: 8px; height: 8px; left: 10%; animation-delay: 0s; }
    .particle:nth-child(2) { width: 6px; height: 6px; left: 20%; animation-delay: 3s; }
    .particle:nth-child(3) { width: 10px; height: 10px; left: 70%; animation-delay: 6s; }
    .particle:nth-child(4) { width: 7px; height: 7px; left: 80%; animation-delay: 2s; }
    .particle:nth-child(5) { width: 9px; height: 9px; left: 50%; animation-delay: 4s; }
    
    h1, h2, h3, h4, h5, h6 {
      font-family: 'Playfair Display', serif;
      font-weight: 600;
      letter-spacing: -0.02em;
    }
    
    /* Header/Navbar */
    .navbar {
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      padding: 15px 0;
      background: rgba(10, 10, 10, 0.9) !important;
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-light);
      transition: all 0.4s ease;
    }
    
    .navbar-brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
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
      color: rgba(255,255,255,0.8) !important;
      font-weight: 500;
      padding: 8px 16px !important;
      border-radius: 20px;
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
      box-shadow: 0 8px 20px rgba(212, 175, 55, 0.2);
    }
    
    /* Login Container */
    .login-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 120px 20px 40px;
    }
    
    .login-container {
      max-width: 480px;
      width: 100%;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 25px;
      padding: 3rem 2.5rem;
      box-shadow: var(--shadow-soft);
      position: relative;
      overflow: hidden;
      animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(212, 175, 55, 0.03) 0%, rgba(139, 90, 150, 0.03) 100%);
      z-index: -1;
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    
    .login-header h2 {
      font-size: 2.2rem;
      color: var(--primary-dark);
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, var(--primary-dark), var(--accent-purple));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .login-header p {
      color: var(--text-muted);
      font-size: 1rem;
      font-weight: 300;
    }
    
    .login-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-hover));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
      animation: pulse 2s infinite;
    }
    
    .login-icon i {
      font-size: 2rem;
      color: white;
    }
    
    /* Form Styles */
    .form-floating {
      margin-bottom: 1.5rem;
      position: relative;
    }
    
    .form-control {
      border: 2px solid rgba(0, 0, 0, 0.1);
      border-radius: 15px;
      padding: 1rem 1.2rem;
      font-size: 1rem;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(5px);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }
    
    .form-control:focus {
      border-color: var(--accent-gold);
      box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25), 0 8px 25px rgba(212, 175, 55, 0.1);
      background: rgba(255, 255, 255, 0.95);
      transform: translateY(-2px);
    }
    
    .form-floating > label {
      color: var(--text-muted);
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
      color: var(--accent-gold);
      font-weight: 600;
    }
    
    /* Button Styles */
    .btn {
      padding: 12px 32px;
      border-radius: 50px;
      font-weight: 600;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border: none;
      position: relative;
      overflow: hidden;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.95rem;
    }
    
    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.2);
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
      width: 100%;
      padding: 15px 32px;
      font-size: 1.1rem;
    }
    
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(212, 175, 55, 0.4);
      background: linear-gradient(135deg, var(--accent-gold-hover), var(--accent-gold));
    }
    
    .btn-outline-secondary {
      border: 2px solid rgba(0, 0, 0, 0.1);
      color: var(--text-muted);
      background: transparent;
    }
    
    .btn-outline-secondary:hover {
      background: var(--accent-purple);
      border-color: var(--accent-purple);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(139, 90, 150, 0.3);
    }
    
    /* Alert Styles */
    .alert {
      border-radius: 15px;
      border: none;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
      backdrop-filter: blur(10px);
      animation: slideDown 0.5s ease;
    }
    
    .alert-danger {
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
      color: #dc3545;
      border-left: 4px solid #dc3545;
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.1);
    }
    
    .delay-indicator {
      display: inline-block;
      background: rgba(220, 53, 69, 0.1);
      border-radius: 10px;
      padding: 2px 8px;
      margin-top: 5px;
      font-size: 0.85rem;
    }
    
    /* Links */
    .login-links {
      text-align: center;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .login-links p {
      margin-bottom: 0.5rem;
      color: var(--text-muted);
      font-size: 0.95rem;
    }
    
    .login-links a {
      color: var(--accent-gold);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .login-links a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -2px;
      left: 0;
      background: var(--accent-gold);
      transition: width 0.3s ease;
    }
    
    .login-links a:hover::after {
      width: 100%;
    }
    
    .login-links a:hover {
      color: var(--accent-gold-hover);
      transform: translateY(-1px);
    }
    
    /* Back Button */
    .back-btn {
      position: absolute;
      top: 100px;
      left: 20px;
      background: var(--glass-bg);
      border: 1px solid var(--border-light);
      color: white;
      border-radius: 50px;
      padding: 12px 20px;
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;
      text-decoration: none;
      font-weight: 500;
    }
    
    .back-btn:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(-5px);
      color: var(--accent-gold);
    }
    
    /* Animations */
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes float {
      0%, 100% {
        transform: translateY(0px) rotate(0deg);
      }
      50% {
        transform: translateY(-30px) rotate(180deg);
      }
    }
    
    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
      }
      50% {
        transform: scale(1.05);
        box-shadow: 0 15px 40px rgba(212, 175, 55, 0.4);
      }
    }
    
    @keyframes moveGrain {
      0% { transform: translate(0, 0); }
      100% { transform: translate(-100px, -100px); }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .login-container {
        margin: 0 10px;
        padding: 2rem 1.5rem;
      }
      
      .login-header h2 {
        font-size: 1.8rem;
      }
      
      .back-btn {
        position: relative;
        top: auto;
        left: auto;
        margin-bottom: 2rem;
        display: inline-block;
      }
      
      .login-wrapper {
        padding: 40px 10px;
      }
    }
    
    @media (max-width: 576px) {
      .login-container {
        padding: 1.5rem 1rem;
      }
      
      .login-header h2 {
        font-size: 1.6rem;
      }
      
      .login-icon {
        width: 60px;
        height: 60px;
      }
      
      .login-icon i {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <!-- Particles Background -->
  <div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
  </div>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
      <a class="navbar-brand" href="index.php">Reminiscencia Photography</a>
      <div class="navbar-nav ms-auto">
        <a class="nav-link" href="index.php">
          <i class="bi bi-house"></i> Inicio
        </a>
      </div>
    </div>
  </nav>

  <!-- Back Button for mobile -->
  <div class="container d-md-none">
    <a href="index.php" class="back-btn">
      <i class="bi bi-arrow-left"></i> Volver al inicio
    </a>
  </div>

  <!-- Login Form -->
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-header">
        <div class="login-icon">
          <i class="bi bi-person-circle"></i>
        </div>
        <h2>Área de Clientes</h2>
        <p>Accede para crear un evento nuevo!</p>
      </div>
      
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <?php echo htmlspecialchars($error); ?>
          <?php if ($_SESSION['login_attempts'] > 1 && $delaySeconds > 0): ?>
            <div class="delay-indicator">
              <i class="bi bi-clock-history me-1"></i>
              Se aplicó un retraso de <?php echo $delaySeconds; ?> segundos
            </div>
          <?php endif; ?>
          <?php if ($_SESSION['login_attempts'] >= 5): ?>
            <div class="mt-2">Tu cuenta está temporalmente bloqueada por seguridad.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <div class="form-floating">
          <input type="email" class="form-control" id="email" name="email" placeholder="tu@email.com" maxlength="100" required>
          <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
        </div>
        
        <div class="form-floating">
          <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" minlength="8" required>
          <label for="password"><i class="bi bi-lock me-2"></i>Contraseña (mín. 8 caracteres)</label>
        </div>
        
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Iniciar Sesión
          </button>
        </div>
      </form>
      
      <div class="login-links">
        <p>¿No tienes una cuenta? <a href="register_cliente.php">Regístrate aquí</a></p>
        <p>¿Eres colaborador? <a href="login_colaborador.php">Inicia sesión aquí</a></p>
        <p>¿Problemas para acceder? <a href="https://wa.me/524491543138?text=Hola,%20tengo%20problemas%20para%20acceder%20a%20mi%20cuenta%20de%20cliente." target="_blank">Contáctanos</a></p>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
  
  <script>
    // Efecto de focus mejorado en inputs
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
      });
    });

    // Validación en tiempo real
    document.getElementById('email').addEventListener('input', function() {
      const email = this.value;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      if (email && !emailRegex.test(email)) {
        this.style.borderColor = '#dc3545';
        this.nextElementSibling.style.color = '#dc3545';
      } else {
        this.style.borderColor = '';
        this.nextElementSibling.style.color = '';
      }
    });

    // Efecto de typing en el título
    function typeWriter(element, text, speed = 100) {
      let i = 0;
      element.innerHTML = '';
      
      function type() {
        if (i < text.length) {
          element.innerHTML += text.charAt(i);
          i++;
          setTimeout(type, speed);
        }
      }
      
      type();
    }

    // Aplicar efecto cuando la página carga
    window.addEventListener('load', function() {
      setTimeout(() => {
        const title = document.querySelector('.login-header h2');
        if (title) {
          const originalText = title.textContent;
          typeWriter(title, originalText, 100);
        }
      }, 300);
    });

    // Efecto parallax suave en el scroll
    window.addEventListener('scroll', function() {
      const scrolled = window.pageYOffset;
      const particles = document.querySelectorAll('.particle');
      
      particles.forEach((particle, index) => {
        const speed = (index + 1) * 0.5;
        particle.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.1}deg)`;
      });
    });

    // Shake animation on error
    <?php if ($error): ?>
    setTimeout(() => {
      const container = document.querySelector('.login-container');
      container.style.animation = 'shake 0.5s ease-in-out';
      setTimeout(() => {
        container.style.animation = '';
      }, 500);
    }, 100);
    
    // Add shake keyframes
    const style = document.createElement('style');
    style.textContent = `
      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
      }
    `;
    document.head.appendChild(style);
    <?php endif; ?>

    // Deshabilitar botón después de enviar
    document.querySelector('form').addEventListener('submit', function() {
      const btn = this.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Procesando...';
    });
  </script>
</body>
</html>
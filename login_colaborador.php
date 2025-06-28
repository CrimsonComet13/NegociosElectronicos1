<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');

// Si ya está logueado, redirigir según su rol
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingresa tu email y contraseña';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND rol = 'colaborador'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['nombre'];
                    $_SESSION['user_role'] = $user['rol'];
                    $_SESSION['tipo_colaborador'] = $user['tipo_colaborador'];
                    $_SESSION['rango_colaborador'] = $user['rango_colaborador'];
                    
                    // Redirigir al dashboard del colaborador
                    header("Location: /colaborador/dashboard.php");
                    exit();
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'No existe una cuenta de colaborador con ese email';
            }
        } catch(PDOException $e) {
            $error = 'Error al iniciar sesión: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión Colaborador - Reminiscencia Photography</title>
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
      min-height: 100vh;
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
    
    /* Hero Section para login */
    .login-hero {
      background-image: 
        linear-gradient(135deg, rgba(10, 10, 10, 0.8) 0%, rgba(139, 90, 150, 0.4) 100%),
        url('assets/images/Foto-20.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      position: relative;
      overflow: hidden;
      padding-top: 120px;
    }
    
    .login-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.2) 100%);
      pointer-events: none; /* CRITICAL FIX */
    }
    
    /* Contenedor de login moderno - SIMPLIFICADO PARA EVITAR CONFLICTOS */
    .login-container {
      max-width: 450px;
      margin: 0 auto;
      padding: 3rem;
      border-radius: 15px;
      box-shadow: var(--shadow-soft);
      background: rgba(255, 255, 255, 0.98); /* Más opaco para mejor contraste */
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      position: relative;
      /* REMOVED: transform effects that cause issues */
      z-index: 10; /* Asegurar que esté por encima del background */
    }
    
    /* REMOVED: pseudo-elements that interfere with form interaction */
    
    .login-title {
      text-align: center;
      margin-bottom: 2rem;
      color: var(--primary-dark);
      font-size: 2rem;
      position: relative;
      z-index: 2;
    }
    
    .login-subtitle {
      text-align: center;
      color: var(--text-muted);
      margin-bottom: 2rem;
      font-weight: 400;
      position: relative;
      z-index: 2;
    }
    
    .form-label {
      font-weight: 500;
      color: var(--primary-dark);
      margin-bottom: 0.5rem;
      position: relative;
      z-index: 2;
    }
    
    /* INPUTS COMPLETAMENTE REDISEÑADOS PARA FUNCIONALIDAD */
    .form-group {
      position: relative;
      z-index: 50; /* Muy alto para asegurar accesibilidad */
      margin-bottom: 1.5rem;
      /* Removed any transform or overlay effects */
    }
    
    .form-control {
      padding: 15px 20px;
      border-radius: 8px;
      border: 2px solid rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      background: white; /* Fondo sólido blanco */
      font-size: 1rem;
      position: relative;
      z-index: 100; /* Máxima prioridad */
      pointer-events: auto;
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      width: 100%;
      box-sizing: border-box;
      /* Prevent any interference from parent containers */
      isolation: isolate;
    }
    
    .form-control:focus {
      border-color: var(--accent-gold);
      box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
      background: white;
      outline: none;
      z-index: 200; /* Even higher on focus */
    }
    
    .form-control:hover {
      border-color: rgba(212, 175, 55, 0.5);
      background: white;
    }
    
    .form-control::placeholder {
      color: #999;
      opacity: 1;
    }
    
    /* Botones modernos */
    .btn {
      padding: 15px 32px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border: none;
      position: relative;
      overflow: hidden;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.9rem;
      z-index: 50;
      pointer-events: auto;
      cursor: pointer;
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
      pointer-events: none;
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
    
    .btn-outline-primary {
      background: transparent;
      border: 2px solid var(--accent-gold);
      color: var(--accent-gold);
    }
    
    .btn-outline-primary:hover {
      background: var(--accent-gold);
      color: white;
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
    }
    
    /* Alertas personalizadas */
    .alert {
      border-radius: 8px;
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
    
    /* Enlaces de navegación */
    .login-links {
      text-align: center;
      margin-top: 2rem;
      position: relative;
      z-index: 2;
    }
    
    .login-links p {
      margin-bottom: 0.5rem;
      color: var(--text-muted);
    }
    
    .login-links a {
      color: var(--accent-gold);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .login-links a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -2px;
      left: 50%;
      background: var(--accent-gold);
      transition: all 0.3s ease;
      transform: translateX(-50%);
    }
    
    .login-links a:hover {
      color: var(--accent-gold-hover);
      transform: translateY(-1px);
    }
    
    .login-links a:hover::after {
      width: 100%;
    }
    
    /* Icono de colaborador */
    .collaborator-icon {
      text-align: center;
      margin-bottom: 1.5rem;
      position: relative;
      z-index: 2;
    }
    
    .collaborator-icon i {
      font-size: 3rem;
      background: linear-gradient(135deg, var(--accent-gold), var(--accent-purple));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    /* Animaciones */
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    
    .fade-in:nth-child(2) {
      animation-delay: 0.2s;
    }
    
    .fade-in:nth-child(3) {
      animation-delay: 0.4s;
    }
    
    .fade-in:nth-child(4) {
      animation-delay: 0.6s;
    }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Responsive mejorado */
    @media (max-width: 768px) {
      .login-container {
        padding: 2rem;
        margin: 1rem;
        border-radius: 12px;
      }
      
      .login-title {
        font-size: 1.6rem;
      }
      
      .navbar-brand {
        font-size: 1.5rem;
      }
      
      .login-hero {
        padding-top: 80px;
      }
    }
    
    @media (max-width: 576px) {
      .login-container {
        padding: 1.5rem;
        margin: 0.5rem;
        border-radius: 10px;
      }
      
      .login-title {
        font-size: 1.4rem;
      }
      
      .btn {
        padding: 12px 24px;
        font-size: 0.85rem;
      }
      
      .login-hero {
        padding-top: 60px;
      }
    }
    
    /* CRITICAL FIX: Ensure form interaction works properly */
    form {
      position: relative;
      z-index: 100;
      isolation: isolate;
    }
    
    /* Disable any pointer-events interference */
    .login-container * {
      pointer-events: auto;
    }
    
    /* Make sure pseudo-elements don't interfere */
    .login-container::before,
    .login-container::after {
      pointer-events: none !important;
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
                <a class="nav-link" href="agendar_llamada.php" style="color: white;">
                  <i class="bi bi-telephone"></i> Agendar Llamada
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" href="login_colaborador.php" style="color: white;">
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
  
  
  <!-- Hero Section con Login -->
  <section class="login-hero">
    <div class="container">
      <div class="login-container fade-in">
        <div class="collaborator-icon fade-in">
          <i class="bi bi-people-fill"></i>
        </div>
        
        <h2 class="login-title fade-in">Bienvenido Colaborador</h2>
        <p class="login-subtitle fade-in">Accede a tu área de trabajo</p>
        
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger fade-in" id="error-alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        
        <form id="loginForm" class="fade-in" method="POST" novalidate>
          <div class="form-group">
            <label for="email" class="form-label">
              <i class="bi bi-envelope me-2"></i>Email
            </label>
            <input type="email" class="form-control" id="email" name="email" placeholder="tu@email.com" required autocomplete="email" tabindex="1" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          
          <div class="form-group">
            <label for="password" class="form-label">
              <i class="bi bi-lock me-2"></i>Contraseña
            </label>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" tabindex="2">
          </div>
          
          <div class="d-grid mb-4">
            <button type="submit" class="btn btn-primary btn-lg" tabindex="3">
              <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
            </button>
          </div>
        </form>
        
        <div class="login-links fade-in">
          <p>¿No tienes una cuenta? <a href="register_colaborador.php">Regístrate aquí</a></p>
          <p>¿Eres cliente? <a href="login_cliente.php">Inicia sesión aquí</a></p>
          <p>¿Eres administrador? <a href="admin_login.php">Acceso admin</a></p>
        </div>
      </div>
    </div>
  </section>

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

    // Mejorar la funcionalidad del formulario
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM cargado, iniciando configuración del formulario...');
      
      const inputs = document.querySelectorAll('.form-control');
      const form = document.getElementById('loginForm');
      const errorAlert = document.getElementById('error-alert');
      const errorMessage = document.getElementById('error-message');
      
      console.log('Inputs encontrados:', inputs.length);
      
      // Configurar eventos para cada input
      inputs.forEach((input, index) => {
        console.log(`Configurando input ${index + 1}:`, input.id);
        
        // Event listeners básicos
        input.addEventListener('focus', function(e) {
          console.log('Input focused:', this.id);
          this.style.borderColor = 'var(--accent-gold)';
          this.style.boxShadow = '0 0 0 0.25rem rgba(212, 175, 55, 0.25)';
        });
        
        input.addEventListener('blur', function(e) {
          console.log('Input blurred:', this.id);
          if (!this.value.trim()) {
            this.style.borderColor = 'rgba(0, 0, 0, 0.1)';
            this.style.boxShadow = 'none';
          }
        });
        
        input.addEventListener('input', function(e) {
          console.log('Input changed:', this.id, this.value);
          // Hide error when user starts typing
          if (errorAlert && errorAlert.style.display !== 'none') {
            errorAlert.style.display = 'none';
          }
        });

        // Asegurar que los clicks funcionen
        input.addEventListener('click', function(e) {
          console.log('Input clicked:', this.id);
          e.stopPropagation();
          this.focus();
        });
      });

      // Configurar el envío del formulario
      if (form) {
        form.addEventListener('submit', function(e) {
          const email = document.getElementById('email').value.trim();
          const password = document.getElementById('password').value.trim();
          
          console.log('Email:', email);
          console.log('Password length:', password.length);
          
          // Validación básica
          if (!email || !password) {
            if (errorAlert && errorMessage) {
              errorMessage.textContent = 'Por favor, completa todos los campos';
              errorAlert.style.display = 'block';
            }
            e.preventDefault();
            return false;
          }
          
          if (!isValidEmail(email)) {
            if (errorAlert && errorMessage) {
              errorMessage.textContent = 'Por favor, ingresa un email válido';
              errorAlert.style.display = 'block';
            }
            e.preventDefault();
            return false;
          }
        });
      }

      // Validar email
      function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
      }

      // Test de funcionalidad
      setTimeout(() => {
        console.log('=== TEST DE FUNCIONALIDAD ===');
        console.log('Email input accesible:', document.getElementById('email') !== null);
        console.log('Password input accesible:', document.getElementById('password') !== null);
        console.log('Formulario accesible:', document.getElementById('loginForm') !== null);
        
        // Intentar hacer focus en el primer input
        const emailInput = document.getElementById('email');
        if (emailInput) {
          console.log('Intentando hacer focus en email input...');
          emailInput.focus();
          setTimeout(() => {
            console.log('Email input tiene focus:', document.activeElement === emailInput);
          }, 100);
        }
      }, 1000);
    });
  </script>
</body>
</html>
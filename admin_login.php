<?php
require_once(__DIR__ . '/includes/config.php');
require_once (__DIR__ . '/includes/auth.php');
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
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND rol = 'admin'");
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
                    
                    // Redirigir al dashboard del admin
                    header("Location: /admin/dashboard.php");
                    exit();
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'No tienes permisos de administrador';
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
  <title>Acceso Administrador - Reminiscencia Photography</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
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
      background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
      color: white;
      font-family: 'Inter', sans-serif;
      line-height: 1.7;
      min-height: 100vh;
      display: flex;
      align-items: center;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Background pattern */
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="0.5" fill="rgba(255,255,255,0.02)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      opacity: 0.5;
    }
    
    /* Floating particles effect */
    .particles {
      position: absolute;
      width: 100%;
      height: 100%;
      overflow: hidden;
      top: 0;
      left: 0;
    }
    
    .particle {
      position: absolute;
      background: rgba(212, 175, 55, 0.1);
      border-radius: 50%;
      animation: float 8s infinite ease-in-out;
    }
    
    .particle:nth-child(1) { width: 6px; height: 6px; left: 10%; animation-delay: 0s; }
    .particle:nth-child(2) { width: 4px; height: 4px; left: 20%; animation-delay: 2s; }
    .particle:nth-child(3) { width: 8px; height: 8px; left: 80%; animation-delay: 4s; }
    .particle:nth-child(4) { width: 5px; height: 5px; left: 70%; animation-delay: 1s; }
    .particle:nth-child(5) { width: 7px; height: 7px; left: 90%; animation-delay: 3s; }
    
    .login-container {
      max-width: 450px;
      width: 100%;
      margin: 0 auto;
      padding: 3rem;
      border-radius: 25px;
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(20px);
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-soft);
      position: relative;
      z-index: 10;
      animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(212, 175, 55, 0.05) 0%, rgba(139, 90, 150, 0.05) 100%);
      border-radius: 25px;
      z-index: -1;
    }
    
    .brand-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    
    .brand-logo {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      background: linear-gradient(135deg, #fff, var(--accent-gold));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 0.5rem;
    }
    
    .brand-subtitle {
      color: rgba(255, 255, 255, 0.7);
      font-size: 0.9rem;
      font-weight: 300;
      letter-spacing: 0.5px;
    }
    
    .login-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      font-weight: 600;
      text-align: center;
      margin-bottom: 2rem;
      color: white;
      position: relative;
    }
    
    .login-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: linear-gradient(90deg, var(--accent-gold), var(--accent-purple));
      border-radius: 2px;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }
    
    .form-label {
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      letter-spacing: 0.3px;
    }
    
    .form-control {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px;
      padding: 12px 20px;
      color: white;
      font-size: 1rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
    }
    
    .form-control:focus {
      background: rgba(255, 255, 255, 0.15);
      border-color: var(--accent-gold);
      box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
      color: white;
      transform: translateY(-2px);
    }
    
    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.5);
    }
    
    .input-group {
      position: relative;
    }
    
    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255, 255, 255, 0.6);
      z-index: 10;
      pointer-events: none;
    }
    
    .form-control.with-icon {
      padding-left: 45px;
    }
    
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
    
    .btn-admin {
      background: linear-gradient(135deg, var(--accent-purple), #7a4d84);
      color: white;
      box-shadow: 0 8px 25px rgba(139, 90, 150, 0.3);
      width: 100%;
      padding: 15px;
      font-size: 1rem;
    }
    
    .btn-admin:hover {
      background: linear-gradient(135deg, #7a4d84, var(--accent-purple));
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(139, 90, 150, 0.4);
      color: white;
    }
    
    .btn-back {
      background: rgba(255, 255, 255, 0.1);
      color: rgba(255, 255, 255, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 25px;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }
    
    .btn-back:hover {
      background: rgba(255, 255, 255, 0.15);
      color: white;
      transform: translateY(-2px);
      text-decoration: none;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .alert {
      border-radius: 15px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 1.5rem;
      backdrop-filter: blur(10px);
      animation: slideIn 0.5s ease;
    }
    
    .alert-danger {
      background: rgba(220, 53, 69, 0.15);
      color: #ff6b7a;
      border: 1px solid rgba(220, 53, 69, 0.3);
    }
    
    .back-link {
      text-align: center;
      margin-top: 2rem;
    }
    
    .divider {
      text-align: center;
      margin: 2rem 0;
      position: relative;
    }
    
    .divider::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    }
    
    .divider span {
      background: var(--secondary-dark);
      padding: 0 1rem;
      color: rgba(255, 255, 255, 0.6);
      font-size: 0.8rem;
    }
    
    /* Animaciones */
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
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes float {
      0%, 100% {
        transform: translateY(0px) rotate(0deg);
      }
      50% {
        transform: translateY(-20px) rotate(180deg);
      }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      body {
        padding: 1rem;
      }
      
      .login-container {
        padding: 2rem 1.5rem;
        margin: 1rem;
      }
      
      .brand-logo {
        font-size: 1.6rem;
      }
      
      .login-title {
        font-size: 1.5rem;
      }
    }
    
    @media (max-width: 480px) {
      .login-container {
        padding: 1.5rem 1rem;
      }
      
      .brand-logo {
        font-size: 1.4rem;
      }
      
      .login-title {
        font-size: 1.3rem;
      }
    }
  </style>
</head>
<body>
  <div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
  </div>
  
  <div class="container">
    <div class="login-container">
      <div class="brand-header">
        <div class="brand-logo">Reminiscencia Photography</div>
        <div class="brand-subtitle">Panel de Administración</div>
      </div>
      
      <h2 class="login-title">
        <i class="bi bi-shield-lock me-2"></i>
        Acceso Seguro
      </h2>
      
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <div class="form-group">
          <label for="email" class="form-label">
            <i class="bi bi-envelope me-1"></i>
            Correo Electrónico
          </label>
          <div class="input-group">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" class="form-control with-icon" id="email" name="email" 
                   placeholder="admin@reminiscencia.com" required>
          </div>
        </div>
        
        <div class="form-group">
          <label for="password" class="form-label">
            <i class="bi bi-lock me-1"></i>
            Contraseña
          </label>
          <div class="input-group">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" class="form-control with-icon" id="password" name="password" 
                   placeholder="••••••••" required>
          </div>
        </div>
        
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-admin">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Iniciar Sesión
          </button>
        </div>
        
        <div class="divider">
          <span>o</span>
        </div>
        
        <div class="back-link">
          <a href="/" class="btn-back">
            <i class="bi bi-arrow-left"></i>
            Volver al sitio principal
          </a>
        </div>
      </form>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Efecto de focus en los inputs
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
      });
    });
    
    // Validación en tiempo real
    document.getElementById('email').addEventListener('input', function() {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (this.value && !emailRegex.test(this.value)) {
        this.style.borderColor = '#dc3545';
      } else {
        this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
      }
    });
    
    // Efecto de loading en el botón
    document.querySelector('form').addEventListener('submit', function() {
      const btn = document.querySelector('.btn-admin');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Verificando...';
      btn.disabled = true;
      
      // Restaurar si hay error (después de 3 segundos)
      setTimeout(() => {
        if (btn.disabled) {
          btn.innerHTML = originalText;
          btn.disabled = false;
        }
      }, 3000);
    });
    
    // Animación de entrada
    window.addEventListener('load', function() {
      document.querySelector('.login-container').style.animation = 'fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
    });
  </script>
</body>
</html>
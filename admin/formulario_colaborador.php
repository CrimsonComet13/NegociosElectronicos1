<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Verificar permisos de administrador
if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

// Inicializar variables para evitar warnings
$nombre = $email = $telefono = $tipo_colaborador = $rango_colaborador = '';
$error = '';

// Tipos y rangos disponibles
$tipos_colaborador = [
    'fotografo' => 'Fotógrafo',
    'videografo' => 'Videógrafo',
    'auxiliar' => 'Auxiliar',
];

$rangos_colaborador = [
    'I' => 'I',
    'II' => 'II',
    'III' => 'III',
];

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger y validar datos con valores por defecto
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING) ?? '';
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '';
        $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING) ?? '';
        $tipo_colaborador = $_POST['tipo_colaborador'] ?? '';
        $rango_colaborador = $_POST['rango_colaborador'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validaciones
        if (empty($nombre) || empty($email) || empty($password) || empty($tipo_colaborador) || empty($rango_colaborador)) {
            throw new Exception("Todos los campos marcados con * son obligatorios");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Las contraseñas no coinciden");
        }

        if (strlen($password) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres");
        }

        if (!array_key_exists($tipo_colaborador, $tipos_colaborador)) {
            throw new Exception("Tipo de colaborador no válido");
        }

        if (!array_key_exists($rango_colaborador, $rangos_colaborador)) {
            throw new Exception("Rango de colaborador no válido");
        }

        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("El email ya está registrado");
        }

        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar en la base de datos
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, telefono, password, rol, activo, tipo_colaborador, rango_colaborador) 
                               VALUES (?, ?, ?, ?, 'colaborador', 1, ?, ?)");
        $stmt->execute([$nombre, $email, $telefono, $password_hash, $tipo_colaborador, $rango_colaborador]);

        // Redirigir con mensaje de éxito
        $_SESSION['mensaje_exito'] = "Colaborador registrado exitosamente";
        header("Location: gestion_colaboradores.php");
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrar Colaborador - Reminiscencia Photography</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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

    .page-header {
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .page-header h1 {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .back-button {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1.2rem;
      color: var(--text-primary);
      text-decoration: none;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .back-button:hover {
      color: var(--text-primary);
      transform: translateY(-2px);
      box-shadow: var(--shadow-glow);
    }

    .form-container {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      padding: 2rem;
      max-width: 800px;
      margin: 0 auto;
      position: relative;
      overflow: hidden;
    }

    .form-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .form-label {
      color: var(--text-primary);
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    .required-field::after {
      content: " *";
      color: #ff6b6b;
    }

    .form-control {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .form-control:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(102, 126, 234, 0.5);
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      color: var(--text-primary);
    }

    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.5);
    }

    .form-select {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .form-select:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(102, 126, 234, 0.5);
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      color: var(--text-primary);
    }

    .form-text {
      color: var(--text-secondary);
      font-size: 0.8rem;
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
      border: 1px solid var(--glass-border);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn-group {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .alert {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.2);
      border-radius: 12px;
      color: #ff6b6b;
      backdrop-filter: blur(10px);
    }

    .password-strength {
      height: 4px;
      border-radius: 2px;
      margin-top: 0.5rem;
      transition: all 0.3s ease;
    }

    .strength-0 {
      width: 20%;
      background: var(--danger-gradient);
    }

    .strength-1 {
      width: 40%;
      background: var(--danger-gradient);
    }

    .strength-2 {
      width: 60%;
      background: var(--warning-gradient);
    }

    .strength-3 {
      width: 80%;
      background: var(--info-gradient);
    }

    .strength-4 {
      width: 100%;
      background: var(--success-gradient);
    }

    /* Menu Toggle Button */
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
      }

      .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .mobile-nav {
        display: block;
      }

      .main-content {
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

      .page-header h1 {
        font-size: 1.5rem;
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
        <a href="gestion_eventos.php" class="nav-link">
          <i class="bi bi-calendar-event-fill"></i>
          <span>Eventos</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="gestion_colaboradores.php" class="nav-link active">
          <i class="bi bi-people-fill"></i>
          <span>Colaboradores</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="gestion_clientes.php" class="nav-link">
          <i class="bi bi-person-lines-fill"></i>
          <span>Clientes</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="llamadas.php" class="nav-link">
          <i class="bi bi-telephone-fill"></i>
          <span>Llamadas</span>
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
    <div class="page-header">
      <a href="gestion_colaboradores.php" class="back-button">
        <i class="bi bi-arrow-left"></i>
        Volver
      </a>
      <h1>Registrar Colaborador</h1>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form method="POST" autocomplete="off">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="nombre" class="form-label required-field">Nombre Completo</label>
            <input type="text" class="form-control" id="nombre" name="nombre" 
                   value="<?php echo htmlspecialchars($nombre); ?>" required>
          </div>
          
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label required-field">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>" required>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="tel" class="form-control" id="telefono" name="telefono"
                   value="<?php echo htmlspecialchars($telefono); ?>">
          </div>
          
          <div class="col-md-6 mb-3">
            <label for="tipo_colaborador" class="form-label required-field">Tipo de Colaborador</label>
            <select class="form-select" id="tipo_colaborador" name="tipo_colaborador" required>
              <option value="">Seleccionar tipo...</option>
              <?php foreach ($tipos_colaborador as $valor => $texto): ?>
                <option class="text-dark" value="<?php echo htmlspecialchars($valor); ?>" 
                  <?php echo ($tipo_colaborador === $valor) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($texto); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="rango_colaborador" class="form-label required-field">Rango de Colaborador</label>
            <select class="form-select" id="rango_colaborador" name="rango_colaborador" required>
              <option value="">Seleccionar rango...</option>
              <?php foreach ($rangos_colaborador as $valor => $texto): ?>
                <option class="text-dark" value="<?php echo htmlspecialchars($valor); ?>" 
                  <?php echo ($rango_colaborador === $valor) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($texto); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="password" class="form-label required-field">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required minlength="8">
            <div class="password-strength" id="password-strength"></div>
            <div class="form-text">Mínimo 8 caracteres</div>
          </div>
          
          <div class="col-md-6 mb-3">
            <label for="confirm_password" class="form-label required-field">Confirmar Contraseña</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
        </div>
        
        <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
          <a href="gestion_colaboradores.php" class="btn btn-secondary order-md-1">
            <i class="bi bi-arrow-left"></i> Cancelar
          </a>
          <button type="submit" class="btn btn-primary order-md-2">
            <i class="bi bi-save"></i> Registrar Colaborador
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

    // Password strength indicator
    document.getElementById('password').addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('password-strength');
      let strength = 0;
      
      // Check password length
      if (password.length >= 8) strength++;
      if (password.length >= 12) strength++;
      
      // Check for mixed case
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
      
      // Check for numbers
      if (/\d/.test(password)) strength++;
      
      // Check for special chars
      if (/[^A-Za-z0-9]/.test(password)) strength++;
      
      // Cap at 4 for our strength meter
      strength = Math.min(strength, 4);
      
      // Update strength meter
      strengthBar.className = 'password-strength strength-' + strength;
    });

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const tipoColaborador = document.getElementById('tipo_colaborador').value;
      const rangoColaborador = document.getElementById('rango_colaborador').value;
      
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Las contraseñas no coinciden');
        return false;
      }
      
      if (password.length < 8) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 8 caracteres');
        return false;
      }
      
      if (!tipoColaborador) {
        e.preventDefault();
        alert('Debes seleccionar un tipo de colaborador');
        return false;
      }
      
      if (!rangoColaborador) {
        e.preventDefault();
        alert('Debes seleccionar un rango de colaborador');
        return false;
      }
      
      return true;
    });

    // Add ripple effect to buttons
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
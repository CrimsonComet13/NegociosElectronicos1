<?php
// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'use_strict_mode' => true
    ]);
}

require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo colaboradores pueden acceder
if (!isCollaborator()) {
    header("Location: /login.php");
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Procesar cambio de disponibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evento_id'])) {
    // Validar token CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor, recargue la página e intente nuevamente.';
        // Regenerar token para el próximo intento
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $evento_id = $_POST['evento_id'];
        $disponibilidad = $_POST['disponibilidad'];
        
        // Validar disponibilidad contra lista blanca
        $allowed_values = ['disponible', 'no_disponible'];
        if (!in_array($disponibilidad, $allowed_values)) {
            $error = 'Valor de disponibilidad no válido';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE evento_colaborador 
                                      SET disponibilidad = :disponibilidad,
                                          fecha_actualizacion = NOW()
                                      WHERE evento_id = :evento_id AND colaborador_id = :colaborador_id");
                $stmt->bindParam(':disponibilidad', $disponibilidad);
                $stmt->bindParam(':evento_id', $evento_id);
                $stmt->bindParam(':colaborador_id', $user_id);
                $stmt->execute();
                
                $success = 'Tu disponibilidad ha sido actualizada';
                // Redirección segura para evitar reenvío de formulario
                header("Location: eventos.php");
                exit();
            } catch(PDOException $e) {
                // Registrar error detallado
                error_log("Error al actualizar disponibilidad: " . $e->getMessage());
                // Mensaje genérico al usuario
                $error = 'Error al actualizar disponibilidad. Por favor, inténtelo de nuevo más tarde.';
            }
        }
    }
}

// Obtener eventos asignados
try {
    $stmt = $conn->prepare("SELECT e.*, u.nombre as cliente_nombre, ec.disponibilidad 
                          FROM eventos e 
                          JOIN usuarios u ON e.cliente_id = u.id
                          JOIN evento_colaborador ec ON e.id = ec.evento_id
                          WHERE ec.colaborador_id = :colaborador_id
                          ORDER BY e.fecha_evento ASC");
    $stmt->bindParam(':colaborador_id', $user_id);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Registrar error detallado
    error_log("Error al obtener eventos: " . $e->getMessage());
    // Mensaje genérico al usuario
    $error = "Error al cargar eventos. Por favor, inténtelo de nuevo más tarde.";
}

// Definir estados y tipos de evento para mostrar
$estados_evento = [
    'pendiente'    => 'Pendiente',
    'confirmado'   => 'Confirmado',
    'en_progreso'  => 'En progreso',
    'completado'   => 'Completado',
    'cancelado'    => 'Cancelado'
];

$tipos_evento = [
    'bodas'        => 'Boda',
    'xv'           => 'XV Años',
    'graduaciones' => 'Graduación',
    'corporativos' => 'Evento Corporativo',
    'otro'         => 'Otro'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mis Eventos - Colaborador | Reminiscencia Photography</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
  />
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

    /* Sidebar Styles */
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

    /* Main Content */
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

    /* Card Styles */
    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-glow);
    }

    .card-header {
      padding: 1.5rem 2rem;
      border-bottom: 1px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.02);
    }

    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin: 0;
    }

    .card-body {
      padding: 2rem;
    }

    /* Event Card Styles */
    .event-card {
      position: relative; /* Para posicionar el badge */
      overflow: hidden;
      height: 100%;
      animation: fadeInUp 0.6s ease forwards;
    }

    /* Ícono del tipo de evento */
    .event-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      margin-right: 1.5rem;
      background: var(--primary-gradient);
      color: white;
      flex-shrink: 0;
    }

    .event-icon.bodas {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    }

    .event-icon.xv {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .event-icon.graduaciones {
      background: var(--success-gradient);
    }

    .event-icon.corporativos {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .event-icon.otro {
      background: rgba(255, 255, 255, 0.2);
      color: var(--text-primary);
    }

    /* Datos meta del evento */
    .event-meta {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .event-meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
      color: var(--text-secondary);
    }

    .event-meta-item i {
      font-size: 1rem;
    }

    /* Badge de estado: posición absolute dentro de .event-card */
    .status-badge {
      position: absolute;
      top: 0.5rem;
      right: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.75rem;
      color: white;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      display: inline-block;
      z-index: 2;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.24);
    }

    /* Estados */
    .status-pendiente {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .status-confirmado {
      background: var(--success-gradient);
      color: white;
    }

    .status-en_progreso {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .status-completado {
      background: var(--primary-gradient);
      color: white;
    }

    .status-cancelado {
      background: var(--danger-gradient);
      color: white;
    }

    /* Estilos Botón Ver detalles */
    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
    }

    .btn-outline-light {
      background: transparent;
      border: 1px solid var(--glass-border);
      color: var(--text-primary);
    }

    .btn-outline-light:hover {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-primary);
    }

    /* Alert Styles */
    .alert {
      border-radius: 12px;
      backdrop-filter: blur(20px);
    }

    .alert-success {
      background: rgba(79, 172, 254, 0.1);
      border: 1px solid rgba(79, 172, 254, 0.2);
      color: #4facfe;
    }

    .alert-danger {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.2);
      color: #ff6b6b;
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--text-secondary);
    }

    .empty-state i {
      font-size: 4rem;
      opacity: 0.3;
      margin-bottom: 1rem;
      display: block;
    }

    .empty-state p {
      font-size: 1.1rem;
      margin-bottom: 0;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
      .event-meta {
        flex-direction: column;
        gap: 0.5rem;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
        margin-left: 0;
      }

      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .event-icon {
        width: 40px;
        height: 40px;
        margin-right: 1rem;
        font-size: 1.1rem;
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

      .main-content {
        padding-bottom: 6rem;
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
  </style>
</head>
<body>
  <!-- Menu Toggle Button -->
  <button class="menu-toggle btn btn-secondary" onclick="toggleSidebar()">
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
        <a href="eventos.php" class="nav-link active">
          <i class="bi bi-calendar-event-fill"></i>
          <span>Mis Eventos</span>
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
      <h1>Mis Eventos Asignados</h1>
      <p>Gestiona tu participación en los eventos programados</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>
    
    <div class="row mt-4">
      <?php if (count($eventos) > 0): ?>
        <?php foreach ($eventos as $evento): ?>
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card event-card">
              <div class="card-body">
                <div class="d-flex align-items-start mb-3">
  <!-- Ícono del tipo de evento -->
  <div class="event-icon <?php echo htmlspecialchars($evento['tipo']); ?>">
    <?php
      $iconos = [
        'bodas'        => 'bi-heart-fill',
        'xv'           => 'bi-gem',
        'graduaciones' => 'bi-mortarboard-fill',
        'corporativos' => 'bi-briefcase-fill',
        'otro'         => 'bi-calendar-event-fill'
      ];
      $tipo_lower = strtolower($evento['tipo']);
      $icono = $iconos[$tipo_lower] ?? $iconos['otro'];
    ?>
    <i class="bi <?php echo $icono; ?>"></i>
  </div>

  <div class="flex-grow-1 position-relative">
      
      <br/>
    <!-- Título del evento -->
    <h5 class="card-title mb-1 text-white"><?php echo htmlspecialchars($evento['nombre']); ?></h5>
    <!-- Tipo de evento en texto pequeño -->
    <p class="text-secondary mb-2" style="font-size: 0.9rem;">
      <?php echo htmlspecialchars($tipos_evento[$evento['tipo']] ?? ucfirst($evento['tipo'])); ?>
    </p>
  </div>
  <span class="status-badge status-<?php echo htmlspecialchars($evento['estado']); ?>">
      <?php echo htmlspecialchars($estados_evento[$evento['estado']] ?? $evento['estado']); ?>
    </span>
</div>

                
                <!-- Datos meta (fecha, hora, lugar, cliente) -->
                <div class="event-meta">
                  <div class="event-meta-item">
                    <i class="bi bi-calendar3"></i>
                    <span><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></span>
                  </div>
                  <div class="event-meta-item">
                    <i class="bi bi-clock"></i>
                    <span>
                      <?php echo substr($evento['hora_inicio'], 0, 5); ?> -
                      <?php echo substr($evento['hora_fin'], 0, 5); ?>
                    </span>
                  </div>
                  <div class="event-meta-item">
                    <i class="bi bi-geo-alt"></i>
                    <span><?php echo htmlspecialchars($evento['lugar']); ?></span>
                  </div>
                  <div class="event-meta-item">
                    <i class="bi bi-person"></i>
                    <span><?php echo htmlspecialchars($evento['cliente_nombre']); ?></span>
                  </div>
                </div>
<div class="d-flex justify-content-end mt-3">
  <a
    href="detalles_evento.php?id=<?php echo $evento['id']; ?>"
    class="btn btn-sm btn-outline-light"
    target="_self"
    style="pointer-events: auto; z-index: 1;"
  >
    <i class="bi bi-eye"></i> Ver detalles
  </a>
</div>

              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="card empty-state">
            <i class="bi bi-calendar-x"></i>
            <h4>No tienes eventos asignados</h4>
            <p>Cuando te asignen eventos, aparecerán aquí para que puedas confirmar tu disponibilidad.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>


  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle sidebar on mobile
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('open');
    }
  </script>
</body>
</html>
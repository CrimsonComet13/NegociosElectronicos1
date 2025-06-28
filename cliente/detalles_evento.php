<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo clientes pueden acceder
if (!isClient()) {
    header("Location: /login_cliente.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$evento_id = $_GET['id'] ?? 0;
$error = '';
$evento = null;
$colaboradores = [];

// Generar token CSRF para el formulario de cancelación
if (!isset($_SESSION['cancel_token'])) {
    $_SESSION['cancel_token'] = bin2hex(random_bytes(32));
}

try {
    // Obtener detalles del evento (solo si pertenece al cliente)
    $stmt = $conn->prepare("SELECT e.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono
                          FROM eventos e
                          JOIN usuarios u ON e.cliente_id = u.id
                          WHERE e.id = :evento_id AND e.cliente_id = :cliente_id");
    $stmt->bindParam(':evento_id', $evento_id, PDO::PARAM_INT);
    $stmt->bindParam(':cliente_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 1) {
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener colaboradores asignados al evento
        $stmt = $conn->prepare("SELECT u.nombre, u.tipo_colaborador, u.rango_colaborador
                               FROM evento_colaborador ec
                               JOIN usuarios u ON ec.colaborador_id = u.id
                               WHERE ec.evento_id = :evento_id");
        $stmt->bindParam(':evento_id', $evento_id, PDO::PARAM_INT);
        $stmt->execute();
        $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = 'Evento no encontrado o no tienes permiso para verlo';
    }
} catch(PDOException $e) {
    $error = 'Error al obtener detalles del evento: ' . $e->getMessage();
}

// Listas blancas para validación
$tiposPermitidos = ['graduacion', 'xv', 'bautizo', 'boda', 'sesion', 'otro'];
$estadosPermitidos = ['pendiente', 'confirmado', 'cancelado', 'completado'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalles del Evento - Cliente | Reminiscencia Photography</title>
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

    .badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .badge.bg-warning {
      background: var(--warning-gradient) !important;
      color: var(--dark-bg) !important;
    }

    .badge.bg-success {
      background: var(--success-gradient) !important;
    }

    .badge.bg-danger {
      background: var(--danger-gradient) !important;
    }

    .badge.bg-info {
      background: var(--info-gradient) !important;
      color: var(--dark-bg) !important;
    }

    .badge.bg-primary {
      background: var(--primary-gradient) !important;
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

    .btn-success {
      background: var(--success-gradient);
      color: white;
    }

    .btn-warning {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .btn-danger {
      background: var(--danger-gradient);
      color: white;
    }

    .btn-info {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

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

    .event-icon.graduacion {
      background: var(--success-gradient);
    }

    .event-icon.xv,
    .event-icon.quinceañera {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .event-icon.bautizo {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .event-icon.boda {
      background: var(--danger-gradient);
    }

    .event-icon.sesion {
      background: var(--primary-gradient);
    }

    .detail-item {
      display: flex;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .detail-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      background: var(--primary-gradient);
      color: white;
    }

    .detail-content {
      flex: 1;
    }

    .detail-label {
      color: var(--text-secondary);
      font-size: 0.9rem;
      margin-bottom: 0.25rem;
    }

    .detail-value {
      font-size: 1.1rem;
      font-weight: 500;
    }

    .team-member {
      display: flex;
      align-items: center;
      padding: 1rem;
      margin-bottom: 1rem;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .team-member:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(5px);
    }

    .member-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      font-weight: 600;
      color: white;
    }

    .member-info {
      flex: 1;
    }

    .member-name {
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .member-role {
      color: var(--text-secondary);
      font-size: 0.85rem;
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

      .content-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .header h1 {
        font-size: 2rem;
      }

      .detail-item {
        flex-direction: column;
        align-items: flex-start;
      }

      .detail-icon {
        margin-bottom: 0.5rem;
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

    /* Modal styles */
    .modal-content {
      background: var(--dark-bg);
      border: 1px solid var(--glass-border);
      color: var(--text-primary);
    }

    .modal-header {
      border-bottom: 1px solid var(--glass-border);
    }

    .modal-footer {
      border-top: 1px solid var(--glass-border);
    }

    .btn-close {
      filter: invert(1);
    }

    /* Description box */
    .description-box {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 12px;
      padding: 1.5rem;
      margin-top: 1rem;
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

    .animated {
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
        <a href="#" class="nav-link active">
          <i class="bi bi-info-circle-fill"></i>
          <span>Detalles Evento</span>
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
      <h1>Detalles del Evento</h1>
      <p>Información completa sobre tu evento programado</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $error; ?>
      </div>
    <?php elseif ($evento): ?>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-white"><?php echo htmlspecialchars($evento['nombre']); ?></h2>
        <span class="badge bg-<?php 
          switch($evento['estado']) {
            case 'pendiente': echo 'warning'; break;
            case 'confirmado': echo 'success'; break;
            case 'cancelado': echo 'danger'; break;
            case 'completado': echo 'info'; break;
            default: echo 'primary';
          }
        ?>">
          <?php echo ucfirst($evento['estado']); ?>
        </span>
      </div>
      
      <div class="row">
        <!-- Main Event Details -->
        <div class="col-lg-8">
          <div class="card mb-4 animated">
            <div class="card-header">
              <h3 class="card-title text-white">
                <i class="bi bi-calendar-event me-2"></i>
                Información del Evento
              </h3>
            </div>
            <div class="card-body">
              <?php
                // Get icon based on event type
                $iconos = [
                  'graduacion' => 'bi-mortarboard-fill',
                  'xv' => 'bi-gem',
                  'bautizo' => 'bi-droplet-fill',
                  'boda' => 'bi-heart-fill',
                  'sesion' => 'bi-camera-fill',
                  'otro' => 'bi-calendar-event-fill'
                ];
                $tipo_lower = strtolower($evento['tipo']);
                $icono = isset($iconos[$tipo_lower]) ? $iconos[$tipo_lower] : $iconos['otro'];
              ?>
              
              <div class="event-icon <?php echo $tipo_lower; ?>">
                <i class="bi <?php echo $icono; ?>"></i>
              </div>
              
              <div class="row mt-4">
                <div class="col-md-6">
                  <div class="detail-item">
                    <div class="detail-icon">
                      <i class="bi bi-tag"></i>
                    </div>
                    <div class="detail-content">
                      <div class="detail-label">Tipo de Evento</div>
                      <div class="detail-value text-white"><?php echo ucfirst($evento['tipo']); ?></div>
                    </div>
                  </div>
                  
                  <div class="detail-item">
                    <div class="detail-icon">
                      <i class="bi bi-calendar-date"></i>
                    </div>
                    <div class="detail-content">
                      <div class="detail-label">Fecha</div>
                      <div class="detail-value text-white"><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></div>
                    </div>
                  </div>
                  
                  <div class="detail-item">
                    <div class="detail-icon">
                      <i class="bi bi-clock"></i>
                    </div>
                    <div class="detail-content">
                      <div class="detail-label">Horario</div>
                      <div class="detail-value text-white">
                        <?php echo substr($evento['hora_inicio'], 0, 5); ?> - <?php echo substr($evento['hora_fin'], 0, 5); ?>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="detail-item">
                    <div class="detail-icon">
                      <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="detail-content">
                      <div class="detail-label">Lugar</div>
                      <div class="detail-value text-white"><?php echo htmlspecialchars($evento['lugar']); ?></div>
                    </div>
                  </div>
                  
                  <div class="detail-item">
                    <div class="detail-icon">
                      <i class="bi bi-people"></i>
                    </div>
                    <div class="detail-content">
                      <div class="detail-label">Personas Estimadas</div>
                      <div class="detail-value text-white"><?php echo $evento['personas_estimadas'] ?? 'No especificado'; ?></div>
                    </div>
                  </div>
                </div>
              </div>
              
              <?php if (!empty($evento['descripcion'])): ?>
                <div class="mt-4 text-white">
                  <h5 class="mb-3"><i class="bi bi-card-text me-2"></i> Descripción / Notas</h5>
                  <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?>
                  </div>
                </div>
              <?php endif; ?>
              
              <div class="d-flex gap-2 mt-4">
                <a href="dashboard.php" class="btn btn-secondary">
                  <i class="bi bi-arrow-left"></i> Volver
                </a>
                <?php if ($evento['estado'] === 'pendiente' || $evento['estado'] === 'confirmado'): ?>
                  <a href="editar_evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar Evento
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <!-- Team Members -->
          <?php if (!empty($colaboradores)): ?>
            <div class="card animated" style="animation-delay: 0.2s;">
              <div class="card-header">
                <h3 class="card-title text-white">
                  <i class="bi bi-people-fill me-2"></i>
                  Equipo Asignado
                </h3>
              </div>
              <div class="card-body">
                <?php foreach ($colaboradores as $colaborador): ?>
                  <div class="team-member">
                    <div class="member-avatar">
                      <?php 
                        $nombres = explode(' ', $colaborador['nombre']);
                        $iniciales = '';
                        foreach ($nombres as $nombre) {
                          $iniciales .= strtoupper(substr($nombre, 0, 1));
                        }
                        echo substr($iniciales, 0, 2);
                      ?>
                    </div>
                    <div class="member-info">
                      <div class="member-name"><?php echo htmlspecialchars($colaborador['nombre']); ?></div>
                      <div class="member-role">
                        <?php echo htmlspecialchars($colaborador['tipo_colaborador']); ?>
                        <?php if (!empty($colaborador['rango_colaborador'])): ?>
                          (<?php echo htmlspecialchars($colaborador['rango_colaborador']); ?>)
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Sidebar Content -->
        <div class="col-lg-4">
          <!-- Quick Actions -->
          <div class="card mb-4 animated" style="animation-delay: 0.1s;">
            <div class="card-header bg-primary">
              <h3 class="card-title">
                <i class="bi bi-lightning-fill me-2"></i>
                Acciones Rápidas
              </h3>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2">
                <a href="https://wa.me/524491543138" class="btn btn-success" target="_blank">
                  <i class="bi bi-whatsapp"></i> Soporte Inmediato
                </a>
                <?php if ($evento['estado'] === 'pendiente'): ?>
                  <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-circle"></i> Cancelar Evento
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
         
      <!-- Modal para cancelar evento -->
      <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Confirmar Cancelación</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>¿Estás seguro que deseas cancelar este evento? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
              <form method="POST" action="cancelar_evento.php">
                <input type="hidden" name="evento_id" value="<?php echo $evento['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['cancel_token']; ?>">
                <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
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